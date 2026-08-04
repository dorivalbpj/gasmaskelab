<?php
// includes/layout/sidebar.php

// Lógica para saber qual menu deixar "aceso" (Active)
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Overlay do Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-wrapper">
            <img src="<?= BASE_URL ?>assets/img/logo-h.png" class="logo-img logo-h" alt="Logo">
        </div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-section-label">Visão Geral</div>
        <a href="<?= BASE_URL ?>index.php" class="<?= ($current_page == 'index.php' && $current_dir == 'gasmaske') ? 'active' : '' ?>">
            <i class="ph ph-squares-four" style="font-size: 18px;"></i> <span class="hide-on-collapse">Dashboard</span>
        </a>
        
        <div class="sidebar-section-label">Comercial & CRM</div>
        <a href="<?= BASE_URL ?>modules/briefing/index.php" class="<?= ($current_dir == 'briefing') ? 'active' : '' ?>">
            <i class="ph ph-envelope-simple-open" style="font-size: 18px;"></i> <span class="hide-on-collapse">Briefings</span>
        </a>
        <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>modules/clientes/index.php" class="<?= ($current_dir == 'clientes') ? 'active' : '' ?>">
                <i class="ph ph-users" style="font-size: 18px;"></i> <span class="hide-on-collapse">Clientes</span>
            </a>
            <a href="<?= BASE_URL ?>modules/propostas/index.php" class="<?= ($current_dir == 'propostas') ? 'active' : '' ?>">
                <i class="ph ph-file-text" style="font-size: 18px;"></i> <span class="hide-on-collapse">Propostas</span>
            </a>
            <a href="<?= BASE_URL ?>modules/contratos/index.php" class="<?= ($current_dir == 'contratos') ? 'active' : '' ?>">
                <i class="ph ph-handshake" style="font-size: 18px;"></i> <span class="hide-on-collapse">Contratos</span>
            </a>
            <a href="<?= BASE_URL ?>modules/crm/index.php" class="<?= ($current_dir == 'crm') ? 'active' : '' ?>">
                <i class="ph ph-address-book" style="font-size: 18px;"></i> <span class="hide-on-collapse">CRM</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-section-label">Labs</div>
        <a href="<?= BASE_URL ?>modules/ia/index.php" class="<?= ($current_dir == 'ia') ? 'active' : '' ?>">
            <i class="ph ph-rocket" style="font-size: 18px;"></i> <span class="hide-on-collapse">Gasmaske IA</span>
        </a>

        <div class="sidebar-section-label">Operação</div>
        <a href="<?= BASE_URL ?>modules/planejamento/index.php" class="<?= ($current_dir == 'planejamento') ? 'active' : '' ?>">
            <i class="ph ph-kanban" style="font-size: 18px;"></i> <span class="hide-on-collapse">Tarefas</span>
        </a>
        
        <?php if (isAdmin()): ?>
            <div class="sidebar-section-label">Gestão</div>
            <div class="sidebar-submenu-wrapper <?= ($current_dir == 'financeiro') ? 'open' : '' ?>">
                <div class="sidebar-submenu-trigger <?= ($current_dir == 'financeiro') ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                    <i class="ph ph-currency-dollar" style="font-size: 18px;"></i> <span class="hide-on-collapse">Financeiro</span>
                    <i class="ph ph-caret-down sidebar-caret" style="font-size: 12px; margin-left: auto;"></i>
                </div>
                <div class="sidebar-submenu">
                    <a href="<?= BASE_URL ?>modules/financeiro/index.php" class="<?= ($current_page == 'index.php' && $current_dir == 'financeiro') ? 'active' : '' ?>">
                        <i class="ph ph-arrow-down" style="font-size: 15px;"></i> <span class="hide-on-collapse">Entradas</span>
                    </a>
                    <a href="<?= BASE_URL ?>modules/financeiro/saidas.php" class="<?= ($current_page == 'saidas.php') ? 'active' : '' ?>">
                        <i class="ph ph-arrow-up" style="font-size: 15px;"></i> <span class="hide-on-collapse">Saídas</span>
                    </a>
                    <a href="<?= BASE_URL ?>modules/financeiro/cartoes.php" class="<?= ($current_page == 'cartoes.php') ? 'active' : '' ?>">
                        <i class="ph ph-credit-card" style="font-size: 15px;"></i> <span class="hide-on-collapse">Cartões</span>
                    </a>
                    <a href="<?= BASE_URL ?>modules/financeiro/recorrentes.php" class="<?= ($current_page == 'recorrentes.php') ? 'active' : '' ?>">
                        <i class="ph ph-repeat" style="font-size: 15px;"></i> <span class="hide-on-collapse">Recorrentes</span>
                    </a>
                    <a href="<?= BASE_URL ?>modules/financeiro/fluxo.php" class="<?= ($current_page == 'fluxo.php') ? 'active' : '' ?>">
                        <i class="ph ph-chart-line" style="font-size: 15px;"></i> <span class="hide-on-collapse">Fluxo de Caixa</span>
                    </a>
                </div>
            </div>
            <a href="<?= BASE_URL ?>modules/equipe/servicos.php" class="<?= ($current_dir == 'equipe' || $current_dir == 'usuarios') ? 'active' : '' ?>">
                <i class="ph ph-gear" style="font-size: 18px;"></i> <span class="hide-on-collapse">Configurações</span>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        
        <!-- Botão Toggle e Título adicionados aqui -->
        <div style="display: flex; align-items: center;">
            <button class="btn-toggle-sidebar" id="btnToggleSidebar">
                <i class="ph ph-list"></i>
            </button>
            <div class="top-header-title">
            </div>
        </div>
        
        <!-- DIREITA DO CABEÇALHO -->
        <div class="top-header-right" style="display: flex; align-items: center; gap: 20px;">
            
            <!-- SISTEMA DE NOTIFICAÇÕES (INSERIDO AQUI) -->
            <div class="notification-wrapper">
                <button class="notification-btn" onclick="toggleNotificacoes()">
                    <i class="ph ph-bell"></i>
                    <?php if (isset($total_notificacoes) && $total_notificacoes > 0): ?>
                        <span class="notification-badge"><?= $total_notificacoes ?></span>
                    <?php endif; ?>
                </button>
                
                <!-- DROPDOWN DE NOTIFICAÇÕES -->
                <div class="notification-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <h4>Notificações</h4>
                        <span><?= $total_notificacoes ?? 0 ?> pendentes</span>
                    </div>
                    <div class="notif-body">
                        <?php if (!isset($total_notificacoes) || $total_notificacoes == 0): ?>
                            <div class="notif-empty">
                                <i class="ph ph-check-circle"></i> Tudo limpo por aqui!
                            </div>
                        <?php else: ?>
                            
                            <?php if ($notif_briefings > 0): ?>
                                <a href="<?= BASE_URL ?>modules/briefing/index.php" class="notif-item">
                                    <div class="notif-icon bg-green-light text-green"><i class="ph-fill ph-file-plus"></i></div>
                                    <div class="notif-content">
                                        <strong>Briefings Novos</strong>
                                        <p><?= $notif_briefings ?> solicitação(ões) na caixa</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($notif_propostas > 0): ?>
                                <a href="<?= BASE_URL ?>modules/propostas/index.php" class="notif-item">
                                    <div class="notif-icon bg-yellow-light text-yellow"><i class="ph-fill ph-file-text"></i></div>
                                    <div class="notif-content">
                                        <strong>Propostas</strong>
                                        <p><?= $notif_propostas ?> parada(s) ou alterada(s)</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($notif_contratos > 0): ?>
                                <a href="<?= BASE_URL ?>modules/contratos/index.php" class="notif-item">
                                    <div class="notif-icon bg-blue-light text-blue"><i class="ph-fill ph-handshake"></i></div>
                                    <div class="notif-content">
                                        <strong>Contratos</strong>
                                        <p><?= $notif_contratos ?> aguardando assinatura</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($notif_crm > 0): ?>
                                <a href="<?= BASE_URL ?>modules/crm/index.php" class="notif-item">
                                    <div class="notif-icon bg-red-light text-red"><i class="ph-fill ph-phone-call"></i></div>
                                    <div class="notif-content">
                                        <strong>CRM - Follow-up</strong>
                                        <p><?= $notif_crm ?> contato(s) para hoje/atrasado</p>
                                    </div>
                                </a>
                            <?php endif; ?>
                            
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PERFIL DO USUÁRIO -->
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="text-align: right;">
                    <span style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.2;">
                        <?= htmlspecialchars($_SESSION['usuario_nome']) ?>
                    </span>
                    <span style="display: block; font-size: 10px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                        <?= isAdmin() ? 'Administrador' : 'Membro da Equipe' ?>
                    </span>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['usuario_nome'], 0, 1)) ?>
                </div>
            </div>
            
            <div style="width: 1px; height: 24px; background: var(--border); margin: 0 8px;"></div>
            
            <a href="<?= BASE_URL ?>logout.php" class="btn-sair">
                <i class="ph ph-sign-out" style="margin-right: 5px; font-size: 14px;"></i> Sair
            </a>
            
        </div>
    </div>
    <div class="content-body">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('btnToggleSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('collapsed');
            } else {
                sidebar.classList.toggle('mobile-open');
                if (overlay) overlay.classList.toggle('active');
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
    }
});

function toggleSubmenu(el) {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768 && sidebar.classList.contains('collapsed')) {
        sidebar.classList.remove('collapsed');
    }
    el.parentElement.classList.toggle('open');
}

// Funções do Dropdown de Notificações
function toggleNotificacoes() {
    document.getElementById('notifDropdown').classList.toggle('active');
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(event) {
    const wrapper = document.querySelector('.notification-wrapper');
    const dropdown = document.getElementById('notifDropdown');
    if (wrapper && dropdown && !wrapper.contains(event.target)) {
        dropdown.classList.remove('active');
    }
});
</script>