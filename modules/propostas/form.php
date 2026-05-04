<?php
// modules/propostas/form.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;
$cliente_get = $_GET['cliente_id'] ?? ''; 

$proposta = [
    'cliente_id' => $cliente_get, 
    'titulo' => '', 'descricao' => '', 'servicos_inclusos' => '',
    'valor' => 0, 'tipo_cobranca' => 'mensal', 'duracao_meses' => 3, 
    'data_validade' => date('Y-m-d', strtotime('+7 days')), 'codigo_proposta' => ''
];
$mensagem = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if ($res) {
        $proposta = array_merge($proposta, $res);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $titulo = $_POST['titulo'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
    $tipo_cobranca = $_POST['tipo_cobranca'] ?? 'mensal';
    $duracao_meses = $_POST['duracao_meses'] ?? 1;
    $data_validade = $_POST['data_validade'] ?? '';
    $servicos_inclusos = isset($_POST['servicos']) ? implode(', ', $_POST['servicos']) : '';

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE propostas SET cliente_id=?, titulo=?, descricao=?, servicos_inclusos=?, valor=?, tipo_cobranca=?, duracao_meses=?, data_validade=? WHERE id=?");
            $stmt->execute([$cliente_id, $titulo, $descricao, $servicos_inclusos, $valor, $tipo_cobranca, $duracao_meses, $data_validade, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Proposta atualizada!</div>";
        } else {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO propostas (cliente_id, titulo, descricao, servicos_inclusos, valor, tipo_cobranca, duracao_meses, data_validade, token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho')");
            $stmt->execute([$cliente_id, $titulo, $descricao, $servicos_inclusos, $valor, $tipo_cobranca, $duracao_meses, $data_validade, $token]);
            $id = $pdo->lastInsertId();
            $codigo_proposta = "PRP-" . str_pad($id, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE propostas SET codigo_proposta = ? WHERE id = ?")->execute([$codigo_proposta, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Proposta criada!</div>";
        }
        
        $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if ($res) $proposta = array_merge($proposta, $res);
        
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
$todos_servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
$servicos_marcados = empty($proposta['servicos_inclusos']) ? [] : array_map('trim', explode(',', $proposta['servicos_inclusos']));

// MÁGICA: Buscar todos os pacotes da base de dados e organizá-os para o Javascript
$todos_pacotes_db = $pdo->query("SELECT * FROM servico_pacotes ORDER BY tipo DESC, valor ASC")->fetchAll();
$pacotes_js = [];
foreach ($todos_pacotes_db as $pacote) {
    $pacotes_js[$pacote['servico_id']][] = [
        'id' => $pacote['id'],
        'nome' => $pacote['nome'],
        'tipo' => $pacote['tipo'],
        'valor' => (float) $pacote['valor'],
        'descricao' => $pacote['descricao']
    ];
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Editar Proposta (' . htmlspecialchars($proposta['codigo_proposta']) . ')' : 'Nova Proposta' ?></h2>
        <p class="page-subtitle">Configure o escopo, valores e prazos do projeto.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar</a>
        <?php if ($id): ?>
            <a href="../contratos/form.php?proposta_id=<?= $id ?>" class="btn btn-primary" style="background: var(--purple); border-color: var(--purple);">
                <i class="ph ph-scroll"></i> Gerar Contrato
            </a>
        <?php endif; ?>
    </div>
</div>

<?= $mensagem ?>

<form method="POST" id="formProposta">
    <div class="card">
        <h3 class="card-title"><i class="ph ph-identification-card"></i> Identificação</h3>
        <div class="dashboard-grid dashboard-grid--equal">
            <div class="form-group">
                <label>Cliente Associado *</label>
                <select name="cliente_id" class="form-control" required>
                    <option value="">Selecione um cliente...</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ((string)$c['id'] === (string)$proposta['cliente_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Título da Proposta *</label>
                <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($proposta['titulo']) ?>" required>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><i class="ph ph-briefcase"></i> Escopo do Projeto</h3>
        
        <div class="form-group">
            <label>Serviços Inclusos</label>
            <div class="grid-servicos-proposta">
                <?php foreach ($todos_servicos as $index => $s): ?>
                    <div>
                        <input type="checkbox" name="servicos[]" value="<?= htmlspecialchars($s['nome']) ?>" id="srv_<?= $index ?>" data-id="<?= $s['id'] ?>" class="card-checkbox-input check-servico" <?= in_array($s['nome'], $servicos_marcados) ? 'checked' : '' ?> onchange="atualizarBotoesPacotes()">
                        <label for="srv_<?= $index ?>" class="card-checkbox-label"><?= htmlspecialchars($s['nome']) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <div class="cabecalho" style="margin-bottom: 5px;">
                <label>Detalhamento da Proposta *</label>
                
                <div id="container_pacotes" style="display: none;">
                    <div class="templates-wrapper" id="botoes_dinamicos">
                        </div>
                </div>
            </div>
            <textarea name="descricao" id="campo_descricao" rows="16" class="form-control" placeholder="Escreve o escopo ou clica nos pacotes dinâmicos para preencher automaticamente..."><?= htmlspecialchars($proposta['descricao']) ?></textarea>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><i class="ph ph-currency-dollar"></i> Financeiro</h3>
        <div class="box-totalizador">
            <div class="form-group">
                <label>Valor da Parcela (R$) *</label>
                <input type="number" step="0.01" id="valor_input" name="valor" class="form-control" value="<?= number_format((float)$proposta['valor'], 2, '.', '') ?>" required>
            </div>
            <div class="form-group">
                <label>Tipo de Cobrança *</label>
                <select name="tipo_cobranca" id="tipo_cobranca" class="form-control" required>
                    <option value="mensal" <?= $proposta['tipo_cobranca'] == 'mensal' ? 'selected' : '' ?>>Mensal</option>
                    <option value="unico" <?= $proposta['tipo_cobranca'] == 'unico' ? 'selected' : '' ?>>Único</option>
                </select>
            </div>
            <div class="form-group">
                <label>Duração (Meses) *</label>
                <input type="number" id="duracao_input" name="duracao_meses" class="form-control" value="<?= htmlspecialchars($proposta['duracao_meses']) ?>" required min="1">
            </div>
            <div class="total-preview-card">
                <span class="txt-meta-sm">Total do Contrato</span>
                <strong id="total_preview">R$ 0,00</strong>
            </div>
        </div>
        <div class="form-group" style="max-width: 250px;">
            <label>Validade da Proposta *</label>
            <input type="date" name="data_validade" class="form-control" value="<?= htmlspecialchars($proposta['data_validade']) ?>" required>
        </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; height: 55px; justify-content: center;">
        <i class="ph ph-floppy-disk"></i> SALVAR PROPOSTA
    </button>
</form>

<script>
// --- MOTOR DINÂMICO DE PACOTES (LÊ DA BASE DE DADOS) ---
const pacotesDoBanco = <?= json_encode($pacotes_js) ?>;

function atualizarBotoesPacotes() {
    const container = document.getElementById('botoes_dinamicos');
    const masterContainer = document.getElementById('container_pacotes');
    container.innerHTML = ''; // Limpa botões antigos
    let temPacote = false;
    
    // Verifica todos os serviços que estão marcados
    document.querySelectorAll('.check-servico:checked').forEach(cb => {
        const servicoId = cb.getAttribute('data-id');
        
        // Se este serviço tiver pacotes no banco, criamos os botões
        if (pacotesDoBanco[servicoId]) {
            temPacote = true;
            pacotesDoBanco[servicoId].forEach(pac => {
                const btn = document.createElement('button');
                btn.type = 'button';
                
                if (pac.tipo === 'pacote') {
                    btn.className = 'btn-pacote p-red';
                    btn.innerText = '📦 ' + pac.nome;
                } else {
                    btn.className = 'btn-pacote p-blue';
                    btn.innerText = '➕ ' + pac.nome;
                }
                
                // Quando clicar, injeta o pacote
                btn.onclick = () => injetarPacoteDinamico(pac);
                container.appendChild(btn);
            });
        }
    });
    
    // Se não houver nenhum pacote para os serviços selecionados, esconde o bloco
    masterContainer.style.display = temPacote ? 'block' : 'none';
}

function injetarPacoteDinamico(pacote) {
    const campoDescricao = document.getElementById('campo_descricao');
    const campoValor = document.getElementById('valor_input');
    
    if (pacote.tipo === 'pacote') {
        // Substitui tudo (Pacote Base)
        campoDescricao.value = pacote.descricao;
        campoValor.value = pacote.valor.toFixed(2);
    } else {
        // Adiciona ao final (Módulo Adicional)
        campoDescricao.value += "\n\n➕ MÓDULO ADICIONAL: " + pacote.nome + "\n" + pacote.descricao;
        let v = parseFloat(campoValor.value) || 0;
        campoValor.value = (v + pacote.valor).toFixed(2);
    }
    
    calcularTotal();
    
    // Efeito visual rápido de feedback
    campoDescricao.style.borderColor = 'var(--red)';
    setTimeout(() => campoDescricao.style.borderColor = 'var(--border-mid)', 400);
}

// Executa ao carregar o ecrã para mostrar botões de propostas que já estejam gravadas
document.addEventListener("DOMContentLoaded", atualizarBotoesPacotes);

// --- CÁLCULO TOTAL ---
function calcularTotal() {
    const valor = parseFloat(document.getElementById('valor_input').value) || 0;
    const meses = parseInt(document.getElementById('duracao_input').value) || 1;
    const tipo = document.getElementById('tipo_cobranca').value;
    let total = (tipo === 'mensal') ? (valor * meses) : valor;
    document.getElementById('total_preview').innerText = 'R$ ' + total.toLocaleString('pt-PT', {minimumFractionDigits: 2});
}
document.getElementById('valor_input').addEventListener('input', calcularTotal);
document.getElementById('duracao_input').addEventListener('input', calcularTotal);
document.getElementById('tipo_cobranca').addEventListener('change', calcularTotal);
calcularTotal();
</script>

<?php require_once '../../includes/layout/footer.php'; ?>