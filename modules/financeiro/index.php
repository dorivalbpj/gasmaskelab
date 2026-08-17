<?php
// modules/financeiro/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';
$pdo->query("UPDATE parcelas SET status = 'atrasado' WHERE status = 'pendente' AND data_vencimento < CURRENT_DATE");

// --- PROCESSA AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];
    try {
        if ($acao == 'salvar_avulsa') {
            $status_avulsa = ($_POST['status'] ?? 'pendente') === 'pago' ? 'pago' : 'pendente';
            $data_pag_avulsa = ($status_avulsa === 'pago') ? date('Y-m-d') : null;

            $pdo->prepare("INSERT INTO parcelas (descricao, valor, data_vencimento, data_pagamento, status, contrato_id, numero_parcela) VALUES (?, ?, ?, ?, ?, NULL, 1)")
                ->execute([trim($_POST['descricao']), str_replace(',', '.', $_POST['valor']), $_POST['data_vencimento'], $data_pag_avulsa, $status_avulsa]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Entrada registrada!</div>";
        } elseif ($acao == 'dar_baixa') {
            $pdo->prepare("UPDATE parcelas SET status = 'pago', data_pagamento = ? WHERE id = ?")
                ->execute([$_POST['data_pagamento'] ?? date('Y-m-d'), $_POST['parcela_id']]);
            header("Location: index.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
        } elseif ($acao == 'editar_entrada') {
            $status_edit = ($_POST['status'] ?? 'pendente') === 'pago' ? 'pago' : 'pendente';

            // Busca o status/data_pagamento atuais pra saber se precisa ajustar a data de pagamento
            $stmt_atual = $pdo->prepare("SELECT status, data_pagamento FROM parcelas WHERE id = ?");
            $stmt_atual->execute([$_POST['parcela_id']]);
            $atual = $stmt_atual->fetch();

            if ($status_edit === 'pago') {
                // Se está virando pago agora e ainda não tinha data de pagamento, usa hoje
                $data_pagamento_edit = ($atual && $atual['data_pagamento']) ? $atual['data_pagamento'] : date('Y-m-d');
            } else {
                // Voltando para pendente: limpa a data de pagamento
                $data_pagamento_edit = null;
            }

            $query_upd = "UPDATE parcelas SET descricao = ?, valor = ?, data_vencimento = ?, status = ?, data_pagamento = ?";
            $params = [$_POST['descricao'], str_replace(',', '.', $_POST['valor']), $_POST['data_vencimento'], $status_edit, $data_pagamento_edit];

            // Lógica de Upload do Comprovante
            if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] == 0) {
                $dir = '../../uploads/financeiro/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $ext = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
                $nome_arquivo = 'comp_ent_' . time() . '_' . rand(10,99) . '.' . $ext;
                
                if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $dir . $nome_arquivo)) {
                    $query_upd .= ", comprovante_url = ?";
                    $params[] = '/gasmaske/uploads/financeiro/' . $nome_arquivo;
                }
            }

            $query_upd .= " WHERE id = ?";
            $params[] = $_POST['parcela_id'];

            $pdo->prepare($query_upd)->execute($params);
            header("Location: index.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
        } elseif ($acao == 'acao_lote') {
            $ids = array_filter(array_map('intval', explode(',', $_POST['ids_lote'])));
            if (count($ids) > 0) {
                $ids_str = implode(',', $ids);
                if ($_POST['tipo_acao'] == 'receber') {
                    $pdo->query("UPDATE parcelas SET status = 'pago', data_pagamento = CURRENT_DATE WHERE id IN ($ids_str)");
                } elseif ($_POST['tipo_acao'] == 'excluir') {
                    $pdo->query("DELETE FROM parcelas WHERE id IN ($ids_str)");
                }
            }
            header("Location: index.php?" . http_build_query($_GET) . "&msg=sucesso");
            exit;
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// --- SISTEMA DE FILTROS E PAGINAÇÃO ---
$filtro_mesano = $_GET['mesano'] ?? date('Y-m');
$filtro_status = $_GET['status'] ?? '';
$busca = $_GET['busca'] ?? '';
$pagina = max(1, (int)($_GET['p'] ?? 1));
$limite = 50;
$offset = ($pagina - 1) * $limite;

// Navegação rápida
$time_atual = strtotime($filtro_mesano . '-01');
$prev_mesano = date('Y-m', strtotime('-1 month', $time_atual));
$next_mesano = date('Y-m', strtotime('+1 month', $time_atual));
$meses_pt = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
$label_mes_atual = $meses_pt[date('m', $time_atual)] . ' / ' . date('Y', $time_atual);

list($ano_filtro, $mes_filtro) = explode('-', $filtro_mesano);
$where = ["YEAR(p.data_vencimento) = ? AND MONTH(p.data_vencimento) = ?"];
$params = [$ano_filtro, $mes_filtro];

if ($filtro_status) { $where[] = "p.status = ?"; $params[] = $filtro_status; }
if ($busca) {
    $where[] = "(p.descricao LIKE ? OR cli.nome LIKE ? OR c.codigo_agc LIKE ?)";
    $params[] = "%$busca%"; $params[] = "%$busca%"; $params[] = "%$busca%";
}

$where_sql = implode(' AND ', $where);

// Paginação Count
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM parcelas p LEFT JOIN contratos c ON p.contrato_id = c.id LEFT JOIN clientes cli ON c.cliente_id = cli.id WHERE $where_sql");
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $limite);

