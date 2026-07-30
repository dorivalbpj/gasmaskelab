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
            
            $stmt_check = $pdo->prepare("SELECT forma_pagamento FROM fin_lancamentos WHERE id = ?");
            $stmt_check->execute([$lancamento_id]);
            if ($stmt_check->fetchColumn() == 'cartao') {
                throw new Exception("Lançamentos de cartão devem ser pagos pela fatura.");
            }
            
            $pdo->prepare("UPDATE fin_lancamentos SET status = 'pago', data_pagamento = ? WHERE id = ?")
                ->execute([$data_pagamento, $lancamento_id]);
            header("Location: saidas.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
            
        } elseif ($acao == 'excluir_lancamento') {
            $lancamento_id = $_POST['lancamento_id'] ?? 0;
            $excluir_grupo = $_POST['excluir_grupo'] ?? 0;
            
            if ($excluir_grupo) {
                $g_id = $pdo->query("SELECT grupo_id FROM fin_lancamentos WHERE id = $lancamento_id")->fetchColumn();
                if ($g_id) $pdo->prepare("DELETE FROM fin_lancamentos WHERE grupo_id = ?")->execute([$g_id]);
                else $pdo->prepare("DELETE FROM fin_lancamentos WHERE id = ?")->execute([$lancamento_id]);
            } else {
                $pdo->prepare("DELETE FROM fin_lancamentos WHERE id = ?")->execute([$lancamento_id]);
            }
            header("Location: saidas.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
            
        } elseif ($acao == 'editar_lancamento') {
            $query_upd = "UPDATE fin_lancamentos SET descricao = ?, categoria_id = ?, tipo = ?, valor = ?, data_vencimento = ?, codigo_pagamento = ?";
            $params = [$_POST['descricao'], $_POST['categoria_id'] ?: null, $_POST['tipo'], str_replace(',', '.', $_POST['valor']), $_POST['data_vencimento'], $_POST['codigo_pagamento']];

            $dir = '../../uploads/financeiro/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            // Upload Arquivo de Cobrança (Boleto/NF)
            if (isset($_FILES['arquivo_cobranca']) && $_FILES['arquivo_cobranca']['error'] == 0) {
                $ext1 = pathinfo($_FILES['arquivo_cobranca']['name'], PATHINFO_EXTENSION);
                $nome_cob = 'cob_' . time() . '_' . rand(10,99) . '.' . $ext1;
                if (move_uploaded_file($_FILES['arquivo_cobranca']['tmp_name'], $dir . $nome_cob)) {
                    $query_upd .= ", cobranca_url = ?";
                    $params[] = '/gasmaske/uploads/financeiro/' . $nome_cob;
                }
            }

            // Upload Comprovante de Pagamento
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
                $ext2 = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $nome_comp = 'comp_sai_' . time() . '_' . rand(10,99) . '.' . $ext2;
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $dir . $nome_comp)) {
                    $query_upd .= ", comprovante_url = ?";
                    $params[] = '/gasmaske/uploads/financeiro/' . $nome_comp;
                }
            }

            $query_upd .= " WHERE id = ?";
            $params[] = $_POST['lancamento_id'];

            $pdo->prepare($query_upd)->execute($params);
            header("Location: saidas.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
            
        } elseif ($acao == 'acao_lote') {
            $ids = array_filter(array_map('intval', explode(',', $_POST['ids_lote'])));
            if (count($ids) > 0) {
                $ids_str = implode(',', $ids);
                if ($_POST['tipo_acao'] == 'pagar') {
                    $pdo->query("UPDATE fin_lancamentos SET status = 'pago', data_pagamento = CURRENT_DATE WHERE id IN ($ids_str) AND forma_pagamento != 'cartao'");
                } elseif ($_POST['tipo_acao'] == 'excluir') {
                    $pdo->query("DELETE FROM fin_lancamentos WHERE id IN ($ids_str)");
                }
            }
            header("Location: saidas.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// --- BUSCA CATEGORIAS PARA FILTRO E MODAL ---
$categorias = $pdo->query("SELECT id, nome, cor FROM fin_categorias ORDER BY nome")->fetchAll();

// --- SISTEMA DE FILTROS E PAGINAÇÃO ---
$filtro_mesano = $_GET['mesano'] ?? date('Y-m');
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_categoria = $_GET['categoria'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, (int)($_GET['p'] ?? 1));
$limite = 50;
$offset = ($pagina - 1) * $limite;

// Navegação rápida de meses
$time_atual = strtotime($filtro_mesano . '-01');
$prev_mesano = date('Y-m', strtotime('-1 month', $time_atual));
$next_mesano = date('Y-m', strtotime('+1 month', $time_atual));
$meses_pt = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
$label_mes_atual = $meses_pt[date('m', $time_atual)] . ' / ' . date('Y', $time_atual);

list($ano_filtro, $mes_filtro) = explode('-', $filtro_mesano);
$where = ["YEAR(l.data_vencimento) = ? AND MONTH(l.data_vencimento) = ?", "l.forma_pagamento != 'cartao'"];
$params = [$ano_filtro, $mes_filtro];

if ($filtro_tipo) { $where[] = "l.tipo = ?"; $params[] = $filtro_tipo; }
if ($filtro_status) { $where[] = "l.status = ?"; $params[] = $filtro_status; }
if ($filtro_categoria) { $where[] = "l.categoria_id = ?"; $params[] = $filtro_categoria; }
if ($busca) {
    $where[] = "(l.descricao LIKE ? OR c.nome LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%";
}

$where_sql = implode(' AND ', $where);

// Paginação (Conta o total)
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM fin_lancamentos l LEFT JOIN fin_categorias c ON l.categoria_id = c.id WHERE $where_sql");
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $limite);

// --- CONSULTA PRINCIPAL ---
$stmt = $pdo->prepare("
    SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor, fat.cartao_id, cart.nome as cartao_nome
    FROM fin_lancamentos l
    LEFT JOIN fin_categorias c ON l.categoria_id = c.id
    LEFT JOIN fin_faturas fat ON l.fatura_id = fat.id
    LEFT JOIN fin_cartoes cart ON fat.cartao_id = cart.id
    WHERE $where_sql
    ORDER BY l.data_vencimento ASC
    LIMIT $limite OFFSET $offset
");
$stmt->execute($params);
$lancamentos = $stmt->fetchAll();

// --- INJETA AS FATURAS DO MÊS (Apenas na página 1 e se não houver busca/categoria limitante) ---
if ($pagina == 1 && !$busca && !$filtro_categoria) {
    $stmt_faturas = $pdo->prepare("SELECT f.*, c.nome as cartao_nome FROM fin_faturas f JOIN fin_cartoes c ON f.cartao_id = c.id WHERE YEAR(f.data_vencimento) = ? AND MONTH(f.data_vencimento) = ?");
    $stmt_faturas->execute([$ano_filtro, $mes_filtro]);
    $faturas_mes = $stmt_faturas->fetchAll();

    foreach ($faturas_mes as $fat) {
        $total = (float)$pdo->query("SELECT SUM(valor) FROM fin_lancamentos WHERE fatura_id = {$fat['id']}")->fetchColumn();
        if ($total > 0) {
            $status_fat = ($fat['status'] == 'paga') ? 'pago' : ((strtotime($fat['data_vencimento']) < strtotime(date('Y-m-d'))) ? 'atrasado' : 'pendente');
            if ($filtro_status && $filtro_status != $status_fat) continue;
            if ($filtro_tipo) continue;
            
            $lancamentos[] = [
                'id' => 0, 'real_fatura_id' => $fat['id'], 'descricao' => 'Fatura ' . $fat['cartao_nome'],
                'categoria_nome' => 'Cartão de Crédito', 'categoria_cor' => '#8b5cf6', 'data_vencimento' => $fat['data_vencimento'],
                'data_pagamento' => $fat['data_pagamento'], 'valor' => $total, 'status' => $status_fat, 'tipo' => 'misto',
                'forma_pagamento' => 'fatura', 'cartao_nome' => $fat['cartao_nome'], 'total_parcelas' => 1, 'parcela_atual' => 1, 'grupo_id' => null
            ];
        }
    }
    usort($lancamentos, fn($a, $b) => strtotime($a['data_vencimento']) <=> strtotime($b['data_vencimento']));
}

// CÁLCULO DAS MÉTRICAS (Da página atual)
$total_pagar = $total_atrasado = $total_pago = $total_mes = 0;
foreach ($lancamentos as $l) {
    $val = (float)$l['valor'];
    $total_mes += $val;
    if ($l['status'] == 'pago') $total_pago += $val;
    elseif ($l['status'] == 'atrasado') $total_atrasado += $val;
    else $total_pagar += $val;
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title" style="display: flex; align-items: center; gap: 10px;">
            Financeiro — Saídas
            <a href="?mesano=<?= $prev_mesano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Anterior"><i class="ph ph-caret-left"></i></a>
            <span style="font-size: 16px; color: var(--text-2);"><?= $label_mes_atual ?></span>
            <a href="?mesano=<?= $next_mesano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Seguinte"><i class="ph ph-caret-right"></i></a>
        </h2>
        <p class="page-subtitle">Controle seus gastos pessoais e da agência.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="recorrentes.php" class="btn btn-secondary"><i class="ph ph-repeat"></i> Gerar Recorrentes</a>
        <a href="novo_lancamento.php" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Lançamento</a>
    </div>
</div>

<?= $mensagem ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'sucesso'): ?>
    <div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Operação realizada com sucesso!</div>
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

<!-- Filtros Completos -->
<div class="card" style="padding: 16px 22px; margin-bottom: 24px;">
    <form method="GET" class="filter-bar-container" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
        <input type="hidden" name="mesano" value="<?= $filtro_mesano ?>">
        
        <div class="filter-col-lg">
            <label class="filter-label">Busca Rápida</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" name="busca" class="form-control input-pl-40" value="<?= htmlspecialchars($busca) ?>" placeholder="Descrição ou Categoria">
            </div>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Categoria</label>
            <select name="categoria" class="form-control">
                <option value="">Todas</option>
                <?php foreach($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $filtro_categoria == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="empresa" <?= $filtro_tipo == 'empresa' ? 'selected' : '' ?>>Empresa</option>
                <option value="pessoal" <?= $filtro_tipo == 'pessoal' ? 'selected' : '' ?>>Pessoal</option>
            </select>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Todos</option>
                <option value="pendente" <?= $filtro_status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="atrasado" <?= $filtro_status == 'atrasado' ? 'selected' : '' ?>>Atrasado</option>
                <option value="pago" <?= $filtro_status == 'pago' ? 'selected' : '' ?>>Pago</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary btn-h44"><i class="ph ph-funnel"></i> Filtrar</button>
            <?php if($busca || $filtro_categoria || $filtro_tipo || $filtro_status): ?>
                <a href="?mesano=<?= $filtro_mesano ?>" class="btn btn-ghost btn-h44" title="Limpar Filtros"><i class="ph ph-x"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Ações em Lote e Tabela -->
<div class="card">
    <div class="card-header" style="padding-bottom: 0; border-bottom: none; margin-bottom: 0;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <h3 class="card-title">Extrato de Saídas</h3>
            <span class="badge badge-gray"><?= $total_registros ?> Registros</span>
        </div>
        <div id="acoes-massa" style="display: none; align-items: center; gap: 10px; background: var(--bg-hover); padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--border-mid);">
            <span style="font-size: 12px; font-weight: 600; color: var(--text-2);"><span id="count-selecionados">0</span> selecionados</span>
            <form method="POST" style="margin: 0; display: flex; gap: 8px;" id="formLote">
                <input type="hidden" name="acao" value="acao_lote">
                <input type="hidden" name="tipo_acao" id="tipo_acao_lote">
                <input type="hidden" name="ids_lote" id="ids_lote">
                <button type="button" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);" onclick="processarLote('pagar')"><i class="ph ph-check"></i> Pagar Lote</button>
                <button type="button" class="btn btn-ghost btn--sm text-red" onclick="processarLote('excluir')"><i class="ph ph-trash"></i></button>
            </form>
        </div>
    </div>
    
    <?php if (count($lancamentos) > 0): ?>
        <div class="table-wrapper" style="margin-top: 16px;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="checkAll" onchange="toggleCheckboxes(this)"></th>
                        <th style="width: 100px;">Vencimento</th>
                        <th>Descrição / Categoria</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Forma Pgto</th>
                        <th class="text-center" style="width: 70px;">Código</th>
                        <th style="text-align: right;">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lancamentos as $l): ?>
                    <?php 
                        $cls_row = ($l['status'] == 'pago') ? 'row-pago' : (($l['status'] == 'atrasado') ? 'row-atrasado' : ''); 
                        $forma_pgto_label = strtoupper($l['forma_pagamento']);
                        if ($l['forma_pagamento'] == 'cartao' && !empty($l['cartao_nome'])) $forma_pgto_label = "<i class='ph ph-credit-card'></i> " . htmlspecialchars($l['cartao_nome']);
                    ?>
                    <tr class="<?= $cls_row ?>">
                        <td style="text-align: center;">
                            <?php if($l['id'] > 0 && $l['status'] != 'pago'): ?>
                                <input type="checkbox" class="check-item" value="<?= $l['id'] ?>" onchange="atualizarLote()">
                            <?php endif; ?>
                        </td>
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
                                <span style="font-size: 10px; color: var(--blue); background: rgba(59,130,246,0.1); padding: 1px 4px; border-radius: 4px; margin-left: 5px;">Parc <?= $l['parcela_atual'] ?>/<?= $l['total_parcelas'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge <?= $l['tipo'] == 'empresa' ? 'badge-blue' : 'badge-gray' ?>"><?= strtoupper($l['tipo']) ?></span></td>
                        <td class="text-center"><span class="tag-tipo"><?= $forma_pgto_label ?></span></td>
                        
                        <!-- Coluna Código -->
                        <td class="text-center">
                            <?php if (!empty($l['codigo_pagamento'])): ?>
                                <button type="button" class="btn btn-ghost btn--sm btn-icon-table" style="color: var(--blue);" title="Copiar Código" onclick="copiarCodigo(this, '<?= htmlspecialchars(addslashes($l['codigo_pagamento'])) ?>')">
                                    <i class="ph ph-copy"></i>
                                </button>
                            <?php else: ?>
                                <span style="color: var(--border-mid);">-</span>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 14px;"><?= money($l['valor']) ?></td>
                        <td style="text-align: center;">
                            <?php 
                                if ($l['status'] == 'pago') echo '<span class="badge badge-green">PAGO</span>';
                                elseif ($l['status'] == 'atrasado') echo '<span class="badge badge-red">ATRASADO</span>';
                                else echo '<span class="badge badge-yellow">PENDENTE</span>';
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <?php if ($l['forma_pagamento'] == 'fatura'): ?>
                                    <a href="fatura.php?id=<?= $l['real_fatura_id'] ?>" class="btn btn-secondary btn--sm" style="color: var(--purple); border-color: rgba(139,92,246,0.3);"><i class="ph ph-file-text"></i> Ver Fatura</a>
                                <?php else: ?>
                                    
                                    <!-- Visualizar Arquivos -->
                                    <?php if (!empty($l['cobranca_url'])): ?>
                                        <a href="<?= htmlspecialchars($l['cobranca_url']) ?>" target="_blank" class="btn btn-ghost btn--sm btn-icon-table" title="Ver Arquivo de Cobrança"><i class="ph ph-file-pdf" style="color: var(--yellow);"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($l['comprovante_url'])): ?>
                                        <a href="<?= htmlspecialchars($l['comprovante_url']) ?>" target="_blank" class="btn btn-ghost btn--sm btn-icon-table" title="Ver Comprovante de Pagamento"><i class="ph ph-receipt" style="color: var(--green);"></i></a>
                                    <?php endif; ?>
                                    
                                    <!-- Editar -->
                                    <button type="button" class="btn btn-ghost btn--sm btn-icon-table" onclick="abrirModalEditarSaida(<?= $l['id'] ?>, '<?= addslashes(htmlspecialchars($l['descricao'])) ?>', '<?= $l['valor'] ?>', '<?= $l['data_vencimento'] ?>', '<?= $l['categoria_id'] ?>', '<?= $l['tipo'] ?>', '<?= addslashes(htmlspecialchars($l['codigo_pagamento'] ?? '')) ?>')" title="Editar Lançamento"><i class="ph ph-pencil-simple"></i></button>
                                    
                                    <!-- Ações Básicas -->
                                    <?php if ($l['status'] != 'pago'): ?>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirmar o pagamento?');">
                                            <input type="hidden" name="acao" value="dar_baixa">
                                            <input type="hidden" name="lancamento_id" value="<?= $l['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);"><i class="ph ph-check"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este lançamento?');">
                                        <input type="hidden" name="acao" value="excluir_lancamento">
                                        <input type="hidden" name="lancamento_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn--sm btn-icon-table text-red" title="Excluir"><i class="ph ph-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_paginas > 1): ?>
            <div style="display: flex; justify-content: center; gap: 6px; margin-top: 20px;">
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                    <a href="?mesano=<?= $filtro_mesano ?>&busca=<?= urlencode($busca) ?>&categoria=<?= $filtro_categoria ?>&status=<?= $filtro_status ?>&tipo=<?= $filtro_tipo ?>&p=<?= $i ?>" class="btn <?= $i == $pagina ? 'btn-primary' : 'btn-secondary' ?> btn--sm"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="ph ph-receipt empty-state-icon"></i>
            Nenhum lançamento encontrado para os filtros selecionados.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Edição (Com Uploads Personalizados) -->
<div class="modal-overlay" id="modalEditarSaida">
    <div class="modal-box" style="max-width: 450px;">
        <button type="button" class="modal-close-btn" onclick="fecharModalEditarSaida()"><i class="ph ph-x"></i></button>
        <h3 style="margin-top: 0; margin-bottom: 20px;">Editar Lançamento</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="acao" value="editar_lancamento">
            <input type="hidden" name="lancamento_id" id="edit_id">
            
            <div class="form-group"><label>Descrição</label><input type="text" name="descricao" id="edit_desc" class="form-control" required></div>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group"><label>Valor (R$)</label><input type="number" step="0.01" name="valor" id="edit_valor" class="form-control" required></div>
                <div class="form-group"><label>Vencimento</label><input type="date" name="data_vencimento" id="edit_data" class="form-control" required></div>
            </div>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Categoria</label>
                    <select name="categoria_id" id="edit_cat" class="form-control">
                        <option value="">Sem categoria</option>
                        <?php foreach($categorias as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo</label>
                    <select name="tipo" id="edit_tipo" class="form-control" required>
                        <option value="pessoal">Pessoal</option>
                        <option value="empresa">Empresa</option>
                    </select>
                </div>
            </div>

            <div class="divider"></div>
            
            <div class="form-group">
                <label>Código PIX ou Código de Barras</label>
                <input type="text" name="codigo_pagamento" id="edit_codigo" class="form-control" placeholder="Cole o código aqui...">
            </div>

            <!-- Uploads Customizados (Invisíveis e acionados por Label) -->
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="file_cobranca" class="btn btn-secondary w-100" style="justify-content: center; height: 44px; border: 1px dashed var(--yellow); color: var(--yellow); cursor: pointer;">
                        <i class="ph ph-upload-simple"></i> Cobrança
                    </label>
                    <input type="file" name="arquivo_cobranca" id="file_cobranca" accept=".pdf, .jpg, .png" style="display: none;" onchange="mostrarNomeArquivo(this, 'nome_cobranca')">
                    <div id="nome_cobranca" style="font-size: 10px; text-align: center; margin-top: 4px; color: var(--text-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
                </div>
                <div class="form-group">
                    <label for="file_comprovante" class="btn btn-secondary w-100" style="justify-content: center; height: 44px; border: 1px dashed var(--green); color: var(--green); cursor: pointer;">
                        <i class="ph ph-upload-simple"></i> Comprovante
                    </label>
                    <input type="file" name="comprovante" id="file_comprovante" accept=".pdf, .jpg, .png" style="display: none;" onchange="mostrarNomeArquivo(this, 'nome_comprovante')">
                    <div id="nome_comprovante" style="font-size: 10px; text-align: center; margin-top: 4px; color: var(--text-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3" style="justify-content: center; height: 44px;">Salvar Alterações</button>
        </form>
    </div>
</div>

<style>
    .row-pago { opacity: 0.5; }
    .row-atrasado td:first-child { border-left: 4px solid var(--red); }
</style>

<script>
function abrirModalEditarSaida(id, desc, valor, data, cat, tipo, codigo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_desc').value = desc;
    document.getElementById('edit_valor').value = parseFloat(valor).toFixed(2);
    document.getElementById('edit_data').value = data;
    document.getElementById('edit_cat').value = cat;
    document.getElementById('edit_tipo').value = tipo;
    document.getElementById('edit_codigo').value = codigo || '';
    
    // Reseta os nomes dos arquivos
    document.getElementById('nome_cobranca').innerText = '';
    document.getElementById('nome_comprovante').innerText = '';
    
    document.getElementById('modalEditarSaida').classList.add('active');
}

function fecharModalEditarSaida() { document.getElementById('modalEditarSaida').classList.remove('active'); }

// Script para mostrar o nome do arquivo após selecionar no modal
function mostrarNomeArquivo(input, spanId) {
    const span = document.getElementById(spanId);
    if (input.files && input.files.length > 0) {
        span.innerText = input.files[0].name;
    } else {
        span.innerText = '';
    }
}

// Script para copiar código de barras e dar feedback visual no botão
function copiarCodigo(btn, codigo) {
    navigator.clipboard.writeText(codigo).then(() => {
        const icon = btn.querySelector('i');
        const oldClass = icon.className;
        
        // Troca para ícone de check verdinho
        icon.className = 'ph ph-check text-green';
        
        // Retorna ao estado original após 2 segundos
        setTimeout(() => {
            icon.className = oldClass;
        }, 2000);
    }).catch(err => {
        alert('Erro ao copiar o código: ', err);
    });
}

function toggleCheckboxes(source) {
    let checkboxes = document.querySelectorAll('.check-item');
    checkboxes.forEach(cb => cb.checked = source.checked);
    atualizarLote();
}

function atualizarLote() {
    let checked = document.querySelectorAll('.check-item:checked');
    document.getElementById('count-selecionados').innerText = checked.length;
    document.getElementById('acoes-massa').style.display = checked.length > 0 ? 'flex' : 'none';
    
    let ids = Array.from(checked).map(cb => cb.value).join(',');
    document.getElementById('ids_lote').value = ids;
}

function processarLote(tipo) {
    if(!confirm(`Tem certeza que deseja ${tipo} os itens selecionados?`)) return;
    document.getElementById('tipo_acao_lote').value = tipo;
    document.getElementById('formLote').submit();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>