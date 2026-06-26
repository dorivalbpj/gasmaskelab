<?php
// modules/financeiro/fatura.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = (int)($_GET['id'] ?? 0);
$cartao_id = (int)($_GET['cartao_id'] ?? 0);
$mes = (int)($_GET['mes'] ?? 0);
$ano = (int)($_GET['ano'] ?? 0);

// Se recebeu um ID de cartão mas não de fatura, busca a fatura mais recente deste cartão
if (!$id && $cartao_id) {
    if ($mes && $ano) {
        // Busca a fatura do mês/ano específico
        $stmt_busca = $pdo->prepare("SELECT id FROM fin_faturas WHERE cartao_id = ? AND mes = ? AND ano = ? LIMIT 1");
        $stmt_busca->execute([$cartao_id, $mes, $ano]);
        $fatura_encontrada = $stmt_busca->fetchColumn();
    } else {
        // Ordena priorizando faturas abertas e depois as mais recentes
        $stmt_busca = $pdo->prepare("SELECT id FROM fin_faturas WHERE cartao_id = ? ORDER BY status = 'aberta' DESC, ano DESC, mes DESC LIMIT 1");
        $stmt_busca->execute([$cartao_id]);
        $fatura_encontrada = $stmt_busca->fetchColumn();
    }
    
    if ($fatura_encontrada) {
        header("Location: fatura.php?id=" . $fatura_encontrada);
        exit;
    } else {
        // Se a fatura não existe (cartão novo ou mês que não possui faturas), cria uma nova em branco
        $mes_novo = $mes ?: (int)date('n');
        $ano_novo = $ano ?: (int)date('Y');
        
        $stmt_c = $pdo->prepare("SELECT dia_vencimento FROM fin_cartoes WHERE id = ?");
        $stmt_c->execute([$cartao_id]);
        $cartao = $stmt_c->fetch();
        
        if ($cartao) {
            $data_base = sprintf('%04d-%02d-01', $ano_novo, $mes_novo);
            $max_dias = date('t', strtotime($data_base));
            $dia_venc_real = min((int)$cartao['dia_vencimento'], $max_dias);
            $data_vencimento = sprintf('%04d-%02d-%02d', $ano_novo, $mes_novo, $dia_venc_real);
            
            $pdo->prepare("INSERT INTO fin_faturas (cartao_id, mes, ano, data_vencimento, status) VALUES (?, ?, ?, ?, 'aberta')")
                ->execute([$cartao_id, $mes_novo, $ano_novo, $data_vencimento]);
            
            $novo_id = $pdo->lastInsertId();
            header("Location: fatura.php?id=" . $novo_id);
            exit;
        }
    }
}

if (!$id) {
    // Se não encontrou nem ID e nem conseguiu achar fatura pro cartão, vai pra saídas
    header("Location: saidas.php");
    exit;
}

$mensagem = '';

