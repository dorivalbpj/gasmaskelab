<?php
// modules/clientes/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) {
    die("<div class='empty-state-padded'><h1>Acesso Negado</h1></div>");
}

$mensagem = '';

// Lógica de Novo Cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'novo') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cpf_cnpj = trim($_POST['cpf_cnpj'] ?? '');

    if (!empty($nome)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, cpf_cnpj) VALUES (:nome, :email, :telefone, :cpf_cnpj)");
            $stmt->execute(['nome' => $nome, 'email' => $email, 'telefone' => $telefone, 'cpf_cnpj' => $cpf_cnpj]);
            
            $cliente_id = $pdo->lastInsertId();

            if(!empty($email)) {
                 $senha_padrao = password_hash($email, PASSWORD_DEFAULT);
                 $stmt_user = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, cliente_id) VALUES (:nome, :email, :senha, 'cliente', :cliente_id)");
                 $stmt_user->execute(['nome' => $nome, 'email' => $email, 'senha' => $senha_padrao, 'cliente_id' => $cliente_id]);
            }
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cliente cadastrado com sucesso!</div>";
        } catch (PDOException $e) {
            if($e->getCode() == 23000) {
                 $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: Já existe um usuário cadastrado com este e-mail.</div>";
            } else {
                 $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro ao cadastrar.</div>";
            }
        }
    }
}

$stmt = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
$clientes = $stmt->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">CRM / Clientes</h2>
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
            <button type="button" class="btn btn-ghost btn-h44" onclick="document.getElementById('filtroTexto').value=''; filtrarClientes();" title="Limpar">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Carteira de Clientes</h3>
        <span class="badge badge-red" id="contadorRegistros"><?= count($clientes) ?> Registros</span>
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
                    <?php $texto_busca = strtolower($c['nome'] . " " . $c['email'] . " " . $c['cpf_cnpj'] . " " . $c['telefone']); ?>
                    <tr class="linha-cliente" data-busca="<?= htmlspecialchars($texto_busca) ?>">
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($c['nome']) ?></span>
                            <span class="txt-meta-sm">CNPJ/CPF: <?= htmlspecialchars($c['cpf_cnpj'] ?: 'Não informado') ?></span>
                        </td>
                        <td>
                            <span class="txt-contact-main"><?= htmlspecialchars($c['email']) ?></span>
                            <span class="txt-contact-sub"><?= htmlspecialchars($c['telefone']) ?></span>
                        </td>
                        <td class="text-center">
                            <a href="ficha.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn--sm">Ficha Completa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div id="msgSemResultados" class="empty-state empty-state-padded" style="display: none;">
                <i class="ph ph-magnifying-glass empty-state-icon"></i>
                Nenhum cliente encontrado.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-buildings empty-state-icon"></i>
            Nenhum cliente cadastrado ainda.
        </div>
    <?php endif; ?>
</div>

<div id="modalNovoCliente" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalCliente()"><i class="ph ph-x"></i></button>
        <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Cadastrar Novo Lead</h3>
        
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
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="Opcional agora">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">Cadastrar</button>
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
    const filtro = document.getElementById('filtroTexto').value.toLowerCase();
    const linhas = document.querySelectorAll('.linha-cliente');
    let visiveis = 0;

    linhas.forEach(linha => {
        if (linha.getAttribute('data-busca').includes(filtro)) {
            linha.style.display = ''; visiveis++;
        } else {
            linha.style.display = 'none';
        }
    });

    document.getElementById('contadorRegistros').innerText = visiveis + ' Registros';
    document.getElementById('msgSemResultados').style.display = visiveis === 0 ? 'block' : 'none';
    document.getElementById('tabelaClientes').style.display = visiveis === 0 ? 'none' : 'table';
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>