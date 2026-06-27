<?php
// modules/clientes/visualizar.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="flex-between mb-20">
    <div>
        <h2 class="page-title">Visão Geral: <?= htmlspecialchars($cliente['nome'] ?? '') ?></h2>
        <a href="index.php">← Voltar para a lista</a>
    </div>
    <a href="editar.php?id=<?= $cliente['id'] ?>" class="btn btn-primary">Editar Cliente</a>
</div>

<div class="dashboard-grid">
    <div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Dados do Cliente</h3></div>
            <div class="form-group"><label>Empresa / Nome</label><div><?= htmlspecialchars($cliente['nome'] ?? '') ?></div></div>
            <div class="form-group"><label>E-mail</label><div><?= htmlspecialchars($cliente['email'] ?? '') ?></div></div>
            <div class="form-group"><label>Telefone</label><div><?= htmlspecialchars($cliente['telefone'] ?? '') ?></div></div>
            <div class="form-group"><label>CPF / CNPJ</label><div><?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?></div></div>
            <div class="form-group"><label>Endereço</label><div><?= nl2br(htmlspecialchars($cliente['endereco_completo'] ?? $cliente['endereco'] ?? '')) ?></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Redes Sociais (@)</h3></div>
            <div class="form-group"><label>Instagram</label><div><?= htmlspecialchars($cliente['user_insta'] ?? '') ?></div></div>
            <div class="form-group"><label>Facebook</label><div><?= htmlspecialchars($cliente['user_fb'] ?? '') ?></div></div>
            <div class="form-group"><label>TikTok</label><div><?= htmlspecialchars($cliente['user_tt'] ?? '') ?></div></div>
            <div class="form-group"><label>LinkedIn</label><div><?= htmlspecialchars($cliente['user_li'] ?? '') ?></div></div>
            <div class="form-group"><label>YouTube</label><div><?= htmlspecialchars($cliente['user_yt'] ?? '') ?></div></div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Escopo Operacional Mensal</h3></div>
            <div class="form-group"><label>Total de Posts por Semana</label><div><?= htmlspecialchars($cliente['posts_semanais'] ?? '0') ?></div></div>
            <div class="form-group"><label>Vídeos por Semana</label><div><?= htmlspecialchars($cliente['videos_semana'] ?? '0') ?></div></div>
            <div class="form-group"><label>Estáticos por Semana</label><div><?= htmlspecialchars($cliente['estaticos_semana'] ?? '0') ?></div></div>
            <div class="form-group"><label>Roteiros (Qtd)</label><div><?= htmlspecialchars($cliente['roteiros'] ?? '0') ?></div></div>
            <div class="form-group"><label>Diárias de Captação</label><div><?= htmlspecialchars($cliente['captacao_mensal'] ?? '0') ?></div></div>
            <div class="form-group"><label>Tráfego Pago</label><div><?= ($cliente['trafego_pago'] ?? 0) > 0 ? 'Sim (' . htmlspecialchars($cliente['trafego_pago'] ?? '0') . ')' : 'Não' ?></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Links & Observações</h3></div>
            <div class="form-group"><label>Pasta no Drive</label><div><a href="<?= htmlspecialchars($cliente['link_drive'] ?? '#') ?>" target="_blank">Acessar Drive</a></div></div>
            <div class="form-group"><label>Referências</label><div><a href="<?= htmlspecialchars($cliente['link_referencias'] ?? '#') ?>" target="_blank">Acessar Referências</a></div></div>
            <div class="form-group"><label>Observações</label><div><?= nl2br(htmlspecialchars($cliente['observacoes'] ?? '')) ?></div></div>
        </div>
    </div>
</div>