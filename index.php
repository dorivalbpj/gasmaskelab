<?php
// index.php (Dashboard Cirúrgica - Foco Operacional e Mobile)

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Apenas Admin tem acesso a este dashboard completo
if (!isAdmin()) {
    header("Location: modules/cliente/index.php"); // Fallback
    exit;
}

// =======================================================
// 1. NOTIFICAÇÕES (ALTA PRIORIDADE)
// =======================================================

$stmt_briefings = $pdo->query("SELECT COUNT(*) as qtd FROM briefings WHERE status = 'novo'");
$notif_briefings = $stmt_briefings->fetch()['qtd'] ?? 0;

$stmt_propostas = $pdo->query("SELECT COUNT(*) as qtd FROM propostas WHERE status IN ('aguardando_aprovacao', 'alterada', 'revisada', 'rascunho', 'enviada')");
$notif_propostas = $stmt_propostas->fetch()['qtd'] ?? 0;

$stmt_contratos = $pdo->query("SELECT COUNT(*) as qtd FROM contratos WHERE status IN ('aguardando_aceite_cliente', 'alterado')");
$notif_contratos = $stmt_contratos->fetch()['qtd'] ?? 0;

$stmt_crm = $pdo->query("SELECT COUNT(*) as qtd FROM leads WHERE data_proximo_contato IS NOT NULL AND data_proximo_contato <= CURDATE() AND status NOT IN ('ganho', 'perdido')");
$notif_crm = $stmt_crm->fetch()['qtd'] ?? 0;

// =======================================================
// 2. IA - GASTO DA SEMANA
// =======================================================
$gasto_ia_semana = 0;
try {
    $stmt_ia = $pdo->query("SELECT SUM(custo_total) as total FROM ia_geracoes WHERE YEARWEEK(criado_em, 1) = YEARWEEK(CURDATE(), 1)");
    $gasto_ia_semana = $stmt_ia->fetch()['total'] ?? 0;
} catch (Exception $e) { }

// =======================================================
// 3. RADAR DE TAREFAS (Planejamento)
// =======================================================
$status_badge = [
    'a_fazer' => 'badge-gray', 'em_execucao' => 'badge-blue', 'revisao_interna' => 'badge-yellow',
    'revisao_externa' => 'badge-yellow', 'aguardar_interno' => 'badge-gray', 'aguardar_cliente' => 'badge-gray',
    'postar' => 'badge-green', 'finalizado' => 'badge-green', 'arquivado' => 'badge-gray'
];

