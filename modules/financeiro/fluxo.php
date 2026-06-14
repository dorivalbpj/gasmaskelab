<?php
// modules/financeiro/fluxo.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Gerar projeção para o mês atual + próximos 5 meses (Total: 6 meses)
$meses_projecao = [];
$meses_nomes = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

$total_entradas_semestre = 0;
$total_saidas_semestre = 0;

for ($i = 0; $i < 6; $i++) {
    // Usa o dia 1º para não pular meses em anos bissextos ou dias 31
    $timestamp = mktime(0, 0, 0, date('n') + $i, 1, date('Y'));
    $mes = (int)date('n', $timestamp);
    $ano = (int)date('Y', $timestamp);

    // 1. Entradas (Parcelas de contratos do mês)
    $stmt_entradas = $pdo->prepare("SELECT SUM(valor) FROM parcelas WHERE MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ?");
    $stmt_entradas->execute([$mes, $ano]);
    $entradas = (float)$stmt_entradas->fetchColumn();

    // 2. Saídas Geradas (Lançamentos avulsos, parcelas de cartão e contas fixas já geradas)
    $stmt_saidas = $pdo->prepare("SELECT SUM(valor) FROM fin_lancamentos WHERE MONTH(data_vencimento) = ? AND YEAR(data_vencimento) = ?");
    $stmt_saidas->execute([$mes, $ano]);
    $saidas_geradas = (float)$stmt_saidas->fetchColumn();

    // 3. Recorrentes NÃO Geradas (A verdadeira projeção do custo fixo)
    // Soma todas as recorrentes ativas que ainda não possuem um lançamento com seu ID nesta competência
    $stmt_recorrentes = $pdo->prepare("
        SELECT SUM(r.valor) FROM fin_recorrentes r 
        LEFT JOIN fin_lancamentos l ON r.id = l.recorrente_id 
            AND l.mes_referencia = ? AND l.ano_referencia = ?
        WHERE r.ativo = 1 AND l.id IS NULL
    ");
    $stmt_recorrentes->execute([$mes, $ano]);
    $saidas_projecao = (float)$stmt_recorrentes->fetchColumn();

    $saidas_totais = $saidas_geradas + $saidas_projecao;
    $saldo = $entradas - $saidas_totais;
    
    $total_entradas_semestre += $entradas;
    $total_saidas_semestre += $saidas_totais;

    $meses_projecao[] = [
        'mes' => $mes,
        'ano' => $ano,
        'nome' => $meses_nomes[$mes] . ' / ' . $ano,
        'entradas' => $entradas,
        'saidas_geradas' => $saidas_geradas,
        'saidas_projecao' => $saidas_projecao,
        'saidas_totais' => $saidas_totais,
        'saldo' => $saldo
    ];
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Fluxo de Caixa Projetado</h2>
        <p class="page-subtitle">Previsão financeira da agência para os próximos 6 meses baseada em contratos e despesas fixas.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
    <?php foreach($meses_projecao as $p): ?>
        <?php $cor_saldo = ($p['saldo'] >= 0) ? 'var(--green)' : 'var(--red)'; ?>
        
        <div class="card" style="margin-bottom: 0; position: relative; overflow: hidden;">
            <!-- Barra colorida no topo baseada no saldo -->
            <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: <?= $cor_saldo ?>;"></div>
            
            <div class="card-header" style="border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 16px; margin-top: 4px;">
                <h3 class="card-title" style="color: var(--text); font-size: 13px;"><i class="ph ph-calendar-blank" style="font-size: 16px; margin-right: 6px; vertical-align: bottom;"></i> <?= $p['nome'] ?></h3>
            </div>
            
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;"><span style="color: var(--text-2);">Entradas (Contratos Ativos)</span><strong style="color: var(--green);">+ <?= money($p['entradas']) ?></strong></div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13px;"><span style="color: var(--text-2);">Saídas Já Lançadas</span><strong style="color: var(--red); opacity: 0.8;">- <?= money($p['saidas_geradas']) ?></strong></div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 13px;"><span style="color: var(--text-2);">Saídas Projetadas (Contas Fixas)</span><strong style="color: var(--yellow); opacity: 0.8;">- <?= money($p['saidas_projecao']) ?></strong></div>
            
            <div style="height: 1px; background: var(--border-mid); margin-bottom: 16px;"></div>
            
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-3);">Saldo do Mês</span>
                <strong style="font-size: 24px; color: <?= $cor_saldo ?>;"><?= money($p['saldo']) ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>
