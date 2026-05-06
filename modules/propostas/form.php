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
    'data_validade' => date('Y-m-d', strtotime('+7 days')), 'codigo_proposta' => '',
    'token' => ''
];

$mensagem = '';
$exibir_modal_ninja = false;
$nome_cliente_ninja = '';
$token_ninja = '';

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
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Proposta atualizada! O link está pronto para envio.</div>";
        } else {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO propostas (cliente_id, titulo, descricao, servicos_inclusos, valor, tipo_cobranca, duracao_meses, data_validade, token, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho')");
            $stmt->execute([$cliente_id, $titulo, $descricao, $servicos_inclusos, $valor, $tipo_cobranca, $duracao_meses, $data_validade, $token]);
            $id = $pdo->lastInsertId();
            $codigo_proposta = "PRP-" . str_pad($id, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE propostas SET codigo_proposta = ? WHERE id = ?")->execute([$codigo_proposta, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Proposta criada! O link está pronto para envio.</div>";
        }
        
        $stmt = $pdo->prepare("SELECT * FROM propostas WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if ($res) $proposta = array_merge($proposta, $res);
        
        $cliente_stmt = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
        $cliente_stmt->execute([$cliente_id]);
        $nome_cliente_ninja = $cliente_stmt->fetchColumn();
        
        $exibir_modal_ninja = true;
        $token_ninja = $res['token'];
        
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
$todos_servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
$servicos_marcados = empty($proposta['servicos_inclusos']) ? [] : array_map('trim', explode(',', $proposta['servicos_inclusos']));

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
    .ql-toolbar.ql-snow { border: 1px solid var(--border-mid); background: var(--bg-surface); border-top-left-radius: 8px; border-top-right-radius: 8px; }
    .ql-container.ql-snow { border: 1px solid var(--border-mid); background: var(--bg-elevated); border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; color: var(--text-primary); font-family: 'DM Sans', sans-serif; font-size: 15px; }
    .ql-snow .ql-stroke { stroke: var(--text-secondary); }
    .ql-snow .ql-fill, .ql-snow .ql-stroke.ql-fill { fill: var(--text-secondary); }
    .ql-editor { min-height: 400px; }
    .layout-proposta { display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: start; }
    @media(max-width: 992px) { .layout-proposta { grid-template-columns: 1fr; } }
</style>

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Editar Proposta (' . htmlspecialchars($proposta['codigo_proposta']) . ')' : 'Nova Proposta' ?></h2>
        <p class="page-subtitle">Configure o cliente, a parte financeira e deixe a IA cuidar do resto.</p>
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

<form method="POST" id="formProposta" onsubmit="sincronizarEditor()">
    <div class="layout-proposta">
        
        <div class="config-col">
            <div class="card">
                <h3 class="card-title"><i class="ph ph-sliders"></i> Ajustes Iniciais</h3>
                <div class="form-group">
                    <label>Cliente *</label>
                    <select name="cliente_id" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ((string)$c['id'] === (string)$proposta['cliente_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Título da Proposta *</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($proposta['titulo']) ?>" placeholder="Ex: Gestão e Rebranding..." required>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title"><i class="ph ph-currency-dollar"></i> Financeiro</h3>
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="number" step="0.01" id="valor_input" name="valor" class="form-control" value="<?= number_format((float)$proposta['valor'], 2, '.', '') ?>" required>
                </div>
                <div class="dashboard-grid dashboard-grid--equal" style="gap: 10px;">
                    <div class="form-group">
                        <label>Cobrança *</label>
                        <select name="tipo_cobranca" id="tipo_cobranca" class="form-control" required>
                            <option value="mensal" <?= $proposta['tipo_cobranca'] == 'mensal' ? 'selected' : '' ?>>Mensal</option>
                            <option value="unico" <?= $proposta['tipo_cobranca'] == 'unico' ? 'selected' : '' ?>>Único</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duração *</label>
                        <input type="number" id="duracao_input" name="duracao_meses" class="form-control" value="<?= htmlspecialchars($proposta['duracao_meses']) ?>" required min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label>Validade *</label>
                    <input type="date" name="data_validade" class="form-control" value="<?= htmlspecialchars($proposta['data_validade']) ?>" required>
                </div>
                
                <div class="total-preview-card" style="margin-top: 10px;">
                    <span class="txt-meta-sm">Total a Receber</span>
                    <strong id="total_preview" style="font-size: 20px; color: var(--red);">R$ 0,00</strong>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; justify-content: center; font-size: 15px;">
                <i class="ph ph-floppy-disk"></i> Salvar Proposta
            </button>
        </div>

        <div class="editor-col">
            <div class="card" style="padding: 0; border: none; background: transparent;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <h3 class="card-title" style="margin: 0;"><i class="ph ph-text-align-left"></i> Corpo da Proposta</h3>
                    
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary" onclick="abrirPrevia()" style="font-size: 11px; padding: 6px 12px;">
                            <i class="ph ph-eye"></i> PRÉVIA
                        </button>
                        
                        <button type="button" class="btn btn-primary" id="btnGerarIA" style="background: linear-gradient(45deg, #FF3B2F, #a78bfa); border: none; font-size: 11px; padding: 6px 12px;" onclick="gerarPropostaIA()">
                            <i class="ph-fill ph-magic-wand"></i> GERAR COM IA
                        </button>
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <span class="txt-meta-sm" style="margin-bottom: 6px;">Serviços mapeados (Ajuda a IA a escrever):</span>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <?php foreach ($todos_servicos as $index => $s): ?>
                            <label style="display: inline-flex; align-items: center; gap: 4px; background: var(--bg-hover); padding: 4px 8px; border-radius: 4px; font-size: 11px; color: var(--text-secondary); cursor: pointer;">
                                <input type="checkbox" name="servicos[]" value="<?= htmlspecialchars($s['nome']) ?>" <?= in_array($s['nome'], $servicos_marcados) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($s['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="editor-container"><?= $proposta['descricao'] ?></div>
                <textarea name="descricao" id="hidden_descricao" style="display:none;"></textarea>
            </div>
        </div>
    </div>
</form>

<!-- MODAL DE PRÉVIA -->
<div id="modalPrevia" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 999999; align-items: center; justify-content: center;">
    <div style="background: #fff; width: 90%; max-width: 900px; height: 85vh; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
        <div style="background: #111; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color: #fff; margin: 0; font-family: 'DM Serif Display', serif;">Prévia da Proposta</h3>
            <button type="button" onclick="fecharPrevia()" style="background: #FF3B2F; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold;">FECHAR X</button>
        </div>
        <div id="box_previa" style="padding: 40px; overflow-y: auto; color: #333; font-family: 'DM Sans', sans-serif; font-size: 16px; line-height: 1.6;">
            <!-- O texto entra aqui -->
        </div>
    </div>
</div>

<!-- MODAL NINJA (COPIAR LINK) -->
<?php if ($exibir_modal_ninja): ?>
    <?php $link_proposta = "http://localhost/gasmaske/publico/proposta.php?token=" . $token_ninja; ?>
    <div id="modalNinja" class="modal-ninja-overlay">
        <div class="modal-ninja-box">
            <div class="modal-ninja-header">Proposta Pronta! 🚀</div>
            <span class="modal-ninja-tag">Ação Requerida</span>
            <textarea id="textoProposta" class="modal-ninja-textarea" readonly>Fala <?= htmlspecialchars($nome_cliente_ninja) ?>, tudo bem?

Separei aqui a nossa proposta detalhando tudo o que conversamos. Segue o link com acesso exclusivo:
<?= $link_proposta ?>

Qualquer dúvida, me dá um toque por aqui!</textarea>
            <button class="modal-ninja-btn-copy" onclick="copiarTextoNinja(this)">Copiar Link e Mensagem</button>
            <button class="modal-ninja-btn-close" onclick="document.getElementById('modalNinja').style.display='none'">Fechar</button>
        </div>
    </div>
    <script>
        function copiarTextoNinja(btn) {
            navigator.clipboard.writeText(document.getElementById("textoProposta").value).then(function() {
                btn.innerHTML = "✔ Mensagem Copiada!";
                setTimeout(() => btn.innerHTML = "Copiar Link e Mensagem", 2500);
            });
        }
    </script>
<?php endif; ?>

<!-- SCRIPT PRINCIPAL -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
    var quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    });

    function sincronizarEditor() {
        document.getElementById('hidden_descricao').value = quill.root.innerHTML;
    }

    function calcularTotal() {
        const valor = parseFloat(document.getElementById('valor_input').value) || 0;
        const meses = parseInt(document.getElementById('duracao_input').value) || 1;
        const tipo = document.getElementById('tipo_cobranca').value;
        let total = (tipo === 'mensal') ? (valor * meses) : valor;
        document.getElementById('total_preview').innerText = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }
    document.getElementById('valor_input').addEventListener('input', calcularTotal);
    document.getElementById('duracao_input').addEventListener('input', calcularTotal);
    document.getElementById('tipo_cobranca').addEventListener('change', calcularTotal);
    calcularTotal();

    function abrirPrevia() {
        document.getElementById('box_previa').innerHTML = quill.root.innerHTML;
        document.getElementById('modalPrevia').style.display = 'flex';
    }

    function fecharPrevia() {
        document.getElementById('modalPrevia').style.display = 'none';
    }

    async function gerarPropostaIA() {
        const clienteId = document.querySelector('select[name="cliente_id"]').value;
        if (!clienteId) {
            alert("Opa! Selecione um Cliente primeiro para a IA saber para quem é a proposta.");
            return;
        }

        const btn = document.getElementById('btnGerarIA');
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '⏳ PENSANDO...';
        btn.disabled = true;

        try {
            const formData = new FormData();
            formData.append('cliente_id', clienteId);

            const res = await fetch('gerar_proposta_ia.php', { method: 'POST', body: formData });
            const rawText = await res.text();

            let data;
            try {
                data = JSON.parse(rawText);
            } catch (jsonErr) {
                alert("O PHP quebrou nos bastidores! Veja o erro real:\n\n" + rawText.substring(0, 500));
                return;
            }

            if (data.erro) {
                alert("Aviso: " + data.erro);
            } else {
                // O texto já vem limpo do PHP, só garante que não sobrou nenhum backtick
                let htmlLimpo = data.texto
                    .replace(/^```html\s*/gi, '')
                    .replace(/^```\s*/gim, '')
                    .replace(/```\s*$/gim, '')
                    .trim();

                quill.clipboard.dangerouslyPasteHTML(htmlLimpo);
                alert("Mágica concluída! Proposta estruturada no editor.");
            }
        } catch (e) {
            alert("Erro de rede: " + e.message);
        } finally {
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
        }
    }
</script>

<?php require_once '../../includes/layout/footer.php'; ?>