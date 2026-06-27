<?php
// modules/clientes/visualizar.php - VERSÃO COMPLETA

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

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- CSS ESPECÍFICO DA PÁGINA -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<div class="cabecalho">
    <div class="cabecalho-cliente">
        <?php if (!empty($cliente['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($cliente['avatar_url']) ?>" 
                 alt="Avatar do Instagram" 
                 class="avatar-cliente">
        <?php else: ?>
            <div class="avatar-placeholder">
                <i class="ph ph-user"></i>
            </div>
        <?php endif; ?>
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
                    <span class="info-value"><a href="mailto:<?= htmlspecialchars($cliente['email'] ?? '') ?>"><?= htmlspecialchars($cliente['email'] ?? '') ?></a></span>
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

        <!-- Card: Links & Observações -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="ph ph-link"></i> Links & Observações</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label"><i class="ph ph-folder"></i> Drive</span>
                    <span class="info-value">
                        <?php if (!empty($cliente['link_drive'])): ?>
                            <a href="<?= htmlspecialchars($cliente['link_drive']) ?>" target="_blank">
                                <i class="ph ph-arrow-square-out"></i> Acessar Pasta
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-secondary);">Não informado</span>
                        <?php endif; ?>
                    </span>
                </div>
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

<?php require_once '../../includes/layout/footer.php'; ?>