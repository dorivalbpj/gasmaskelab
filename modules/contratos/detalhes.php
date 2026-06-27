<?php
// modules/contratos/detalhes.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// ======== AUTO-FIX MÁGICO DO BANCO DE DADOS ========
try {
    $pdo->exec("ALTER TABLE contratos ADD COLUMN valor DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cliente_id");
    $pdo->exec("ALTER TABLE contratos ADD COLUMN link_drive VARCHAR(255) NULL AFTER texto_contrato");
} catch (PDOException $e) { }
// ===================================================

$id = $_GET['id'] ?? 0;
$mensagem = '';

$stmt = $pdo->prepare("
    SELECT c.*, cli.nome AS cliente_nome, cli.email AS cliente_email, cli.id AS cliente_id
    FROM contratos c
    JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contrato = $stmt->fetch();

if (!$contrato) die("Erro: Contrato não encontrado.");

$valor_parcela  = (float)($contrato['valor'] ?? 0);
$duracao        = (int)($contrato['duracao_meses'] > 0 ? $contrato['duracao_meses'] : 1);
$valor_contrato = $valor_parcela * $duracao;

$link_publico = BASE_URL . "publico/contrato.php?token=" . $contrato['token'];

// --- LÓGICA DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
        $stmt_user->execute([$_SESSION['usuario_id']]);
        $usuario_id_log = $stmt_user->fetch() ? $_SESSION['usuario_id'] : null;

        if ($acao == 'enviar_cliente' && $contrato['status'] == 'rascunho') {
            $pdo->prepare("UPDATE contratos SET status = 'aguardando_aceite_cliente' WHERE id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO contrato_log (contrato_id, usuario_id, descricao) VALUES (?, ?, 'Contrato enviado para o cliente.')")->execute([$id, $usuario_id_log]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Link liberado! O cliente já pode acessar e assinar.</div>";
            $contrato['status'] = 'aguardando_aceite_cliente';

        } elseif ($acao == 'confirmar_pagamento' && in_array($contrato['status'], ['aguardando_aceite_cliente', 'aguardando_pagamento'])) {
            $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            $dia_vencimento = date('d', strtotime($data_pagamento));
            $link_drive     = null;
            $msg_drive      = "";

            try {
                $pasta_mae_id = '1QShkupoCzUHvAD5w9rMifI8337HEDx7K';
                $client = new \Google_Client();
                $client->setAuthConfig('../../config/google-credentials.json');
                $client->addScope(\Google_Service_Drive::DRIVE);
                $driveService = new \Google_Service_Drive($client);

                $nome_pasta   = $contrato['codigo_agc'] . ' - ' . $contrato['cliente_nome'];
                $fileMetadata = new \Google_Service_Drive_DriveFile([
                    'name'     => $nome_pasta,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents'  => [$pasta_mae_id]
                ]);
                $folder     = $driveService->files->create($fileMetadata, ['fields' => 'id, webViewLink']);
                $link_drive = $folder->webViewLink;
                $msg_drive  = " e pasta do Drive criada";
            } catch (Exception $e) {
                $msg_drive = " (Falha no Drive: " . $e->getMessage() . ")";
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE contratos SET status = 'em_andamento', data_inicio = ?, dia_vencimento = ?, link_drive = ? WHERE id = ?")->execute([$data_pagamento, $dia_vencimento, $link_drive, $id]);
            $pdo->prepare("DELETE FROM parcelas WHERE contrato_id = ?")->execute([$id]);

            $stmt_p = $pdo->prepare("INSERT INTO parcelas (contrato_id, numero_parcela, descricao, valor, data_vencimento, status, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)");
            for ($i = 1; $i <= $duracao; $i++) {
                $desc = "Parcela $i/$duracao - " . $contrato['codigo_agc'];
                if ($i == 1) {
                    $stmt_p->execute([$id, $i, $desc, $valor_parcela, $data_pagamento, 'pago', $data_pagamento]);
                } else {
                    $meses_add  = $i - 1;
                    $vencimento = date('Y-m-d', strtotime("+$meses_add months", strtotime($data_pagamento)));
                    $stmt_p->execute([$id, $i, $desc, $valor_parcela, $vencimento, 'pendente', null]);
                }
            }

            $pdo->prepare("INSERT INTO contrato_log (contrato_id, usuario_id, descricao) VALUES (?, ?, ?)")->execute([$id, $usuario_id_log, "Pagamento confirmado$msg_drive."]);
            $pdo->commit();

            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pagamento confirmado! Contrato ativo$msg_drive.</div>";
            $contrato['status']     = 'em_andamento';
            $contrato['link_drive'] = $link_drive;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// Busca parcelas
$stmt_parcelas = $pdo->prepare("SELECT * FROM parcelas WHERE contrato_id = ? ORDER BY numero_parcela ASC");
$stmt_parcelas->execute([$id]);
$parcelas = $stmt_parcelas->fetchAll();

// Totais das parcelas
$total_pago    = array_sum(array_column(array_filter($parcelas, fn($p) => $p['status'] === 'pago'),    'valor'));
$total_aberto  = array_sum(array_column(array_filter($parcelas, fn($p) => $p['status'] !== 'pago'),    'valor'));

// Logs
$stmt_log = $pdo->prepare("SELECT l.*, u.nome AS usuario_nome FROM contrato_log l LEFT JOIN usuarios u ON l.usuario_id = u.id WHERE l.contrato_id = ? ORDER BY l.criado_em DESC");
$stmt_log->execute([$id]);
$logs = $stmt_log->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- Cabeçalho -->
<div class="cabecalho">
    <div>
        <h2 class="page-title">Contrato <?= htmlspecialchars($contrato['codigo_agc']) ?></h2>
        <p class="page-subtitle">
            Cliente:
            <a href="<?= BASE_URL ?>modules/clientes/visualizar.php?id=<?= $contrato['cliente_id'] ?>">
                <?= htmlspecialchars($contrato['cliente_nome']) ?>
            </a>
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="<?= BASE_URL ?>modules/clientes/visualizar.php?id=<?= $contrato['cliente_id'] ?>" class="btn btn-ghost">
            <i class="ph ph-user"></i> Ver Cliente
        </a>
        <a href="form.php?id=<?= $contrato['id'] ?>" class="btn btn-secondary">
            <i class="ph ph-pencil-simple"></i> Editar
        </a>
        <a href="index.php" class="btn btn-ghost">
            <i class="ph ph-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?= $mensagem ?>

<div class="grid-2col">

    <!-- Coluna Esquerda -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Card: Resumo -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-info"></i> Resumo do Acordo</h3>
                <?php
                    $badge_status = 'badge-gray';
                    if ($contrato['status'] == 'aguardando_aceite_cliente') $badge_status = 'badge-yellow';
                    if ($contrato['status'] == 'aguardando_pagamento')      $badge_status = 'badge-blue';
                    if ($contrato['status'] == 'em_andamento')              $badge_status = 'badge-green';
                    if ($contrato['status'] == 'finalizado')                $badge_status = 'badge-purple';
                    if ($contrato['status'] == 'rascunho')                  $badge_status = 'badge-gray';
                ?>
                <span class="badge <?= $badge_status ?>"><?= str_replace('_', ' ', $contrato['status']) ?></span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Cliente</span>
                    <span class="info-value">
                        <a href="<?= BASE_URL ?>modules/clientes/visualizar.php?id=<?= $contrato['cliente_id'] ?>">
                            <?= htmlspecialchars($contrato['cliente_nome']) ?>
                        </a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Duração</span>
                    <span class="info-value"><?= $duracao ?> meses</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Valor / Parcela</span>
                    <span class="info-value"><?= money($valor_parcela) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total do Contrato</span>
                    <span class="info-value text-red"><?= money($valor_contrato) ?></span>
                </div>
                <?php if (!empty($contrato['data_inicio'])): ?>
                <div class="info-row">
                    <span class="info-label">Início</span>
                    <span class="info-value"><?= dataBR($contrato['data_inicio']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($contrato['dia_vencimento'])): ?>
                <div class="info-row">
                    <span class="info-label">Vencimento</span>
                    <span class="info-value">Todo dia <?= $contrato['dia_vencimento'] ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($contrato['link_drive'])): ?>
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-folder"></i> Drive</span>
                    <span class="info-value">
                        <a href="<?= htmlspecialchars($contrato['link_drive']) ?>" target="_blank" class="btn btn-secondary btn--sm">
                            <i class="ph ph-folder-open"></i> Abrir Pasta
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card: Parcelas -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-currency-circle-dollar"></i> Parcelas</h3>
                <span class="badge badge-gray"><?= count($parcelas) ?> parcela<?= count($parcelas) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (count($parcelas) > 0): ?>

                <!-- Mini resumo financeiro -->
                <div class="stats-grid" style="margin-bottom: 16px;">
                    <div class="stat-box">
                        <div class="stat-number text-green"><?= money($total_pago) ?></div>
                        <div class="stat-label">Recebido</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number text-yellow"><?= money($total_aberto) ?></div>
                        <div class="stat-label">Em Aberto</div>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Vencimento</th>
                                <th>Valor</th>
                                <th>Pagamento</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parcelas as $p): ?>
                                <?php
                                    $badge_p = 'badge-gray';
                                    if ($p['status'] === 'pago')     $badge_p = 'badge-green';
                                    if ($p['status'] === 'atrasado') $badge_p = 'badge-red';
                                    if ($p['status'] === 'pendente') $badge_p = 'badge-yellow';
                                ?>
                                <tr>
                                    <td>
                                        <span class="txt-name-main"><?= $p['numero_parcela'] ?>/<?= $duracao ?></span>
                                    </td>
                                    <td>
                                        <span class="txt-date-sm"><?= dataBR($p['data_vencimento']) ?></span>
                                    </td>
                                    <td>
                                        <span class="txt-contact-main"><?= money($p['valor']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['data_pagamento'])): ?>
                                            <span class="txt-date-sm text-green"><?= dataBR($p['data_pagamento']) ?></span>
                                        <?php else: ?>
                                            <span class="txt-meta-sm">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $badge_p ?>"><?= $p['status'] ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="txt-meta-sm" style="margin-top: 12px;">
                    <i class="ph ph-info"></i> Para registrar pagamentos, acesse o módulo
                    <a href="<?= BASE_URL ?>modules/financeiro/">Financeiro</a>.
                </p>

            <?php else: ?>
                <div class="empty-state">
                    <i class="ph ph-currency-circle-dollar empty-state-icon"></i>
                    Parcelas geradas ao confirmar o primeiro pagamento.
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Coluna Direita -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Card: Painel de Controle -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-sliders"></i> Painel de Controle</h3>
            </div>
            <div class="card-body">

                <?php if ($contrato['status'] == 'rascunho'): ?>
                    <p class="txt-contact-main" style="margin-bottom: 16px;">O contrato está em rascunho. Edite as cláusulas se necessário e envie para o cliente assinar.</p>
                    <form method="POST">
                        <input type="hidden" name="acao" value="enviar_cliente">
                        <button type="submit" class="btn btn-primary w-100" style="justify-content: center; height: 45px;">
                            <i class="ph ph-paper-plane-right"></i> Enviar para Cliente
                        </button>
                    </form>

                <?php elseif (in_array($contrato['status'], ['aguardando_aceite_cliente', 'aguardando_pagamento'])): ?>
                    <p class="txt-contact-main" style="margin-bottom: 16px;">Aguardando ação do cliente. Confirme o pagamento para ativar o contrato.</p>
                    <form method="POST">
                        <input type="hidden" name="acao" value="confirmar_pagamento">
                        <div class="form-group">
                            <label>Data do Pagamento</label>
                            <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="justify-content: center; height: 45px; background: var(--green); border-color: var(--green);">
                            <i class="ph ph-check-circle"></i> Ativar Contrato
                        </button>
                    </form>

                    <hr class="divider">

                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="<?= $link_publico ?>" target="_blank" class="btn btn-secondary w-100" style="justify-content: center;">
                            <i class="ph ph-link"></i> Abrir Link Público
                        </a>
                        <button type="button"
                            class="btn btn-ghost btn-icon-wpp w-100"
                            style="justify-content: center;"
                            onclick="copiarMensagemContrato('<?= addslashes($contrato['cliente_nome']) ?>', '<?= addslashes($contrato['codigo_agc']) ?>', '<?= $link_publico ?>', this)">
                            <i class="ph ph-whatsapp-logo"></i> Copiar Zap
                        </button>
                    </div>

                <?php elseif ($contrato['status'] == 'em_andamento'): ?>
                    <div class="empty-state">
                        <i class="ph-fill ph-check-circle empty-state-icon text-green"></i>
                        <strong class="text-green">Contrato Ativo</strong>
                    </div>

                <?php elseif ($contrato['status'] == 'finalizado'): ?>
                    <div class="empty-state">
                        <i class="ph-fill ph-flag empty-state-icon text-secondary"></i>
                        <strong>Contrato Finalizado</strong>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Card: Histórico -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-clock-countdown"></i> Histórico</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="info-row">
                            <span class="info-label txt-date-sm"><?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?></span>
                            <span class="info-value">
                                <span class="txt-contact-main"><?= htmlspecialchars($log['descricao']) ?></span>
                                <?php if (!empty($log['usuario_nome'])): ?>
                                    <span class="txt-meta-sm"><?= htmlspecialchars($log['usuario_nome']) ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="txt-meta-sm">Nenhum registro encontrado.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Texto do contrato colapsável -->
<div class="card mb-0">
    <div class="card-header" style="cursor: pointer;" onclick="toggleContrato()">
        <h3 class="card-title"><i class="ph ph-file-text"></i> Texto do Contrato</h3>
        <button type="button" class="btn btn-ghost btn--sm" id="btnToggleContrato">
            <i class="ph ph-caret-down" id="iconToggleContrato"></i> Expandir
        </button>
    </div>
    <div id="textoContratoWrapper" style="display: none;">
        <div class="card-body">
            <pre class="txt-contact-main" style="white-space: pre-wrap; font-family: var(--font-mono); font-size: 12px; max-height: 400px; overflow-y: auto;"><?= !empty($contrato['texto_contrato']) ? htmlspecialchars($contrato['texto_contrato']) : 'Contrato sem texto definido.' ?></pre>
        </div>
    </div>
</div>

<script>
function toggleContrato() {
    const wrapper = document.getElementById('textoContratoWrapper');
    const btn     = document.getElementById('btnToggleContrato');
    const icon    = document.getElementById('iconToggleContrato');
    const aberto  = wrapper.style.display !== 'none';

    wrapper.style.display = aberto ? 'none' : 'block';
    icon.className        = aberto ? 'ph ph-caret-down' : 'ph ph-caret-up';
    btn.innerHTML         = (aberto ? '<i class="ph ph-caret-down" id="iconToggleContrato"></i> Expandir' : '<i class="ph ph-caret-up" id="iconToggleContrato"></i> Recolher');
}

function copiarMensagemContrato(nome, codigo, link, btn) {
    const primeiroNome = nome.split(' ')[0];
    const msg = `Olá, ${primeiroNome}! Tudo bem?\n\nO seu contrato de prestação de serviços (*${codigo}*) já está redigido e pronto para assinatura! ✍️\n\nAcesse o link seguro abaixo para ler as cláusulas e assinar digitalmente. O pagamento da primeira parcela é feito direto na página:\n\n🔗 ${link}\n\nQualquer dúvida, estou à disposição!`;

    navigator.clipboard.writeText(msg).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML  = '<i class="ph-fill ph-check-circle"></i> Copiado!';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    }).catch(() => alert('Erro ao copiar.'));
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>