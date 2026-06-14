<?php
// modules/financeiro/recorrentes.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

// --- LÓGICA DE CRUD E GERAÇÃO (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao == 'salvar_recorrente') {
            $id = $_POST['recorrente_id'] ?? '';
            $descricao = $_POST['descricao'] ?? '';
            $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
            $dia_vencimento = (int)($_POST['dia_vencimento'] ?? 1);
            $categoria_id = !empty($_POST['categoria_id']) ? $_POST['categoria_id'] : null;
            $tipo = $_POST['tipo'] ?? 'pessoal';
            $forma_pagamento = $_POST['forma_pagamento'] ?? 'boleto';
            $codigo_pagamento = $_POST['codigo_pagamento'] ?? null;
            
            if ($id) {
                $pdo->prepare("UPDATE fin_recorrentes SET descricao=?, valor=?, dia_vencimento=?, categoria_id=?, tipo=?, forma_pagamento=?, codigo_pagamento=? WHERE id=?")
                    ->execute([$descricao, $valor, $dia_vencimento, $categoria_id, $tipo, $forma_pagamento, $codigo_pagamento, $id]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Conta recorrente atualizada!</div>";
            } else {
                $pdo->prepare("INSERT INTO fin_recorrentes (descricao, valor, dia_vencimento, categoria_id, tipo, forma_pagamento, codigo_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$descricao, $valor, $dia_vencimento, $categoria_id, $tipo, $forma_pagamento, $codigo_pagamento]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Nova conta recorrente salva com sucesso!</div>";
            }
        } elseif ($acao == 'excluir_recorrente') {
            $id = $_POST['recorrente_id'] ?? '';
            // Soft Delete
            $pdo->prepare("UPDATE fin_recorrentes SET ativo = 0 WHERE id=?")->execute([$id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Template excluído com sucesso. Lançamentos passados não foram afetados.</div>";
            
        } elseif ($acao == 'gerar_mes') {
            $mes = (int)($_POST['mes_gerar'] ?? date('m'));
            $ano = (int)($_POST['ano_gerar'] ?? date('Y'));
            
            // 1. Busca todos os templates ativos
            $stmt_ativos = $pdo->query("SELECT * FROM fin_recorrentes WHERE ativo = 1");
            $templates = $stmt_ativos->fetchAll();
            
            $qtd_gerados = 0;
            $qtd_ignorados = 0;
            
            $stmt_check = $pdo->prepare("SELECT id FROM fin_lancamentos WHERE recorrente_id = ? AND mes_referencia = ? AND ano_referencia = ?");
            $stmt_insert = $pdo->prepare("INSERT INTO fin_lancamentos (descricao, valor, data_vencimento, categoria_id, tipo, forma_pagamento, codigo_pagamento, recorrente_id, mes_referencia, ano_referencia, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
            
            foreach ($templates as $r) {
                // Verifica se já existe
                $stmt_check->execute([$r['id'], $mes, $ano]);
                if ($stmt_check->fetch()) {
                    $qtd_ignorados++;
                    continue; // Pula pra próxima se já gerou
                }
                
                // Calcula data exata de vencimento resolvendo dias 31 em meses curtos
                $max_dias = date('t', strtotime(sprintf('%04d-%02d-01', $ano, $mes)));
                $dia_real = min((int)$r['dia_vencimento'], $max_dias);
                $data_vencimento = sprintf('%04d-%02d-%02d', $ano, $mes, $dia_real);
                
                $stmt_insert->execute([
                    $r['descricao'], 
                    $r['valor'], 
                    $data_vencimento, 
                    $r['categoria_id'], 
                    $r['tipo'], 
                    $r['forma_pagamento'], 
                    $r['codigo_pagamento'], 
                    $r['id'], 
                    $mes, 
                    $ano
                ]);
                $qtd_gerados++;
            }
            
            $msg_text = "<strong>Lote gerado para $mes/$ano:</strong> $qtd_gerados novas contas inseridas.";
            if ($qtd_ignorados > 0) $msg_text .= " ($qtd_ignorados contas ignoradas pois já existiam).";
            $mensagem = "<div class='alert alert-info'><i class='ph-fill ph-info'></i> $msg_text</div>";
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// --- CONSULTAS PARA A TELA ---
$categorias = $pdo->query("SELECT id, nome FROM fin_categorias ORDER BY nome ASC")->fetchAll();

$stmt = $pdo->query("
    SELECT r.*, c.nome as categoria_nome, c.cor as categoria_cor 
    FROM fin_recorrentes r 
    LEFT JOIN fin_categorias c ON r.categoria_id = c.id 
    WHERE r.ativo = 1 
    ORDER BY r.dia_vencimento ASC, r.descricao ASC
");
$recorrentes = $stmt->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Contas Recorrentes</h2>
        <p class="page-subtitle">Templates de contas fixas (energia, sistemas, pró-labore, etc).</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-secondary" style="border-color: var(--blue); color: var(--blue);" onclick="abrirModalGerar()"><i class="ph ph-calendar-plus"></i> Gerar Mês</button>
        <button type="button" class="btn btn-primary" onclick="abrirModal()"><i class="ph ph-plus"></i> Novo Template</button>
    </div>
</div>

<?= $mensagem ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Templates Ativos</h3>
        <span class="badge badge-gray"><?= count($recorrentes) ?> Registros</span>
    </div>
    
    <?php if (count($recorrentes) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">Dia Venc.</th>
                        <th>Descrição / Categoria</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Forma Pgto</th>
                        <th style="text-align: right;">Valor Médio</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recorrentes as $r): ?>
                    <tr>
                        <td style="text-align: center;">
                            <strong style="font-size: 16px; color: var(--text-primary);"><?= str_pad($r['dia_vencimento'], 2, '0', STR_PAD_LEFT) ?></strong>
                        </td>
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($r['descricao']) ?></span>
                            <span class="txt-meta-sm">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $r['categoria_cor'] ?? '#999' ?>; margin-right: 4px;"></span>
                                <?= htmlspecialchars($r['categoria_nome'] ?? 'Sem categoria') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $r['tipo'] == 'empresa' ? 'badge-blue' : 'badge-gray' ?>"><?= strtoupper($r['tipo']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="tag-tipo"><?= strtoupper($r['forma_pagamento']) ?></span>
                        </td>
                        <td style="text-align: right; font-weight: 700; font-size: 14px; color: var(--text-primary);">
                            <?= money($r['valor']) ?>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <?php
                                    // Prepara dados pro JS
                                    $js_dados = htmlspecialchars(json_encode([
                                        'id' => $r['id'],
                                        'descricao' => $r['descricao'],
                                        'valor' => (float)$r['valor'],
                                        'dia_vencimento' => $r['dia_vencimento'],
                                        'categoria_id' => $r['categoria_id'],
                                        'tipo' => $r['tipo'],
                                        'forma_pagamento' => $r['forma_pagamento'],
                                        'codigo_pagamento' => $r['codigo_pagamento']
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button type="button" class="btn btn-ghost btn--sm btn-icon-table" onclick="editarModal(<?= $js_dados ?>)" title="Editar">
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Deseja excluir (inativar) este template? As contas já geradas no passado não serão alteradas.');">
                                    <input type="hidden" name="acao" value="excluir_recorrente">
                                    <input type="hidden" name="recorrente_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn--sm btn-icon-table text-red" title="Excluir">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="ph ph-repeat empty-state-icon"></i>
            Nenhum template recorrente configurado.<br>Crie templates para facilitar o lançamento mensal das suas contas fixas.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Novo/Editar Recorrente -->
<div class="modal-overlay" id="modalRecorrente">
    <div class="modal-box" style="max-width: 600px;">
        <button type="button" class="modal-close-btn" onclick="fecharModal()"><i class="ph ph-x"></i></button>
        <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 20px;">Nova Conta Recorrente</h3>
        
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_recorrente">
            <input type="hidden" name="recorrente_id" id="mod_id">
            
            <div class="form-group"><label>Descrição da Conta *</label><input type="text" name="descricao" id="mod_descricao" class="form-control" required></div>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group"><label>Valor Médio (R$) *</label><input type="number" step="0.01" name="valor" id="mod_valor" class="form-control" required></div>
                <div class="form-group"><label>Dia do Vencimento *</label><input type="number" name="dia_vencimento" id="mod_dia" class="form-control" min="1" max="31" required></div>
            </div>
            
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label>Categoria *</label>
                    <select name="categoria_id" id="mod_categoria" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach($categorias as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" id="mod_tipo" class="form-control" required>
                        <option value="pessoal">Pessoal (Sócio)</option>
                        <option value="empresa">Empresa (Agência)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Forma de Pagamento Padrão</label>
                <select name="forma_pagamento" id="mod_forma" class="form-control">
                    <option value="boleto">Boleto Bancário</option><option value="pix">PIX</option>
                    <option value="debito">Débito Automático</option><option value="dinheiro">Dinheiro Físico</option>
                </select>
            </div>
            
            <div class="form-group"><label>Chave PIX ou Cód. Boleto Padrão (Opcional)</label><textarea name="codigo_pagamento" id="mod_codigo" class="form-control" style="min-height: 60px;"></textarea></div>
            
            <div style="text-align: right; margin-top: 20px;"><button type="submit" class="btn btn-primary">Salvar Template</button></div>
        </form>
    </div>
</div>

<!-- Modal Gerar Mês -->
<div class="modal-overlay" id="modalGerar">
    <div class="modal-box" style="max-width: 400px; text-align: center;">
        <button type="button" class="modal-close-btn" onclick="fecharModalGerar()"><i class="ph ph-x"></i></button>
        <h3 style="margin-top: 0; margin-bottom: 10px;"><i class="ph-fill ph-calendar-plus" style="color: var(--blue); font-size: 32px; display: block; margin-bottom: 10px;"></i> Gerar Lote do Mês</h3>
        <p style="color: var(--text-secondary); font-size: 13px; margin-bottom: 20px;">Isso irá copiar todos os templates ativos e criar contas avulsas pendentes para o mês e ano escolhidos. O sistema ignora duplicatas automaticamente.</p>
        
        <form method="POST">
            <input type="hidden" name="acao" value="gerar_mes">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; text-align: left;">
                <div class="form-group mb-0"><label>Mês</label><select name="mes_gerar" class="form-control"><?php for($m=1;$m<=12;$m++): $sel = ($m==date('n'))?'selected':''; echo "<option value='$m' $sel>".str_pad($m,2,'0',STR_PAD_LEFT)."</option>"; endfor; ?></select></div>
                <div class="form-group mb-0"><label>Ano</label><input type="number" name="ano_gerar" class="form-control" value="<?= date('Y') ?>" min="2020" max="2100"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Executar Geração</button>
        </form>
    </div>
</div>

<script>
function abrirModal() { document.getElementById('mod_id').value = ''; document.getElementById('mod_descricao').value = ''; document.getElementById('mod_valor').value = ''; document.getElementById('mod_dia').value = ''; document.getElementById('mod_categoria').value = ''; document.getElementById('mod_tipo').value = 'pessoal'; document.getElementById('mod_forma').value = 'boleto'; document.getElementById('mod_codigo').value = ''; document.getElementById('modalTitle').innerText = 'Nova Conta Recorrente'; document.getElementById('modalRecorrente').classList.add('active'); }
function editarModal(obj) { document.getElementById('mod_id').value = obj.id; document.getElementById('mod_descricao').value = obj.descricao; document.getElementById('mod_valor').value = obj.valor; document.getElementById('mod_dia').value = obj.dia_vencimento; document.getElementById('mod_categoria').value = obj.categoria_id; document.getElementById('mod_tipo').value = obj.tipo; document.getElementById('mod_forma').value = obj.forma_pagamento; document.getElementById('mod_codigo').value = obj.codigo_pagamento || ''; document.getElementById('modalTitle').innerText = 'Editar Template'; document.getElementById('modalRecorrente').classList.add('active'); }
function fecharModal() { document.getElementById('modalRecorrente').classList.remove('active'); }
function abrirModalGerar() { document.getElementById('modalGerar').classList.add('active'); }
function fecharModalGerar() { document.getElementById('modalGerar').classList.remove('active'); }
</script>

<?php require_once '../../includes/layout/footer.php'; ?>
