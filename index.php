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
// 1. IA - GASTO DA SEMANA
// =======================================================
$gasto_ia_semana = 0;
try {
    $stmt_ia = $pdo->query("SELECT SUM(custo_total) as total FROM ia_geracoes WHERE YEARWEEK(criado_em, 1) = YEARWEEK(CURDATE(), 1)");
    $gasto_ia_semana = $stmt_ia->fetch()['total'] ?? 0;
} catch (Exception $e) { }

// =======================================================
// 2. RADAR DE TAREFAS (Planejamento - Filtrado por Usuário)
// =======================================================
$status_badge = [
    'a_fazer' => 'badge-gray', 'em_execucao' => 'badge-blue', 'revisao_interna' => 'badge-yellow',
    'revisao_externa' => 'badge-yellow', 'aguardar_interno' => 'badge-gray', 'aguardar_cliente' => 'badge-gray',
    'postar' => 'badge-green', 'finalizado' => 'badge-green', 'arquivado' => 'badge-gray'
];

$usuario_id_logado = $_SESSION['usuario_id'] ?? 0;

$stmt_hoje = $pdo->prepare("
    SELECT p.*, c.nome as cliente_nome 
    FROM planejamento p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.data_publicacao = CURDATE() 
      AND p.status_geral != 'finalizado' 
      AND p.responsavel_id = ?
    ORDER BY p.prioridade DESC
");
$stmt_hoje->execute([$usuario_id_logado]);
$tarefas_hoje = $stmt_hoje->fetchAll();

$stmt_atrasadas = $pdo->prepare("
    SELECT p.*, c.nome as cliente_nome 
    FROM planejamento p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.data_publicacao < CURDATE() 
      AND p.status_geral != 'finalizado' 
      AND p.responsavel_id = ?
    ORDER BY p.data_publicacao ASC
");
$stmt_atrasadas->execute([$usuario_id_logado]);
$tarefas_atrasadas = $stmt_atrasadas->fetchAll();

// =======================================================
// 3. RADAR FINANCEIRO (Curto Prazo - Somando Atrasados)
// =======================================================
$stmt_rec_hoje = $pdo->query("SELECT SUM(valor) as total FROM parcelas WHERE data_vencimento = CURDATE() AND status = 'pendente'");
$receber_hoje = $stmt_rec_hoje->fetch()['total'] ?? 0;

// Soma pendentes + atrasados da semana atual
$stmt_rec_sem = $pdo->query("SELECT SUM(valor) as total FROM parcelas WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('pendente', 'atrasado')");
$receber_semana = $stmt_rec_sem->fetch()['total'] ?? 0;

$stmt_pag_hoje = $pdo->query("SELECT SUM(valor) as total FROM fin_lancamentos WHERE data_vencimento = CURDATE() AND status = 'pendente'");
$pagar_hoje = $stmt_pag_hoje->fetch()['total'] ?? 0;

// Soma pendentes + atrasados da semana atual
$stmt_pag_sem = $pdo->query("SELECT SUM(valor) as total FROM fin_lancamentos WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) AND status IN ('pendente', 'atrasado')");
$pagar_semana = $stmt_pag_sem->fetch()['total'] ?? 0;


require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<div class="dashboard-premium">

    <div class="greeting-premium">
        <h1>Central de Operações</h1>
        <p>Visão cirúrgica de hoje. Foque apenas no que exige sua atenção agora.</p>
    </div>

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
                <h3 style="color: var(--text);"><i class="ph-fill ph-crosshair" style="color: var(--red); margin-right: 5px;"></i> Meu Radar de Tarefas</h3>
                <span class="badge badge-gray"><?= count($tarefas_atrasadas) + count($tarefas_hoje) ?> pendências</span>
            </div>
            
            <div class="radar-body">
                
                <!-- AVISO DE TAREFAS ATRASADAS -->
                <?php if (count($tarefas_atrasadas) > 0): ?>
                    <div class="alert-tarefas-atrasadas">
                        <i class="ph-fill ph-warning-circle"></i> Atenção: Você tem <strong><?= count($tarefas_atrasadas) ?> tarefa(s) atrasada(s)</strong> na fila.
                    </div>
                <?php endif; ?>
                
                <?php if (count($tarefas_atrasadas) == 0 && count($tarefas_hoje) == 0): ?>
                    <div class="empty-state empty-state-padded">
                        <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                        Nenhuma tarefa pendente para você hoje. Tudo limpo!
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
                    <span class="fin-radar-label"><i class="ph-fill ph-arrow-down-right" style="color: var(--green);"></i> Pendente / Atrasado</span>
                    <span class="fin-radar-value text-green">+ <?= money($receber_semana) ?></span>
                </div>
                <div class="fin-radar-row">
                    <span class="fin-radar-label"><i class="ph-fill ph-arrow-up-right" style="color: var(--red);"></i> Pendente / Atrasado</span>
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
        var hiddenItems = document.querySelectorAll('.hide-on-mobile');
        hiddenItems.forEach(function(item) {
            item.classList.remove('hide-on-mobile');
        });
        document.getElementById('btnVerMaisTarefas').style.display = 'none';
    }
</script>

<?php require_once 'includes/layout/footer.php'; ?>