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
<?php
// Converte o array do PHP gerado na sua lógica para JSON, para o JavaScript ler nativamente
$json_dados = json_encode($meses_projecao);
?>

<!-- Importa o ApexCharts via CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Fluxo de Caixa Projetado</h2>
        <p class="page-subtitle">Previsão financeira da agência para os próximos 6 meses.</p>
    </div>
</div>

<!-- BLOCO 1: O GRÁFICO VISUAL -->
<div class="card" style="padding: 24px; margin-bottom: 24px;">
    <div style="margin-bottom: 16px;">
        <h3 class="card-title">Projeção Semestral</h3>
        <p style="color: var(--text-3); font-size: 13px;">Visão geral de Entradas vs Saídas e a linha de tendência do Saldo.</p>
    </div>
    <div id="graficoFluxo"></div>
</div>

<!-- BLOCO 2: TABELA RESUMO COMPACTA -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalhamento Mês a Mês</h3>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Competência</th>
                    <th style="text-align: right;">(+) Entradas</th>
                    <th style="text-align: right;">(-) Saídas Lançadas</th>
                    <th style="text-align: right;">(-) Custos Projetados</th>
                    <th style="text-align: right; font-weight: bold;">(=) Saldo Final</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($meses_projecao as $p): ?>
                    <?php $cor_saldo = ($p['saldo'] >= 0) ? 'var(--green)' : 'var(--red)'; ?>
                    <tr>
                        <td><strong style="color: var(--text-primary);"><?= $p['nome'] ?></strong></td>
                        <td style="text-align: right; color: var(--green);"><?= money($p['entradas']) ?></td>
                        <td style="text-align: right; color: var(--red); opacity: 0.8;"><?= money($p['saidas_geradas']) ?></td>
                        <td style="text-align: right; color: var(--yellow); opacity: 0.8;"><?= money($p['saidas_projecao']) ?></td>
                        <td style="text-align: right; color: <?= $cor_saldo ?>; font-weight: bold; font-size: 15px;">
                            <?= money($p['saldo']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- Linha de Totalizador do Semestre -->
                <tr style="background: var(--bg-hover); border-top: 2px solid var(--border-mid);">
                    <td><strong style="color: var(--text-primary); text-transform: uppercase; font-size: 11px;">Total Semestre</strong></td>
                    <td style="text-align: right; font-weight: bold; color: var(--green);"><?= money($total_entradas_semestre) ?></td>
                    <td colspan="2" style="text-align: right; font-weight: bold; color: var(--red);"><?= money($total_saidas_semestre) ?></td>
                    <td style="text-align: right; font-weight: bold; font-size: 16px; color: <?= ($total_entradas_semestre - $total_saidas_semestre >= 0) ? 'var(--green)' : 'var(--red)' ?>;">
                        <?= money($total_entradas_semestre - $total_saidas_semestre) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pega os dados do PHP dinamicamente
    const dadosProjecao = <?= $json_dados ?>;
    
    // Mapeia os arrays para o gráfico
    const categorias = dadosProjecao.map(d => d.nome);
    const entradas = dadosProjecao.map(d => d.entradas);
    const saidas = dadosProjecao.map(d => d.saidas_totais);
    const saldos = dadosProjecao.map(d => d.saldo);

    const options = {
        series: [
            {
                name: 'Entradas',
                type: 'column',
                data: entradas
            },
            {
                name: 'Saídas Totais',
                type: 'column',
                data: saidas
            },
            {
                name: 'Saldo Projetado',
                type: 'area', // Linha com preenchimento suave embaixo
                data: saldos
            }
        ],
        chart: {
            height: 380,
            type: 'line',
            stacked: false,
            toolbar: { show: false },
            fontFamily: 'inherit' // Puxa a fonte nativa do seu CSS
        },
        stroke: {
            width: [0, 0, 4],
            curve: 'smooth'
        },
        colors: ['#22c55e', '#ef4444', '#3b82f6'], // Verde (Entradas), Vermelho (Saídas), Azul (Saldo)
        fill: {
            opacity: [0.85, 0.85, 0.15],
            type: ['solid', 'solid', 'solid']
        },
        markers: {
            size: [0, 0, 6],
            colors: ['#3b82f6'],
            strokeColors: '#fff',
            strokeWidth: 2,
            hover: { size: 8 }
        },
        xaxis: {
            categories: categorias,
            labels: {
                style: { colors: '#888', fontSize: '13px' }
            },
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: {
                style: { colors: '#888' },
                formatter: function (value) {
                    return "R$ " + value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        grid: {
            borderColor: 'rgba(150, 150, 150, 0.1)',
            strokeDashArray: 4,
            yaxis: { lines: { show: true } }
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (y) {
                    if (typeof y !== "undefined") {
                        return "R$ " + y.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    }
                    return y;
                }
            }
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            offsetY: -20
        }
    };

    const chart = new ApexCharts(document.querySelector("#graficoFluxo"), options);
    chart.render();
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>