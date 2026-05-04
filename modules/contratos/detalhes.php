<?php
// modules/contratos/detalhes.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

requireLogin();
if (!isAdmin()) die("Acesso negado. Você precisa ser admin.");

// ======== AUTO-FIX MÁGICO DO BANCO DE DADOS ========
try {
    $pdo->exec("ALTER TABLE contratos ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cliente_id");
    $pdo->exec("ALTER TABLE contratos ADD COLUMN link_drive VARCHAR(255) NULL AFTER texto_contrato");
} catch (PDOException $e) { }
// ===================================================

$id = $_GET['id'] ?? 0;
$mensagem = '';

$stmt = $pdo->prepare("SELECT c.*, cli.nome as cliente_nome, cli.email as cliente_email 
                       FROM contratos c 
                       JOIN clientes cli ON c.cliente_id = cli.id 
                       WHERE c.id = ?");
$stmt->execute([$id]);
$contrato = $stmt->fetch();

if (!$contrato) die("Erro: Contrato não encontrado.");

$valor_contrato = isset($contrato['valor']) ? (float)$contrato['valor'] : 0;
$duracao = (isset($contrato['duracao_meses']) && $contrato['duracao_meses'] > 0) ? (int)$contrato['duracao_meses'] : 1;
$valor_parcela = $valor_contrato / $duracao;

// Monta o link absoluto para o WhatsApp
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$link_completo_contrato = $protocolo . "://" . $dominio . "/gasmaske/publico/contrato.php?token=" . $contrato['token'];

// --- LÓGICA DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao == 'enviar_cliente' && $contrato['status'] == 'rascunho') {
            $pdo->prepare("UPDATE contratos SET status = 'aguardando_aceite_cliente' WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO contrato_log (contrato_id, usuario_id, descricao) VALUES (?, ?, 'Contrato enviado para o cliente.')")->execute([$id, $_SESSION['usuario_id']]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Link liberado! O cliente já pode acessar e assinar.</div>";
            $contrato['status'] = 'aguardando_aceite_cliente';
            
        } elseif ($acao == 'confirmar_pagamento' && ($contrato['status'] == 'aguardando_pagamento' || $contrato['status'] == 'aguardando_aceite_cliente')) {
            $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            $dia_vencimento = date('d', strtotime($data_pagamento));
            $link_drive = null;
            $msg_drive = "";

            // --- 🤖 ROBÔ DO GOOGLE DRIVE ---
            try {
                $pasta_mae_id = '1QShkupoCzUHvAD5w9rMifI8337HEDx7K'; 

                $client = new \Google_Client();
                $client->setAuthConfig('../../config/google-credentials.json');
                $client->addScope(\Google_Service_Drive::DRIVE);
                $driveService = new \Google_Service_Drive($client);

                $nome_pasta = $contrato['codigo_agc'] . ' - ' . $contrato['cliente_nome'];

                $fileMetadata = new \Google_Service_Drive_DriveFile([
                    'name' => $nome_pasta,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$pasta_mae_id]
                ]);

                $folder = $driveService->files->create($fileMetadata, ['fields' => 'id, webViewLink']);
                $link_drive = $folder->webViewLink;
                $msg_drive = " e pasta do Drive criada";
            } catch (Exception $e) {
                $msg_drive = " (Falha no Drive: " . $e->getMessage() . ")";
            }
            // ---------------------------------
            
            $pdo->beginTransaction();
            
            $pdo->prepare("UPDATE contratos SET status = 'em_andamento', data_inicio = ?, dia_vencimento = ?, link_drive = ? WHERE id = ?")->execute([$data_pagamento, $dia_vencimento, $link_drive, $id]);
            
            $stmt_parcela = $pdo->prepare("INSERT INTO parcelas (contrato_id, numero_parcela, descricao, valor, data_vencimento, status, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)");
            for ($i = 1; $i <= $duracao; $i++) {
                $desc = "Parcela $i/" . $duracao;
                if ($i == 1) {
                    $stmt_parcela->execute([$id, $i, $desc, $valor_parcela, $data_pagamento, 'pago', $data_pagamento]);
                } else {
                    $meses_add = $i - 1;
                    $vencimento = date('Y-m-d', strtotime("+$meses_add months", strtotime($data_pagamento)));
                    $stmt_parcela->execute([$id, $i, $desc, $valor_parcela, $vencimento, 'pendente', null]);
                }
            }
            $pdo->prepare("INSERT INTO contrato_log (contrato_id, usuario_id, descricao) VALUES (?, ?, ?)")->execute([$id, $_SESSION['usuario_id'], "Pagamento confirmado$msg_drive."]);
            $pdo->commit();
            
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pagamento confirmado! Contrato ativo$msg_drive.</div>";
            $contrato['status'] = 'em_andamento';
            $contrato['link_drive'] = $link_drive;
        }
    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

