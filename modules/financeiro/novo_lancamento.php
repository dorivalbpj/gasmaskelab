<?php
// modules/financeiro/novo_lancamento.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $descricao = trim($_POST['descricao'] ?? '');
    $valor = (float)str_replace(',', '.', $_POST['valor'] ?? 0);
    $data_vencimento = $_POST['data_vencimento'] ?? date('Y-m-d');
    $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
    $tipo = $_POST['tipo'] ?? 'empresa';
    $forma_pagamento = $_POST['forma_pagamento'] ?? 'pix';
    $observacao = trim($_POST['observacao'] ?? '');
    
    try {
        $pdo->beginTransaction();
        
        if ($forma_pagamento === 'cartao') {
            // --- LÓGICA DE CARTÃO E PARCELAMENTO ---
            $cartao_id = $_POST['cartao_id'] ?? null;
            $parcelas = (int)($_POST['parcelas'] ?? 1);
            
            if (!$cartao_id) throw new Exception("Selecione um cartão de crédito.");
            
            $valor_parcela = $valor / $parcelas;
            $grupo_id = uniqid('compra_'); // Hash único para a compra parcelada
            
            // Busca os dados do cartão para saber o fechamento
            $stmt_c = $pdo->prepare("SELECT dia_fechamento, dia_vencimento FROM fin_cartoes WHERE id = ?");
            $stmt_c->execute([$cartao_id]);
            $cartao = $stmt_c->fetch();
            
            // Pega a data da compra informada no formulário
            $data_compra = new DateTime($data_vencimento);
            $dia_compra = (int)$data_compra->format('d');
            $mes_fatura = (int)$data_compra->format('n');
            $ano_fatura = (int)$data_compra->format('Y');
            
            // Se comprou no dia do fechamento ou depois, cai na fatura do mês seguinte
            if ($dia_compra >= $cartao['dia_fechamento']) {
                $mes_fatura++;
                if ($mes_fatura > 12) {
                    $mes_fatura = 1;
                    $ano_fatura++;
                }
            }
            
            // Prepara os inserts
            $stmt_check_fatura = $pdo->prepare("SELECT id FROM fin_faturas WHERE cartao_id = ? AND mes = ? AND ano = ?");
            $stmt_insert_fatura = $pdo->prepare("INSERT INTO fin_faturas (cartao_id, mes, ano, data_vencimento, status) VALUES (?, ?, ?, ?, 'aberta')");
            $stmt_insert_lancamento = $pdo->prepare("INSERT INTO fin_lancamentos (descricao, valor, data_vencimento, categoria_id, tipo, forma_pagamento, status, fatura_id, grupo_id, parcela_atual, total_parcelas, observacao) VALUES (?, ?, ?, ?, ?, 'cartao', 'pendente', ?, ?, ?, ?, ?)");
            
            for ($i = 1; $i <= $parcelas; $i++) {
                // Trata meses com 28, 29 ou 30 dias para o vencimento da fatura
                $data_base_fatura = sprintf('%04d-%02d-01', $ano_fatura, $mes_fatura);
                $max_dias = date('t', strtotime($data_base_fatura));
                $dia_venc_real = min((int)$cartao['dia_vencimento'], $max_dias);
                $data_venc_fatura = sprintf('%04d-%02d-%02d', $ano_fatura, $mes_fatura, $dia_venc_real);
                
                // Checa se a fatura já existe, se não, cria
                $stmt_check_fatura->execute([$cartao_id, $mes_fatura, $ano_fatura]);
                $fatura = $stmt_check_fatura->fetch();
                
                if ($fatura) {
                    $fatura_id = $fatura['id'];
                } else {
                    $stmt_insert_fatura->execute([$cartao_id, $mes_fatura, $ano_fatura, $data_venc_fatura]);
                    $fatura_id = $pdo->lastInsertId();
                }
                
                // Ajusta a descrição se for parcelado
                $desc_parcela = $descricao . ($parcelas > 1 ? " ($i/$parcelas)" : "");
                
                // Insere o lançamento vinculado à fatura
                $stmt_insert_lancamento->execute([
                    $desc_parcela, $valor_parcela, $data_venc_fatura, $categoria_id, $tipo, $fatura_id, $grupo_id, $i, $parcelas, $observacao
                ]);
                
                // Avança um mês para a próxima parcela
                $mes_fatura++;
                if ($mes_fatura > 12) {
                    $mes_fatura = 1;
                    $ano_fatura++;
                }
            }
            
            $pdo->commit();
            // O redirecionamento vai dar 404 até criarmos o saidas.php, mas a URL já está pronta!
            header("Location: saidas.php?msg=sucesso");
            exit;
            
        } else {
            // --- LÓGICA DE DESPESA NORMAL (PIX, BOLETO, DINHEIRO) ---
            $status = $_POST['status'] ?? 'pendente';
            $codigo_pagamento = trim($_POST['codigo_pagamento'] ?? '');
            $data_pagamento = ($status === 'pago') ? date('Y-m-d') : null;
            
            $stmt = $pdo->prepare("INSERT INTO fin_lancamentos (descricao, valor, data_vencimento, data_pagamento, categoria_id, tipo, forma_pagamento, codigo_pagamento, status, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $descricao, $valor, $data_vencimento, $data_pagamento, $categoria_id, $tipo, $forma_pagamento, $codigo_pagamento, $status, $observacao
            ]);
            
            $pdo->commit();
            header("Location: saidas.php?msg=sucesso");
            exit;
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao salvar: " . $e->getMessage() . "</div>";
    }
}

