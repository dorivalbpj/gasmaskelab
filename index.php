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

$usuario_id_logado = $_SESSION['usuario_id'] ?? 0;
$hoje_str = date('Y-m-d');

// =======================================================
// HELPER: calcula em quantos dias fecha a fatura do cartão
// (considera virada de mês, ex: hoje dia 29, fechamento dia 5)
// =======================================================
function diasParaFechar($dia_fechamento) {
    $hoje = new DateTime();
    $diaAtual = (int)$hoje->format('j');
    $diaFechamento = (int)$dia_fechamento;

    if ($diaFechamento == $diaAtual) return 0;

    $dataFechamento = new DateTime($hoje->format('Y-m-01'));
    if ($diaFechamento < $diaAtual) {
        $dataFechamento->modify('+1 month');
    }
    // Protege contra meses com menos dias que o dia de fechamento (ex: dia 30 em fevereiro)
    $ultimoDiaMes = (int)$dataFechamento->format('t');
    $dataFechamento->setDate((int)$dataFechamento->format('Y'), (int)$dataFechamento->format('m'), min($diaFechamento, $ultimoDiaMes));

    $diff = $hoje->diff($dataFechamento);
    return (int)$diff->format('%r%a');
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
// 3. RADAR FINANCEIRO (Hoje / Atrasado / Semana)
// =======================================================

// A RECEBER - separado em atrasado vs vence hoje
$receber_atrasado = $pdo->query("SELECT SUM(valor) as total, COUNT(*) as qtd FROM parcelas WHERE status IN ('pendente','atrasado') AND data_vencimento < CURDATE()")->fetch();
$receber_hoje_row = $pdo->query("SELECT SUM(valor) as total, COUNT(*) as qtd FROM parcelas WHERE status IN ('pendente','atrasado') AND data_vencimento = CURDATE()")->fetch();

// A PAGAR - separado em atrasado vs vence hoje (empresa + pessoal)
$pagar_atrasado = $pdo->query("SELECT SUM(valor) as total, COUNT(*) as qtd FROM fin_lancamentos WHERE status IN ('pendente','atrasado') AND data_vencimento < CURDATE()")->fetch();
$pagar_hoje_row = $pdo->query("SELECT SUM(valor) as total, COUNT(*) as qtd FROM fin_lancamentos WHERE status = 'pendente' AND data_vencimento = CURDATE()")->fetch();

$pagar_hoje = ($pagar_atrasado['total'] ?? 0) + ($pagar_hoje_row['total'] ?? 0);
$receber_hoje = ($receber_atrasado['total'] ?? 0) + ($receber_hoje_row['total'] ?? 0);

// Soma pendentes + atrasados da semana atual (A RECEBER)
$stmt_rec_sem = $pdo->query("
    SELECT SUM(valor) as total 
    FROM parcelas 
    WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) 
    AND status IN ('pendente', 'atrasado')
");
$receber_semana = $stmt_rec_sem->fetch()['total'] ?? 0;

// Soma pendentes + atrasados da semana atual (A PAGAR)
$stmt_pag_sem = $pdo->query("
    SELECT SUM(valor) as total 
    FROM fin_lancamentos 
    WHERE YEARWEEK(data_vencimento, 1) = YEARWEEK(CURDATE(), 1) 
    AND data_vencimento <= CURDATE() 
    AND status IN ('pendente', 'atrasado')
");
$pagar_semana = $stmt_pag_sem->fetch()['total'] ?? 0;

// =======================================================
// 4. CARTÕES - Fechamento de Fatura
// =======================================================
$stmt_cartoes = $pdo->query("
    SELECT c.*, 
           COALESCE((
               SELECT SUM(l.valor) 
               FROM fin_lancamentos l 
               JOIN fin_faturas f ON l.fatura_id = f.id 
               WHERE f.cartao_id = c.id AND f.status = 'aberta'
           ), 0) as valor_fatura_aberta
    FROM fin_cartoes c 
    WHERE c.ativo = 1
    ORDER BY c.dia_fechamento ASC
");
$cartoes_ativos = $stmt_cartoes->fetchAll();

$cartoes_fechando = [];
foreach ($cartoes_ativos as $c) {
    $dias = diasParaFechar($c['dia_fechamento']);
    if ($dias >= 0 && $dias <= 3) {
        $c['dias_para_fechar'] = $dias;
        $cartoes_fechando[] = $c;
    }
}

// =======================================================
// 5. CRM - Agenda de Contatos (Hoje / Atrasado)
// =======================================================
$stmt_crm = $pdo->query("
    SELECT * FROM leads 
    WHERE data_proximo_contato <= CURDATE() 
      AND status NOT IN ('ganho', 'perdido')
    ORDER BY data_proximo_contato ASC
");
$contatos_pendentes = $stmt_crm->fetchAll();

// =======================================================
// 6. BRIEFINGS PENDENTES
// =======================================================
$briefings_pendentes = [];
try {
    $stmt_brief = $pdo->query("SELECT * FROM briefings WHERE status = 'novo' ORDER BY criado_em ASC");
    $briefings_pendentes = $stmt_brief->fetchAll();
} catch (Exception $e) { }

// =======================================================
// 7. PROPOSTAS ENVIADAS SEM RESPOSTA
// =======================================================
$stmt_prop = $pdo->query("
    SELECT p.*, c.nome as cliente_nome 
    FROM propostas p 
    JOIN clientes c ON p.cliente_id = c.id 
    WHERE p.status = 'enviada' 
    ORDER BY p.criado_em ASC
");
$propostas_pendentes = $stmt_prop->fetchAll();

// =======================================================
// 8. CONTRATOS - Pendências e Vencimento Próximo (último mês)
// =======================================================
$stmt_contratos = $pdo->query("
    SELECT c.*, cli.nome as cliente_nome,
           DATE_ADD(c.data_inicio, INTERVAL c.duracao_meses MONTH) as data_fim
    FROM contratos c
    JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.status IN ('aguardando_aceite_cliente', 'aguardando_pagamento')
       OR (
            c.status = 'em_andamento' 
            AND c.data_inicio IS NOT NULL
            AND DATE_ADD(c.data_inicio, INTERVAL c.duracao_meses MONTH) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       )
    ORDER BY data_fim ASC
");
$contratos_atencao = $stmt_contratos->fetchAll();

// =======================================================
// TOTAL DE ALERTAS (para o aviso geral do topo)
// =======================================================
$total_alertas = count($tarefas_atrasadas) + count($contatos_pendentes) + count($briefings_pendentes)
                + count($propostas_pendentes) + count($contratos_atencao) + count($cartoes_fechando);

require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<style>
    /* --- Acordeões do Dashboard (usa as mesmas variáveis de cor do resto do sistema) --- */
    .acc-panel { margin-bottom: 16px; padding: 0; overflow: hidden; }
    .acc-panel > summary { list-style: none; cursor: pointer; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; }
    .acc-panel > summary::-webkit-details-marker { display: none; }
    .acc-panel > summary .acc-title { display: flex; align-items: center; gap: 8px; font-weight: 600; color: var(--text); font-size: 15px; }
    .acc-panel > summary .acc-right { display: flex; align-items: center; gap: 10px; }
    .acc-panel > summary .acc-chevron { transition: transform 0.2s ease; color: var(--text-2); }
    .acc-panel[open] > summary .acc-chevron { transform: rotate(180deg); }
    .acc-body { padding: 0 20px 16px 20px; }
    .acc-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border); text-decoration: none; color: inherit; }
    .acc-row:first-child { border-top: none; }
    .acc-row-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .acc-row-title { font-size: 14px; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acc-row-sub { font-size: 12px; color: var(--text-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acc-row-side { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
    .top-alert-banner { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-radius: 12px; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); color: var(--red); font-size: 14px; font-weight: 500; margin-bottom: 20px; }
    .fin-mini-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 4px; }
    .fin-mini-card { padding: 14px; border-radius: 10px; background: var(--bg-hover); border: 1px solid var(--border); }
    .fin-mini-label { font-size: 12px; color: var(--text-2); display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
    .fin-mini-value { font-size: 18px; font-weight: 700; }
    @media (max-width: 768px) {
        .acc-panel > summary { padding: 14px 16px; }
        .acc-body { padding: 0 16px 14px 16px; }
    }
</style>

<div class="dashboard-premium">

    <div class="greeting-premium">
        <h1>Central de Operações</h1>
        <p>Visão cirúrgica de hoje. Foque apenas no que exige sua atenção agora.</p>
    </div>

    <!-- AVISO GERAL (bater o olho e saber o que precisa fazer hoje) -->
    <?php if ($total_alertas > 0): ?>
        <div class="top-alert-banner">
            <i class="ph-fill ph-bell-ringing" style="font-size: 20px;"></i>
            Você tem <strong><?= $total_alertas ?> pendência<?= $total_alertas != 1 ? 's' : '' ?></strong> espalhadas pelo sistema que precisam da sua atenção hoje.
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
            <div class="metric-premium-label">A Pagar (Hoje + Atrasado)</div>
            <i class="ph ph-arrow-right metric-premium-link"></i>
        </a>
        <a href="modules/financeiro/index.php" class="metric-premium-card clickable accent-green" style="text-decoration: none;">
            <div class="metric-premium-icon" style="color: var(--green);"><i class="ph-fill ph-trend-up"></i></div>
            <div class="metric-premium-value text-green"><?= money($receber_hoje) ?></div>
            <div class="metric-premium-label">A Receber (Hoje + Atrasado)</div>
            <i class="ph ph-arrow-right metric-premium-link"></i>
        </a>
        <a href="modules/financeiro/cartoes.php" class="metric-premium-card clickable accent-yellow" style="text-decoration: none;">
            <div class="metric-premium-icon" style="color: var(--yellow);"><i class="ph-fill ph-credit-card"></i></div>
            <div class="metric-premium-value"><?= count($cartoes_fechando) ?></div>
            <div class="metric-premium-label">Cartões Fechando (3 dias)</div>
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
                    <a href="modules/planejamento/item.php?id=<?= $t['id'] ?>" class="radar-item <?= $mobile_hide ?>" style="border-left: 3px solid var(--red); text-decoration: none; color: inherit;">
                        <div>
                            <span class="task-tema"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="task-cliente"><?= htmlspecialchars($t['cliente_nome'] ?? 'Interno') ?> · Venceu: <?= date('d/m/Y', strtotime($t['data_publicacao'])) ?></span>
                        </div>
                        <span class="badge badge-red">Atrasada</span>
                    </a>
                <?php endforeach; ?>

                <!-- Hoje -->
                <?php foreach ($tarefas_hoje as $t): ?>
                    <?php
                        $task_counter++;
                        $mobile_hide = ($task_counter > 5) ? 'hide-on-mobile' : '';
                        $badge_cls = $status_badge[$t['status_geral']] ?? 'badge-gray';
                    ?>
                    <a href="modules/planejamento/item.php?id=<?= $t['id'] ?>" class="radar-item <?= $mobile_hide ?>" style="border-left: 3px solid var(--blue); text-decoration: none; color: inherit;">
                        <div>
                            <span class="task-tema"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="task-cliente"><?= htmlspecialchars($t['cliente_nome'] ?? 'Interno') ?></span>
                        </div>
                        <span class="badge <?= $badge_cls ?>"><?= str_replace('_', ' ', $t['status_geral']) ?></span>
                    </a>
                <?php endforeach; ?>

                <!-- Botão Ver Mais (Mobile) -->
                <?php if ($task_counter > 5): ?>
                    <button id="btnVerMaisTarefas" class="btn btn-ghost w-100 btn-mobile-only" onclick="mostrarMaisTarefas()" style="justify-content: center; height: 50px; border-radius: 0; border-top: 1px solid var(--border); color: var(--text-2);">
                        <i class="ph ph-caret-down"></i> Ver mais <?= $task_counter - 5 ?> tarefas
                    </button>
                <?php endif; ?>

            </div>
        </div>

        <!-- RADAR FINANCEIRO (VISÃO DO DIA) -->
        <div class="radar-panel panel-financeiro">
            <div class="radar-header">
                <h3 style="color: var(--text);"><i class="ph-fill ph-calendar-check" style="color: var(--blue); margin-right: 5px;"></i> Radar Financeiro (Resumo)</h3>
            </div>
            <div class="radar-body" style="padding: 16px 20px;">

                <div class="fin-mini-grid">
                    <div class="fin-mini-card">
                        <div class="fin-mini-label"><i class="ph-fill ph-warning-circle" style="color: var(--red);"></i> A Pagar Atrasado</div>
                        <div class="fin-mini-value text-red"><?= money($pagar_atrasado['total'] ?? 0) ?></div>
                    </div>
                    <div class="fin-mini-card">
                        <div class="fin-mini-label"><i class="ph-fill ph-calendar" style="color: var(--yellow);"></i> A Pagar Hoje</div>
                        <div class="fin-mini-value"><?= money($pagar_hoje_row['total'] ?? 0) ?></div>
                    </div>
                    <div class="fin-mini-card">
                        <div class="fin-mini-label"><i class="ph-fill ph-warning-circle" style="color: var(--red);"></i> A Receber Atrasado</div>
                        <div class="fin-mini-value text-red"><?= money($receber_atrasado['total'] ?? 0) ?></div>
                    </div>
                    <div class="fin-mini-card">
                        <div class="fin-mini-label"><i class="ph-fill ph-calendar" style="color: var(--green);"></i> A Receber Hoje</div>
                        <div class="fin-mini-value text-green"><?= money($receber_hoje_row['total'] ?? 0) ?></div>
                    </div>
                </div>

                <?php
                    $saldo_semana = $receber_semana - $pagar_semana;
                    $cor_saldo = $saldo_semana >= 0 ? 'var(--blue)' : 'var(--red)';
                ?>
                <div class="fin-radar-row" style="background: var(--bg-hover); margin-top: 12px;">
                    <span class="fin-radar-label" style="color: var(--text);">Balanço da Semana</span>
                    <span class="fin-radar-value" style="color: <?= $cor_saldo ?>;"><?= money($saldo_semana) ?></span>
                </div>

                <div style="padding-top: 16px;">
                    <a href="modules/financeiro/index.php" class="btn btn-secondary w-100" style="justify-content: center; height: 44px;">
                        <i class="ph ph-wallet"></i> Acessar Financeiro Completo
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- CARTÕES FECHANDO -->
    <details class="card acc-panel" <?= count($cartoes_fechando) > 0 ? 'open' : '' ?>>
        <summary>
            <span class="acc-title"><i class="ph-fill ph-credit-card" style="color: var(--yellow);"></i> Fechamento de Cartões</span>
            <span class="acc-right">
                <span class="badge <?= count($cartoes_fechando) > 0 ? 'badge-yellow' : 'badge-gray' ?>"><?= count($cartoes_fechando) ?></span>
                <i class="ph ph-caret-down acc-chevron"></i>
            </span>
        </summary>
        <div class="acc-body">
            <?php if (count($cartoes_fechando) == 0): ?>
                <div class="empty-state empty-state-padded">
                    <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                    Nenhum cartão fechando nos próximos 3 dias.
                </div>
            <?php else: ?>
                <?php foreach ($cartoes_fechando as $c): ?>
                    <a href="modules/financeiro/fatura.php?cartao_id=<?= $c['id'] ?>" class="acc-row">
                        <div class="acc-row-main">
                            <span class="acc-row-title"><?= htmlspecialchars($c['nome']) ?></span>
                            <span class="acc-row-sub">Fatura aberta: <?= money($c['valor_fatura_aberta']) ?></span>
                        </div>
                        <div class="acc-row-side">
                            <?php if ($c['dias_para_fechar'] == 0): ?>
                                <span class="badge badge-red">Fecha hoje</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Fecha em <?= $c['dias_para_fechar'] ?> dia<?= $c['dias_para_fechar'] != 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <!-- CRM: AGENDA DE CONTATOS -->
    <details class="card acc-panel" <?= count($contatos_pendentes) > 0 ? 'open' : '' ?>>
        <summary>
            <span class="acc-title"><i class="ph-fill ph-phone-call" style="color: var(--purple);"></i> CRM · Preciso Ligar / Contatar</span>
            <span class="acc-right">
                <span class="badge <?= count($contatos_pendentes) > 0 ? 'badge-purple' : 'badge-gray' ?>"><?= count($contatos_pendentes) ?></span>
                <i class="ph ph-caret-down acc-chevron"></i>
            </span>
        </summary>
        <div class="acc-body">
            <?php if (count($contatos_pendentes) == 0): ?>
                <div class="empty-state empty-state-padded">
                    <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                    Nenhum contato pendente hoje.
                </div>
            <?php else: ?>
                <?php foreach ($contatos_pendentes as $lead): ?>
                    <?php $atrasado_lead = $lead['data_proximo_contato'] < $hoje_str; ?>
                    <a href="modules/crm/form.php?id=<?= $lead['id'] ?>" class="acc-row">
                        <div class="acc-row-main">
                            <span class="acc-row-title"><?= htmlspecialchars($lead['nome']) ?></span>
                            <span class="acc-row-sub"><?= htmlspecialchars($lead['empresa'] ?? $lead['telefone']) ?></span>
                        </div>
                        <div class="acc-row-side">
                            <?= $atrasado_lead ? '<span class="badge badge-red">Atrasado</span>' : '<span class="badge badge-blue">Hoje</span>' ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <!-- BRIEFINGS PENDENTES -->
    <details class="card acc-panel">
        <summary>
            <span class="acc-title"><i class="ph-fill ph-tray" style="color: var(--blue);"></i> Briefings Pendentes</span>
            <span class="acc-right">
                <span class="badge <?= count($briefings_pendentes) > 0 ? 'badge-blue' : 'badge-gray' ?>"><?= count($briefings_pendentes) ?></span>
                <i class="ph ph-caret-down acc-chevron"></i>
            </span>
        </summary>
        <div class="acc-body">
            <?php if (count($briefings_pendentes) == 0): ?>
                <div class="empty-state empty-state-padded">
                    <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                    Nenhum briefing novo aguardando análise.
                </div>
            <?php else: ?>
                <?php foreach ($briefings_pendentes as $b): ?>
                    <a href="modules/briefing/ver.php?id=<?= $b['id'] ?>" class="acc-row">
                        <div class="acc-row-main">
                            <span class="acc-row-title"><?= htmlspecialchars($b['nome']) ?></span>
                            <span class="acc-row-sub"><?= htmlspecialchars($b['empresa'] ?? $b['email']) ?></span>
                        </div>
                        <div class="acc-row-side">
                            <span class="badge badge-blue">Novo</span>
                            <span class="acc-row-sub"><?= dataBR($b['criado_em']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <!-- PROPOSTAS ENVIADAS SEM RESPOSTA -->
    <details class="card acc-panel">
        <summary>
            <span class="acc-title"><i class="ph-fill ph-file-text" style="color: var(--yellow);"></i> Propostas Sem Resposta</span>
            <span class="acc-right">
                <span class="badge <?= count($propostas_pendentes) > 0 ? 'badge-yellow' : 'badge-gray' ?>"><?= count($propostas_pendentes) ?></span>
                <i class="ph ph-caret-down acc-chevron"></i>
            </span>
        </summary>
        <div class="acc-body">
            <?php if (count($propostas_pendentes) == 0): ?>
                <div class="empty-state empty-state-padded">
                    <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                    Nenhuma proposta enviada aguardando retorno.
                </div>
            <?php else: ?>
                <?php foreach ($propostas_pendentes as $p): ?>
                    <?php
                        $dias_desde_envio = (int)floor((time() - strtotime($p['criado_em'])) / 86400);
                        $validade_vencida = !empty($p['data_validade']) && $p['data_validade'] < $hoje_str;
                    ?>
                    <a href="modules/propostas/form.php?id=<?= $p['id'] ?>" class="acc-row">
                        <div class="acc-row-main">
                            <span class="acc-row-title"><?= htmlspecialchars($p['cliente_nome']) ?></span>
                            <span class="acc-row-sub"><?= htmlspecialchars($p['titulo']) ?></span>
                        </div>
                        <div class="acc-row-side">
                            <?php if ($validade_vencida): ?>
                                <span class="badge badge-red">Validade vencida</span>
                            <?php else: ?>
                                <span class="badge badge-yellow">Há <?= $dias_desde_envio ?> dia<?= $dias_desde_envio != 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                            <span class="acc-row-sub"><?= money($p['valor']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

    <!-- CONTRATOS: PENDÊNCIAS E VENCIMENTO PRÓXIMO -->
    <details class="card acc-panel">
        <summary>
            <span class="acc-title"><i class="ph-fill ph-scroll" style="color: var(--red);"></i> Contratos: Pendência / Vencendo</span>
            <span class="acc-right">
                <span class="badge <?= count($contratos_atencao) > 0 ? 'badge-red' : 'badge-gray' ?>"><?= count($contratos_atencao) ?></span>
                <i class="ph ph-caret-down acc-chevron"></i>
            </span>
        </summary>
        <div class="acc-body">
            <?php if (count($contratos_atencao) == 0): ?>
                <div class="empty-state empty-state-padded">
                    <i class="ph ph-check-circle empty-state-icon" style="color: var(--green);"></i>
                    Nenhum contrato com pendência ou vencimento próximo.
                </div>
            <?php else: ?>
                <?php foreach ($contratos_atencao as $c): ?>
                    <?php
                        if ($c['status'] == 'aguardando_aceite_cliente') {
                            $situacao = '<span class="badge badge-yellow">Aguard. aceite</span>';
                        } elseif ($c['status'] == 'aguardando_pagamento') {
                            $situacao = '<span class="badge badge-yellow">Aguard. pagamento</span>';
                        } else {
                            $dias_venc = (int)floor((strtotime($c['data_fim']) - strtotime($hoje_str)) / 86400);
                            $situacao = $dias_venc < 0
                                ? '<span class="badge badge-red">Venceu há ' . abs($dias_venc) . ' dia' . (abs($dias_venc) != 1 ? 's' : '') . '</span>'
                                : '<span class="badge badge-red">Vence em ' . $dias_venc . ' dia' . ($dias_venc != 1 ? 's' : '') . '</span>';
                        }
                    ?>
                    <a href="modules/contratos/detalhes.php?id=<?= $c['id'] ?>" class="acc-row">
                        <div class="acc-row-main">
                            <span class="acc-row-title"><?= htmlspecialchars($c['cliente_nome']) ?></span>
                            <span class="acc-row-sub"><?= htmlspecialchars($c['codigo_agc']) ?></span>
                        </div>
                        <div class="acc-row-side">
                            <?= $situacao ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>

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