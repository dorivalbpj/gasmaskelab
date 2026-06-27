<?php
// modules/clientes/visualizar.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_avatar.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

// Se não tem avatar salvo, gera por iniciais e salva para próximas visitas
if (empty($cliente['avatar_url'])) {
    salvarAvatarCliente($cliente['id'], $cliente['nome'], $pdo);
    $cliente['avatar_url'] = gerarAvatarIniciais($cliente['nome']);
}

// Busca contratos vinculados ao cliente
$stmt_contratos = $pdo->prepare("
    SELECT id, codigo_agc, valor, status, data_inicio, duracao_meses, link_drive, criado_em
    FROM contratos
    WHERE cliente_id = ?
    ORDER BY criado_em DESC
");
$stmt_contratos->execute([$id]);
$contratos = $stmt_contratos->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<div class="cabecalho">
    <div class="cabecalho-cliente">
        <img src="<?= htmlspecialchars($cliente['avatar_url']) ?>"
             alt="Avatar de <?= htmlspecialchars($cliente['nome']) ?>"
             class="avatar-cliente">
        <div>
            <h2 class="page-title">Visualizar Cliente</h2>
            <p class="page-subtitle"><?= htmlspecialchars($cliente['nome'] ?? '') ?></p>
        </div>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="index.php" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Voltar
        </a>
        <a href="editar.php?id=<?= $cliente['id'] ?>" class="btn btn-primary">
            <i class="ph ph-pencil-simple"></i> Editar
        </a>
    </div>
</div>

<div class="grid-2col">
    <!-- Coluna Esquerda -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Card: Dados do Cliente -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-user"></i> Dados do Cliente</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Empresa / Nome</span>
                    <span class="info-value"><?= htmlspecialchars($cliente['nome'] ?? '') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">E-mail</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['email'])): ?>
                            <a href="mailto:<?= htmlspecialchars($cliente['email']) ?>"><?= htmlspecialchars($cliente['email']) ?></a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Telefone / WhatsApp</span>
                    <span class="info-value" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span><?= htmlspecialchars($cliente['telefone'] ?? '') ?></span>
                        <?php if (!empty($cliente['telefone'])): ?>
                            <a href="https://wa.me/55<?= preg_replace('/[^0-9]/', '', $cliente['telefone']) ?>"
                               target="_blank"
                               class="btn-whatsapp">
                                <i class="ph ph-whatsapp-logo" style="font-size: 16px;"></i>
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">CPF / CNPJ</span>
                    <span class="info-value"><?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Endereço</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($cliente['endereco_completo'] ?? $cliente['endereco'] ?? '')) ?></span>
                </div>
            </div>
        </div>

        <!-- Card: Redes Sociais -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-share-network"></i> Redes Sociais</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-instagram-logo" style="color: #E4405F;"></i> Instagram</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['user_insta'])): ?>
                            <a href="https://instagram.com/<?= htmlspecialchars($cliente['user_insta']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                @<?= htmlspecialchars($cliente['user_insta']) ?> <i class="ph ph-arrow-square-out" style="font-size: 12px;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-facebook-logo" style="color: #1877F2;"></i> Facebook</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['user_fb'])): ?>
                            <a href="https://facebook.com/<?= htmlspecialchars($cliente['user_fb']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                <?= htmlspecialchars($cliente['user_fb']) ?> <i class="ph ph-arrow-square-out" style="font-size: 12px;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-tiktok-logo" style="color: #000;"></i> TikTok</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['user_tt'])): ?>
                            <a href="https://tiktok.com/@<?= htmlspecialchars($cliente['user_tt']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                @<?= htmlspecialchars($cliente['user_tt']) ?> <i class="ph ph-arrow-square-out" style="font-size: 12px;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-linkedin-logo" style="color: #0A66C2;"></i> LinkedIn</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['user_li'])): ?>
                            <a href="https://linkedin.com/in/<?= htmlspecialchars($cliente['user_li']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                <?= htmlspecialchars($cliente['user_li']) ?> <i class="ph ph-arrow-square-out" style="font-size: 12px;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-youtube-logo" style="color: #FF0000;"></i> YouTube</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['user_yt'])): ?>
                            <a href="https://youtube.com/@<?= htmlspecialchars($cliente['user_yt']) ?>" target="_blank" style="color: var(--primary-color); text-decoration: none;">
                                @<?= htmlspecialchars($cliente['user_yt']) ?> <i class="ph ph-arrow-square-out" style="font-size: 12px;"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

    </div>

    <!-- Coluna Direita -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

        <!-- Card: Escopo Operacional -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-chart-bar"></i> Escopo Operacional Mensal</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-number"><?= htmlspecialchars($cliente['posts_semanais'] ?? '0') ?></div>
                        <div class="stat-label">Posts / Semana</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?= htmlspecialchars($cliente['videos_semana'] ?? '0') ?></div>
                        <div class="stat-label">Vídeos / Semana</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?= htmlspecialchars($cliente['estaticos_semana'] ?? '0') ?></div>
                        <div class="stat-label">Estáticos / Semana</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?= htmlspecialchars($cliente['roteiros'] ?? '0') ?></div>
                        <div class="stat-label">Roteiros</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?= htmlspecialchars($cliente['captacao_mensal'] ?? '0') ?></div>
                        <div class="stat-label">Diárias Captação</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" style="color: <?= ($cliente['trafego_pago'] ?? 0) > 0 ? 'var(--green-500)' : 'var(--text-secondary)' ?>;">
                            <?= ($cliente['trafego_pago'] ?? 0) > 0 ? 'Sim' : 'Não' ?>
                        </div>
                        <div class="stat-label">Tráfego Pago</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card: Links & Observações (sem Drive) -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-link"></i> Links & Observações</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-book"></i> Referências</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['link_referencias'])): ?>
                            <a href="<?= htmlspecialchars($cliente['link_referencias']) ?>" target="_blank">
                                <i class="ph ph-arrow-square-out"></i> Acessar Referências
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row" style="border-bottom: none;">
                    <span class="info-label"><i class="ph ph-note"></i> Observações</span>
                    <span class="info-value" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($cliente['observacoes'] ?? '')) ?: '<span style="color: var(--text-secondary);">Nenhuma observação</span>' ?></span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Card: Contratos do Cliente (largura total) -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <h3 class="card-title"><i class="ph ph-scroll"></i> Contratos</h3>
        <span class="badge badge-gray"><?= count($contratos) ?> Registro<?= count($contratos) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (count($contratos) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Data</th>
                        <th>Duração</th>
                        <th>Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Drive</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos as $c): ?>
                        <?php
                            $badge_class = 'badge-gray';
                            if ($c['status'] == 'aguardando_aceite_cliente') $badge_class = 'badge-yellow';
                            if ($c['status'] == 'aguardando_pagamento')      $badge_class = 'badge-blue';
                            if ($c['status'] == 'em_andamento')              $badge_class = 'badge-green';
                            if ($c['status'] == 'finalizado')                $badge_class = 'badge-purple';
                        ?>
                        <tr>
                            <td>
                                <span class="txt-name-main"><?= htmlspecialchars($c['codigo_agc']) ?></span>
                            </td>
                            <td>
                                <span class="txt-date-sm"><?= dataBR($c['criado_em']) ?></span>
                            </td>
                            <td>
                                <span class="txt-contact-main"><?= (int)$c['duracao_meses'] ?> meses</span>
                            </td>
                            <td>
                                <span class="txt-contact-main">R$ <?= number_format($c['valor'], 2, ',', '.') ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $badge_class ?>">
                                    <?= str_replace('_', ' ', $c['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($c['link_drive'])): ?>
                                    <a href="<?= htmlspecialchars($c['link_drive']) ?>" target="_blank" class="btn btn-ghost btn-sm btn-icon-table" title="Abrir pasta no Drive">
                                        <i class="ph ph-folder-open"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary);">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="<?= BASE_URL ?>modules/contratos/detalhes.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon-table" title="Ver Detalhes">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>modules/contratos/form.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon-table" title="Editar Contrato">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-scroll empty-state-icon"></i>
            Nenhum contrato vinculado a este cliente.
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>