// Busca as parcelas
$stmt = $pdo->prepare("SELECT p.*, c.codigo_agc, cli.nome as cliente_nome 
                     FROM parcelas p 
                     LEFT JOIN contratos c ON p.contrato_id = c.id 
                     LEFT JOIN clientes cli ON c.cliente_id = cli.id 
                     WHERE $where_sql ORDER BY p.data_vencimento ASC LIMIT $limite OFFSET $offset");
$stmt->execute($params);
$parcelas = $stmt->fetchAll();

// CÁLCULO DAS MÉTRICAS
$total_receber = $total_atrasado = $total_recebido = $total_mes = 0;
foreach ($parcelas as $p) {
    $val = (float)$p['valor'];
    $total_mes += $val;
    if ($p['status'] == 'pago') $total_recebido += $val;
    elseif ($p['status'] == 'atrasado') $total_atrasado += $val;
    else $total_receber += $val;
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- Importação do CSS Mobile Específico -->
<link rel="stylesheet" href="../../assets/css/mobile-financeiro.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title" style="display: flex; align-items: center; gap: 10px;">
            Gestão Financeira
            <a href="?mesano=<?= $prev_mesano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Anterior"><i class="ph ph-caret-left"></i></a>
            <span style="font-size: 16px; color: var(--text-2);"><?= $label_mes_atual ?></span>
            <a href="?mesano=<?= $next_mesano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Seguinte"><i class="ph ph-caret-right"></i></a>
        </h2>
        <p class="page-subtitle">Acompanhe os recebimentos dos seus contratos e serviços avulsos.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-primary" onclick="abrirModalAvulso()"><i class="ph ph-plus"></i> Entrada Avulsa</button>
    </div>
</div>

<?= $mensagem ?>
<?php if(isset($_GET['msg']) && $_GET['msg'] == 'sucesso'): ?>
    <div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Operação realizada com sucesso!</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
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
    <div class="metric-card accent-yellow">
        <div class="metric-label">Total do Mês</div>
        <div class="metric-value"><?= money($total_mes) ?></div>
    </div>
</div>

<!-- Filtros -->
<div class="card" style="padding: 16px 22px; margin-bottom: 24px;">
    <form method="GET" class="filter-bar-container" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
        <input type="hidden" name="mesano" value="<?= $filtro_mesano ?>">
        
        <div class="filter-col-lg">
            <label class="filter-label">Busca Rápida</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" name="busca" class="form-control input-pl-40" value="<?= htmlspecialchars($busca) ?>" placeholder="Cliente, Código ou Descrição">
            </div>
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
            <?php if($busca || $filtro_status): ?>
                <a href="?mesano=<?= $filtro_mesano ?>" class="btn btn-ghost btn-h44" title="Limpar Filtros"><i class="ph ph-x"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header" style="padding-bottom: 0; border-bottom: none; margin-bottom: 0;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <h3 class="card-title">Lançamentos / Faturas</h3>
            <span class="badge badge-gray"><?= $total_registros ?> Registros</span>
        </div>
        <div id="acoes-massa-entradas" style="display: none; align-items: center; gap: 10px; background: var(--bg-hover); padding: 8px 12px; border-radius: var(--r-md); border: 1px solid var(--border-mid);">
            <span style="font-size: 12px; font-weight: 600; color: var(--text-2);"><span id="count-entradas">0</span> selecionados</span>
            <form method="POST" style="margin: 0; display: flex; gap: 8px;" id="formLoteEntradas">
                <input type="hidden" name="acao" value="acao_lote">
                <input type="hidden" name="tipo_acao" id="tipo_acao_entradas">
                <input type="hidden" name="ids_lote" id="ids_lote_entradas">
                <button type="button" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);" onclick="processarLoteEntradas('receber')"><i class="ph ph-check"></i> Receber Lote</button>
                <button type="button" class="btn btn-ghost btn--sm text-red" onclick="processarLoteEntradas('excluir')"><i class="ph ph-trash"></i></button>
            </form>
        </div>
    </div>
    
    <?php if (count($parcelas) > 0): ?>
        <div class="table-wrapper" style="margin-top: 16px;">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="checkAllEntradas" onchange="toggleCheckboxesEntradas(this)"></th>
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
                    <?php $cls_row = ($p['status'] == 'pago') ? 'row-pago' : (($p['status'] == 'atrasado') ? 'row-atrasado' : ''); ?>
                    <tr class="<?= $cls_row ?>">
                        <td style="text-align: center;">
                            <?php if($p['status'] != 'pago'): ?>
                                <input type="checkbox" class="check-entrada" value="<?= $p['id'] ?>" onchange="atualizarLoteEntradas()">
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); display: block;"><?= date('d/m/Y', strtotime($p['data_vencimento'])) ?></strong>
                            <?php if($p['status'] == 'pago'): ?>
                                <span style="font-size: 11px; color: var(--green);">Pago em <?= date('d/m/y', strtotime($p['data_pagamento'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: var(--text-primary); display: block;"><?= !empty($p['cliente_nome']) ? htmlspecialchars($p['cliente_nome']) : 'Serviço Avulso' ?></strong>
                            <span style="font-size: 11px; color: var(--text-muted);"><?= !empty($p['codigo_agc']) ? htmlspecialchars($p['codigo_agc']) : 'Sem Contrato' ?></span>
                        </td>
                        <td style="color: var(--text-secondary);"><?= htmlspecialchars($p['descricao']) ?></td>
                        <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 14px;"><?= money($p['valor']) ?></td>
                        <td style="text-align: center;">
                            <?php 
                                if ($p['status'] == 'pago') echo '<span class="badge badge-green">PAGO</span>';
                                elseif ($p['status'] == 'atrasado') echo '<span class="badge badge-red">ATRASADO</span>';
                                else echo '<span class="badge badge-yellow">PENDENTE</span>';
                            ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <button type="button" class="btn btn-ghost btn--sm btn-icon-table" onclick="abrirModalEditarEntrada(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['descricao'])) ?>', '<?= $p['valor'] ?>', '<?= $p['data_vencimento'] ?>', '<?= $p['status'] == 'pago' ? 'pago' : 'pendente' ?>')" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                <?php if ($p['status'] != 'pago'): ?>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Confirmar o recebimento?');">
                                        <input type="hidden" name="acao" value="dar_baixa">
                                        <input type="hidden" name="parcela_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="data_pagamento" value="<?= date('Y-m-d') ?>">
                                        <button type="submit" class="btn btn-secondary btn--sm" style="color: var(--green); border-color: rgba(34,197,94,0.3);"><i class="ph ph-check"></i> Baixa</button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!empty($p['comprovante_url'])): ?>
                                    <a href="<?= $p['comprovante_url'] ?>" target="_blank" class="btn btn-ghost btn--sm btn-icon-table" title="Ver Comprovante">
                                        <i class="ph ph-receipt" style="color: var(--blue);"></i>
                                    </a>
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
                    <a href="?mesano=<?= $filtro_mesano ?>&busca=<?= urlencode($busca) ?>&status=<?= $filtro_status ?>&p=<?= $i ?>" class="btn <?= $i == $pagina ? 'btn-primary' : 'btn-secondary' ?> btn--sm"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <div style="font-size: 30px; margin-bottom: 10px;">💸</div>
            Nenhuma entrada encontrada para os filtros selecionados.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Nova Entrada Avulsa -->
<div class="modal-overlay" id="modalAvulso">
    <div class="modal-box" style="max-width: 400px;">
        <button type="button" class="modal-close-btn" onclick="fecharModalAvulso()"><i class="ph ph-x"></i></button>
        <h3 style="margin-top: 0; margin-bottom: 20px;">Nova Entrada Rápida</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_avulsa">
            <div class="form-group"><label>Descrição do Serviço *</label><input type="text" name="descricao" class="form-control" required placeholder="Ex: Ajuste de Arte, Flyer..."></div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group"><label>Valor (R$) *</label><input type="number" step="0.01" name="valor" class="form-control" required placeholder="0.00"></div>
                <div class="form-group"><label>Data de Vencimento *</label><input type="date" name="data_vencimento" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status" class="form-control">
                    <option value="pendente">Pendente (Agendar / A Receber)</option>
                    <option value="pago">Já Recebido</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="justify-content: center;">Registrar Entrada</button>
        </form>
    </div>
</div>

<!-- Modal Editar Entrada -->
<div class="modal-overlay" id="modalEditarEntrada">
    <div class="modal-box" style="max-width: 400px;">
        <button type="button" class="modal-close-btn" onclick="fecharModalEditarEntrada()"><i class="ph ph-x"></i></button>
        <h3 style="margin-top: 0; margin-bottom: 20px;">Editar Entrada</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="editar_entrada">
            <input type="hidden" name="parcela_id" id="edit_ent_id">
            <div class="form-group"><label>Descrição / Referência</label><input type="text" name="descricao" id="edit_ent_desc" class="form-control" required></div>
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group"><label>Valor (R$)</label><input type="number" step="0.01" name="valor" id="edit_ent_valor" class="form-control" required></div>
                <div class="form-group"><label>Vencimento</label><input type="date" name="data_vencimento" id="edit_ent_data" class="form-control" required></div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_ent_status" class="form-control">
                    <option value="pendente">Pendente (A Receber)</option>
                    <option value="pago">Recebido</option>
                </select>
            </div>

            <div class="form-group mt-3">
                <label><i class="ph ph-paperclip"></i> Anexar Comprovante do Cliente</label>
                <input type="file" name="comprovante" class="form-control" accept=".pdf, .jpg, .jpeg, .png">
            </div>
            <button type="submit" class="btn btn-primary w-100" style="justify-content: center;">Salvar Alterações</button>
        </form>
    </div>
</div>

<style>
    .row-pago { opacity: 0.5; }
    .row-atrasado td:first-child { border-left: 4px solid var(--red); }
</style>

<script>
// Modal Entradas
function abrirModalAvulso() { document.getElementById('modalAvulso').classList.add('active'); }
function fecharModalAvulso() { document.getElementById('modalAvulso').classList.remove('active'); }
function abrirModalEditarEntrada(id, desc, valor, data, status) {
    document.getElementById('edit_ent_id').value = id;
    document.getElementById('edit_ent_desc').value = desc;
    document.getElementById('edit_ent_valor').value = parseFloat(valor).toFixed(2);
    document.getElementById('edit_ent_data').value = data;
    document.getElementById('edit_ent_status').value = status;
    document.getElementById('modalEditarEntrada').classList.add('active');
}
function fecharModalEditarEntrada() { document.getElementById('modalEditarEntrada').classList.remove('active'); }

// Lote Entradas
function toggleCheckboxesEntradas(source) {
    let checkboxes = document.querySelectorAll('.check-entrada');
    checkboxes.forEach(cb => cb.checked = source.checked);
    atualizarLoteEntradas();
}
function atualizarLoteEntradas() {
    let checked = document.querySelectorAll('.check-entrada:checked');
    document.getElementById('count-entradas').innerText = checked.length;
    document.getElementById('acoes-massa-entradas').style.display = checked.length > 0 ? 'flex' : 'none';
    
    let ids = Array.from(checked).map(cb => cb.value).join(',');
    document.getElementById('ids_lote_entradas').value = ids;
}
function processarLoteEntradas(tipo) {
    if(!confirm(`Tem certeza que deseja ${tipo} os itens selecionados?`)) return;
    document.getElementById('tipo_acao_entradas').value = tipo;
    document.getElementById('formLoteEntradas').submit();
}

// Toggle Mobile View
document.querySelectorAll('.table-wrapper tr').forEach(row => {
    row.addEventListener('click', function(e) {
        if(e.target.tagName === 'INPUT' || e.target.closest('button') || e.target.closest('a')) return;
        this.classList.toggle('mobile-expanded');
    });
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>