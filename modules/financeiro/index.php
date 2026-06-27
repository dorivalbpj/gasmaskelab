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

/// --- INÍCIO DO CÓDIGO NOVO PARA ENTRADA AVULSA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'salvar_avulsa') {
    $descricao = trim($_POST['descricao']);
    $valor = str_replace(',', '.', $_POST['valor']);
    $data = $_POST['data_vencimento'];
    
    try {
        // Adicionado 'numero_parcela' com o valor 1 fixo no final do INSERT
        $pdo->prepare("INSERT INTO parcelas (descricao, valor, data_vencimento, data_pagamento, status, contrato_id, numero_parcela) VALUES (?, ?, ?, ?, 'pago', NULL, 1)")
            ->execute([$descricao, $valor, $data, $data]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Entrada rápida registrada com sucesso!</div>";
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao registrar: " . $e->getMessage() . "</div>";
    }
}

// --- SISTEMA DE FILTROS ---
$filtro_mesano = $_GET['mesano'] ?? date('Y-m');
$filtro_status = $_GET['status'] ?? '';

list($ano_filtro, $mes_filtro) = explode('-', $filtro_mesano);

$where = ["YEAR(p.data_vencimento) = ? AND MONTH(p.data_vencimento) = ?"];
$params = [$ano_filtro, $mes_filtro];

if ($filtro_status) {
    $where[] = "p.status = ?";
    $params[] = $filtro_status;
}

$where_sql = implode(' AND ', $where);

// Busca as parcelas aplicando os filtros
$stmt = $pdo->prepare("SELECT p.*, c.codigo_agc, cli.nome as cliente_nome 
                     FROM parcelas p 
                     LEFT JOIN contratos c ON p.contrato_id = c.id 
                     LEFT JOIN clientes cli ON c.cliente_id = cli.id 
                     WHERE $where_sql
                     ORDER BY p.data_vencimento ASC");
$stmt->execute($params);
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
        <p class="page-subtitle">Acompanhe os recebimentos dos seus contratos e serviços avulsos.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalAvulso()">
        <i class="ph ph-plus"></i> Entrada Avulsa
    </button>
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
                            <strong style="color: var(--text-primary); display: block;">
                                <?= !empty($p['cliente_nome']) ? htmlspecialchars($p['cliente_nome']) : 'Serviço Avulso' ?>
                            </strong>
                            <span style="font-size: 11px; color: var(--text-muted);">
                                <?= !empty($p['codigo_agc']) ? htmlspecialchars($p['codigo_agc']) : 'Sem Contrato' ?>
                            </span>
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
            
            <div class="form-group">
                <label>Descrição do Serviço *</label>
                <input type="text" name="descricao" class="form-control" required placeholder="Ex: Ajuste de Arte, Flyer Redes Sociais...">
            </div>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" name="valor" class="form-control" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Data *</label>
                    <input type="date" name="data_vencimento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" class="btn btn-primary w-100" style="justify-content: center;">Registrar Entrada</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalAvulso() { document.getElementById('modalAvulso').classList.add('active'); }
function fecharModalAvulso() { document.getElementById('modalAvulso').classList.remove('active'); }
</script>

<?php require_once '../../includes/layout/footer.php'; ?>