// Consultas para preencher os selects
$categorias = $pdo->query("SELECT id, nome FROM fin_categorias ORDER BY nome ASC")->fetchAll();
$cartoes = $pdo->query("SELECT id, nome, bandeira FROM fin_cartoes WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Novo Lançamento</h2>
        <p class="page-subtitle">Registre uma nova despesa ou compra no cartão.</p>
    </div>
    <a href="saidas.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Voltar</a>
</div>

<?= $mensagem ?>

<form method="POST" action="" id="formLancamento">
    <div class="card" style="max-width: 800px;">
        <div class="form-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div class="form-group"><label>Descrição do Gasto *</label><input type="text" name="descricao" class="form-control" required placeholder="Ex: Assinatura Adobe CC, Compra de Monitor..."></div>
            <div class="form-group"><label>Valor Total (R$) *</label><input type="number" step="0.01" name="valor" class="form-control" required placeholder="0.00"></div>
        </div>

        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
            <div class="form-group"><label id="label_data">Data (Vencimento/Compra) *</label><input type="date" name="data_vencimento" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group">
                <label>Categoria *</label>
                <select name="categoria_id" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach($categorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo *</label>
                <select name="tipo" class="form-control" required>
                    <option value="empresa">Empresa (Agência)</option>
                    <option value="pessoal">Pessoal (Sócio)</option>
                </select>
            </div>
        </div>

        <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label>Forma de Pagamento *</label>
                <select name="forma_pagamento" id="forma_pagamento" class="form-control" onchange="toggleCampos()" required>
                    <option value="pix">PIX</option>
                    <option value="boleto">Boleto Bancário</option>
                    <option value="cartao">Cartão de Crédito</option>
                    <option value="debito">Débito Automático</option>
                    <option value="dinheiro">Dinheiro Físico</option>
                </select>
            </div>
            <div class="form-group" id="bloco_status">
                <label>Status do Pagamento</label>
                <select name="status" class="form-control">
                    <option value="pendente">Pendente (A Pagar)</option>
                    <option value="pago">Já Pago (Dar baixa agora)</option>
                </select>
            </div>
        </div>

        <!-- Campos de Cartão -->
        <div id="bloco_cartao" style="display: none; background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.2); padding: 16px; border-radius: var(--r-md); margin-bottom: 16px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group mb-0"><label>Selecione o Cartão *</label><select name="cartao_id" id="cartao_id" class="form-control"><option value="">Selecione...</option><?php foreach($cartoes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?> (<?= htmlspecialchars($c['bandeira']) ?>)</option><?php endforeach; ?></select></div>
                <div class="form-group mb-0"><label>Parcelamento</label><select name="parcelas" id="parcelas" class="form-control"><option value="1">À vista (1x)</option><?php for($i=2;$i<=24;$i++): ?><option value="<?= $i ?>"><?= $i ?>x</option><?php endfor; ?></select></div>
            </div>
            <div style="font-size: 11px; color: var(--blue); margin-top: 10px; display: flex; align-items: center; gap: 5px;"><i class="ph-fill ph-info"></i> O sistema irá dividir o valor total e lançar automaticamente nas faturas correspondentes.</div>
        </div>

        <div class="form-group" id="bloco_codigo"><label>Código de Barras ou Chave PIX</label><input type="text" name="codigo_pagamento" class="form-control"></div>
        <div class="form-group mb-0"><label>Observação (Opcional)</label><textarea name="observacao" class="form-control" style="min-height: 60px;"></textarea></div>
        
        <div style="text-align: right; margin-top: 24px; border-top: 1px solid var(--border); padding-top: 20px;">
            <button type="submit" class="btn btn-primary btn-h44"><i class="ph ph-check"></i> Salvar Lançamento</button>
        </div>
    </div>
</form>

<script>
function toggleCampos() {
    const f = document.getElementById('forma_pagamento').value;
    document.getElementById('bloco_cartao').style.display = (f === 'cartao') ? 'block' : 'none';
    document.getElementById('bloco_status').style.display = (f === 'cartao') ? 'none' : 'block';
    document.getElementById('bloco_codigo').style.display = (f === 'cartao' || f === 'dinheiro' || f === 'debito') ? 'none' : 'block';
    document.getElementById('cartao_id').required = (f === 'cartao');
    document.getElementById('label_data').innerText = (f === 'cartao') ? 'Data da Compra *' : 'Data de Vencimento *';
}
document.addEventListener('DOMContentLoaded', toggleCampos);
</script>

<?php require_once '../../includes/layout/footer.php'; ?>
