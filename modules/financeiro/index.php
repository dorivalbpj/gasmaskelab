<?php
// modules/financeiro/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

// Atualiza automaticamente as parcelas que venceram para 'atrasado'
$pdo->query("UPDATE parcelas SET status = 'atrasado' WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE");

// Se o Admin deu baixa em uma parcela
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'dar_baixa') {
    $parcela_id = $_POST['parcela_id'] ?? 0;
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    
    try {
        $pdo->beginTransaction();

        // 1. Dá baixa na parcela
        $pdo->prepare("UPDATE parcelas SET status = 'pago', data_pagamento = ? WHERE id = ?")->execute([$data_pagamento, $parcela_id]);

        // 2. Verifica a qual contrato essa parcela pertence
        $stmt_cid = $pdo->prepare("SELECT contrato_id FROM parcelas WHERE id = ?");
        $stmt_cid->execute([$parcela_id]);
        $contrato_id = $stmt_cid->fetchColumn();

        // 3. MÁGICA: Ativa o contrato automaticamente se for a primeira parcela
        if ($contrato_id) {
            $pdo->prepare("UPDATE contratos SET status = 'em_andamento' WHERE id = ? AND status = 'aguardando_pagamento'")->execute([$contrato_id]);
        }

        $pdo->commit();
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pagamento registrado! O Contrato agora está Ativo.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao registrar pagamento.</div>";
    }
}

// Busca todas as parcelas com os dados do cliente e do contrato
$stmt = $pdo->query("SELECT p.*, c.codigo_agc, cli.nome as cliente_nome 
                     FROM parcelas p 
                     JOIN contratos c ON p.contrato_id = c.id 
                     JOIN clientes cli ON c.cliente_id = cli.id 
                     ORDER BY p.data_vencimento ASC");
$parcelas = $stmt->fetchAll();

$total_receber = 0;
$total_atrasado = 0;
$total_recebido = 0;

foreach ($parcelas as $p) {
    if ($p['status'] == 'pago') {
        $total_recebido += $p['valor'];
    } elseif ($p['status'] == 'atrasado') {
        $total_atrasado += $p['valor'];
    } else {
        // Se não está pago nem atrasado, está no prazo (pendente)
        $total_receber += $p['valor'];
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Gestão Financeira</h2>
        <p class="page-subtitle">Acompanhe os recebimentos dos seus contratos ativos.</p>
    </div>
</div>

<?= $mensagem ?>

<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="metric-card accent-blue">
        <div class="metric-label">A Receber (No Prazo)</div>
        <div class="metric-value"><?= money($total_receber) ?></div>
    </div>
    <div class="metric-card accent-red">
        <div class="metric-label text-red">Atrasado</div>
        <div class="metric-value"><?= money($total_atrasado) ?></div>
    </div>
    <div class="metric-card accent-green">
        <div class="metric-label text-green">Já Recebido</div>
        <div class="metric-value"><?= money($total_recebido) ?></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Lançamentos / Faturas</h3>
        <span class="badge badge-gray"><?= count($parcelas) ?> Registros</span>
    </div>
    
    <?php if (count($parcelas) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Vencimento</th>
                        <th>Cliente / Contrato</th>
                        <th>Referência</th>
                        <th style="text-align: right;">Valor</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parcelas as $p): ?>
                    <?php $opacidade = ($p['status'] == 'pago') ? 'opacity: 0.5;' : ''; ?>
                    <tr style="<?= $opacidade ?>">
                        <td>
                            <strong style="color: var(--text-primary); display: block;"><?= date('d/m/Y', strtotime($p['data_vencimento'])) ?></strong>
                            <?php if($p['status'] == 'pago'): ?>
                                <span style="font-size: 11px; color: var(--green);">Pago em <?= date('d/m/y', strtotime($p['data_pagamento'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); display: block;"><?= htmlspecialchars($p['cliente_nome']) ?></strong>
                            <span style="font-size: 11px; color: var(--text-muted);"><?= htmlspecialchars($p['codigo_agc']) ?></span>
                        </td>
                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($p['descricao']) ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 14px;">
                            <?= money($p['valor']) ?>
                        </td>
                        <td style="text-align: center;">
                            <?php 
                                if ($p['status'] == 'pago') echo '<span class="badge badge-green">PAGO</span>';
                                elseif ($p['status'] == 'atrasado') echo '<span class="badge badge-red">ATRASADO</span>';
                                else echo '<span class="badge badge-yellow">PENDENTE</span>';
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($p['status'] != 'pago'): ?>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirmar o recebimento desta parcela?');">
                                    <input type="hidden" name="acao" value="dar_baixa">
                                    <input type="hidden" name="parcela_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="data_pagamento" value="<?= date('Y-m-d') ?>">
                                    <button type="submit" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);"> Dar Baixa</button>
                                </form>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700;">Finalizado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div style="font-size: 30px; margin-bottom: 10px;">💸</div>
            Nenhuma parcela registrada ainda. Elas são geradas automaticamente ao iniciar um contrato!
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>