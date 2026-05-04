<?php
// includes/layout/sidebar.php

// Lógica para saber qual menu deixar "aceso" (Active)
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="brand-wrapper">
    <img src="/gasmaske/assets/img/logo-h.png" class="logo-img logo-h" alt="Logo">
</div>
    </div>
    
    <div class="sidebar-menu">
        <div class="sidebar-section-label">Visão Geral</div>
        <a href="/gasmaske/index.php" class="<?= ($current_page == 'index.php' && $current_dir == 'gasmaske') ? 'active' : '' ?>">
            <i class="ph ph-squares-four" style="font-size: 18px;"></i> Dashboard
        </a>
        
        <div class="sidebar-section-label">Comercial & CRM</div>
        <a href="/gasmaske/modules/briefing/index.php" class="<?= ($current_dir == 'briefing') ? 'active' : '' ?>">
            <i class="ph ph-envelope-simple-open" style="font-size: 18px;"></i> Briefings
        </a>
        <?php if (isAdmin()): ?>
            <a href="/gasmaske/modules/clientes/index.php" class="<?= ($current_dir == 'clientes') ? 'active' : '' ?>">
                <i class="ph ph-users" style="font-size: 18px;"></i> Clientes
            </a>
            <a href="/gasmaske/modules/propostas/index.php" class="<?= ($current_dir == 'propostas') ? 'active' : '' ?>">
                <i class="ph ph-file-text" style="font-size: 18px;"></i> Propostas
            </a>
            <a href="/gasmaske/modules/contratos/index.php" class="<?= ($current_dir == 'contratos') ? 'active' : '' ?>">
                <i class="ph ph-handshake" style="font-size: 18px;"></i> Contratos
            </a>
        <?php endif; ?>

        <div class="sidebar-section-label">Operação</div>
        <a href="/gasmaske/modules/planejamento/index.php" class="<?= ($current_dir == 'planejamento') ? 'active' : '' ?>">
            <i class="ph ph-kanban" style="font-size: 18px;"></i> Master Task List
        </a>
        
        <?php if (isAdmin()): ?>
            <div class="sidebar-section-label">Gestão</div>
            <a href="/gasmaske/modules/financeiro/index.php" class="<?= ($current_dir == 'financeiro') ? 'active' : '' ?>">
                <i class="ph ph-currency-dollar" style="font-size: 18px;"></i> Financeiro
            </a>
            <a href="/gasmaske/modules/equipe/servicos.php" class="<?= ($current_dir == 'equipe' || $current_dir == 'usuarios') ? 'active' : '' ?>">
                <i class="ph ph-gear" style="font-size: 18px;"></i> Configurações
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="main-content">
    <div class="top-header">
        <div class="top-header-title">
            </div>
        <div class="top-header-right">
            
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
            
            <a href="/gasmaske/logout.php" class="btn-sair">
                <i class="ph ph-sign-out" style="margin-right: 5px; font-size: 14px;"></i> Sair
            </a>
            
        </div>
    </div>
    <div class="content-body">