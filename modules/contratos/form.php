<?php
// modules/contratos/form.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;
$proposta_id = $_GET['proposta_id'] ?? 0;

$contrato = [
    'cliente_id' => '', 'codigo_agc' => '', 'valor' => 0, 
    'duracao_meses' => 1, 'texto_contrato' => '', 'status' => 'rascunho'
];
$mensagem = '';

// Puxa dados da proposta (se vier dela)
if ($proposta_id && !$id) {
    $stmt_prop = $pdo->prepare("SELECT p.* FROM propostas p WHERE p.id = ?");
    $stmt_prop->execute([$proposta_id]);
    $dados_proposta = $stmt_prop->fetch();
    if ($dados_proposta) {
        $contrato['cliente_id'] = $dados_proposta['cliente_id'];
        $contrato['valor'] = $dados_proposta['valor'];
        $contrato['duracao_meses'] = $dados_proposta['duracao_meses'];
    }
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if ($res) $contrato = array_merge($contrato, $res);
}

// Salvamento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_contrato'])) {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
    $duracao_meses = $_POST['duracao_meses'] ?? 1;
    $texto_contrato = $_POST['texto_contrato'] ?? '';
    $status = $_POST['status'] ?? 'rascunho';

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE contratos SET cliente_id=?, valor=?, duracao_meses=?, texto_contrato=?, status=? WHERE id=?");
            $stmt->execute([$cliente_id, $valor, $duracao_meses, $texto_contrato, $status, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph ph-check-circle'></i> Contrato salvo com sucesso!</div>";
        } else {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO contratos (cliente_id, valor, duracao_meses, texto_contrato, status, token) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $valor, $duracao_meses, $texto_contrato, $status, $token]);
            $id = $pdo->lastInsertId();
            $codigo_agc = "CTR-" . str_pad($id, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE contratos SET codigo_agc = ? WHERE id = ?")->execute([$codigo_agc, $id]);
            $contrato['codigo_agc'] = $codigo_agc;
            $mensagem = "<div class='alert alert-success'><i class='ph ph-check-circle'></i> Novo contrato gerado!</div>";
        }
        $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if ($res) $contrato = array_merge($contrato, $res);
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$url_base_dinamica = $protocolo . "://" . $dominio . "/gasmaske/publico/contrato.php?token=";

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<style>
    /* UX Melhorado do Editor */
    #textAreaContrato {
        background-color: #fcfcfc !important;
        color: #1a1a1a !important; 
        font-family: 'Space Mono', monospace;
        font-size: 14px;
        line-height: 1.8;
        padding: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        resize: vertical;
        width: 100%;
        min-height: 600px;
    }
    #textAreaContrato:focus {
        border-color: var(--red);
        outline: none;
        background-color: #ffffff !important;
    }
    .editor-toolbar { display: flex; gap: 10px; margin-bottom: 15px; }
    .btn-tool {
        background: #f0f0ed; border: 1px solid #d1d1cf; color: #555; padding: 6px 14px;
        font-size: 11px; font-weight: 600; border-radius: 4px; cursor: pointer; text-transform: uppercase;
    }
    .btn-tool:hover { background: #e2e2df; color: #000; }
    
    /* Organização dos Cards */
    .dashboard-grid-vertical { display: flex; flex-direction: column; gap: 20px; }
    .config-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
</style>

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Minuta ' . htmlspecialchars($contrato['codigo_agc']) : 'Novo Contrato' ?></h2>
        <p class="page-subtitle">Revise as cláusulas jurídicas da Gasmaske Lab antes do envio.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar</a>
        
        <?php if ($id && $contrato['status'] == 'rascunho'): ?>
            <button type="button" class="btn" onclick="finalizarContrato()" style="background: #10B981; color: #fff; border: none; font-weight: 600;">
                <i class="ph ph-paper-plane-right"></i> Finalizar e Enviar
            </button>
        <?php endif; ?>

        <button type="submit" form="formContrato" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Rascunho</button>
    </div>
</div>

<?= $mensagem ?>

<form method="POST" id="formContrato">
    <input type="hidden" name="salvar_contrato" value="1">
    <input type="hidden" id="proposta_id_input" value="<?= $proposta_id ?>">
    
    <div class="dashboard-grid-vertical">
        
        <div class="card">
            <h3 class="card-title"><i class="ph ph-sliders"></i> Dados do Contrato</h3>
            <div class="config-row">
                <div class="form-group mb-0">
                    <label>Cliente Vinculado *</label>
                    <select name="cliente_id" id="select_cliente" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ((string)$c['id'] === (string)$contrato['cliente_id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label>Valor Mensal (R$) *</label>
                    <input type="number" step="0.01" name="valor" class="form-control" value="<?= number_format((float)$contrato['valor'], 2, '.', '') ?>" required>
                </div>
                <div class="form-group mb-0">
                    <label>Duração (Meses) *</label>
                    <input type="number" name="duracao_meses" class="form-control" value="<?= htmlspecialchars($contrato['duracao_meses']) ?>" required>
                </div>
                <div class="form-group mb-0">
                    <label>Status Atual *</label>
                    <select name="status" id="statusSelect" class="form-control">
                        <option value="rascunho" <?= $contrato['status'] == 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                        <option value="aguardando_aceite_cliente" <?= $contrato['status'] == 'aguardando_aceite_cliente' ? 'selected' : '' ?>>Enviado para Assinatura</option>
                        <option value="em_andamento" <?= $contrato['status'] == 'em_andamento' ? 'selected' : '' ?>>Ativo</option>
                    </select>
                </div>
            </div>
        </div>

        <?php if($id): ?>
        <div class="card" style="border-left: 4px solid var(--blue); padding: 15px 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="font-size: 14px; display: block;">Link de Assinatura:</strong>
                    <span style="font-size: 12px; color: var(--text-muted);"><?= $url_base_dinamica . $contrato['token'] ?></span>
                </div>
                <button type="button" class="btn btn-ghost btn--sm" onclick="navigator.clipboard.writeText('<?= $url_base_dinamica . $contrato['token'] ?>'); alert('Link copiado!');" style="color: var(--blue);">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="card" style="border-top: 3px solid var(--purple);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                <div>
                    <h3 class="card-title"><i class="ph ph-scroll"></i> Minuta Jurídica</h3>
                    <p class="text-muted" style="font-size: 12px; margin-bottom: 0;">Texto final do contrato. Edição livre.</p>
                </div>
                <button type="button" class="btn btn-primary" id="btnGerarIA" onclick="redigirContratoIA()" style="background: #111; border: none; font-weight: 800; letter-spacing: 0.5px;">
                    <i class="ph-fill ph-magic-wand"></i> REDIGIR COM IA
                </button>
            </div>

            <div class="editor-toolbar">
                <button type="button" class="btn-tool" onclick="copiarTexto()">Copiar Tudo</button>
                <button type="button" class="btn-tool" onclick="document.getElementById('textAreaContrato').value = '';">Limpar Texto</button>
                <button type="button" class="btn-tool" onclick="document.getElementById('textAreaContrato').value = document.getElementById('textAreaContrato').value.toUpperCase();">Tudo Maiúsculo</button>
            </div>

            <textarea name="texto_contrato" id="textAreaContrato" placeholder="Clique em 'Redigir com IA' ou digite a minuta aqui..."><?= htmlspecialchars($contrato['texto_contrato']) ?></textarea>
        </div>
        
    </div>
</form>

<script>
function finalizarContrato() {
    if(confirm("Deseja finalizar esta minuta? O status mudará para 'Enviado para Assinatura' e o cliente poderá assinar.")) {
        document.getElementById('statusSelect').value = 'aguardando_aceite_cliente';
        document.getElementById('formContrato').submit();
    }
}

async function redigirContratoIA() {
    const propostaId = document.getElementById('proposta_id_input').value;
    const clienteId = document.getElementById('select_cliente').value;
    
    if (!clienteId) {
        alert("Selecione um Cliente primeiro!");
        return;
    }

    const btn = document.getElementById('btnGerarIA');
    const textoOriginal = btn.innerHTML;
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> PROCESSANDO...';
    btn.disabled = true;

    try {
        const formData = new FormData();
        formData.append('proposta_id', propostaId);
        formData.append('cliente_id', clienteId); // Envia o cliente caso não tenha proposta!
        
        const res = await fetch('gerar_contrato_ia.php', { method: 'POST', body: formData });
        const data = await res.json();

        if(data.erro) {
            alert("Aviso: " + data.erro);
        } else {
            let texto = data.texto.replace(/\*\*/g, '').replace(/```/g, '').trim();
            document.getElementById('textAreaContrato').value = texto;
            alert("Minuta gerada com sucesso! Revise as cláusulas.");
        }
    } catch(e) {
        alert("Erro de conexão com o servidor.");
    } finally {
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
    }
}

function copiarTexto() {
    const area = document.getElementById('textAreaContrato');
    area.select();
    document.execCommand('copy');
    alert("Texto copiado para a área de transferência!");
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>