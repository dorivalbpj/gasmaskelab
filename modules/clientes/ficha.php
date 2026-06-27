<?php
// modules/clientes/ficha.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Lógica de Atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar') {
    $sql = "UPDATE clientes SET 
                nome = ?, email = ?, telefone = ?, cpf_cnpj = ?,
                endereco = ?, observacoes = ?, credenciais = ?
            WHERE id = ?";

    $dados = [
        $_POST['nome'],
        $_POST['email'],
        $_POST['telefone'],
        $_POST['cpf_cnpj'],
        $_POST['endereco'],
        $_POST['observacoes'],
        $_POST['credenciais_json'],
        $id
    ];

    try {
        $pdo->prepare($sql)->execute($dados);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Ficha atualizada com sucesso!</div>";
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro ao atualizar: " . $e->getMessage() . "</div>";
    }
}

// Busca os dados do cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

// Busca link do Drive vindo do contrato
$link_drive = '';
$stmt_drive = $pdo->prepare("SELECT link_drive FROM contratos WHERE cliente_id = ? AND link_drive IS NOT NULL AND link_drive != '' ORDER BY id DESC LIMIT 1");
$stmt_drive->execute([$id]);
$link_drive = $stmt_drive->fetchColumn() ?: '';

// Migração invisível de credenciais antigas para o novo formato JSON
if (empty($cliente['credenciais']) && (!empty($cliente['user_insta']) || !empty($cliente['user_fb']))) {
    $creds_antigas = [];
    if (!empty($cliente['user_insta'])) $creds_antigas[] = ['plat' => 'Instagram', 'user' => $cliente['user_insta'], 'pass' => $cliente['pass_insta']];
    if (!empty($cliente['user_fb']))    $creds_antigas[] = ['plat' => 'Facebook',  'user' => $cliente['user_fb'],    'pass' => $cliente['pass_fb']];
    if (!empty($cliente['user_tt']))    $creds_antigas[] = ['plat' => 'TikTok',    'user' => $cliente['user_tt'],    'pass' => $cliente['pass_tt']];
    if (!empty($cliente['user_li']))    $creds_antigas[] = ['plat' => 'LinkedIn',  'user' => $cliente['user_li'],    'pass' => $cliente['pass_li']];
    $cliente['credenciais'] = json_encode($creds_antigas);
}

$json_credenciais = !empty($cliente['credenciais']) ? $cliente['credenciais'] : '[]';

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="flex-between mb-20">
    <div>
        <h2 class="page-title">Ficha do Cliente: <?= htmlspecialchars($cliente['nome']) ?></h2>
        <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 14px;">← Voltar para a lista</a>
    </div>
</div>

<?= $mensagem ?>

<form method="POST" id="formFicha">
    <input type="hidden" name="acao" value="atualizar">
    <input type="hidden" name="credenciais_json" id="inputCredenciais" value="">

    <div class="dashboard-grid">

        <!-- Coluna Esquerda -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <div class="card">
                <div class="card-header"><h3 class="card-title">Dados de Contato</h3></div>
                <div class="form-group">
                    <label>Nome / Razão Social</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone / WhatsApp</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>" placeholder="Preenchido automaticamente na assinatura">
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <textarea name="endereco" class="form-control" rows="2"><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>Observações Internas</label>
                    <textarea name="observacoes" class="form-control" rows="2"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Credenciais de Acesso</h3></div>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Redes Sociais, Hospedagem, Domínio, etc.</p>

                <div id="listaCredenciais"></div>

                <button type="button" class="btn btn-ghost" onclick="adicionarCredencial()" style="width: 100%; justify-content: center; margin-top: 10px; border: 1px dashed var(--border-mid);">
                    + Adicionar Acesso
                </button>
            </div>
        </div>

        <!-- Coluna Direita -->
        <div style="display: flex; flex-direction: column; gap: 24px;">

            <?php if ($link_drive): ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Google Drive</h3></div>
                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">Pasta vinculada ao contrato deste cliente.</p>
                <a href="<?= htmlspecialchars($link_drive) ?>" target="_blank" class="btn btn-secondary">Abrir pasta no Drive</a>
            </div>
            <?php endif; ?>

            <div class="card">
                <button type="submit" onclick="prepararEnvio()" class="btn btn-primary" style="width: 100%; height: 50px; font-size: 16px;">Salvar Ficha</button>
            </div>

        </div>
    </div>
</form>

<script>
let credenciais = <?= $json_credenciais ?>;

function renderizarCredenciais() {
    const container = document.getElementById('listaCredenciais');
    container.innerHTML = '';
    credenciais.forEach((cred, index) => {
        const div = document.createElement('div');
        div.className = 'cred-row';
        div.innerHTML = `
            <input type="text" class="form-control input-cred" placeholder="Plataforma (ex: Instagram)" value="${cred.plat || ''}" onchange="atualizarCred(${index}, 'plat', this.value)">
            <input type="text" class="form-control input-cred" placeholder="Usuário / E-mail" value="${cred.user || ''}" onchange="atualizarCred(${index}, 'user', this.value)">
            <input type="text" class="form-control input-cred" placeholder="Senha" value="${cred.pass || ''}" onchange="atualizarCred(${index}, 'pass', this.value)">
            <button type="button" onclick="removerCredencial(${index})" title="Remover">X</button>
        `;
        container.appendChild(div);
    });
}

function adicionarCredencial() {
    credenciais.push({ plat: '', user: '', pass: '' });
    renderizarCredenciais();
}

function removerCredencial(index) {
    credenciais.splice(index, 1);
    renderizarCredenciais();
}

function atualizarCred(index, campo, valor) {
    credenciais[index][campo] = valor;
}

function prepararEnvio() {
    document.getElementById('inputCredenciais').value = JSON.stringify(credenciais);
}

renderizarCredenciais();
</script>

<?php require_once '../../includes/layout/footer.php'; ?>