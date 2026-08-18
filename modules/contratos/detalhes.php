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

            // === INÍCIO DA AUTOMAÇÃO DRIVE E E-MAIL ===
            try {
                // MACETE PARA A HOSTINGER
                \Firebase\JWT\JWT::$leeway = 300;

                // Troque pelo ID da sua pasta nova que funcionou!
                $pasta_mae_id = '1UyXCtbT2Q-LdFzOzC_UiQ6DBa3dp9FCu'; 
                $client = new \Google_Client();
                $client->setAuthConfig('../../config/google-credentials.json');
                $client->addScope(\Google_Service_Drive::DRIVE);
                $driveService = new \Google_Service_Drive($client);

                // 1. CRIA A PASTA MÃE DO CLIENTE
                $nome_pasta   = $contrato['codigo_agc'] . ' - ' . $contrato['cliente_nome'];
                $fileMetadata = new \Google_Service_Drive_DriveFile([
                    'name'     => $nome_pasta,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents'  => [$pasta_mae_id]
                ]);
                $folder     = $driveService->files->create($fileMetadata, ['fields' => 'id, webViewLink']);
                $link_drive = $folder->webViewLink;
                $pasta_cliente_id = $folder->id;

                // 2. ESTRUTURA DE SUBPASTAS (A Árvore)
                $estrutura_pastas = [
                    '1 - Captação' => ['1.1 - Visual' => []],
                    '2 - Contratos e Administrativo' => [],
                    '3 - Identidade Visual e Branding' => ['3.1 - Logotipo' => []],
                    '4 - Planejamento Estratégico' => [],
                    '5 - Produção de Conteúdo' => [
                        '5.1 - Imagens' => ['5.1.1 - Entrega' => [], '5.1.2 - Produção' => []],
                        '5.2 - Vídeos' => ['5.2.1 - Entrega' => [], '5.2.2 - Produção' => []],
                        '5.3 - Roteiros' => ['5.3.1 - Videos' => [], '5.3.2 - Carrossel' => []]
                    ],
                    '6 - Compartilhada' => []
                ];

                $criarPastas = function($estrutura, $parentId) use (&$criarPastas, $driveService) {
                    foreach ($estrutura as $nome => $sub) {
                        $meta = new \Google_Service_Drive_DriveFile([
                            'name' => $nome,
                            'mimeType' => 'application/vnd.google-apps.folder',
                            'parents' => [$parentId]
                        ]);
                        $f = $driveService->files->create($meta, ['fields' => 'id']);
                        if (!empty($sub)) {
                            $criarPastas($sub, $f->id);
                        }
                    }
                };

                $criarPastas($estrutura_pastas, $pasta_cliente_id);

                // 3. GERA O ARQUIVO TXT DO CONTRATO LOCALMENTE
                $cpf_cnpj_aceite = $contrato['cpf_cnpj_aceite'] ?? 'Não assinado';
                $aceito_em = $contrato['aceito_em'] ? date('d/m/Y H:i:s', strtotime($contrato['aceito_em'])) : 'Não assinado';
                $aceito_ip = $contrato['aceito_ip'] ?? 'Não registrado';
                $texto_contrato = $contrato['texto_contrato'] ?? '';
                $texto_contrato = str_replace('{{SISTEMA_DATA_HORA}}', $aceito_em, $texto_contrato);
                $texto_contrato = str_replace('{{SISTEMA_IP}}', $aceito_ip, $texto_contrato);
                $texto_contrato = str_replace('Será preenchido na assinatura', $cpf_cnpj_aceite, $texto_contrato);

                $valor_parcela_fmt = number_format($contrato['valor'] ?? 0, 2, ',', '.');
                $duracao_fmt = (int)($contrato['duracao_meses'] ?? 1);
                $valor_total_fmt = number_format(($contrato['valor'] ?? 0) * $duracao_fmt, 2, ',', '.');
                $endereco_cliente = $contrato['cliente_endereco_completo'] ?? $contrato['cliente_endereco'] ?? 'Não informado';

                $conteudo_txt = "=" . str_repeat("=", 78) . "\n";
                $conteudo_txt .= "                    CONTRATO DE PRESTAÇÃO DE SERVIÇOS\n";
                $conteudo_txt .= "                  GASMASKE LAB - Assessoria Musical\n";
                $conteudo_txt .= "=" . str_repeat("=", 78) . "\n\n";
                $conteudo_txt .= "📄 CÓDIGO: " . $contrato['codigo_agc'] . "\n";
                $conteudo_txt .= "📅 DATA DE GERAÇÃO: " . date('d/m/Y H:i:s') . "\n";
                $conteudo_txt .= "📊 STATUS: EM ANDAMENTO\n\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n";
                $conteudo_txt .= "DADOS DO CONTRATANTE\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n\n";
                $conteudo_txt .= "NOME / RAZÃO SOCIAL: " . $contrato['cliente_nome'] . "\n";
                $conteudo_txt .= "CPF / CNPJ: " . $cpf_cnpj_aceite . "\n";
                $conteudo_txt .= "E-MAIL: " . ($contrato['cliente_email'] ?? 'Não informado') . "\n";
                $conteudo_txt .= "TELEFONE: " . ($contrato['cliente_telefone'] ?? 'Não informado') . "\n";
                $conteudo_txt .= "ENDEREÇO: " . $endereco_cliente . "\n\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n";
                $conteudo_txt .= "DADOS DO CONTRATO\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n\n";
                $conteudo_txt .= "VALOR MENSAL: R$ " . $valor_parcela_fmt . "\n";
                $conteudo_txt .= "DURAÇÃO: " . $duracao_fmt . " meses\n";
                $conteudo_txt .= "VALOR TOTAL: R$ " . $valor_total_fmt . "\n";
                $conteudo_txt .= "DIA DE VENCIMENTO: " . $dia_vencimento . "\n\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n";
                $conteudo_txt .= "REGISTRO DE ASSINATURA DIGITAL\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n\n";
                $conteudo_txt .= "DOCUMENTO: " . $cpf_cnpj_aceite . "\n";
                $conteudo_txt .= "DATA E HORA: " . $aceito_em . "\n";
                $conteudo_txt .= "IP DE ORIGEM: " . $aceito_ip . "\n";
                $conteudo_txt .= "VALIDADE JURÍDICA: Lei nº 14.063/2020 (Assinatura Digital)\n\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n";
                $conteudo_txt .= "CLÁUSULAS DO CONTRATO\n";
                $conteudo_txt .= str_repeat("-", 78) . "\n\n";
                $conteudo_txt .= $texto_contrato . "\n\n";
                $conteudo_txt .= str_repeat("=", 78) . "\n";
                $conteudo_txt .= "Documento gerado eletronicamente em " . date('d/m/Y H:i:s') . "\n";
                $conteudo_txt .= "Gasmaske Lab\n";
                $conteudo_txt .= "Este documento tem fé pública e validade jurídica nos termos da Lei nº 14.063/2020.\n";
                $conteudo_txt .= "Verifique a autenticidade pelo código do contrato: " . $contrato['codigo_agc'] . "\n";
                $conteudo_txt .= str_repeat("=", 78) . "\n";

                $nome_arquivo = 'Contrato_' . $contrato['codigo_agc'] . '_' . date('Y-m-d') . '.txt';
                $caminho_temp = sys_get_temp_dir() . '/' . $nome_arquivo;
                file_put_contents($caminho_temp, $conteudo_txt);

                // 4. DISPARO DOS E-MAILS COM ANEXO
                $boundary = md5(time());
                $headers  = "From: contato@gasmaskelab.com.br\r\n";
                $headers .= "Reply-To: contato@gasmaskelab.com.br\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";

                $mensagem_html = "Olá " . $contrato['cliente_nome'] . ",\n\nSeu contrato de prestação de serviços (Código: ".$contrato['codigo_agc'].") está ativo e em andamento.\n\nSegue em anexo a sua via do contrato, contendo o registro de assinatura e validade jurídica.\n\nAgradecemos a confiança e estamos prontos para iniciar o projeto!\n\nAtenciosamente,\nEquipe Gasmaske Lab";
                
                $body = "--$boundary\r\n";
                $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $body .= $mensagem_html . "\r\n\r\n";

                $body .= "--$boundary\r\n";
                $body .= "Content-Type: text/plain; name=\"$nome_arquivo\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n";
                $body .= "Content-Disposition: attachment; filename=\"$nome_arquivo\"\r\n\r\n";
                $body .= chunk_split(base64_encode(file_get_contents($caminho_temp))) . "\r\n";
                $body .= "--$boundary--";

                if (!empty($contrato['cliente_email'])) {
                    mail($contrato['cliente_email'], "Seu Contrato Ativo - Gasmaske Lab", $body, $headers, "-fcontato@gasmaskelab.com.br");
                }
                mail("contato@gasmaskelab.com.br", "CONTRATO ATIVADO: " . $contrato['codigo_agc'] . " - " . $contrato['cliente_nome'], $body, $headers, "-fcontato@gasmaskelab.com.br");

                unlink($caminho_temp);
                $msg_drive  = " | Pastas geradas e e-mails disparados com sucesso!";
            } catch (Exception $e) {
                $msg_drive = " (Falha na automação: " . $e->getMessage() . ")";
            }
            // === FIM DA AUTOMAÇÃO DRIVE E E-MAIL ===

            // === ATUALIZAÇÃO DO BANCO DE DADOS E GERAÇÃO DE PARCELAS ===
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