$stmt_hoje = $pdo->query("
    SELECT p.*, c.nome as cliente_nome 
    FROM planejamento p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.data_publicacao = CURDATE() AND p.status_geral != 'finalizado'
    ORDER BY p.prioridade DESC
");
$tarefas_hoje = $stmt_hoje->fetchAll();

$stmt_atrasadas = $pdo->query("
    SELECT p.*, c.nome as cliente_nome 
    FROM planejamento p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.data_publicacao < CURDATE() AND p.status_geral != 'finalizado'
    ORDER BY p.data_publicacao ASC
");
$tarefas_atrasadas = $stmt_atrasadas->fetchAll();

// =======================================================
// 4. RADAR FINANCEIRO (Curto Prazo)
// =======================================================
$stmt_rec_hoje = $pdo->query("SELECT SUM(valor) as total FROM parcelas WHERE data_vencimento = CURDATE() AND status = 'pendente'");
$receber_hoje = $stmt_rec_hoje->fetch()['total'] ?? 0;

$stmt_rec_sem = $pdo->query("SELECT SUM(valor) as total FROM parcelas WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) AND data_vencimento > CURDATE() AND status = 'pendente'");
$receber_semana = $stmt_rec_sem->fetch()['total'] ?? 0;

$stmt_pag_hoje = $pdo->query("SELECT SUM(valor) as total FROM fin_lancamentos WHERE data_vencimento = CURDATE() AND status = 'pendente'");
$pagar_hoje = $stmt_pag_hoje->fetch()['total'] ?? 0;

$stmt_pag_sem = $pdo->query("SELECT SUM(valor) as total FROM fin_lancamentos WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) AND data_vencimento > CURDATE() AND status = 'pendente'");
$pagar_semana = $stmt_pag_sem->fetch()['total'] ?? 0;


require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<div class="dashboard-premium">

    <div class="greeting-premium">
        <h1>Central de Operações</h1>
        <p>Visão cirúrgica de hoje. Foque apenas no que exige sua atenção agora.</p>
    </div>

    <!-- NOTIFICAÇÕES -->
    <?php if ($notif_briefings > 0 || $notif_propostas > 0 || $notif_contratos > 0 || $notif_crm > 0): ?>
        <div class="alerts-premium-grid">
            <?php if ($notif_briefings > 0): ?>
                <a href="modules/briefing/index.php" class="alert-premium-card alert-card-success">
                    <div class="alert-premium-icon" style="background: rgba(34,197,94,.1); color: var(--green);"><i class="ph-fill ph-file-plus"></i></div>
                    <div class="alert-premium-content">
                        <div class="alert-premium-title" style="color: var(--green);">Briefings Novos</div>
                        <div class="alert-premium-desc"><strong><?= $notif_briefings ?></strong> solicitação(ões) na caixa de entrada</div>
                    </div>
                </a>
            <?php endif; ?>
            <?php if ($notif_propostas > 0): ?>
                <a href="modules/propostas/index.php" class="alert-premium-card alert-card-warning">
                    <div class="alert-premium-icon" style="background: rgba(245,158,11,.1); color: var(--yellow);"><i class="ph-fill ph-file-text"></i></div>
                    <div class="alert-premium-content">
                        <div class="alert-premium-title" style="color: var(--yellow);">Propostas</div>
                        <div class="alert-premium-desc"><strong><?= $notif_propostas ?></strong> proposta(s) parada(s) ou alterada(s)</div>
                    </div>
                </a>
            <?php endif; ?>
            <?php if ($notif_contratos > 0): ?>
                <a href="modules/contratos/index.php" class="alert-premium-card alert-card-info">
                    <div class="alert-premium-icon" style="background: rgba(59,130,246,.1); color: var(--blue);"><i class="ph-fill ph-handshake"></i></div>
                    <div class="alert-premium-content">
                        <div class="alert-premium-title" style="color: var(--blue);">Contratos</div>
                        <div class="alert-premium-desc"><strong><?= $notif_contratos ?></strong> aguardando assinatura ou revisão</div>
                    </div>
                </a>
            <?php endif; ?>
            <?php if ($notif_crm > 0): ?>
                <a href="modules/crm/index.php" class="alert-premium-card alert-card-danger">
                    <div class="alert-premium-icon" style="background: rgba(255,63,52,.1); color: var(--red);"><i class="ph-fill ph-phone-call"></i></div>
                    <div class="alert-premium-content">
                        <div class="alert-premium-title" style="color: var(--red);">CRM - Follow-up</div>
                        <div class="alert-premium-desc"><strong><?= $notif_crm ?></strong> contato(s) atrasado(s) ou agendado(s) para hoje</div>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- MÉTRICAS -->
    <div class="metrics-premium-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <a href="modules/ia/index.php" class="metric-premium-card clickable accent-purple" style="text-decoration: none;">
            <div class="metric-premium-icon" style="color: var(--purple);"><i class="ph-fill ph-robot"></i></div>
            <div class="metric-premium-value"><?= money($gasto_ia_semana) ?></div>
            <div class="metric-premium-label">Gasto IA (Esta Semana)</div>
            <i class="ph ph-arrow-right metric-premium-link"></i>
        </a>
        <a href="modules/financeiro/saidas.php" class="metric-premium-card clickable accent-red" style="text-decoration: none;">
            <div class="metric-premium-icon" style="color: var(--red);"><i class="ph-fill ph-trend-down"></i></div>
            <div class="metric-premium-value text-red"><?= money($pagar_hoje) ?></div>
            <div class="metric-premium-label">A Pagar (Vence Hoje)</div>
            <i class="ph ph-arrow-right metric-premium-link"></i>
        </a>
        <a href="modules/financeiro/index.php" class="metric-premium-card clickable accent-green" style="text-decoration: none;">
            <div class="metric-premium-icon" style="color: var(--green);"><i class="ph-fill ph-trend-up"></i></div>
            <div class="metric-premium-value text-green"><?= money($receber_hoje) ?></div>
            <div class="metric-premium-label">A Receber (Vence Hoje)</div>
            <i class="ph ph-arrow-right metric-premium-link"></i>
        </a>
    </div>

    <!-- RADAR: TAREFAS E FINANCEIRO -->
    <div class="radar-grid">
        
        <!-- RADAR DE TAREFAS -->
        <div class="radar-panel panel-tarefas">
            <div class="radar-header">
                <h3 style="color: var(--text);"><i class="ph-fill ph-crosshair" style="color: var(--red); margin-right: 5px;"></i> Radar de Tarefas</h3>
                <span class="badge badge-gray"><?= count($tarefas_atrasadas) + count($tarefas_hoje) ?> pendências</span>
            </div>
            <div class="radar-body">
                
                <?php if (count($tarefas_atrasadas) == 0 && count($tarefas_hoje) == 0): ?>
                    <div class="empty-state empty-state-padded">
                        <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                        Nenhuma tarefa atrasada ou agendada para hoje. Tudo limpo!
                    </div>
                <?php endif; ?>

                <?php $task_counter = 0; ?>

                <!-- Atrasadas -->
                <?php foreach ($tarefas_atrasadas as $t): ?>
                    <?php 
                        $task_counter++; 
                        $mobile_hide = ($task_counter > 5) ? 'hide-on-mobile' : '';
                    ?>
                    <div class="radar-item <?= $mobile_hide ?>" style="border-left: 3px solid var(--red);">
                        <div>
                            <span class="task-tema"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="task-cliente"><?= htmlspecialchars($t['cliente_nome'] ?? 'Interno') ?> · Venceu: <?= date('d/m/Y', strtotime($t['data_publicacao'])) ?></span>
                        </div>
                        <span class="badge badge-red">Atrasada</span>
                    </div>
                <?php endforeach; ?>

                <!-- Hoje -->
                <?php foreach ($tarefas_hoje as $t): ?>
                    <?php 
                        $task_counter++; 
                        $mobile_hide = ($task_counter > 5) ? 'hide-on-mobile' : '';
                        $badge_cls = $status_badge[$t['status_geral']] ?? 'badge-gray'; 
                    ?>
                    <div class="radar-item <?= $mobile_hide ?>" style="border-left: 3px solid var(--blue);">
                        <div>
                            <span class="task-tema"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="task-cliente"><?= htmlspecialchars($t['cliente_nome'] ?? 'Interno') ?></span>
                        </div>
                        <span class="badge <?= $badge_cls ?>"><?= str_replace('_', ' ', $t['status_geral']) ?></span>
                    </div>
                <?php endforeach; ?>

                <!-- Botão Ver Mais (Mobile) -->
                <?php if ($task_counter > 5): ?>
                    <button id="btnVerMaisTarefas" class="btn btn-ghost w-100 btn-mobile-only" onclick="mostrarMaisTarefas()" style="justify-content: center; height: 50px; border-radius: 0; border-top: 1px solid var(--border); color: var(--text-2);">
                        <i class="ph ph-caret-down"></i> Ver mais <?= $task_counter - 5 ?> tarefas
                    </button>
                <?php endif; ?>

            </div>
        </div>

        <!-- RADAR FINANCEIRO (VISÃO DA SEMANA) -->
        <div class="radar-panel panel-financeiro">
            <div class="radar-header">
                <h3 style="color: var(--text);"><i class="ph-fill ph-calendar-check" style="color: var(--blue); margin-right: 5px;"></i> Radar Financeiro (Semana)</h3>
            </div>
            <div class="radar-body">
                <div class="fin-radar-row">
                    <span class="fin-radar-label"><i class="ph-fill ph-arrow-down-right" style="color: var(--green);"></i> A Receber na semana</span>
                    <span class="fin-radar-value text-green">+ <?= money($receber_semana) ?></span>
                </div>
                <div class="fin-radar-row">
                    <span class="fin-radar-label"><i class="ph-fill ph-arrow-up-right" style="color: var(--red);"></i> A Pagar na semana</span>
                    <span class="fin-radar-value text-red">- <?= money($pagar_semana) ?></span>
                </div>
                
                <?php 
                    $saldo_semana = $receber_semana - $pagar_semana;
                    $cor_saldo = $saldo_semana >= 0 ? 'var(--blue)' : 'var(--red)';
                ?>
                <div class="fin-radar-row" style="background: var(--bg-hover);">
                    <span class="fin-radar-label" style="color: var(--text);">Balanço da Semana</span>
                    <span class="fin-radar-value" style="color: <?= $cor_saldo ?>;"><?= money($saldo_semana) ?></span>
                </div>
                
                <div style="padding: 16px 20px;">
                    <a href="modules/financeiro/index.php" class="btn btn-secondary w-100" style="justify-content: center; height: 44px;">
                        <i class="ph ph-wallet"></i> Acessar Financeiro Completo
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Lógica para mostrar as tarefas extras no Mobile
    function mostrarMaisTarefas() {
        // Pega todos os itens que estão escondidos no mobile
        var hiddenItems = document.querySelectorAll('.hide-on-mobile');
        hiddenItems.forEach(function(item) {
            item.classList.remove('hide-on-mobile');
        });
        
        // Esconde o botão após clicar
        document.getElementById('btnVerMaisTarefas').style.display = 'none';
    }
</script>

<?php require_once 'includes/layout/footer.php'; ?>