$stmt_log = $pdo->prepare("SELECT l.*, u.nome as usuario_nome FROM contrato_log l LEFT JOIN usuarios u ON l.usuario_id = u.id WHERE l.contrato_id = ? ORDER BY l.criado_em DESC");
$stmt_log->execute([$id]);
$logs = $stmt_log->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 class="page-title">Contrato <?= htmlspecialchars($contrato['codigo_agc']) ?></h2>
        <p class="page-subtitle">Detalhes e gestão deste contrato.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="form.php?id=<?= $contrato['id'] ?>" class="btn btn-secondary"><i class="ph ph-pencil-simple"></i> Editar Contrato</a>
        <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar</a>
    </div>
</div>

<?= $mensagem ?>

<div style="display: flex; gap: 24px; align-items: flex-start;">
    
    <div style="flex: 2; display: flex; flex-direction: column; gap: 24px;">
        
        <div class="card">
            <h3 class="card-title">Resumo do Acordo</h3>
            <div style="background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-mid); display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <span style="display: block; color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Cliente</span>
                    <strong style="color: var(--text-primary); font-size: 15px;"><?= htmlspecialchars($contrato['cliente_nome']) ?></strong>
                </div>
                <div>
                    <span style="display: block; color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Status Atual</span>
                    <strong style="color: var(--blue); font-size: 15px; text-transform: uppercase;"><?= str_replace('_', ' ', $contrato['status']) ?></strong>
                </div>
                <div>
                    <span style="display: block; color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Duração</span>
                    <strong style="color: var(--text-primary); font-size: 15px;"><?= $duracao ?> meses</strong>
                </div>
                <div>
                    <span style="display: block; color: var(--text-muted); font-size: 12px; text-transform: uppercase;">Investimento Total</span>
                    <strong style="color: var(--red); font-size: 18px;"><?= money($valor_contrato) ?></strong><br>
                    <span style="font-size: 12px; color: var(--text-secondary);">(<?= $duracao ?>x de <?= money($valor_parcela) ?>)</span>
                </div>
            </div>

            <?php if(!empty($contrato['link_drive'])): ?>
            <div style="margin-top: 20px; background: rgba(34, 197, 94, 0.1); padding: 15px; border-radius: var(--radius-md); border: 1px dashed rgba(34, 197, 94, 0.4); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="color: var(--green); display: flex; align-items: center; gap: 5px;"><i class="ph-fill ph-folder"></i> Pasta do Projeto Criada</strong>
                    <span style="font-size: 12px; color: var(--text-secondary);">Arquivos, Mídias e Relatórios deste contrato.</span>
                </div>
                <a href="<?= htmlspecialchars($contrato['link_drive']) ?>" target="_blank" class="btn btn-secondary" style="border-color: var(--green); color: var(--green);">Abrir Drive</a>
            </div>
            <?php endif; ?>

            <h3 class="card-title" style="margin-top: 30px;">Texto do Contrato</h3>
            <div style="background: var(--bg-elevated); padding: 20px; border: 1px solid var(--border-mid); border-radius: var(--radius-md); white-space: pre-wrap; font-family: monospace; font-size: 13px; color: var(--text-secondary); max-height: 400px; overflow-y: auto;">
                <?= !empty($contrato['texto_contrato']) ? htmlspecialchars($contrato['texto_contrato']) : '<i>Contrato sem texto definido.</i>' ?>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">Histórico e Logs</h3>
            <div style="border-left: 2px solid var(--border-mid); margin-left: 10px; padding-left: 20px; margin-top: 15px;">
                <?php foreach($logs as $log): ?>
                    <div style="position: relative; margin-bottom: 20px;">
                        <div style="position: absolute; left: -26px; top: 4px; width: 10px; height: 10px; background: var(--bg-surface); border: 2px solid var(--text-secondary); border-radius: 50%;"></div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></div>
                        <div style="font-size: 14px; color: var(--text-primary); margin-top: 2px;"><?= htmlspecialchars($log['descricao']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($logs)): ?>
                    <p style="color: var(--text-muted); font-size: 13px;">Nenhum registro encontrado.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div style="flex: 1;">
        <div class="card">
            <h3 class="card-title" style="border-bottom: 1px solid var(--border); padding-bottom: 15px;">Painel de Controle</h3>
            
            <?php if ($contrato['status'] == 'rascunho'): ?>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">O contrato está em rascunho. Edite as cláusulas se necessário e envie para o cliente assinar.</p>
                <form method="POST">
                    <input type="hidden" name="acao" value="enviar_cliente">
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;"><i class="ph ph-paper-plane-right"></i> Enviar para Cliente</button>
                </form>
            
            <?php elseif ($contrato['status'] == 'aguardando_aceite_cliente' || $contrato['status'] == 'aguardando_pagamento'): ?>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Aguardando ação do cliente. Confirme o pagamento para ativar:</p>
                <form method="POST">
                    <input type="hidden" name="acao" value="confirmar_pagamento">
                    <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required style="margin-bottom: 15px;">
                    <button type="submit" class="btn btn-primary" style="background: var(--green); border-color: var(--green); width: 100%; justify-content: center; height: 45px;"><i class="ph ph-check-circle"></i> Ativar Contrato</button>
                </form>
                
                <div style="margin-top: 25px; border-top: 1px dashed var(--border-mid); padding-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?= $link_completo_contrato ?>" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                        <i class="ph ph-link"></i> Abrir Link Público
                    </a>
                    
                    <button type="button" class="btn btn-ghost" onclick="copiarMensagemContrato('<?= addslashes($contrato['cliente_nome']) ?>', '<?= addslashes($contrato['codigo_agc']) ?>', '<?= $link_completo_contrato ?>', this)" style="color: #25D366; border-color: rgba(37, 211, 102, 0.2); background: rgba(37, 211, 102, 0.05); width: 100%; justify-content: center;">
                        <i class="ph ph-whatsapp-logo" style="font-size: 18px;"></i> Copiar Zap
                    </button>
                </div>
            
            <?php elseif ($contrato['status'] == 'em_andamento'): ?>
                <div style="text-align: center; padding: 20px 0;">
                    <i class="ph-fill ph-check-circle" style="font-size: 40px; color: var(--green); margin-bottom: 10px;"></i>
                    <strong style="font-size: 16px; color: var(--green); display: block;">CONTRATO ATIVO</strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// --- MOTOR DE CÓPIA DO WHATSAPP PARA O CONTRATO ---
function copiarMensagemContrato(nome, codigo, link, btn) {
    const primeiroNome = nome.split(' ')[0];
    
    // Mensagem magnética de envio de contrato
    const msg = `Olá, ${primeiroNome}! Tudo bem?\n\nO seu contrato de prestação de serviços (*${codigo}*) já está redigido e pronto para assinatura! ✍️\n\nAcesse o link seguro abaixo para ler as cláusulas e assinar digitalmente. O pagamento da primeira parcela é feito direto na página:\n\n🔗 ${link}\n\nQualquer dúvida, estou à disposição!`;

    navigator.clipboard.writeText(msg).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ph-fill ph-check-circle"></i> Copiado!';
        btn.style.background = 'rgba(37, 211, 102, 0.15)';
        
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = 'rgba(37, 211, 102, 0.05)';
        }, 2000);
    }).catch(err => {
        alert('Erro ao copiar a mensagem.');
    });
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>