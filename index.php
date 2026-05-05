<?php
// index.php (Página Inicial / Dashboard Misto)

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

if (isAdmin()) {
    // --- VISÃO DO ADMIN DA AGÊNCIA ---
    $stmt_briefings = $pdo->query("SELECT COUNT(*) as total FROM briefings WHERE status = 'novo'");
    $briefings_novos = $stmt_briefings->fetch()['total'];

    $stmt_contratos = $pdo->query("SELECT COUNT(*) as total FROM contratos WHERE status = 'em_andamento'");
    $contratos_ativos = $stmt_contratos->fetch()['total'];

    $stmt_tarefas = $pdo->query("SELECT COUNT(*) as total FROM planejamento WHERE status_geral != 'finalizado'");
    $tarefas_pendentes = $stmt_tarefas->fetch()['total'];

    $mes_atual = date('m');
    $ano_atual = date('Y');
    $stmt_receita = $pdo->prepare("SELECT SUM(valor) as total FROM parcelas WHERE status = 'pago' AND MONTH(data_pagamento) = ? AND YEAR(data_pagamento) = ?");
    $stmt_receita->execute([$mes_atual, $ano_atual]);
    $receita_mes = $stmt_receita->fetch()['total'] ?? 0;

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

// Mapeamento status → badge
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

// --- INÍCIO: LÓGICA DO DASHBOARD EXPRESSIVO ---
$qtd_briefings_ninja = $pdo->query("SELECT COUNT(*) FROM briefings WHERE status = 'novo'")->fetchColumn();
$qtd_propostas_ninja = $pdo->query("SELECT COUNT(*) FROM propostas WHERE status IN ('rascunho', 'enviada')")->fetchColumn();
// --- FIM: LÓGICA DO DASHBOARD EXPRESSIVO ---

require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<!-- --- INÍCIO: ALERTAS DO DASHBOARD EXPRESSIVO --- -->
<?php if ($qtd_briefings_ninja > 0 || $qtd_propostas_ninja > 0): ?>
    <div class="dash-alertas-grid">

        <?php if ($qtd_briefings_ninja > 0): ?>
        <div class="aprovacao-alerta dash-alerta-briefing">
            <div class="aprovacao-alerta-header">
                <span class="aprovacao-alerta-icon">🔥</span>
                <div>
                    <strong><?= $qtd_briefings_ninja ?> Briefing(s) Novo(s)!</strong>
                    <p>Tem cliente querendo fechar negócio. Não deixa esfriar.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($qtd_propostas_ninja > 0): ?>
        <div class="aprovacao-alerta dash-alerta-proposta">
            <div class="aprovacao-alerta-header">
                <span class="aprovacao-alerta-icon">⏳</span>
                <div>
                    <strong><?= $qtd_propostas_ninja ?> Proposta(s) Parada(s)</strong>
                    <p>Existem propostas em rascunho ou enviadas aguardando resposta.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
<?php endif; ?>
<!-- --- FIM: ALERTAS DO DASHBOARD EXPRESSIVO --- -->

<div class="card dash-wrapper">

    <div class="dashboard-greeting dash-greeting-box">
        <h1 class="greeting-title">Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>.</h1>
        <p class="greeting-sub">
            <?= isAdmin() ? 'O que vamos construir hoje? Aqui está o panorama da sua agência.' : 'Bem-vindo ao seu portal corporativo na Gasmaske Lab.' ?>
        </p>
    </div>

    <?php if (isAdmin()): ?>

        <div class="dashboard-metrics dash-metrics-grid">
            <div class="metric-card accent-yellow metric-card--surface">
                <i class="ph-fill ph-envelope-open metric-bg-icon"></i>
                <div class="metric-label metric-label--secondary">Briefings Novos</div>
                <div class="metric-value metric-value--primary"><?= $briefings_novos ?></div>
            </div>
            <div class="metric-card accent-blue metric-card--surface">
                <i class="ph-fill ph-handshake metric-bg-icon"></i>
                <div class="metric-label metric-label--secondary">Contratos Ativos</div>
                <div class="metric-value metric-value--primary"><?= $contratos_ativos ?></div>
            </div>
            <div class="metric-card accent-red metric-card--surface">
                <i class="ph-fill ph-kanban metric-bg-icon"></i>
                <div class="metric-label metric-label--secondary">Tarefas Pendentes</div>
                <div class="metric-value metric-value--primary"><?= $tarefas_pendentes ?></div>
            </div>
            <div class="metric-card accent-green metric-card--surface">
                <i class="ph-fill ph-currency-dollar metric-bg-icon"></i>
                <div class="metric-label metric-label--secondary">Receita do Mês</div>
                <div class="metric-value metric-value--sm metric-value--primary"><?= money($receita_mes) ?></div>
            </div>
        </div>

        <h3 class="dash-section-title">Acesso Rápido</h3>

        <div class="qa-grid">

            <a href="modules/propostas/form.php" class="qa-card blue">
                <div class="qa-icon-wrapper">
                    <i class="ph ph-file-text"></i>
                </div>
                <div class="qa-title">Criar Proposta</div>
                <div class="qa-desc">Monte um orçamento comercial para um lead.</div>
            </a>

            <a href="modules/contratos/form.php" class="qa-card green">
                <div class="qa-icon-wrapper">
                    <i class="ph ph-handshake"></i>
                </div>
                <div class="qa-title">Gerar Contrato</div>
                <div class="qa-desc">Efetive um projeto com assinatura digital.</div>
            </a>

            <a href="publico/briefing.php" target="_blank" class="qa-card red">
                <div class="qa-icon-wrapper">
                    <i class="ph ph-link"></i>
                </div>
                <div class="qa-title">Link do Briefing</div>
                <div class="qa-desc">Acesse o formulário público para enviar a clientes.</div>
            </a>

            <a href="modules/planejamento/index.php" class="qa-card purple">
                <div class="qa-icon-wrapper">
                    <i class="ph ph-kanban"></i>
                </div>
                <div class="qa-title">Master Task List</div>
                <div class="qa-desc">Acompanhe a esteira de produção da equipe.</div>
            </a>

        </div>

        <div class="dash-panel dash-panel--wide dash-tasks-panel">
            <div class="dash-panel-title dash-tasks-panel-header">Últimas Tarefas Movimentadas</div>

            <div class="dash-tasks-panel-body">
            <?php if (count($tarefas_urgentes) > 0): ?>
                <?php foreach ($tarefas_urgentes as $t): ?>
                    <?php $badge = $status_badge[$t['status_geral']] ?? 'badge-gray'; ?>
                    <div class="dash-task-row dash-task-row--bordered">
                        <div>
                            <span class="dash-task-title"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="dash-task-meta"><?= htmlspecialchars($t['cliente_nome']) ?> · <?= htmlspecialchars($t['tipo']) ?></span>
                        </div>
                        <span class="badge <?= $badge ?>">
                            <?= str_replace('_', ' ', $t['status_geral']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="dash-empty">Nenhuma tarefa em andamento no momento.</p>
            <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <?php if (count($aprovacoes_pendentes) > 0): ?>
            <div class="aprovacao-alerta--cliente">
                <div class="aprovacao-alerta-header">
                    <i class="ph-fill ph-warning-circle aprovacao-alerta-icon"></i>
                    <div>
                        <strong class="aprovacao-alerta-title">Materiais Aguardando sua Aprovação</strong>
                        <p class="aprovacao-alerta-desc">Nossa equipe produziu novos materiais e precisamos da sua revisão para dar continuidade.</p>
                    </div>
                </div>
                <?php foreach ($aprovacoes_pendentes as $ap): ?>
                    <div class="aprovacao-item aprovacao-item--cliente">
                        <div>
                            <span class="aprovacao-tema"><?= htmlspecialchars($ap['tema']) ?></span>
                            <span class="aprovacao-meta"><?= htmlspecialchars($ap['tipo']) ?></span>
                        </div>
                        <a href="publico/aprovacoes.php?token=<?= $ap['contrato_token'] ?>" class="btn btn-primary btn--sm btn-aprovar" target="_blank">
                            Revisar e Aprovar
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid dashboard-grid--equal">
            <div class="dash-panel dash-panel--cliente">
                <div class="dash-panel-title dash-panel-title--cliente">Meus Contratos</div>
                <?php if (count($meus_contratos) > 0): ?>
                    <?php foreach ($meus_contratos as $mc): ?>
                        <div class="contrato-item contrato-item--cliente">
                            <div>
                                <span class="contrato-codigo"><?= htmlspecialchars($mc['codigo_agc']) ?></span>
                                <span class="contrato-meta">Duração: <?= $mc['duracao_meses'] ?> meses</span>
                            </div>
                            <span class="badge badge-green">Ativo</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="dash-empty dash-empty--padded">Você ainda não possui contratos ativos.</p>
                <?php endif; ?>
            </div>

            <div class="dash-panel dash-panel--cliente">
                <div class="dash-panel-title dash-panel-title--cliente">Faturas em Aberto</div>

                <?php if (count($faturas) > 0): ?>
                    <?php foreach ($faturas as $f): ?>
                        <div class="fatura-item <?= $f['status'] == 'atrasado' ? 'fatura-item--atrasada' : 'fatura-item--pendente' ?>">
                            <div class="fatura-desc">
                                <span class="fatura-nome"><?= htmlspecialchars($f['descricao']) ?></span>
                                <span class="fatura-venc">Vence em <?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></span>
                            </div>
                            <span class="fatura-valor"><?= money($f['valor']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <p class="fatura-obs">Faturas pagas via QR Code enviado separadamente.</p>
                <?php else: ?>
                    <div class="faturas-ok faturas-ok--centro">
                        <i class="ph-fill ph-check-circle faturas-ok-icon"></i>
                        <strong>Tudo em dia!</strong>
                        <p>Você não possui faturas em aberto.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once 'includes/layout/footer.php'; ?>