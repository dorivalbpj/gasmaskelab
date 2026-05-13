<?php
// modules/equipe/servicos.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';
$tab = $_GET['tab'] ?? 'servicos';
$acao = $_REQUEST['acao'] ?? 'listar';
$id = (int)($_REQUEST['id'] ?? 0);

// --- PROCESSAMENTO DOS FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // --- AÇÕES: SERVIÇOS ---
    if ($_POST['acao'] == 'novo_servico') {
        $nome = trim($_POST['nome'] ?? '');
        if (!empty($nome)) {
            $stmt = $pdo->prepare("INSERT INTO servicos (nome, descricao_padrao, clausulas_padrao) VALUES (?, ?, ?)");
            $stmt->execute([$nome, '', '']);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Serviço adicionado com sucesso!</div>";
        }
    } elseif ($_POST['acao'] == 'excluir_servico') {
        $id_servico = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([$id_servico]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Serviço removido!</div>";
    }

    // --- AÇÕES: USUÁRIOS ---
    elseif ($_POST['acao'] == 'salvar_usuario') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $perfil = trim($_POST['perfil'] ?? 'equipe');
        $senha_input = $_POST['senha'] ?? '';

        if (empty($nome) || empty($email)) {
            $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Nome e E-mail são obrigatórios.</div>";
            $acao = $id > 0 ? 'editar_usuario' : 'novo_usuario';
        } else {
            try {
                if ($id > 0) {
                    if (!empty($senha_input)) {
                        $senha_hash = password_hash($senha_input, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, senha = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $perfil, $senha_hash, $id]);
                        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Usuário e senha atualizados!</div>";
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $perfil, $id]);
                        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Dados do usuário atualizados!</div>";
                    }
                } else {
                    if (empty($senha_input)) $senha_input = '123456';
                    $senha_hash = password_hash($senha_input, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, perfil, senha) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nome, $email, $perfil, $senha_hash]);
                    $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Novo usuário cadastrado!</div>";
                }
                $acao = 'listar'; // Volta pra tabela
            } catch (Exception $e) {
                $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: E-mail já cadastrado.</div>";
                $acao = $id > 0 ? 'editar_usuario' : 'novo_usuario';
            }
        }
    } elseif ($_POST['acao'] == 'excluir_usuario') {
        if ($id == $_SESSION['usuario_id']) {
            $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Ação bloqueada: Você não pode excluir seu próprio usuário!</div>";
        } else {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-trash'></i> Usuário removido permanentemente.</div>";
        }
        $acao = 'listar';
    }
}

// --- BUSCA DE DADOS ---
$servicos = [];
$usuarios = [];
$user_edit = ['id' => 0, 'nome' => '', 'email' => '', 'perfil' => 'equipe'];

