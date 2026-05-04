<?php
// modules/clientes/ficha.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// ======== AUTO-FIX DO BANCO ========
try {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN credenciais TEXT NULL AFTER observacoes");
} catch (Exception $e) {}
// ===================================

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Lógica de Atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar') {
    $sql = "UPDATE clientes SET 
            nome=?, email=?, telefone=?, cpf_cnpj=?, observacoes=?,
            link_drive=?, link_referencias=?, endereco_completo=?, dados_faturamento=?, credenciais=?
            WHERE id=?";
    
    $dados = [
        $_POST['nome'], $_POST['email'], $_POST['telefone'], $_POST['cpf_cnpj'], $_POST['observacoes'],
        $_POST['link_drive'], $_POST['link_referencias'], $_POST['endereco_completo'], $_POST['dados_faturamento'], $_POST['credenciais_json'],
        $id
    ];

    try {
        $pdo->prepare($sql)->execute($dados);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Ficha atualizada com sucesso!</div>";
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro ao atualizar.</div>";
    }
}

// Busca os dados do cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

// --- INTELIGÊNCIA: PUXAR GOOGLE DRIVE DO CONTRATO ---
if (empty($cliente['link_drive'])) {
    $stmt_drive = $pdo->prepare("SELECT link_drive FROM contratos WHERE cliente_id = ? AND link_drive IS NOT NULL AND link_drive != '' ORDER BY id DESC LIMIT 1");
    $stmt_drive->execute([$id]);
    $drive_contrato = $stmt_drive->fetchColumn();
    if ($drive_contrato) {
        $cliente['link_drive'] = $drive_contrato;
    }
}

// --- MIGRAÇÃO INVISÍVEL DE SENHAS ANTIGAS ---
if (empty($cliente['credenciais']) && (!empty($cliente['user_insta']) || !empty($cliente['user_fb']))) {
    $creds_antigas = [];
    if(!empty($cliente['user_insta'])) $creds_antigas[] = ['plat'=>'Instagram', 'user'=>$cliente['user_insta'], 'pass'=>$cliente['pass_insta']];
    if(!empty($cliente['user_fb'])) $creds_antigas[] = ['plat'=>'Facebook', 'user'=>$cliente['user_fb'], 'pass'=>$cliente['pass_fb']];
    if(!empty($cliente['user_tt'])) $creds_antigas[] = ['plat'=>'TikTok', 'user'=>$cliente['user_tt'], 'pass'=>$cliente['pass_tt']];
    if(!empty($cliente['user_li'])) $creds_antigas[] = ['plat'=>'LinkedIn', 'user'=>$cliente['user_li'], 'pass'=>$cliente['pass_li']];
    $cliente['credenciais'] = json_encode($creds_antigas);
}

// Prepara o JSON pro Javascript ler
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
        
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ph ph-address-book"></i> Dados de Contato</h3></div>
                <div class="form-group">
                    <label>Nome / Razão Social</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>E-mail Principal</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone / Whats</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj']) ?>" placeholder="Será preenchido na assinatura">
                </div>
            </div>

            <div class="card" style="border-top: 3px solid var(--purple);">
                <div class="card-header" style="border-bottom: none; margin-bottom: 0;">
                    <h3 class="card-title"><i class="ph ph-key"></i> Credenciais de Acesso</h3>
                </div>
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 20px;">Adicione quantos acessos forem necessários (Redes Sociais, Hospedagem, Domínio, etc).</p>
                
                <div id="listaCredenciais"></div>

                <button type="button" class="btn btn-ghost" onclick="adicionarCredencial()" style="width: 100%; justify-content: center; margin-top: 10px; border: 1px dashed var(--border-mid);">
                    <i class="ph ph-plus"></i> Adicionar Acesso
                </button>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ph ph-link"></i> Links e Assets</h3></div>
                <div class="form-group">
                    <label>📁 Google Drive (Pasta do Cliente)</label>
                    <input type="url" name="link_drive" class="form-control" value="<?= htmlspecialchars($cliente['link_drive']) ?>" placeholder="Puxa automático do contrato!">
                </div>
                <div class="form-group">
                    <label>📌 Referências Visuais / Outros</label>
                    <textarea name="link_referencias" class="form-control" rows="2"><?= htmlspecialchars($cliente['link_referencias']) ?></textarea>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="ph ph-buildings"></i> Informações Corporativas</h3></div>
                <div class="form-group">
                    <label>Endereço Completo</label>
                    <textarea name="endereco_completo" class="form-control" rows="2" placeholder="Preenchido automaticamente no aceite do contrato."><?= htmlspecialchars($cliente['endereco_completo']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Dados para Faturamento (NF-e, Inscrição...)</label>
                    <textarea name="dados_faturamento" class="form-control" rows="3"><?= htmlspecialchars($cliente['dados_faturamento']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Observações Internas</label>
                    <textarea name="observacoes" class="form-control" rows="2"><?= htmlspecialchars($cliente['observacoes']) ?></textarea>
                </div>
            </div>

            <button type="submit" onclick="prepararEnvio()" class="btn btn-primary" style="width: 100%; height: 50px; font-size: 16px;">💾 Salvar Ficha Completa</button>
        </div>

    </div>
</form>

<script>
// --- MOTOR DE CREDENCIAIS DINÂMICAS ---
let credenciais = <?= $json_credenciais ?>;

function renderizarCredenciais() {
    const container = document.getElementById('listaCredenciais');
    container.innerHTML = '';
    
    credenciais.forEach((cred, index) => {
        const div = document.createElement('div');
        div.className = 'cred-row';
        div.innerHTML = `
            <input type="text" class="form-control input-cred" placeholder="Ex: Instagram" value="${cred.plat}" onchange="atualizarCred(${index}, 'plat', this.value)">
            <input type="text" class="form-control input-cred" placeholder="Usuário/E-mail" value="${cred.user}" onchange="atualizarCred(${index}, 'user', this.value)">
            <input type="text" class="form-control input-cred" placeholder="Senha" value="${cred.pass}" onchange="atualizarCred(${index}, 'pass', this.value)">
            <button type="button" class="cred-btn-remove" onclick="removerCredencial(${index})" title="Remover"><i class="ph ph-trash"></i></button>
        `;
        container.appendChild(div);
    });
}

function adicionarCredencial() {
    credenciais.push({plat: '', user: '', pass: ''});
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
    // Transforma o array JS em string JSON e joga no input escondido pro PHP salvar
    document.getElementById('inputCredenciais').value = JSON.stringify(credenciais);
}

// Inicia a renderização
renderizarCredenciais();
</script>

<?php require_once '../../includes/layout/footer.php'; ?>