<style>
    /* Estilos do Layout Principal */
    .layout-dashboard {
        display: flex;
        flex-direction: column;
        gap: 24px;
        margin-bottom: 24px;
    }
    .grid-top {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 24px;
    }
    .grid-bottom {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
    }

    /* ==== NOVO: Grid do Resumo (Blocos internos) ==== */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 16px;
        margin-top: 8px;
    }
    .summary-item {
        background: var(--bg-surface, #f8f9fc);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 8px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .summary-label {
        font-size: 11px;
        color: var(--text-muted, #f8f9fc);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .summary-label i {
        font-size: 14px;
    }
    .summary-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-main, #f8f9fc);
        word-break: break-word;
    }

    /* ==== NOVO: Dashboard de Parcelas (Totalizadores e Tabela) ==== */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }
    .stat-box {
        background: var(--bg-surface, #f8f9fc);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 8px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .stat-number {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .stat-label {
        font-size: 12px;
        color: var(--text-muted, #f8f9fc);
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .modern-table {
        width: 100%;
        border-collapse: collapse;
    }
    .modern-table th {
        background: var(--bg-surface, #f8f9fc);
        padding: 12px 16px;
        font-size: 12px;
        color: var(--text-muted, #f8f9fc);
        text-transform: uppercase;
        font-weight: 600;
        text-align: left;
        border-bottom: 2px solid var(--border-color, #e2e8f0);
    }
    .modern-table th.text-center { text-align: center; }
    .modern-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color, #e2e8f0);
        vertical-align: middle;
        font-size: 14px;
    }
    .modern-table tr:last-child td {
        border-bottom: none;
    }
    .modern-table tbody tr:hover {
        background: var(--bg-hover, #f1f5f9);
    }

    /* Responsividade */
    @media (max-width: 992px) {
        .grid-bottom {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="layout-dashboard">
    
    <!-- ================= LINHA SUPERIOR ================= -->
    <div class="grid-top">
        
        <!-- Card: Resumo (Agora com Grid Interno) -->
        <div class="card" style="margin-bottom: 0;">
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
                <div class="summary-grid">
                    <div class="summary-item" style="grid-column: 1 / -1;">
                        <span class="summary-label"><i class="ph ph-user"></i> Cliente</span>
                        <span class="summary-value">
                            <a href="<?= BASE_URL ?>modules/clientes/visualizar.php?id=<?= $contrato['cliente_id'] ?>">
                                <?= htmlspecialchars($contrato['cliente_nome']) ?>
                            </a>
                        </span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="ph ph-calendar-blank"></i> Duração</span>
                        <span class="summary-value"><?= $duracao ?> meses</span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="ph ph-currency-dollar"></i> Valor / Parcela</span>
                        <span class="summary-value"><?= money($valor_parcela) ?></span>
                    </div>
                    
                    <div class="summary-item">
                        <span class="summary-label"><i class="ph ph-coins"></i> Total do Contrato</span>
                        <span class="summary-value text-red"><?= money($valor_contrato) ?></span>
                    </div>

                    <?php if (!empty($contrato['data_inicio'])): ?>
                    <div class="summary-item">
                        <span class="summary-label"><i class="ph ph-play-circle"></i> Início</span>
                        <span class="summary-value"><?= dataBR($contrato['data_inicio']) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($contrato['dia_vencimento'])): ?>
                    <div class="summary-item">
                        <span class="summary-label"><i class="ph ph-calendar-check"></i> Vencimento</span>
                        <span class="summary-value">Dia <?= $contrato['dia_vencimento'] ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($contrato['link_drive'])): ?>
                <div style="margin-top: 16px;">
                    <a href="<?= htmlspecialchars($contrato['link_drive']) ?>" target="_blank" class="btn btn-secondary w-100" style="justify-content: center;">
                        <i class="ph ph-folder-open"></i> Acessar Pasta no Drive
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card: Painel de Controle (Oculto se ativo ou finalizado) -->
        <?php if (!in_array($contrato['status'], ['em_andamento', 'finalizado'])): ?>
        <div class="card" style="margin-bottom: 0;">
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

                        <a href="gerar_contrato.php?id=<?= $contrato['id'] ?>" 
                            class="btn w-100" 
                            style="justify-content: center; background: #2D3748; color: #fff; border: none;">
                                <i class="ph ph-file-text"></i> Baixar Contrato .TXT
                            </a>
                    </div>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ================= LINHA INFERIOR ================= -->
    <div class="grid-bottom">
        
        <!-- Card: Parcelas -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-currency-circle-dollar"></i> Parcelas</h3>
                <span class="badge badge-gray"><?= count($parcelas) ?> parcela<?= count($parcelas) !== 1 ? 's' : '' ?></span>
            </div>

            <div class="card-body">
                <?php if (count($parcelas) > 0): ?>
                    <div class="stats-grid">
                        <div class="stat-box" style="border-bottom: 3px solid var(--green);">
                            <div class="stat-number text-green"><?= money($total_pago) ?></div>
                            <div class="stat-label">Recebido</div>
                        </div>
                        <div class="stat-box" style="border-bottom: 3px solid var(--yellow);">
                            <div class="stat-number text-yellow"><?= money($total_aberto) ?></div>
                            <div class="stat-label">Em Aberto</div>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="modern-table">
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
                                        <td><strong><?= $p['numero_parcela'] ?>/<?= $duracao ?></strong></td>
                                        <td><?= dataBR($p['data_vencimento']) ?></td>
                                        <td><strong><?= money($p['valor']) ?></strong></td>
                                        <td>
                                            <?php if (!empty($p['data_pagamento'])): ?>
                                                <span class="text-green"><i class="ph ph-check"></i> <?= dataBR($p['data_pagamento']) ?></span>
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

                    <p class="txt-meta-sm" style="margin-top: 16px; text-align: center;">
                        <i class="ph ph-info"></i> Para registrar pagamentos, acesse o módulo
                        <a href="<?= BASE_URL ?>modules/financeiro/" style="text-decoration: underline;">Financeiro</a>.
                    </p>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="ph ph-currency-circle-dollar empty-state-icon"></i>
                        Parcelas geradas ao confirmar o primeiro pagamento.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card: Histórico -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-clock-countdown"></i> Histórico</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($logs)): ?>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($logs as $log): ?>
                            <div style="border-left: 2px solid var(--border-color, #e2e8f0); padding-left: 12px;">
                                <div style="font-size: 11px; color: var(--text-muted, #f8f9fc); margin-bottom: 2px;">
                                    <i class="ph ph-calendar-blank"></i> <?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?>
                                </div>
                                <div style="font-size: 14px; font-weight: 500; color: var(--text-main, #f8f9fc);">
                                    <?= htmlspecialchars($log['descricao']) ?>
                                </div>
                                <?php if (!empty($log['usuario_nome'])): ?>
                                    <div style="font-size: 12px; color: var(--text-muted, #f8f9fc); margin-top: 4px;">
                                        <i class="ph ph-user"></i> <?= htmlspecialchars($log['usuario_nome']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
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