if ($tab == 'servicos') {
    $servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
} elseif ($tab == 'usuarios') {
    if ($acao == 'listar') {
        $usuarios = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY nome ASC")->fetchAll();
    } elseif ($acao == 'editar_usuario' && $id > 0) {
        $stmt = $pdo->prepare("SELECT id, nome, email, perfil FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $user_edit = $stmt->fetch() ?: $user_edit;
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<style>
    .tabs-container {
        display: flex;
        gap: 20px;
        border-bottom: 2px solid var(--border);
        margin-bottom: 24px;
    }
    .tab-link {
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tab-link:hover {
        color: var(--text-primary);
    }
    .tab-link.active {
        color: var(--blue);
        border-bottom-color: var(--blue);
    }
</style>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Configurações Gerais</h2>
        <p class="page-subtitle">Gerencie os serviços e a equipe da agência.</p>
    </div>
    
    <?php if ($tab == 'servicos'): ?>
        <button type="button" class="btn btn-primary" onclick="abrirModalServico()">
            <i class="ph ph-plus"></i> Novo Serviço
        </button>
    <?php elseif ($tab == 'usuarios'): ?>
        <?php if ($acao == 'listar'): ?>
            <a href="?tab=usuarios&acao=novo_usuario" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Usuário</a>
        <?php else: ?>
            <a href="?tab=usuarios&acao=listar" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Voltar</a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= $mensagem ?>

<div class="tabs-container">
    <a href="?tab=servicos" class="tab-link <?= $tab == 'servicos' ? 'active' : '' ?>">
        <i class="ph ph-briefcase"></i> Catálogo de Serviços
    </a>
    <a href="?tab=usuarios" class="tab-link <?= $tab == 'usuarios' ? 'active' : '' ?>">
        <i class="ph ph-users"></i> Gestão de Usuários
    </a>
</div>

<?php if ($tab == 'servicos'): ?>
    <!-- === ABA DE SERVIÇOS === -->
    <div class="card">
        <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
            <h3 class="card-title">Catálogo de Serviços</h3>
            <span class="badge badge-gray"><?= count($servicos) ?> Registros</span>
        </div>
        
        <?php if (count($servicos) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nome do Serviço</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servicos as $s): ?>
                        <tr>
                            <td>
                                <strong class="txt-name-main" style="margin-bottom: 4px;"><?= htmlspecialchars($s['nome']) ?></strong>
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                    <a href="gerenciar.php?id=<?= $s['id'] ?>" class="btn btn-primary btn--sm" style="padding: 6px 10px;" title="Configurar Fluxo">
                                        <i class="ph ph-sliders" style="font-size: 18px;"></i> Configurar
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Tem certeza? Isso pode afetar dados antigos.');" style="margin: 0;">
                                        <input type="hidden" name="acao" value="excluir_servico">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn--sm" style="color: var(--red); padding: 6px 10px;" title="Excluir Serviço">
                                            <i class="ph ph-trash" style="font-size: 18px;"></i>
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
            <div class="empty-state empty-state-padded">
                <i class="ph ph-briefcase empty-state-icon"></i>
                Nenhum serviço cadastrado na agência.
            </div>
        <?php endif; ?>
    </div>

    <div id="modalNovoServico" class="modal-overlay">
        <div class="modal-box">
            <button type="button" class="modal-close-btn" onclick="fecharModalServico()"><i class="ph ph-x"></i></button>
            <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Cadastrar Novo Serviço</h3>
            
            <form method="POST" action="?tab=servicos">
                <input type="hidden" name="acao" value="novo_servico">
                
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Nome do Serviço *</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Gestão de Tráfego...">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">
                    <i class="ph ph-floppy-disk"></i> Salvar Serviço
                </button>
            </form>
        </div>
    </div>

    <script>
    function abrirModalServico() { const modal = document.getElementById('modalNovoServico'); modal.style.display = 'flex'; setTimeout(() => modal.classList.add('active'), 10); }
    function fecharModalServico() { const modal = document.getElementById('modalNovoServico'); modal.classList.remove('active'); setTimeout(() => modal.style.display = 'none', 300); }
    </script>

<?php elseif ($tab == 'usuarios'): ?>
    <!-- === ABA DE USUÁRIOS === -->
    <?php if ($acao == 'listar'): ?>
        <div class="card">
            <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                <h3 class="card-title">Equipe e Clientes</h3>
                <span class="badge badge-gray"><?= count($usuarios) ?> Registros</span>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th style="text-align: center;">Perfil</th>
                            <th style="text-align: right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                            <td style="color: var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
                            <td style="text-align: center;">
                                <?php if ($u['perfil'] == 'admin') echo '<span class="badge badge-red">Admin</span>'; ?>
                                <?php if ($u['perfil'] == 'equipe') echo '<span class="badge badge-blue">Equipe</span>'; ?>
                                <?php if ($u['perfil'] == 'cliente') echo '<span class="badge badge-gray">Cliente</span>'; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                    <a href="?tab=usuarios&acao=editar_usuario&id=<?= $u['id'] ?>" class="btn btn-secondary btn--sm"><i class="ph ph-pencil-simple"></i> Editar</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir o usuário <?= htmlspecialchars($u['nome']) ?>?');">
                                        <input type="hidden" name="acao" value="excluir_usuario">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-ghost btn--sm" style="color: var(--red); padding: 6px 10px;"><i class="ph ph-trash" style="font-size: 16px;"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <div class="card-header"><h3 class="card-title"><?= $id > 0 ? 'Editar Usuário' : 'Novo Usuário' ?></h3></div>
            
            <form method="POST" action="?tab=usuarios" style="display: flex; flex-direction: column; gap: 15px; padding: 20px;">
                <input type="hidden" name="acao" value="salvar_usuario">
                <input type="hidden" name="id" value="<?= $id ?>">
                
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nome Completo</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($user_edit['nome']) ?>" required class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">E-mail de Acesso</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user_edit['email']) ?>" required class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px;">Nível de Acesso (Perfil)</label>
                    <select name="perfil" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border);">
                        <option value="equipe" <?= $user_edit['perfil'] == 'equipe' ? 'selected' : '' ?>>Membro da Equipe (Padrão)</option>
                        <option value="admin" <?= $user_edit['perfil'] == 'admin' ? 'selected' : '' ?>>Administrador Geral</option>
                        <option value="cliente" <?= $user_edit['perfil'] == 'cliente' ? 'selected' : '' ?>>Cliente Final</option>
                    </select>
                </div>
                <div style="background: var(--bg-surface); padding: 15px; border-radius: 6px; border: 1px dashed var(--border-mid);">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: var(--text-primary);">
                        <?= $id > 0 ? 'Redefinir Senha' : 'Senha de Acesso' ?>
                    </label>
                    <input type="password" name="senha" placeholder="<?= $id > 0 ? 'Deixe em branco para manter a atual' : 'Digite a senha (padrão: 123456)' ?>" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border);">
                </div>
                <div style="margin-top: 10px; text-align: right;">
                    <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Usuário</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once '../../includes/layout/footer.php'; ?>