<?php
// index.php (Dashboard Premium - Layout Clean com Notificações nos Cards)

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

if (isAdmin()) {
    // --- VISÃO DO ADMIN ---
    
    // Alertas Urgentes
    $stmt_faturas_atrasadas = $pdo->query("SELECT COUNT(*) as qtd, SUM(valor) as total FROM parcelas WHERE status = 'atrasado'");
    $faturas_atrasadas = $stmt_faturas_atrasadas->fetch();
    
    $stmt_propostas_paradas = $pdo->query("SELECT COUNT(*) as total FROM propostas WHERE status IN ('rascunho', 'enviada')");
    $propostas_paradas = $stmt_propostas_paradas->fetch()['total'];

    $stmt_briefings = $pdo->query("SELECT COUNT(*) as total FROM briefings WHERE status = 'novo'");
    $briefings_novos = $stmt_briefings->fetch()['total'];

    // Pipeline
    $stmt_pipe_plan = $pdo->query("SELECT COUNT(*) as qtd FROM planejamento WHERE status_geral IN ('roteiro_em_producao', 'roteiro_em_revisao')");
    $pipe_plan = $stmt_pipe_plan->fetch()['qtd'];

    $stmt_pipe_prod = $pdo->query("SELECT COUNT(*) as qtd FROM planejamento WHERE status_geral IN ('peca_em_producao', 'peca_em_revisao')");
    $pipe_prod = $stmt_pipe_prod->fetch()['qtd'];

    $stmt_pipe_aprov = $pdo->query("SELECT COUNT(*) as qtd FROM planejamento WHERE status_geral IN ('roteiro_aguardando_aprovacao', 'peca_aguardando_aprovacao')");
    $pipe_aprov = $stmt_pipe_aprov->fetch()['qtd'];

    $stmt_pipe_pronto = $pdo->query("SELECT COUNT(*) as qtd FROM planejamento WHERE status_geral = 'pronto_para_postar'");
    $pipe_pronto = $stmt_pipe_pronto->fetch()['qtd'];

    // Métricas Gerais
    $stmt_clientes_ativos = $pdo->query("SELECT COUNT(DISTINCT cliente_id) as qtd FROM contratos WHERE status = 'em_andamento'");
    $clientes_ativos = $stmt_clientes_ativos->fetch()['qtd'];

    $stmt_tarefas = $pdo->query("SELECT COUNT(*) as total FROM planejamento WHERE status_geral != 'finalizado'");
    $tarefas_pendentes = $stmt_tarefas->fetch()['total'];

    $mes_atual = date('m');
    $ano_atual = date('Y');
    $stmt_receita = $pdo->prepare("SELECT SUM(valor) as total FROM parcelas WHERE status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?");
    $stmt_receita->execute([$mes_atual, $ano_atual]);
    $receita_mes = $stmt_receita->fetch()['total'] ?? 0;

    $stmt_receber = $pdo->query("SELECT SUM(valor) as total FROM parcelas WHERE status = 'pendente'");
    $receber_prazo = $stmt_receber->fetch()['total'] ?? 0;

    // Feed de últimas atividades
    $stmt_urgentes = $pdo->query("SELECT p.*, cli.nome as cliente_nome
                                  FROM planejamento p
                                  JOIN contratos c ON p.contrato_id = c.id
                                  JOIN clientes cli ON c.cliente_id = cli.id
                                  WHERE p.status_geral != 'finalizado'
                                  ORDER BY p.id DESC LIMIT 5");
    $tarefas_urgentes = $stmt_urgentes->fetchAll();

} else {
    // --- VISÃO DO CLIENTE ---
    $stmt_cli = $pdo->prepare("SELECT cliente_id FROM usuarios WHERE id = ?");
    $stmt_cli->execute([$_SESSION['usuario_id']]);
    $cliente_id = $stmt_cli->fetch()['cliente_id'] ?? 0;

    $stmt_aprovacoes = $pdo->prepare("
        SELECT p.*, c.codigo_agc, c.token as contrato_token
        FROM planejamento p
        JOIN contratos c ON p.contrato_id = c.id
        WHERE c.cliente_id = ?
        AND p.status_geral IN ('roteiro_aguardando_aprovacao', 'peca_aguardando_aprovacao')
    ");
    $stmt_aprovacoes->execute([$cliente_id]);
    $aprovacoes_pendentes = $stmt_aprovacoes->fetchAll();

    $stmt_faturas = $pdo->prepare("
        SELECT p.*, c.codigo_agc
        FROM parcelas p
        JOIN contratos c ON p.contrato_id = c.id
        WHERE c.cliente_id = ? AND p.status IN ('pendente', 'atrasado')
        ORDER BY p.data_vencimento ASC
    ");
    $stmt_faturas->execute([$cliente_id]);
    $faturas = $stmt_faturas->fetchAll();

    $stmt_meus_contratos = $pdo->prepare("SELECT * FROM contratos WHERE cliente_id = ? AND status = 'em_andamento'");
    $stmt_meus_contratos->execute([$cliente_id]);
    $meus_contratos = $stmt_meus_contratos->fetchAll();
}

$status_badge = [
    'pendente'                     => 'badge-gray',
    'roteiro_em_producao'          => 'badge-blue',
    'roteiro_aguardando_aprovacao' => 'badge-yellow',
    'roteiro_em_revisao'           => 'badge-yellow',
    'peca_em_producao'             => 'badge-purple',
    'peca_aguardando_aprovacao'    => 'badge-yellow',
    'peca_em_revisao'              => 'badge-yellow',
    'pronto_para_postar'           => 'badge-blue',
    'finalizado'                   => 'badge-green',
];

require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<div class="dashboard-premium">

    <!-- Saudação -->
    <div class="greeting-premium">
        <h1>Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>.</h1>
        <p><?= isAdmin() ? 'Central de Comando e Operações.' : 'Bem-vindo ao seu portal corporativo.' ?></p>
    </div>

    <?php if (isAdmin()): ?>

        <?php if ($faturas_atrasadas['qtd'] > 0 || $propostas_paradas > 0 || $briefings_novos > 0): ?>
            <!-- Central de Alertas Urgentes -->
            <div class="urgent-alerts-panel">
                <div class="urgent-header">
                    <i class="ph-fill ph-warning-circle"></i> Requer sua Atenção
                </div>
                <div class="urgent-list">
                    <?php if ($faturas_atrasadas['qtd'] > 0): ?>
                        <a href="modules/financeiro/index.php" class="urgent-item alert-red">
                            <span class="urgent-icon">🔴</span>
                            <span class="urgent-text"><strong><?= $faturas_atrasadas['qtd'] ?> Fatura(s) atrasada(s)</strong> (Total: <?= money($faturas_atrasadas['total']) ?>)</span>
                            <i class="ph ph-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($propostas_paradas > 0): ?>
                        <a href="modules/propostas/index.php" class="urgent-item alert-yellow">
                            <span class="urgent-icon">🟡</span>
                            <span class="urgent-text"><strong><?= $propostas_paradas ?> Proposta(s)</strong> parada(s) aguardando envio/ação</span>
                            <i class="ph ph-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($briefings_novos > 0): ?>
                        <a href="modules/briefing/index.php" class="urgent-item alert-green">
                            <span class="urgent-icon">🟢</span>
                            <span class="urgent-text"><strong><?= $briefings_novos ?> Novo(s) Briefing(s)</strong> aguardando proposta</span>
                            <i class="ph ph-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Métricas Principais (Botões Gigantes) -->
        <div class="metrics-premium-grid">
            <a href="modules/financeiro/index.php" class="metric-premium-card clickable">
                <div class="metric-premium-icon" style="color: var(--blue);">
                    <i class="ph-fill ph-currency-dollar"></i>
                </div>
                <div class="metric-premium-value"><?= money($receita_mes) ?></div>
                <div class="metric-premium-label">Receita no Mês</div>
            </a>

            <a href="modules/contratos/index.php" class="metric-premium-card clickable">
                <div class="metric-premium-icon" style="color: var(--purple);">
                    <i class="ph-fill ph-handshake"></i>
                </div>
                <div class="metric-premium-value"><?= $clientes_ativos ?></div>
                <div class="metric-premium-label">Contratos Ativos</div>
            </a>

            <a href="modules/planejamento/index.php" class="metric-premium-card clickable">
                <div class="metric-premium-icon" style="color: var(--red);">
                    <i class="ph-fill ph-kanban"></i>
                </div>
                <div class="metric-premium-value"><?= $tarefas_pendentes ?></div>
                <div class="metric-premium-label">Tarefas Ativas</div>
            </a>

            <a href="modules/propostas/index.php" class="metric-premium-card clickable">
                <div class="metric-premium-icon" style="color: var(--yellow);">
                    <i class="ph-fill ph-file-text"></i>
                </div>
                <div class="metric-premium-value"><?= $propostas_paradas ?></div>
                <div class="metric-premium-label">Propostas Abertas</div>
            </a>
        </div>

        <div class="dashboard-row-2">
            <!-- Pipeline de Produção -->
            <div class="panel-pipeline">
                <div class="section-premium-title">
                    <i class="ph-fill ph-funnel"></i> Status do Pipeline
                </div>
                <div class="pipeline-grid">
                    <a href="modules/planejamento/index.php" class="pipe-stage">
                        <div class="pipe-count"><?= $pipe_plan ?></div>
                        <div class="pipe-name">Planejamento</div>
                    </a>
                    <div class="pipe-arrow"><i class="ph ph-caret-right"></i></div>
                    <a href="modules/planejamento/index.php" class="pipe-stage">
                        <div class="pipe-count"><?= $pipe_prod ?></div>
                        <div class="pipe-name">Em Produção</div>
                    </a>
                    <div class="pipe-arrow"><i class="ph ph-caret-right"></i></div>
                    <a href="modules/planejamento/index.php" class="pipe-stage stage-warning">
                        <div class="pipe-count"><?= $pipe_aprov ?></div>
                        <div class="pipe-name">Aprovação</div>
                    </a>
                    <div class="pipe-arrow"><i class="ph ph-caret-right"></i></div>
                    <a href="modules/planejamento/index.php" class="pipe-stage stage-success">
                        <div class="pipe-count"><?= $pipe_pronto ?></div>
                        <div class="pipe-name">Pronto</div>
                    </a>
                </div>
            </div>

            <!-- Radar Financeiro Prático -->
            <div class="panel-financeiro">
                <div class="section-premium-title">
                    <i class="ph-fill ph-chart-polar"></i> Radar Financeiro
                </div>
                <div class="financeiro-list">
                    <div class="fin-item">
                        <div class="fin-info">
                            <i class="ph-fill ph-check-circle" style="color: var(--green);"></i> Valor Recebido
                        </div>
                        <strong style="color: var(--green);"><?= money($receita_mes) ?></strong>
                    </div>
                    <div class="fin-item">
                        <div class="fin-info">
                            <i class="ph-fill ph-clock" style="color: var(--blue);"></i> A Receber (Prazo)
                        </div>
                        <strong style="color: var(--blue);"><?= money($receber_prazo) ?></strong>
                    </div>
                    <div class="fin-item">
                        <div class="fin-info">
                            <i class="ph-fill ph-warning-circle" style="color: var(--red);"></i> Valor Atrasado
                        </div>
                        <strong style="color: var(--red);"><?= money($faturas_atrasadas['total']) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Últimas Atividades -->
        <div class="tasks-premium-list">
            <div class="tasks-premium-header">
                <i class="ph-list"></i> Últimas Tarefas Movimentadas
            </div>
            <?php if (count($tarefas_urgentes) > 0): ?>
                <?php foreach ($tarefas_urgentes as $t): ?>
                    <?php $badge = $status_badge[$t['status_geral']] ?? 'badge-gray'; ?>
                    <div class="tasks-premium-item">
                        <div class="task-premium-info">
                            <span class="task-premium-title"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="task-premium-meta"><?= htmlspecialchars($t['cliente_nome']) ?> · <?= htmlspecialchars($t['tipo']) ?></span>
                        </div>
                        <span class="badge <?= $badge ?>"><?= str_replace('_', ' ', $t['status_geral']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-premium">
                    <i class="ph-check-circle"></i>
                    Nenhuma tarefa em andamento no momento.
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <!-- VISÃO DO CLIENTE -->

        <!-- Aprovações pendentes (se houver) -->
        <?php if (count($aprovacoes_pendentes) > 0): ?>
            <div class="alerts-premium-grid">
                <div class="alert-premium-card">
                    <div class="alert-premium-icon proposta">
                        <i class="ph-fill ph-warning"></i>
                    </div>
                    <div class="alert-premium-content">
                        <div class="alert-premium-title proposta">Materiais para Aprovação</div>
                        <div class="alert-premium-desc"><?= count($aprovacoes_pendentes) ?> item(ns) aguardando sua revisão</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grid de Contratos e Faturas -->
        <div class="cliente-premium-grid">
            <!-- Meus Contratos -->
            <div class="cliente-premium-panel">
                <div class="cliente-premium-panel-header">
                    <i class="ph-file"></i> Meus Contratos
                </div>
                <div class="cliente-premium-panel-body">
                    <?php if (count($meus_contratos) > 0): ?>
                        <?php foreach ($meus_contratos as $mc): ?>
                            <div class="cliente-premium-item">
                                <div>
                                    <span class="code"><?= htmlspecialchars($mc['codigo_agc']) ?></span>
                                    <span class="meta">Duração: <?= $mc['duracao_meses'] ?> meses</span>
                                </div>
                                <span class="badge-sm badge-sm-green">Ativo</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-premium">
                            <i class="ph-file"></i>
                            Nenhum contrato ativo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Faturas em Aberto -->
            <div class="cliente-premium-panel">
                <div class="cliente-premium-panel-header">
                    <i class="ph-currency-dollar"></i> Faturas em Aberto
                </div>
                <div class="cliente-premium-panel-body">
                    <?php if (count($faturas) > 0): ?>
                        <?php foreach ($faturas as $f): ?>
                            <div class="cliente-premium-item">
                                <div>
                                    <span class="code"><?= htmlspecialchars($f['descricao']) ?></span>
                                    <span class="meta">Vence em <?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></span>
                                </div>
                                <span class="code"><?= money($f['valor']) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="empty-premium" style="padding-top: 8px;">
                            <i class="ph-qr-code"></i>
                            Pagamento via QR Code enviado separadamente.
                        </div>
                    <?php else: ?>
                        <div class="empty-premium">
                            <i class="ph-check-circle"></i>
                            Tudo em dia! Nenhuma fatura pendente.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once 'includes/layout/footer.php'; ?>