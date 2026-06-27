<?php
// modules/clientes/index.php - VERSÃO COMPLETA

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_avatar.php';

requireLogin();
if (!isAdmin()) {
    die("<div class='empty-state-padded'><h1>Acesso Negado</h1></div>");
}

$mensagem = '';

// Função para tratar valores nulos no htmlspecialchars
function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default);
}

// Lógica de Novo Cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'novo') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');
    $user_insta = trim($_POST['user_insta'] ?? '');

    if (!empty($nome)) {
        try {
            // 1. INSERE O CLIENTE COM user_insta
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, cpf_cnpj, user_insta) VALUES (:nome, :email, :telefone, :cpf_cnpj, :user_insta)");
            $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'telefone' => $telefone,
                'cpf_cnpj' => $cpf_cnpj,
                'user_insta' => $user_insta
            ]);
            
            $cliente_id = $pdo->lastInsertId();
            
            // 2. BUSCA E SALVA O AVATAR DO INSTAGRAM
            if (!empty($user_insta)) {
                salvarAvatarCliente($cliente_id, $user_insta, $pdo);
            }
            
            // 3. CRIA USUÁRIO SE TIVER EMAIL
            if (!empty($email)) {
                $senha_padrao = password_hash($email, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, cliente_id) VALUES (:nome, :email, :senha, 'cliente', :cliente_id)");
                $stmt_user->execute([
                    'nome' => $nome,
                    'email' => $email,
                    'senha' => $senha_padrao,
                    'cliente_id' => $cliente_id
                ]);
            }
            
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cliente cadastrado com sucesso!</div>";
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: Já existe um usuário cadastrado com este e-mail.</div>";
            } else {
                $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao cadastrar: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// BUSCA TODOS OS CLIENTES
$stmt = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
$clientes = $stmt->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- CSS ESPECÍFICO DA PÁGINA -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title">Clientes</h2>
        <p class="page-subtitle">Gerencie o portfólio e as informações da sua carteira de clientes.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalCliente()">
        <i class="ph ph-plus"></i> Novo Cliente
    </button>
</div>

<?= $mensagem ?>

<div class="card">
    
    <div class="filter-bar-container">
        <div class="filter-col-lg">
            <label class="filter-label">Buscar por Nome, Documento ou Contato</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" id="filtroTexto" class="form-control input-pl-40" placeholder="Digite para buscar..." onkeyup="filtrarClientes()">
            </div>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Carteira de Clientes</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($clientes) ?> Registros</span>
    </div>

    <?php if (count($clientes) > 0): ?>
        <div class="table-wrapper">
            <table id="tabelaClientes">
                <thead>
                    <tr>
                        <th>Nome / Empresa</th>
                        <th>Contato</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <?php 
                        $texto_busca = strtolower(
                            ($c['nome'] ?? '') . " " . 
                            ($c['email'] ?? '') . " " . 
                            ($c['cpf_cnpj'] ?? '') . " " . 
                            ($c['telefone'] ?? '')
                        ); 
                        ?>
                        <tr class="linha-cliente" data-busca="<?= safeHtml($texto_busca) ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <?php if (!empty($c['avatar_url'])): ?>
                                        <img src="<?= htmlspecialchars($c['avatar_url']) ?>" 
                                             alt="Avatar" 
                                             style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--text-secondary); flex-shrink: 0;">
                                            <i class="ph ph-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="txt-name-main"><?= safeHtml($c['nome']) ?></span>
                                        <span class="txt-meta-sm">CNPJ/CPF: <?= safeHtml($c['cpf_cnpj'] ?? 'Não informado') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="txt-contact-main"><?= safeHtml($c['email'] ?? '') ?></span>
                                <span class="txt-contact-sub"><?= safeHtml($c['telefone'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="visualizar.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon-table" title="Visualizar Cliente">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon-table" title="Editar Cliente">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div id="msgSemResultados" class="empty-state empty-state-padded" style="display: none;">
                <i class="ph ph-magnifying-glass empty-state-icon"></i>
                Nenhum cliente encontrado para estes filtros.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-buildings empty-state-icon"></i>
            Nenhum cliente cadastrado ainda.
        </div>
    <?php endif; ?>
</div>

<!-- Modal Novo Cliente -->
<div id="modalNovoCliente" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalCliente()"><i class="ph ph-x"></i></button>
        <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Cadastrar Novo Cliente</h3>
        
        <form method="POST" action="">
            <input type="hidden" name="acao" value="novo">
            <div class="form-group">
                <label>Nome / Empresa (*)</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Gasmaske Ltda">
            </div>
            <div class="form-group">
                <label>E-mail (*)</label>
                <input type="email" name="email" class="form-control" required placeholder="E-mail principal">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>WhatsApp</label>
                    <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="Opcional">
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label><i class="ph ph-instagram-logo" style="color: #E4405F;"></i> Instagram</label>
                    <input type="text" name="user_insta" class="form-control" placeholder="@usuario (ex: gasmaske)">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">
                <i class="ph ph-check"></i> Cadastrar
            </button>
        </form>
    </div>
</div>

<script>
function abrirModalCliente() {
    const modal = document.getElementById('modalNovoCliente');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

function fecharModalCliente() {
    const modal = document.getElementById('modalNovoCliente');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 300);
}

function filtrarClientes() {
    const filtroTexto = document.getElementById('filtroTexto').value.toLowerCase();
    const linhas = document.querySelectorAll('.linha-cliente');
    let visiveis = 0;

    linhas.forEach(linha => {
        const texto = linha.getAttribute('data-busca');
        
        let mostra = true;
        if (filtroTexto !== '' && !texto.includes(filtroTexto)) mostra = false;

        if (mostra) {
            linha.style.display = '';
            visiveis++;
        } else {
            linha.style.display = 'none';
        }
    });

    document.getElementById('contadorRegistros').innerText = visiveis + ' Registros';
    document.getElementById('msgSemResultados').style.display = visiveis === 0 ? 'block' : 'none';
    document.getElementById('tabelaClientes').style.display = visiveis === 0 ? 'none' : 'table';
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    filtrarClientes();
}

// Fechar modal ao clicar fora
document.getElementById('modalNovoCliente').addEventListener('click', function(e) {
    if (e.target === this) fecharModalCliente();
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>