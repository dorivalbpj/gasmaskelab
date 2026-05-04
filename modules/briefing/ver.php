<?php
// modules/briefing/ver.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Captura o ID de forma robusta
$id = $_REQUEST['id'] ?? 0;
$mensagem = '';

// Busca os detalhes do briefing
$stmt = $pdo->prepare("SELECT * FROM briefings WHERE id = ?");
$stmt->execute([$id]);
$briefing = $stmt->fetch();

if (!$briefing) {
    die("<div class='dashboard-grid' style='padding: 50px;'><div class='card text-center'><h2>Briefing ID #$id não encontrado.</h2><br><a href='index.php' class='btn btn-secondary'>Voltar para a Lista</a></div></div>");
}

// --- LÓGICA: GERAR PROPOSTA AUTOMÁTICA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'gerar_proposta') {
    try {
        $pdo->beginTransaction();

        // 1. Definição do Nome do Cliente (Prioridade: Empresa > Nome Pessoa)
        $nome_final = !empty($briefing['empresa']) ? trim($briefing['empresa']) : trim($briefing['nome']);
        $email_cli = trim($briefing['email']);
        
        // Verifica se o cliente já existe
        $stmt_cli = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
        $stmt_cli->execute([$email_cli]);
        $cliente = $stmt_cli->fetch();

        if ($cliente) {
            $cliente_id = $cliente['id'];
            // CORRIGIDO: Removido o campo 'empresa' que não existe na tabela clientes
            $pdo->prepare("UPDATE clientes SET nome = ?, telefone = ? WHERE id = ?")
                ->execute([$nome_final, $briefing['telefone'], $cliente_id]);
        } else {
            // CORRIGIDO: Inserindo apenas no campo 'nome'
            $stmt_ins_cli = $pdo->prepare("INSERT INTO clientes (nome, email, telefone) VALUES (?, ?, ?)");
            $stmt_ins_cli->execute([$nome_final, $email_cli, $briefing['telefone']]);
            $cliente_id = $pdo->lastInsertId();
        }

        // 2. Coleta das Descrições Padrão dos Serviços
        $servicos_array = array_filter(array_map('trim', explode(',', $briefing['servicos_desejados'])));
        $texto_servicos_padrao = "";
        
        foreach ($servicos_array as $nome_servico) {
            $stmt_srv = $pdo->prepare("SELECT descricao_padrao FROM servicos WHERE nome LIKE ?");
            $stmt_srv->execute(["%" . $nome_servico . "%"]);
            $srv = $stmt_srv->fetch();
            
            if ($srv && !empty($srv['descricao_padrao'])) {
                $texto_servicos_padrao .= "🔹 " . mb_strtoupper($nome_servico, 'UTF-8') . ":\n" . $srv['descricao_padrao'] . "\n\n";
            }
        }

        // 3. Montagem do Escopo
        $descricao_automatica = "--- ESCOPO E OBJETIVOS ---\n\n";
        $descricao_automatica .= $texto_servicos_padrao;
        $descricao_automatica .= "--- INFORMAÇÕES COLETADAS NO BRIEFING ---\n";
        $descricao_automatica .= $briefing['objetivo'];

        // 4. Inserção da Proposta
        $data_validade = date('Y-m-d', strtotime('+7 days'));
        $token = bin2hex(random_bytes(16));
        
        $stmt_prop = $pdo->prepare("INSERT INTO propostas (cliente_id, titulo, descricao, servicos_inclusos, valor, tipo_cobranca, duracao_meses, data_validade, token, status) VALUES (?, ?, ?, ?, 0, 'mensal', 1, ?, ?, 'enviada')");
        $stmt_prop->execute([$cliente_id, "Projeto " . $nome_final, $descricao_automatica, $briefing['servicos_desejados'], $data_validade, $token]);
        $prop_id = $pdo->lastInsertId();

        // Código da Proposta (Ex: PRP-001)
        $codigo_proposta = "PRP-" . str_pad($prop_id, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE propostas SET codigo_proposta = ? WHERE id = ?")->execute([$codigo_proposta, $prop_id]);
        
        // Atualiza status do briefing
        $pdo->prepare("UPDATE briefings SET status = 'proposta_criada' WHERE id = ?")->execute([$id]);

        $pdo->commit();

        header("Location: ../propostas/form.php?id=" . $prop_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Detalhes do Briefing</h2>
        <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 14px;">← Voltar para a Lista</a>
    </div>
    
    <div style="display: flex; gap: 12px;">
        <?php if ($briefing['status'] != 'proposta_criada'): ?>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="acao" value="gerar_proposta">
            <button type="submit" class="btn btn-primary">
                <i class="ph ph-magic-wand"></i> Gerar Proposta Automática
            </button>
        </form>
        <?php else: ?>
            <span class="badge badge-green" style="font-size: 14px; padding: 10px 15px;"><i class="ph-fill ph-check-circle"></i> Proposta já gerada</span>
        <?php endif; ?>
    </div>
</div>

<?= $mensagem ?>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Informações do Contato</h3></div>
        <div style="display: grid; gap: 15px;">
            <div class="dash-panel">
                <span class="txt-meta-sm">Responsável</span>
                <strong class="txt-name-main"><?= htmlspecialchars($briefing['nome']) ?></strong>
            </div>
            <div class="dash-panel">
                <span class="txt-meta-sm">Empresa / Projeto</span>
                <strong class="txt-name-main"><?= htmlspecialchars($briefing['empresa'] ?: 'Não informada') ?></strong>
            </div>
            <div class="briefing-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="dash-panel">
                    <span class="txt-meta-sm">E-mail</span>
                    <strong class="txt-name-main"><?= htmlspecialchars($briefing['email']) ?></strong>
                </div>
                <div class="dash-panel">
                    <span class="txt-meta-sm">WhatsApp</span>
                    <strong class="txt-name-main"><?= htmlspecialchars($briefing['telefone']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 24px;">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Serviços Solicitados</h3></div>
            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php 
                    $servs = array_filter(array_map('trim', explode(',', $briefing['servicos_desejados'])));
                    foreach($servs as $s): echo "<span class='badge badge-blue'>$s</span>"; endforeach;
                ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Respostas Completas</h3></div>
            <div style="background: var(--bg-base); border: 1px solid var(--border-mid); border-radius: var(--radius-md); padding: 20px; white-space: pre-wrap; font-size: 14px; line-height: 1.6; color: var(--text-secondary);">
                <?= htmlspecialchars($briefing['objetivo']) ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>