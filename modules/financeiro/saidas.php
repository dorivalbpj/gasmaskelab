<?php
// modules/financeiro/saidas.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

// Atualiza automaticamente os lançamentos vencidos para 'atrasado'
$pdo->query("UPDATE fin_lancamentos SET status = 'atrasado' WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE");

// Processa ações (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    
    try {
        if ($acao == 'dar_baixa') {
            $lancamento_id = $_POST['lancamento_id'] ?? 0;
            $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            
            // Só pode dar baixa se não for de cartão (cartão se paga pela fatura)
            $stmt_check = $pdo->prepare("SELECT forma_pagamento FROM fin_lancamentos WHERE id = ?");
            $stmt_check->execute([$lancamento_id]);
            $forma = $stmt_check->fetchColumn();
            
            if ($forma == 'cartao') {
                throw new Exception("Lançamentos de cartão de crédito devem ser pagos através do fechamento da fatura.");
            }
            
            $pdo->prepare("UPDATE fin_lancamentos SET status = 'pago', data_pagamento = ? WHERE id = ?")
                ->execute([$data_pagamento, $lancamento_id]);
            
            header("Location: saidas.php?msg=sucesso");
            exit;
            
        } elseif ($acao == 'excluir_lancamento') {
            $lancamento_id = $_POST['lancamento_id'] ?? 0;
            $excluir_grupo = $_POST['excluir_grupo'] ?? 0;
            
            if ($excluir_grupo) {
                // Excluir todas as parcelas daquela compra
                $stmt_g = $pdo->prepare("SELECT grupo_id FROM fin_lancamentos WHERE id = ?");
                $stmt_g->execute([$lancamento_id]);
                $g_id = $stmt_g->fetchColumn();
                
                if ($g_id) {
                    $pdo->prepare("DELETE FROM fin_lancamentos WHERE grupo_id = ?")->execute([$g_id]);
                } else {
                    $pdo->prepare("DELETE FROM fin_lancamentos WHERE id = ?")->execute([$lancamento_id]);
                }
            } else {
                $pdo->prepare("DELETE FROM fin_lancamentos WHERE id = ?")->execute([$lancamento_id]);
            }
            
            header("Location: saidas.php?msg=sucesso");
            exit;
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// --- SISTEMA DE FILTROS ---
$filtro_mesano = $_GET['mesano'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_status = $_GET['status'] ?? '';

list($ano_filtro, $mes_filtro) = explode('-', $filtro_mesano);

$where = ["YEAR(l.data_vencimento) = ? AND MONTH(l.data_vencimento) = ?"];
$params = [$ano_filtro, $mes_filtro];

if ($filtro_tipo) {
    $where[] = "l.tipo = ?";
    $params[] = $filtro_tipo;
}
if ($filtro_status) {
    $where[] = "l.status = ?";
    $params[] = $filtro_status;
}

$where[] = "l.forma_pagamento != 'cartao'";
$where_sql = implode(' AND ', $where);

// --- CONSULTA PRINCIPAL ---
$stmt = $pdo->prepare("
    SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, fat.cartao_id, cart.nome as cartao_nome
    FROM fin_lancamentos l
    LEFT JOIN fin_categorias c ON l.categoria_id = c.id
    LEFT JOIN fin_faturas fat ON l.fatura_id = fat.id
    LEFT JOIN fin_cartoes cart ON fat.cartao_id = cart.id
    WHERE $where_sql
    ORDER BY l.data_vencimento ASC
");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll();

// --- INJETA AS FATURAS DO MÊS COMO LINHA ÚNICA ---
$stmt_faturas = $pdo->prepare("
    SELECT f.*, c.nome as cartao_nome, c.bandeira 
    FROM fin_faturas f
    JOIN fin_cartoes c ON f.cartao_id = c.id
    WHERE YEAR(f.data_vencimento) = ? AND MONTH(f.data_vencimento) = ?
");
$stmt_faturas->execute([$ano_filtro, $mes_filtro]);
$faturas_mes = $stmt_faturas->fetchAll();

foreach ($faturas_mes as $fat) {
    $stmt_tot = $pdo->prepare("SELECT SUM(valor) FROM fin_lancamentos WHERE fatura_id = ?");
    $stmt_tot->execute([$fat['id']]);
    $total = (float)$stmt_tot->fetchColumn();
    
    if ($total > 0) {
        $status_fat = 'pendente';
        if ($fat['status'] == 'paga') $status_fat = 'pago';
        elseif (strtotime($fat['data_vencimento']) < strtotime(date('Y-m-d'))) $status_fat = 'atrasado';
        
        if ($filtro_status && $filtro_status != $status_fat) continue;
        if ($filtro_tipo) continue; // Esconde faturas se filtrar ativamente por pessoal/empresa
        
        $lancamentos[] = [
            'id' => 0, 'real_fatura_id' => $fat['id'], 'descricao' => 'Fatura ' . $fat['cartao_nome'],
            'categoria_nome' => 'Cartão de Crédito', 'categoria_cor' => '#8b5cf6',
            'data_vencimento' => $fat['data_vencimento'], 'data_pagamento' => $fat['data_pagamento'],
            'valor' => $total, 'status' => $status_fat, 'tipo' => 'misto', 'forma_pagamento' => 'fatura',
            'cartao_nome' => $fat['cartao_nome'], 'total_parcelas' => 1, 'parcela_atual' => 1, 'grupo_id' => null
        ];
    }
}

usort($lancamentos, function($a, $b) {
    return strtotime($a['data_vencimento']) <=> strtotime($b['data_vencimento']);
});

// --- CÁLCULO DAS MÉTRICAS ---
$total_pagar = 0;
$total_atrasado = 0;
$total_pago = 0;
$total_mes = 0;

foreach ($lancamentos as $l) {
    $valor = (float)$l['valor'];
    $total_mes += $valor;
    
    if ($l['status'] == 'pago') {
        $total_pago += $valor;
    } elseif ($l['status'] == 'atrasado') {
        $total_atrasado += $valor;
    } else {
        $total_pagar += $valor;
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Financeiro — Saídas</h2>
        <p class="page-subtitle">Controle seus gastos pessoais e da agência.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="recorrentes.php" class="btn btn-secondary"><i class="ph ph-repeat"></i> Gerar Recorrentes</a>
        <a href="novo_lancamento.php" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Lançamento</a>
    </div>
</div>

<?= $mensagem ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'sucesso'): ?>
    <div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Lançamento salvo com sucesso!</div>
<?php endif; ?>

<!-- Métricas -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="metric-card accent-blue">
        <div class="metric-label">A Pagar (Prazo)</div>
        <div class="metric-value"><?= money($total_pagar) ?></div>
    </div>
    <div class="metric-card accent-red">
        <div class="metric-label text-red">Atrasado</div>
        <div class="metric-value"><?= money($total_atrasado) ?></div>
    </div>
    <div class="metric-card accent-green">
        <div class="metric-label text-green">Pago Este Mês</div>
        <div class="metric-value"><?= money($total_pago) ?></div>
    </div>
    <div class="metric-card accent-yellow">
        <div class="metric-label">Total do Mês</div>
        <div class="metric-value"><?= money($total_mes) ?></div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 16px 22px; margin-bottom: 24px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: flex-end; margin: 0;">
        <div class="form-group mb-0" style="flex: 1;">
            <label>Competência</label>
            <select name="mesano" class="form-control">
                <?php
                $meses_pt = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
                for ($i = -6; $i <= 3; $i++) {
                    $time = strtotime("$i months");
                    $val = date('Y-m', $time);
                    $label = $meses_pt[date('m', $time)] . ' ' . date('Y', $time);
                    $sel = ($val == $filtro_mesano) ? 'selected' : '';
                    echo "<option value='$val' $sel>$label</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group mb-0" style="flex: 1;">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos os Tipos</option>
                <option value="empresa" <?= $filtro_tipo == 'empresa' ? 'selected' : '' ?>>Empresa</option>
                <option value="pessoal" <?= $filtro_tipo == 'pessoal' ? 'selected' : '' ?>>Pessoal</option>
            </select>
        </div>
        <div class="form-group mb-0" style="flex: 1;">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">Todos os Status</option>
                <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="atrasado" <?= $filtro_status == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                <option value="pago" <?= $filtro_status == 'pago' ? 'selected' : '' ?>>Pago</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-h44"><i class="ph ph-funnel"></i> Filtrar</button>
    </form>
</div>

<!-- Tabela -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Extrato de Saídas</h3>
        <span class="badge badge-gray"><?= count($lancamentos) ?> Registros</span>
    </div>
    
    <?php if (count($lancamentos) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 110px;">Vencimento</th>
                        <th>Descrição / Categoria</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Forma Pgto</th>
                        <th style="text-align: right;">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lancamentos as $l): ?>
                    <?php 
                        $opacidade = ($l['status'] == 'pago') ? 'opacity: 0.5;' : ''; 
                        $forma_pgto_label = strtoupper($l['forma_pagamento']);
                        if ($l['forma_pagamento'] == 'cartao' && !empty($l['cartao_nome'])) {
                            $forma_pgto_label = "<i class='ph ph-credit-card'></i> " . htmlspecialchars($l['cartao_nome']);
                        }
                    ?>
                    <tr style="<?= $opacidade ?>">
                        <td>
                            <strong style="color: var(--text-primary); display: block;"><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></strong>
                            <?php if($l['status'] == 'pago'): ?>
                                <span style="font-size: 11px; color: var(--green);">Pago em <?= date('d/m/y', strtotime($l['data_pagamento'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($l['descricao']) ?></span>
                            <span class="txt-meta-sm">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $l['categoria_cor'] ?? '#999' ?>; margin-right: 4px;"></span>
                                <?= htmlspecialchars($l['categoria_nome'] ?? 'Sem categoria') ?>
                            </span>
                            <?php if ($l['total_parcelas'] > 1): ?>
                                <span style="font-size: 10px; color: var(--blue); background: rgba(59,130,246,0.1); padding: 1px 4px; border-radius: 4px; margin-left: 5px;">
                                    Parc <?= $l['parcela_atual'] ?>/<?= $l['total_parcelas'] ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($l['forma_pagamento'] == 'fatura'): ?>
                                <span class="badge badge-purple">MISTO</span>
                            <?php else: ?>
                                <span class="badge <?= $l['tipo'] == 'empresa' ? 'badge-blue' : 'badge-gray' ?>"><?= strtoupper($l['tipo']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="tag-tipo" <?= $l['forma_pagamento'] == 'cartao' ? 'style="color: var(--blue); border-color: rgba(59,130,246,0.3);"' : '' ?>>
                                <?= $forma_pgto_label ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 14px;">
                            <?= money($l['valor']) ?>
                        </td>
                        <td style="text-align: center;">
                            <?php 
                                if ($l['status'] == 'pago') echo '<span class="badge badge-green">PAGO</span>';
                                elseif ($l['status'] == 'atrasado') echo '<span class="badge badge-red">ATRASADO</span>';
                                else echo '<span class="badge badge-yellow">PENDENTE</span>';
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <?php if ($l['status'] != 'pago'): ?>
                                    <?php if ($l['forma_pagamento'] == 'fatura'): ?>
                                        <a href="fatura.php?id=<?= $l['real_fatura_id'] ?>" class="btn btn-secondary btn--sm" style="color: var(--purple); border-color: rgba(139,92,246,0.3);">
                                            <i class="ph ph-file-text"></i> Ver Fatura
                                        </a>
                                    <?php else: ?>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirmar o pagamento deste lançamento?');">
                                            <input type="hidden" name="acao" value="dar_baixa">
                                            <input type="hidden" name="lancamento_id" value="<?= $l['id'] ?>">
                                            <input type="hidden" name="data_pagamento" value="<?= date('Y-m-d') ?>">
                                            <button type="submit" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);">
                                                <i class="ph ph-check"></i> Baixa
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; font-weight: 700; width: 60px; display: inline-block;">Finalizado</span>
                                <?php endif; ?>

                                <?php
                                    $msg_exclusao = "Excluir este lançamento?";
                                    $is_grupo = ($l['grupo_id'] != null && $l['total_parcelas'] > 1) ? 1 : 0;
                                    if ($is_grupo) $msg_exclusao = "Atenção: Esta é uma compra parcelada.\\nDeseja excluir TODAS as parcelas dessa compra?";
                                ?>
                                <?php if ($l['forma_pagamento'] != 'fatura'): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('<?= $msg_exclusao ?>');">
                                        <input type="hidden" name="acao" value="excluir_lancamento">
                                        <input type="hidden" name="lancamento_id" value="<?= $l['id'] ?>">
                                        <input type="hidden" name="excluir_grupo" value="<?= $is_grupo ?>">
                                        <button type="submit" class="btn btn-ghost btn--sm btn-icon-table text-red" title="Excluir" style="padding: 4px 6px;">
                                            <i class="ph ph-trash" style="font-size: 16px;"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="ph ph-receipt empty-state-icon"></i>
            Nenhum lançamento encontrado para os filtros selecionados.<br>Utilize o botão "Novo Lançamento" para cadastrar.
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>
