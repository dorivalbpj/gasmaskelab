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

require_once 'includes/layout/header.php';
require_once 'includes/layout/sidebar.php';
?>

<div class="card" style="background: transparent; border: none; padding: 0;">

    <div class="dashboard-greeting" style="background: var(--bg-surface); padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-mid); margin-bottom: 24px;">
        <h1 class="greeting-title" style="font-size: 28px; margin-bottom: 8px;">Olá, <?= htmlspecialchars($_SESSION['usuario_nome']) ?>.</h1>
        <p class="greeting-sub" style="font-size: 15px; color: var(--text-secondary);">
            <?= isAdmin() ? 'O que vamos construir hoje? Aqui está o panorama da sua agência.' : 'Bem-vindo ao seu portal corporativo na Gasmaske Lab.' ?>
        </p>
    </div>

    <?php if (isAdmin()): ?>

        <div class="dashboard-metrics" style="gap: 20px; margin-bottom: 30px;">
            <div class="metric-card accent-yellow" style="background: var(--bg-surface); border: 1px solid var(--border-mid);">
                <i class="ph-fill ph-envelope-open metric-bg-icon"></i>
                <div class="metric-label" style="color: var(--text-secondary);">Briefings Novos</div>
                <div class="metric-value" style="color: var(--text-primary); font-size: 32px;"><?= $briefings_novos ?></div>
            </div>
            <div class="metric-card accent-blue" style="background: var(--bg-surface); border: 1px solid var(--border-mid);">
                <i class="ph-fill ph-handshake metric-bg-icon"></i>
                <div class="metric-label" style="color: var(--text-secondary);">Contratos Ativos</div>
                <div class="metric-value" style="color: var(--text-primary); font-size: 32px;"><?= $contratos_ativos ?></div>
            </div>
            <div class="metric-card accent-purple" style="background: var(--bg-surface); border: 1px solid var(--border-mid);">
                <i class="ph-fill ph-kanban metric-bg-icon"></i>
                <div class="metric-label" style="color: var(--text-secondary);">Tarefas Rolando</div>
                <div class="metric-value" style="color: var(--text-primary); font-size: 32px;"><?= $tarefas_pendentes ?></div>
            </div>
            <div class="metric-card accent-green" style="background: var(--bg-surface); border: 1px solid var(--border-mid);">
                <i class="ph-fill ph-currency-dollar metric-bg-icon"></i>
                <div class="metric-label" style="color: var(--green);">Faturamento (Mês)</div>
                <div class="metric-value metric-value--sm" style="color: var(--text-primary); font-size: 26px;"><?= money($receita_mes) ?></div>
            </div>
        </div>

        <h3 style="color: var(--text-primary); font-size: 18px; font-weight: 600; margin-bottom: 16px;">Acesso Rápido</h3>

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

        <div class="dash-panel dash-panel--wide" style="background: var(--bg-surface); border: 1px solid var(--border-mid); border-radius: var(--radius-lg);">
            <div class="dash-panel-title" style="padding: 20px; border-bottom: 1px solid var(--border-mid); margin: 0;">Últimas Tarefas Movimentadas</div>

            <div style="padding: 0 20px;">
            <?php if (count($tarefas_urgentes) > 0): ?>
                <?php foreach ($tarefas_urgentes as $t): ?>
                    <?php $badge = $status_badge[$t['status_geral']] ?? 'badge-gray'; ?>
                    <div class="dash-task-row" style="padding: 16px 0; border-bottom: 1px solid var(--border-mid);">
                        <div>
                            <span class="dash-task-title" style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($t['tema']) ?></span>
                            <span class="dash-task-meta" style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($t['cliente_nome']) ?> · <?= htmlspecialchars($t['tipo']) ?></span>
                        </div>
                        <span class="badge <?= $badge ?>">
                            <?= str_replace('_', ' ', $t['status_geral']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="dash-empty" style="padding: 30px 0; text-align: center; color: var(--text-muted);">Nenhuma tarefa em andamento no momento.</p>
            <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <?php if (count($aprovacoes_pendentes) > 0): ?>
            <div class="aprovacao-alerta" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 24px;">
                <div class="aprovacao-alerta-header" style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <i class="ph-fill ph-warning-circle aprovacao-alerta-icon" style="color: var(--yellow); font-size: 32px;"></i>
                    <div>
                        <strong style="color: var(--text-primary); font-size: 16px; display: block; margin-bottom: 4px;">Materiais Aguardando sua Aprovação</strong>
                        <p style="color: var(--text-secondary); font-size: 13px; margin: 0;">Nossa equipe produziu novos materiais e precisamos da sua revisão para dar continuidade.</p>
                    </div>
                </div>
                <?php foreach ($aprovacoes_pendentes as $ap): ?>
                    <div class="aprovacao-item" style="background: var(--bg-surface); border: 1px solid var(--border-mid); border-radius: var(--radius-md); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <span class="aprovacao-tema" style="color: var(--text-primary); font-weight: 600; display: block;"><?= htmlspecialchars($ap['tema']) ?></span>
                            <span class="aprovacao-meta" style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($ap['tipo']) ?></span>
                        </div>
                        <a href="publico/aprovacoes.php?token=<?= $ap['contrato_token'] ?>" class="btn btn-primary btn--sm" target="_blank" style="background: var(--yellow); color: #000; border: none; font-weight: 700;">
                            Revisar e Aprovar
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid dashboard-grid--equal">
            <div class="dash-panel" style="background: var(--bg-surface); border: 1px solid var(--border-mid); border-radius: var(--radius-lg); padding: 24px;">
                <div class="dash-panel-title" style="margin-bottom: 20px; font-weight: 600; font-size: 16px; color: var(--text-primary); border-bottom: 1px solid var(--border-mid); padding-bottom: 10px;">Meus Contratos</div>
                <?php if (count($meus_contratos) > 0): ?>
                    <?php foreach ($meus_contratos as $mc): ?>
                        <div class="contrato-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border);">
                            <div>
                                <span class="contrato-codigo" style="color: var(--text-primary); font-weight: 600; display: block;"><?= htmlspecialchars($mc['codigo_agc']) ?></span>
                                <span class="contrato-meta" style="color: var(--text-muted); font-size: 12px;">Duração: <?= $mc['duracao_meses'] ?> meses</span>
                            </div>
                            <span class="badge badge-green">Ativo</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="dash-empty" style="color: var(--text-muted); font-size: 13px;">Você ainda não possui contratos ativos.</p>
                <?php endif; ?>
            </div>

            <div class="dash-panel" style="background: var(--bg-surface); border: 1px solid var(--border-mid); border-radius: var(--radius-lg); padding: 24px;">
                <div class="dash-panel-title" style="margin-bottom: 20px; font-weight: 600; font-size: 16px; color: var(--text-primary); border-bottom: 1px solid var(--border-mid); padding-bottom: 10px;">Faturas em Aberto</div>

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
                    <p class="fatura-obs" style="font-size: 12px; color: var(--text-muted); margin-top: 15px;">Faturas pagas via QR Code enviado separadamente.</p>
                <?php else: ?>
                    <div class="faturas-ok" style="text-align: center; padding: 30px 0;">
                        <i class="ph-fill ph-check-circle faturas-ok-icon" style="color: var(--green); font-size: 40px; margin-bottom: 10px;"></i>
                        <strong style="display: block; color: var(--text-primary); margin-bottom: 5px;">Tudo em dia!</strong>
                        <p style="color: var(--text-muted); font-size: 13px; margin: 0;">Você não possui faturas em aberto.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php require_once 'includes/layout/footer.php'; ?>