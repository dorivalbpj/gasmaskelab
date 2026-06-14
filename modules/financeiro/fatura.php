<?php
// modules/financeiro/fatura.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: saidas.php");
    exit;
}

$mensagem = '';

// --- LÓGICA DE PAGAMENTO "TUDO OU NADA" ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'pagar_fatura') {
    $data_pagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
    
    try {
        $pdo->beginTransaction();
        
        // 1. Atualiza a fatura mãe
        $pdo->prepare("UPDATE fin_faturas SET status = 'paga', data_pagamento = ? WHERE id = ?")
            ->execute([$data_pagamento, $id]);
            
        // 2. Atualiza todos os lançamentos filhos (Baixa em lote)
        $pdo->prepare("UPDATE fin_lancamentos SET status = 'pago', data_pagamento = ? WHERE fatura_id = ?")
            ->execute([$data_pagamento, $id]);
            
        $pdo->commit();
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Fatura paga com sucesso! Todos os lançamentos receberam baixa e o limite do cartão foi liberado.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao pagar fatura: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTA DADOS DA FATURA E CARTÃO ---
$stmt = $pdo->prepare("
    SELECT f.*, c.nome as cartao_nome, c.bandeira, c.dia_fechamento, c.dia_vencimento
    FROM fin_faturas f
    JOIN fin_cartoes c ON f.cartao_id = c.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$fatura = $stmt->fetch();

if (!$fatura) {
    die("<div style='padding: 50px; text-align: center; font-family: sans-serif; color: #fff;'>Fatura não encontrada. <br><br><a href='saidas.php' style='color: #3b82f6;'>Voltar</a></div>");
}

// --- CONSULTA OS LANÇAMENTOS (GASTOS) ---
$stmt_l = $pdo->prepare("
    SELECT l.*, c.nome as categoria_nome, c.cor as categoria_cor 
    FROM fin_lancamentos l
    LEFT JOIN fin_categorias c ON l.categoria_id = c.id
    WHERE l.fatura_id = ?
    ORDER BY l.data_vencimento ASC, l.id ASC
");
$stmt_l->execute([$id]);
$lancamentos = $stmt_l->fetchAll();

// Calcula o total real da fatura
$total_fatura = 0;
foreach ($lancamentos as $l) {
    $total_fatura += (float)$l['valor'];
}

// Tradução do mês
$meses_pt = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
$nome_mes = $meses_pt[str_pad($fatura['mes'], 2, '0', STR_PAD_LEFT)];

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Fatura <?= $nome_mes ?>/<?= $fatura['ano'] ?></h2>
        <p class="page-subtitle"><?= htmlspecialchars($fatura['cartao_nome']) ?> (<?= htmlspecialchars($fatura['bandeira']) ?>)</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="border-color: var(--purple); color: var(--purple);" onclick="alert('Integração com LLM (Gemini) para leitura automática de faturas em PDF será liberada na próxima fase!')" title="Em breve">
            <i class="ph ph-upload"></i> Importar PDF (Gemini)
        </button>
        <a href="saidas.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Voltar</a>
    </div>
</div>

<?= $mensagem ?>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">
    <!-- Lado Esquerdo: Resumo e Pagamento -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><h3 class="card-title">Resumo da Fatura</h3></div>
        
        <div style="font-size: 12px; color: var(--text-2); margin-bottom: 2px;">Valor Total</div>
        <div style="font-size: 32px; font-weight: 700; color: var(--text); margin-bottom: 20px; line-height: 1;"><?= money($total_fatura) ?></div>
        
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid var(--border-mid);">
                <span style="color: var(--text-3); font-size: 13px;">Vencimento</span>
                <strong style="color: var(--text);"><?= date('d/m/Y', strtotime($fatura['data_vencimento'])) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid var(--border-mid);">
                <span style="color: var(--text-3); font-size: 13px;">Status Atual</span>
                <?php if($fatura['status'] == 'paga'): ?>
                    <span class="badge badge-green">PAGA</span>
                <?php elseif($fatura['status'] == 'fechada'): ?>
                    <span class="badge badge-blue">FECHADA</span>
                <?php else: ?>
                    <span class="badge badge-yellow">ABERTA</span>
                <?php endif; ?>
            </div>
            <?php if($fatura['status'] == 'paga'): ?>
            <div style="display: flex; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid var(--border-mid);">
                <span style="color: var(--text-3); font-size: 13px;">Data da Baixa</span>
                <strong style="color: var(--green);"><?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?></strong>
            </div>
            <?php endif; ?>
        </div>

        <?php if($fatura['status'] != 'paga'): ?>
            <form method="POST" onsubmit="return confirm('Tem certeza? Isso marcará a fatura como PAGA e dará baixa em todos os <?= count($lancamentos) ?> lançamentos vinculados a ela simultaneamente.');">
                <input type="hidden" name="acao" value="pagar_fatura">
                <div class="form-group">
                    <label>Data de Pagamento</label>
                    <input type="date" name="data_pagamento" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <button type="submit" class="btn btn-primary w-100" style="justify-content: center; height: 44px; font-size: 14px;">
                    <i class="ph ph-check-circle"></i> Pagar Fatura Inteira
                </button>
            </form>
        <?php else: ?>
            <div class="alert alert-success mb-0" style="justify-content: center; font-size: 14px;">
                <i class="ph-fill ph-check-circle"></i> Fatura Quitada
            </div>
        <?php endif; ?>
    </div>

    <!-- Lado Direito: Tabela de Lançamentos -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h3 class="card-title">Despesas desta Fatura</h3>
            <span class="badge badge-gray"><?= count($lancamentos) ?> Itens</span>
        </div>
        
        <?php if(count($lancamentos) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 100px;">Data</th>
                            <th>Descrição / Categoria</th>
                            <th class="text-center">Tipo</th>
                            <th style="text-align: right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lancamentos as $l): ?>
                        <?php $opacidade = ($fatura['status'] == 'paga') ? 'opacity: 0.6;' : ''; ?>
                        <tr style="<?= $opacidade ?>">
                            <td><strong style="color: var(--text-primary);"><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></strong></td>
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
                                <span class="badge <?= $l['tipo'] == 'empresa' ? 'badge-blue' : 'badge-gray' ?>"><?= strtoupper($l['tipo']) ?></span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 14px;">
                                <?= money($l['valor']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="ph ph-receipt empty-state-icon"></i>
                Nenhum lançamento vinculado a esta fatura.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>
