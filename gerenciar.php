<?php
// modules/usuarios/gerenciar.php

require_once '../../config/session.php';
require_once '../../config/database.php';

// Bloqueio rigoroso: Apenas logados E com perfil de admin
requireLogin();
if (!isAdmin()) {
    die("<div style='padding:50px; text-align:center;'><h2>Acesso Negado</h2><p>Apenas administradores podem acessar esta área.</p><a href='../../index.php'>Voltar ao Início</a></div>");
}

$mensagem = '';
$acao = $_REQUEST['acao'] ?? 'listar';
$id = (int)($_REQUEST['id'] ?? 0);

// --- PROCESSAMENTO DO FORMULÁRIO (SALVAR / EXCLUIR) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($acao == 'salvar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $perfil = trim($_POST['perfil'] ?? 'equipe');
        $senha_input = $_POST['senha'] ?? '';

        if (empty($nome) || empty($email)) {
            $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Nome e E-mail são obrigatórios.</div>";
            $acao = $id > 0 ? 'editar' : 'novo'; // Volta pro formulário
        } else {
            try {
                if ($id > 0) {
                    // ATUALIZAR (EDITAR)
                    if (!empty($senha_input)) {
                        // Redefinindo a senha
                        $senha_hash = password_hash($senha_input, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, senha = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $perfil, $senha_hash, $id]);
                        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Usuário e senha atualizados com sucesso!</div>";
                    } else {
                        // Mantendo a senha antiga
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$nome, $email, $perfil, $id]);
                        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Dados do usuário atualizados! (Senha mantida)</div>";
                    }
                } else {
                    // INSERIR (NOVO)
                    if (empty($senha_input)) $senha_input = '123456'; // Senha padrão de segurança caso esqueçam
                    $senha_hash = password_hash($senha_input, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, perfil, senha) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nome, $email, $perfil, $senha_hash]);
                    $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Novo usuário cadastrado com sucesso!</div>";
                }
                $acao = 'listar';
            } catch (Exception $e) {
                $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: E-mail já cadastrado ou falha no banco.</div>";
                $acao = $id > 0 ? 'editar' : 'novo';
            }
        }
    } elseif ($acao == 'excluir') {
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
$usuarios = [];
$user_edit = ['id' => 0, 'nome' => '', 'email' => '', 'perfil' => 'equipe']; // Default

if ($acao == 'listar') {
    $usuarios = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY nome ASC")->fetchAll();
} elseif ($acao == 'editar' && $id > 0) {
    $stmt = $pdo->prepare("SELECT id, nome, email, perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $user_edit = $stmt->fetch() ?: $user_edit;
}

// Inclusão do layout padrão (ajuste o caminho se necessário)
require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Gerenciar Usuários</h2>
        <p class="page-subtitle">Controle de acesso exclusivo para Administradores.</p>
    </div>
    
    <?php if ($acao == 'listar'): ?>
        <a href="?acao=novo" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Usuário</a>
    <?php else: ?>
        <a href="?acao=listar" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Voltar</a>
    <?php endif; ?>
</div>

<?= $mensagem ?>

<?php if ($acao == 'listar'): ?>
    <!-- LISTAGEM DE USUÁRIOS -->
    <div class="card">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th style="text-align: center;">Perfil / Permissão</th>
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
                            <a href="?acao=editar&id=<?= $u['id'] ?>" class="btn btn-secondary btn--sm"><i class="ph ph-pencil-simple"></i> Editar</a>
                            <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir o usuário <?= $u['nome'] ?>?');">
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn--sm" style="color: red; border-color: rgba(255,0,0,0.2);"><i class="ph ph-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- FORMULÁRIO DE CADASTRO / EDIÇÃO -->
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header"><h3 class="card-title"><?= $id > 0 ? 'Editar Usuário' : 'Novo Usuário' ?></h3></div>
        
        <form method="POST" action="?acao=salvar" style="display: flex; flex-direction: column; gap: 15px;">
            <input type="hidden" name="id" value="<?= $id ?>">
            
            <div><label>Nome Completo</label><input type="text" name="nome" value="<?= htmlspecialchars($user_edit['nome']) ?>" required class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc;"></div>
            <div><label>E-mail de Acesso</label><input type="email" name="email" value="<?= htmlspecialchars($user_edit['email']) ?>" required class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc;"></div>
            <div><label>Nível de Acesso (Perfil)</label><select name="perfil" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc;"><option value="equipe" <?= $user_edit['perfil'] == 'equipe' ? 'selected' : '' ?>>Membro da Equipe (Padrão)</option><option value="admin" <?= $user_edit['perfil'] == 'admin' ? 'selected' : '' ?>>Administrador Geral</option><option value="cliente" <?= $user_edit['perfil'] == 'cliente' ? 'selected' : '' ?>>Cliente Final</option></select></div>
            <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px dashed #ccc;"><label><strong><?= $id > 0 ? 'Redefinir Senha' : 'Senha de Acesso' ?></strong></label><input type="password" name="senha" placeholder="<?= $id > 0 ? 'Deixe em branco para manter a senha atual' : 'Digite a senha do usuário' ?>" class="form-control" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-top: 5px;"><small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">Se esquecer a senha, o Admin pode alterá-la digitando uma nova aqui.</small></div>
            <div style="margin-top: 10px; text-align: right;"><button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Usuário</button></div>
        </form>
    </div>
<?php endif; ?>

<?php require_once '../../includes/layout/footer.php'; ?>
