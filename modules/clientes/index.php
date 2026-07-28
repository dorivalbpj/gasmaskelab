<?php
// modules/clientes/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_avatar.php';

requireLogin();
if (!isAdmin()) {
    die("<div class='empty-state-padded'><h1>Acesso Negado</h1></div>");
}

$mensagem = '';

function safeHtml($value, $default = '') {
    return htmlspecialchars($value ?? $default);
}

// Lógica de Ativar/Desativar Cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'alternar_status') {
    $id_toggle = (int)($_POST['id'] ?? 0);
    if ($id_toggle > 0) {
        $stmt = $pdo->prepare("SELECT status FROM clientes WHERE id = ?");
        $stmt->execute([$id_toggle]);
        $status_atual = $stmt->fetchColumn();

        if ($status_atual !== false) {
            $novo_status = ($status_atual === 'ativo') ? 'inativo' : 'ativo';
            $stmt = $pdo->prepare("UPDATE clientes SET status = ? WHERE id = ?");
            $stmt->execute([$novo_status, $id_toggle]);

            $texto = $novo_status === 'ativo' ? 'ativado' : 'desativado';
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cliente $texto com sucesso!</div>";
        }
    }
}

// Lógica de Novo Cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'novo') {
    $nome      = trim($_POST['nome'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telefone  = trim($_POST['telefone'] ?? '');
    $cpf_cnpj  = trim($_POST['cpf_cnpj'] ?? '');
    $user_insta = trim($_POST['user_insta'] ?? '');

    if (!empty($nome)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, cpf_cnpj, user_insta, avatar_url) VALUES (:nome, :email, :telefone, :cpf_cnpj, :user_insta, :avatar_url)");
            $stmt->execute([
                'nome'       => $nome,
                'email'      => $email,
                'telefone'   => $telefone,
                'cpf_cnpj'   => $cpf_cnpj,
                'user_insta' => $user_insta,
                'avatar_url' => gerarAvatarIniciais($nome),
            ]);

            $cliente_id = $pdo->lastInsertId();

            if (!empty($email)) {
                $senha_padrao = password_hash($email, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, cliente_id) VALUES (:nome, :email, :senha, 'cliente', :cliente_id)");
                $stmt_user->execute([
                    'nome'       => $nome,
                    'email'      => $email,
                    'senha'      => $senha_padrao,
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

// Busca todos os clientes
$stmt = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
$clientes = $stmt->fetchAll();

$total_ativos = 0;
$total_inativos = 0;
foreach ($clientes as $c) {
    if (($c['status'] ?? 'ativo') === 'inativo') {
        $total_inativos++;
    } else {
        $total_ativos++;
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

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
        <div class="status-tabs">
            <button type="button" class="status-tab active" data-status="ativo" onclick="filtrarPorStatus('ativo', this)">
                Ativos <span class="status-tab-count"><?= $total_ativos ?></span>
            </button>
            <button type="button" class="status-tab" data-status="inativo" onclick="filtrarPorStatus('inativo', this)">
                Inativos <span class="status-tab-count"><?= $total_inativos ?></span>
            </button>
            <button type="button" class="status-tab" data-status="todos" onclick="filtrarPorStatus('todos', this)">
                Todos <span class="status-tab-count"><?= count($clientes) ?></span>
            </button>
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
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $c): ?>
                        <?php
                        $avatar = !empty($c['avatar_url'])
                            ? $c['avatar_url']
                            : gerarAvatarIniciais($c['nome']);

                        $status_cliente = ($c['status'] ?? 'ativo') === 'inativo' ? 'inativo' : 'ativo';

                        $texto_busca = strtolower(
                            ($c['nome'] ?? '') . " " .
                            ($c['email'] ?? '') . " " .
                            ($c['cpf_cnpj'] ?? '') . " " .
                            ($c['telefone'] ?? '')
                        );
                        ?>
                        <tr class="linha-cliente linha-status-<?= $status_cliente ?>" data-busca="<?= safeHtml($texto_busca) ?>" data-status="<?= $status_cliente ?>">
                            <td>
                                <a href="visualizar.php?id=<?= $c['id'] ?>" style="display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit;">
                                    <img src="<?= htmlspecialchars($avatar) ?>"
                                         alt="Avatar"
                                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); flex-shrink: 0;">
                                    <div>
                                        <span class="txt-name-main"><?= safeHtml($c['nome']) ?></span>
                                        <span class="txt-meta-sm">CNPJ/CPF: <?= safeHtml($c['cpf_cnpj'] ?? 'Não informado') ?></span>
                                    </div>
                                </a>
                            </td>
                            <td>
                                <span class="txt-contact-main"><?= safeHtml($c['email'] ?? '') ?></span>
                                <span class="txt-contact-sub"><?= safeHtml($c['telefone'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($status_cliente === 'ativo'): ?>
                                    <span class="badge badge-status-ativo">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-status-inativo">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="visualizar.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon-table" title="Visualizar Cliente">
                                        <i class="ph ph-eye"></i>
                                    </a>
                                    <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm btn-icon-table" title="Editar Cliente">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" action="" style="display: contents;" onsubmit="return confirm('<?= $status_cliente === 'ativo' ? 'Desativar' : 'Ativar' ?> este cliente?');">
                                        <input type="hidden" name="acao" value="alternar_status">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <?php if ($status_cliente === 'ativo'): ?>
                                            <button type="submit" class="btn btn-ghost btn-sm btn-icon-table btn-icon-toggle-off" title="Desativar Cliente">
                                                <i class="ph ph-toggle-right"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-ghost btn-sm btn-icon-table btn-icon-toggle-on" title="Ativar Cliente">
                                                <i class="ph ph-toggle-left"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
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
                <label>E-mail</label>
                <input type="email" name="email" class="form-control" placeholder="E-mail principal">
            </div>
            <div class="form-grid-2">
                <div class="form-group">
                    <label>WhatsApp</label>
                    <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control" placeholder="Opcional">
                </div>
                <div class="form-group form-grid-2-full">
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

let statusFiltroAtual = 'ativo';

function filtrarClientes() {
    const filtroTexto = document.getElementById('filtroTexto').value.toLowerCase();
    const linhas = document.querySelectorAll('.linha-cliente');
    let visiveis = 0;

    linhas.forEach(linha => {
        const texto = linha.getAttribute('data-busca');
        const status = linha.getAttribute('data-status');

        const bateTexto = filtroTexto === '' || texto.includes(filtroTexto);
        const bateStatus = statusFiltroAtual === 'todos' || status === statusFiltroAtual;
        const mostra = bateTexto && bateStatus;

        linha.style.display = mostra ? '' : 'none';
        if (mostra) visiveis++;
    });

    document.getElementById('contadorRegistros').innerText = visiveis + ' Registros';
    document.getElementById('msgSemResultados').style.display = visiveis === 0 ? 'block' : 'none';
    document.getElementById('tabelaClientes').style.display = visiveis === 0 ? 'none' : 'table';
}

function filtrarPorStatus(status, botao) {
    statusFiltroAtual = status;

    document.querySelectorAll('.status-tab').forEach(tab => tab.classList.remove('active'));
    botao.classList.add('active');

    filtrarClientes();
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    statusFiltroAtual = 'todos';

    document.querySelectorAll('.status-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelector('.status-tab[data-status="todos"]').classList.add('active');

    filtrarClientes();
}

document.getElementById('modalNovoCliente').addEventListener('click', function(e) {
    if (e.target === this) fecharModalCliente();
});


// Inicializa a página mostrando apenas clientes ativos
document.addEventListener('DOMContentLoaded', function() {
    // Garante que a tab "Ativos" está ativa
    const tabAtiva = document.querySelector('.status-tab[data-status="ativo"]');
    if (tabAtiva) {
        document.querySelectorAll('.status-tab').forEach(tab => tab.classList.remove('active'));
        tabAtiva.classList.add('active');
    }
    // Aplica o filtro
    filtrarClientes();
});


</script>

<?php require_once '../../includes/layout/footer.php'; ?>