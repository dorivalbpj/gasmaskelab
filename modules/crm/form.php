<?php
// modules/crm/form.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;

$lead = [
    'nome' => '', 
    'empresa' => '', 
    'email' => '', 
    'telefone' => '',
    'status' => 'contato_inicial', 
    'temperatura' => 'morno',
    'data_proximo_contato' => '', 
    'anotacoes' => ''
];

$mensagem = '';

// Se passou ID, busca os dados
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if ($res) {
        $lead = array_merge($lead, $res);
    }
}

// Lógica de Salvamento
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $status = $_POST['status'] ?? 'contato_inicial';
    $temperatura = $_POST['temperatura'] ?? 'morno';
    $data_proximo_contato = empty($_POST['data_proximo_contato']) ? null : $_POST['data_proximo_contato'];
    $anotacoes = trim($_POST['anotacoes'] ?? '');

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE leads SET nome=?, empresa=?, email=?, telefone=?, status=?, temperatura=?, data_proximo_contato=?, anotacoes=? WHERE id=?");
            $stmt->execute([$nome, $empresa, $email, $telefone, $status, $temperatura, $data_proximo_contato, $anotacoes, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Lead atualizado com sucesso!</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO leads (nome, empresa, email, telefone, status, temperatura, data_proximo_contato, anotacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $empresa, $email, $telefone, $status, $temperatura, $data_proximo_contato, $anotacoes]);
            $id = $pdo->lastInsertId();
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Novo lead cadastrado com sucesso!</div>";
        }
        
        // Atualiza a variável para refletir na tela
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$id]);
        $lead = $stmt->fetch();
        
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao salvar: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- Importa o CSS global de clientes para herdar o design system -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Editar Lead' : 'Novo Lead' ?></h2>
        <p class="page-subtitle">Cadastre os dados iniciais para não perder o contato de vista.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?= $mensagem ?>

<form method="POST" action="">
    <div class="grid-2col">
        
        <!-- Coluna Esquerda: Dados Pessoais/Empresa -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-identification-card"></i> Dados do Prospect</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label>Nome do Contato *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($lead['nome']) ?>" required placeholder="Ex: João Silva">
                    </div>
                    
                    <div class="form-group">
                        <label>Nome da Empresa / Projeto</label>
                        <input type="text" name="empresa" class="form-control" value="<?= htmlspecialchars($lead['empresa']) ?>" placeholder="Ex: Gasmaske Lab">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label><i class="ph ph-whatsapp-logo" style="color: #25D366;"></i> WhatsApp</label>
                            <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($lead['telefone']) ?>" placeholder="(00) 00000-0000">
                        </div>
                        <div class="form-group">
                            <label><i class="ph ph-envelope"></i> E-mail</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($lead['email']) ?>" placeholder="contato@empresa.com">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Controle e Termômetro -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-crosshair"></i> Termômetro & Negociação</h3>
                </div>
                <div class="card-body">
                    
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Status do Funil</label>
                            <select name="status" class="form-control">
                                <option value="contato_inicial" <?= $lead['status'] == 'contato_inicial' ? 'selected' : '' ?>>Contato Inicial</option>
                                <option value="aguardando_briefing" <?= $lead['status'] == 'aguardando_briefing' ? 'selected' : '' ?>>Aguardando Briefing</option>
                                <option value="em_negociacao" <?= $lead['status'] == 'em_negociacao' ? 'selected' : '' ?>>Em Negociação</option>
                                <option value="ganho" <?= $lead['status'] == 'ganho' ? 'selected' : '' ?>>Ganho (Fechou)</option>
                                <option value="perdido" <?= $lead['status'] == 'perdido' ? 'selected' : '' ?>>Perdido (Esfriou)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Temperatura</label>
                            <select name="temperatura" class="form-control">
                                <option value="quente" <?= $lead['temperatura'] == 'quente' ? 'selected' : '' ?>>🔥 Quente</option>
                                <option value="morno" <?= $lead['temperatura'] == 'morno' ? 'selected' : '' ?>>⚡ Morno</option>
                                <option value="frio" <?= $lead['temperatura'] == 'frio' ? 'selected' : '' ?>>❄️ Frio</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 8px;">
                        <label><i class="ph ph-calendar"></i> Próximo Contato (Follow-up)</label>
                        <input type="date" name="data_proximo_contato" class="form-control" value="<?= htmlspecialchars($lead['data_proximo_contato']) ?>">
                        <small class="form-hint">Isso vai aparecer no seu painel para você não esquecer de chamar o cara.</small>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; margin-top: 16px;">
                        <label><i class="ph ph-note"></i> Histórico & Anotações</label>
                        <textarea name="anotacoes" class="form-control" rows="5" placeholder="O que o cliente precisa? Resumo da última conversa..."><?= htmlspecialchars($lead['anotacoes']) ?></textarea>
                    </div>
                    
                </div>
            </div>
        </div>
        
    </div>

    <div class="form-actions-bar">
        <a href="index.php" class="btn btn-ghost">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="ph ph-floppy-disk"></i> Salvar Lead
        </button>
    </div>
</form>

<?php require_once '../../includes/layout/footer.php'; ?>