// --- LÓGICA DE PAGAMENTO "TUDO OU NADA" ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'pagar_fatura') {
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
    } elseif ($_POST['acao'] == 'editar_lancamento') {
        try {
            $pdo->prepare("UPDATE fin_lancamentos SET descricao = ?, categoria_id = ?, tipo = ? WHERE id = ? AND fatura_id = ?")
                ->execute([$_POST['descricao'], $_POST['categoria_id'] ?: null, $_POST['tipo'], $_POST['lancamento_id'], $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Lançamento atualizado.</div>";
        } catch (Exception $e) {
            $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
        }
    } elseif ($_POST['acao'] == 'excluir_lancamento') {
        try {
            $pdo->prepare("DELETE FROM fin_lancamentos WHERE id = ? AND fatura_id = ?")->execute([$_POST['lancamento_id'], $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Lançamento removido.</div>";
        } catch (Exception $e) {
            $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
        }
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

// Busca categorias
$stmt_cat = $pdo->prepare("SELECT id, nome FROM fin_categorias ORDER BY nome ASC");
$stmt_cat->execute();
$categorias = $stmt_cat->fetchAll();

// Calcula o total real da fatura
$total_fatura = 0;
foreach ($lancamentos as $l) {
    $total_fatura += (float)$l['valor'];
}

// Tradução do mês
$meses_pt = ['01'=>'Janeiro', '02'=>'Fevereiro', '03'=>'Março', '04'=>'Abril', '05'=>'Maio', '06'=>'Junho', '07'=>'Julho', '08'=>'Agosto', '09'=>'Setembro', '10'=>'Outubro', '11'=>'Novembro', '12'=>'Dezembro'];
$nome_mes = $meses_pt[str_pad($fatura['mes'], 2, '0', STR_PAD_LEFT)];

// Navegação (Anterior / Próxima)
$prev_mes = $fatura['mes'] - 1; $prev_ano = $fatura['ano'];
if ($prev_mes < 1) { $prev_mes = 12; $prev_ano--; }
$next_mes = $fatura['mes'] + 1; $next_ano = $fatura['ano'];
if ($next_mes > 12) { $next_mes = 1; $next_ano++; }

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title" style="display: flex; align-items: center; gap: 10px;">
            <a href="fatura.php?cartao_id=<?= $fatura['cartao_id'] ?>&mes=<?= $prev_mes ?>&ano=<?= $prev_ano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Anterior"><i class="ph ph-caret-left"></i></a>
            Fatura <?= $nome_mes ?>/<?= $fatura['ano'] ?>
            <a href="fatura.php?cartao_id=<?= $fatura['cartao_id'] ?>&mes=<?= $next_mes ?>&ano=<?= $next_ano ?>" class="btn btn-ghost btn-icon text-muted" title="Mês Seguinte"><i class="ph ph-caret-right"></i></a>
        </h2>
        <p class="page-subtitle"><?= htmlspecialchars($fatura['cartao_nome']) ?> (<?= htmlspecialchars($fatura['bandeira']) ?>)</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="border-color: var(--purple); color: var(--purple);" onclick="abrirModalUploadPDF()" title="Análise com IA do Gemini">
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
                            <th style="text-align: center; width: 80px;">Ações</th>
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
                            <td style="text-align: center;">
                                <?php if($fatura['status'] != 'paga'): ?>
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <button type="button" class="btn-acao" onclick="abrirModalEditar(<?= $l['id'] ?>, '<?= addslashes(htmlspecialchars($l['descricao'])) ?>', '<?= $l['categoria_id'] ?>', '<?= $l['tipo'] ?>')" title="Editar">
                                        <i class="ph ph-pencil-simple"></i>
                                    </button>
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este lançamento da fatura?');">
                                        <input type="hidden" name="acao" value="excluir_lancamento">
                                        <input type="hidden" name="lancamento_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn-acao text-red" title="Excluir"><i class="ph ph-trash"></i></button>
                                    </form>
                                </div>
                                <?php endif; ?>
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

<!-- ============ MODAL DE UPLOAD PDF ============ -->
<div id="modalUploadPDF" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 500px; margin: 20px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Importar Fatura em PDF</h3>
            <button type="button" onclick="fecharModalUploadPDF()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: var(--text-3);">✕</button>
        </div>
        
        <div style="padding: 20px;">
            <div class="form-group">
                <label><i class="ph ph-file-pdf"></i> Selecione o arquivo PDF da fatura</label>
                <input type="file" id="inputPDF" class="form-control" accept=".pdf" required>
                <small style="color: var(--text-3); display: block; margin-top: 5px;">
                    Máximo: 10MB | Formato: PDF
                </small>
            </div>
            
            <button type="button" class="btn btn-primary w-100" id="btnAnalisarIA" style="justify-content: center; height: 44px;">
                <i class="ph ph-sparkles"></i> Analisar com IA (Gemini)
            </button>
        </div>
        
        <!-- Indicador de Carregamento -->
        <div id="indicadorCarregamento" style="display: none; padding: 20px; text-align: center;">
            <div style="display: inline-block; width: 30px; height: 30px; border: 3px solid rgba(59,130,246,0.2); border-top-color: var(--blue); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <p style="margin-top: 10px; color: var(--text-2);">Analisando PDF com Gemini...</p>
        </div>
    </div>
</div>

<!-- ============ TELA DE REVISÃO DINÂMICA ============ -->
<div id="telaRevisao" style="display: none; margin-top: 30px;">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 class="card-title">Revisão de Lançamentos</h3>
                <small style="color: var(--text-3);">Edite, valide e selecione os gastos para importar</small>
            </div>
            <button type="button" onclick="fecharTelaRevisao()" class="btn btn-secondary" style="font-size: 12px;">
                <i class="ph ph-x"></i> Cancelar
            </button>
        </div>
        
        <div style="overflow-x: auto;">
            <table id="tabelaRevisao" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">
                            <input type="checkbox" id="checkboxTodos" onchange="toggleTodosCheked(this)">
                        </th>
                        <th style="width: 100px;">Data</th>
                        <th style="width: 30%;">Descrição</th>
                        <th style="width: 20%;">Categoria</th>
                        <th style="width: 15%;">Tipo</th>
                        <th style="width: 100px; text-align: right;">Valor</th>
                        <th style="width: 80px; text-align: center;">Ação</th>
                    </tr>
                </thead>
                <tbody id="corpoTabelaRevisao">
                    <!-- Preenchido dinamicamente por JS -->
                </tbody>
            </table>
        </div>
        
        <div style="padding: 20px; border-top: 1px solid var(--border-mid); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Total de <span id="contadorSelecionados">0</span> itens selecionados:</strong>
                <span style="font-size: 20px; font-weight: 700; color: var(--blue); margin-left: 10px;">
                    <span id="valorTotalSelecionados">R$ 0,00</span>
                </span>
            </div>
            <button type="button" class="btn btn-primary" onclick="salvarLancamentosSelecionados()" id="btnSalvarLancamentos">
                <i class="ph ph-check-circle"></i> Salvar Lançamentos
            </button>
        </div>
    </div>
</div>

<!-- ============ MODAL EDITAR LANÇAMENTO ============ -->
<div id="modalEditarLancamento" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 400px; margin: 20px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 class="card-title">Editar Lançamento</h3>
            <button type="button" onclick="fecharModalEditar()" style="background: none; border: none; cursor: pointer; font-size: 20px; color: var(--text-3);">✕</button>
        </div>
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="acao" value="editar_lancamento">
            <input type="hidden" name="lancamento_id" id="edit_lancamento_id">
            
            <div class="form-group">
                <label>Descrição</label>
                <input type="text" name="descricao" id="edit_descricao" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Categoria</label>
                <select name="categoria_id" id="edit_categoria" class="form-control">
                    <option value="">Sem categoria</option>
                    <?php foreach($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select name="tipo" id="edit_tipo" class="form-control" required>
                    <option value="pessoal">Pessoal</option>
                    <option value="empresa">Empresa</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100" style="justify-content: center;">Salvar Alterações</button>
        </form>
    </div>
</div>

<!-- ============ ESTILOS PARA ANIMAÇÃO ============ -->
<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}

.linha-duplicidade {
    background-color: rgba(251, 191, 36, 0.1) !important;
}

.linha-duplicidade td {
    border-color: rgba(251, 191, 36, 0.3);
}

.aviso-duplicidade {
    font-size: 11px;
    color: #d97706;
    background: rgba(217, 119, 6, 0.1);
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
    display: inline-block;
}

.input-revisao {
    background: var(--input-bg);
    border: 1px solid var(--border-light);
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 13px;
    color: var(--text);
    width: 100%;
}

.input-revisao:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.select-revisao {
    background: var(--input-bg);
    border: 1px solid var(--border-light);
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 13px;
    color: var(--text);
    width: 100%;
    cursor: pointer;
}

.select-revisao:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>

<!-- ============ JAVASCRIPT PARA FUNCIONALIDADE ============ -->
<script>
const FATURA_ID = <?= (int)$id ?>;
const CATEGORIAS_DISPONIVEIS = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'nome' => $c['nome']], $categorias ?? [])) ?>;

/**
 * FUNÇÕES DO MODAL DE UPLOAD
 */
function abrirModalUploadPDF() {
    document.getElementById('modalUploadPDF').style.display = 'flex';
}

function fecharModalUploadPDF() {
    document.getElementById('modalUploadPDF').style.display = 'none';
    document.getElementById('inputPDF').value = '';
    document.getElementById('indicadorCarregamento').style.display = 'none';
}

document.getElementById('btnAnalisarIA').addEventListener('click', async () => {
    const inputPDF = document.getElementById('inputPDF');
    const arquivo = inputPDF.files[0];
    
    // Validações
    if (!arquivo) {
        alert('Selecione um arquivo PDF');
        return;
    }
    
    if (arquivo.type !== 'application/pdf') {
        alert('O arquivo deve ser um PDF válido');
        return;
    }
    
    if (arquivo.size > 10 * 1024 * 1024) {
        alert('Arquivo muito grande (máximo 10MB)');
        return;
    }
    
    // Mostra indicador de carregamento
    document.getElementById('indicadorCarregamento').style.display = 'block';
    document.getElementById('btnAnalisarIA').disabled = true;
    
    try {
        // Monta FormData
        const formData = new FormData();
        formData.append('pdf', arquivo);
        formData.append('fatura_id', FATURA_ID);
        
        // Envia para o backend
        const resposta = await fetch('ler_fatura_ia.php', {
            method: 'POST',
            body: formData
        });
        
        const dados = await resposta.json();
        
        if (!resposta.ok || !dados.sucesso) {
            throw new Error(dados.erro || 'Erro ao processar PDF');
        }
        
        // Sucesso! Exibe tela de revisão
        fecharModalUploadPDF();
        exibirTelaRevisao(dados);
        
    } catch (erro) {
        console.error('Erro:', erro);
        alert('Erro ao analisar PDF: ' + erro.message);
    } finally {
        document.getElementById('indicadorCarregamento').style.display = 'none';
        document.getElementById('btnAnalisarIA').disabled = false;
    }
});

/**
 * FUNÇÕES DA TELA DE REVISÃO
 */
let lancamentosRevisao = [];

function exibirTelaRevisao(dados) {
    lancamentosRevisao = dados.lancamentos || [];
    
    // Busca lançamentos já existentes para validar duplicidades
    buscarLancamentosExistentes().then(lancamentosExistentes => {
        // Valida duplicidades
        lancamentosRevisao = lancamentosRevisao.map(item => {
            const duplicado = lancamentosExistentes.some(exist => 
                exist.descricao.trim() === item.descricao.trim() &&
                Math.abs(parseFloat(exist.valor) - parseFloat(item.valor)) < 0.01
            );
            return {
                ...item,
                tipo: 'pessoal',
                parcela_atual: item.parcela_atual || 1,
                total_parcelas: item.total_parcelas || 1,
                selecionado: !duplicado,
                duplicado: duplicado
            };
        });
        
        renderizarTabelaRevisao();
        document.getElementById('telaRevisao').style.display = 'block';
        atualizarContadores();
    });
}

function fecharTelaRevisao() {
    document.getElementById('telaRevisao').style.display = 'none';
    lancamentosRevisao = [];
}

function renderizarTabelaRevisao() {
    const corpo = document.getElementById('corpoTabelaRevisao');
    corpo.innerHTML = '';
    
    lancamentosRevisao.forEach((item, idx) => {
        const tr = document.createElement('tr');
        
        if (item.duplicado) {
            tr.className = 'linha-duplicidade';
        }
        
        const dataFormatada = new Date(item.data_compra).toLocaleDateString('pt-BR');
        const categoriaAtual = CATEGORIAS_DISPONIVEIS.find(c => c.id == item.categoria_id);
        
        tr.innerHTML = `
            <td style="text-align: center;">
                <input type="checkbox" class="checkbox-linha" ${item.selecionado ? 'checked' : ''} 
                       data-idx="${idx}" onchange="atualizarContadores()">
            </td>
            <td>
                <input type="date" class="input-revisao" value="${item.data_compra}" 
                       data-idx="${idx}" onchange="atualizarItem(${idx}, 'data_compra', this.value)">
            </td>
            <td>
                <div>
                    <input type="text" class="input-revisao" value="${escapeHtml(item.descricao)}" 
                           data-idx="${idx}" onchange="atualizarItem(${idx}, 'descricao', this.value)">
                    ${item.total_parcelas > 1 ? `<div style="font-size:10px; color:var(--blue); margin-top:2px;">Parcela ${item.parcela_atual}/${item.total_parcelas}</div>` : ''}
                    ${item.duplicado ? '<div class="aviso-duplicidade">⚠️ Possível Duplicidade</div>' : ''}
                </div>
            </td>
            <td>
                <select class="select-revisao" data-idx="${idx}" onchange="atualizarItem(${idx}, 'categoria_id', this.value)">
                    <option value="">Sem categoria</option>
                    ${CATEGORIAS_DISPONIVEIS.map(cat => 
                        `<option value="${cat.id}" ${cat.id == item.categoria_id ? 'selected' : ''}>
                            ${escapeHtml(cat.nome)}
                        </option>`
                    ).join('')}
                </select>
            </td>
            <td>
                <select class="select-revisao" data-idx="${idx}" onchange="atualizarItem(${idx}, 'tipo', this.value)">
                    <option value="pessoal" ${item.tipo === 'pessoal' ? 'selected' : ''}>Pessoal</option>
                    <option value="empresa" ${item.tipo === 'empresa' ? 'selected' : ''}>Empresa</option>
                </select>
            </td>
            <td style="text-align: right;">
                <input type="number" class="input-revisao" step="0.01" value="${item.valor}" 
                       data-idx="${idx}" onchange="atualizarItem(${idx}, 'valor', parseFloat(this.value))"
                       style="text-align: right;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-acao" onclick="removerLinha(${idx})" title="Remover">
                    <i class="ph ph-trash"></i>
                </button>
            </td>
        `;
        
        corpo.appendChild(tr);
    });
}

function atualizarItem(idx, campo, valor) {
    if (campo === 'valor') {
        lancamentosRevisao[idx][campo] = Math.max(0, parseFloat(valor) || 0);
    } else if (campo === 'categoria_id') {
        lancamentosRevisao[idx][campo] = valor === '' ? null : parseInt(valor);
    } else {
        lancamentosRevisao[idx][campo] = valor;
    }
    atualizarContadores();
}

function removerLinha(idx) {
    lancamentosRevisao.splice(idx, 1);
    renderizarTabelaRevisao();
    atualizarContadores();
}

function toggleTodosCheked(checkbox) {
    document.querySelectorAll('.checkbox-linha').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    atualizarContadores();
}

function atualizarContadores() {
    const selecionados = document.querySelectorAll('.checkbox-linha:checked').length;
    let totalValor = 0;
    
    document.querySelectorAll('.checkbox-linha:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        totalValor += parseFloat(lancamentosRevisao[idx].valor) || 0;
    });
    
    document.getElementById('contadorSelecionados').textContent = selecionados;
    document.getElementById('valorTotalSelecionados').textContent = formatarMoeda(totalValor);
    document.getElementById('btnSalvarLancamentos').disabled = selecionados === 0;
}

function formatarMoeda(valor) {
    return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

/**
 * BUSCA LANÇAMENTOS EXISTENTES PARA VALIDAR DUPLICIDADES
 */
async function buscarLancamentosExistentes() {
    try {
        const resposta = await fetch(`obter_lancamentos_fatura.php?fatura_id=${FATURA_ID}`);
        if (!resposta.ok) return [];
        const dados = await resposta.json();
        return dados.lancamentos || [];
    } catch (erro) {
        console.warn('Erro ao buscar lançamentos existentes:', erro);
        return [];
    }
}

/**
 * SALVA OS LANÇAMENTOS SELECIONADOS
 */
async function salvarLancamentosSelecionados() {
    const selecionados = [];
    
    document.querySelectorAll('.checkbox-linha:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        selecionados.push(lancamentosRevisao[idx]);
    });
    
    if (selecionados.length === 0) {
        alert('Selecione ao menos um lançamento');
        return;
    }
    
    document.getElementById('btnSalvarLancamentos').disabled = true;
    
    try {
        const resposta = await fetch('salvar_lancamentos_fatura.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                fatura_id: FATURA_ID,
                lancamentos: selecionados
            })
        });
        
        const dados = await resposta.json();
        
        if (!resposta.ok || !dados.sucesso) {
            throw new Error(dados.erro || 'Erro ao salvar lançamentos');
        }
        
        alert(`✓ ${dados.total_salvo} lançamento(s) importado(s) com sucesso!`);
        fecharTelaRevisao();
        location.reload(); // Recarrega para exibir os novos lançamentos
        
    } catch (erro) {
        console.error('Erro:', erro);
        alert('Erro ao salvar lançamentos: ' + erro.message);
    } finally {
        document.getElementById('btnSalvarLancamentos').disabled = false;
    }
}

function abrirModalEditar(id, descricao, categoria_id, tipo) {
    document.getElementById('edit_lancamento_id').value = id;
    document.getElementById('edit_descricao').value = descricao;
    document.getElementById('edit_categoria').value = categoria_id;
    document.getElementById('edit_tipo').value = tipo;
    document.getElementById('modalEditarLancamento').style.display = 'flex';
}
function fecharModalEditar() {
    document.getElementById('modalEditarLancamento').style.display = 'none';
}
</script>


<?php require_once '../../includes/layout/footer.php'; ?>
