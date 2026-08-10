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
                $acao = 'listar';
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
    
    // --- AÇÕES: TEMA ---
    elseif ($_POST['acao'] == 'salvar_tema') {
        $tema_escolhido = $_POST['tema_ui'] ?? 'dark';
        $_SESSION['tema_ui'] = $tema_escolhido;
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET tema_ui = ? WHERE id = ?");
            $stmt->execute([$tema_escolhido, $_SESSION['usuario_id']]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Tema atualizado com sucesso!</div>";
        } catch (Exception $e) {
            $mensagem = "<div class='alert alert-warning'><i class='ph-fill ph-warning-circle'></i> Tema alterado! (Lembrete: crie a coluna 'tema_ui' na tabela usuarios).</div>";
        }
        $tab = 'tema';
    }

    // --- AÇÕES: CATEGORIAS DE TAREFAS ---
    elseif ($_POST['acao'] == 'salvar_task_categoria') {
        $id_cat = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $cor = trim($_POST['cor'] ?? '#6366f1');
        $icone = trim($_POST['icone'] ?? 'ph-list-checks');
        
        if (!empty($nome)) {
            if ($id_cat > 0) {
                $stmt = $pdo->prepare("UPDATE task_categorias SET nome = ?, cor = ?, icone = ? WHERE id = ?");
                $stmt->execute([$nome, $cor, $icone, $id_cat]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria de tarefa atualizada!</div>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO task_categorias (nome, cor, icone) VALUES (?, ?, ?)");
                $stmt->execute([$nome, $cor, $icone]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria de tarefa adicionada!</div>";
            }
        }
        $tab = 'task_categorias';
    } elseif ($_POST['acao'] == 'excluir_task_categoria') {
        $id_cat = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM task_categorias WHERE id = ?")->execute([$id_cat]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria de tarefa removida!</div>";
        $tab = 'task_categorias';
    }

    // --- AÇÕES: CATEGORIAS FINANCEIRAS ---
    elseif ($_POST['acao'] == 'salvar_fin_categoria') {
        $id_cat = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $cor = trim($_POST['cor'] ?? '#6366f1');
        $icone = trim($_POST['icone'] ?? 'ph-tag');

        if (!empty($nome)) {
            if ($id_cat > 0) {
                $stmt = $pdo->prepare("UPDATE fin_categorias SET nome = ?, cor = ?, icone = ? WHERE id = ?");
                $stmt->execute([$nome, $cor, $icone, $id_cat]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria financeira atualizada!</div>";
            } else {
                $stmt = $pdo->prepare("INSERT INTO fin_categorias (nome, cor, icone) VALUES (?, ?, ?)");
                $stmt->execute([$nome, $cor, $icone]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria financeira adicionada!</div>";
            }
        }
        $tab = 'fin_categorias';
    } elseif ($_POST['acao'] == 'excluir_fin_categoria') {
        $id_cat = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM fin_categorias WHERE id = ?")->execute([$id_cat]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Categoria financeira removida!</div>";
        $tab = 'fin_categorias';
    }
}

// --- BUSCA DE DADOS ---
$servicos = [];
$usuarios = [];
$task_categorias = [];
$fin_categorias = [];
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
} elseif ($tab == 'task_categorias') {
    $task_categorias = $pdo->query("SELECT * FROM task_categorias ORDER BY nome ASC")->fetchAll();
} elseif ($tab == 'fin_categorias') {
    $fin_categorias = $pdo->query("SELECT * FROM fin_categorias ORDER BY nome ASC")->fetchAll();
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- ===== CSS ESPECÍFICO DA PÁGINA ===== -->
<link rel="stylesheet" href="../../assets/css/servicos.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title">Configurações do Sistema</h2>
        <p class="page-subtitle">Gerencie as preferências, serviços e cadastros base da agência.</p>
    </div>
    
    <?php if ($tab == 'servicos'): ?>
        <button type="button" class="btn btn-primary" onclick="abrirModalServico()">
            <i class="ph ph-plus"></i> Novo Serviço
        </button>
    <?php elseif ($tab == 'usuarios'): ?>
        <?php if ($acao == 'listar'): ?>
            <a href="?tab=usuarios&acao=novo_usuario" class="btn btn-primary">
                <i class="ph ph-plus"></i> Novo Usuário
            </a>
        <?php else: ?>
            <a href="?tab=usuarios&acao=listar" class="btn btn-secondary">
                <i class="ph ph-arrow-left"></i> Voltar
            </a>
        <?php endif; ?>
    <?php elseif ($tab == 'fin_categorias'): ?>
        <button type="button" class="btn btn-primary" onclick="abrirModalFinNovo()">
            <i class="ph ph-plus"></i> Nova Categoria
        </button>
    <?php elseif ($tab == 'task_categorias'): ?>
        <button type="button" class="btn btn-primary" onclick="abrirModalTaskNovo()">
            <i class="ph ph-plus"></i> Nova Categoria
        </button>
    <?php endif; ?>
</div>

<?= $mensagem ?>

<!-- ===== CONTAINER PRINCIPAL ESCALÁVEL ===== -->
<div class="settings-container">
    
    <!-- MENU LATERAL (SIDEBAR DE CONFIGURAÇÕES) -->
    <aside class="settings-sidebar">
        <div class="settings-group-title">Geral</div>
        <a href="?tab=tema" class="settings-nav-link <?= $tab == 'tema' ? 'active' : '' ?>">
            <i class="ph ph-palette"></i> Aparência
        </a>
        <a href="?tab=servicos" class="settings-nav-link <?= $tab == 'servicos' ? 'active' : '' ?>">
            <i class="ph ph-briefcase"></i> Serviços
        </a>
        
        <div class="settings-group-title">Acessos</div>
        <a href="?tab=usuarios" class="settings-nav-link <?= $tab == 'usuarios' ? 'active' : '' ?>">
            <i class="ph ph-users"></i> Usuários da Equipe
        </a>
        
        <div class="settings-group-title">Cadastros Base</div>
        <a href="?tab=fin_categorias" class="settings-nav-link <?= $tab == 'fin_categorias' ? 'active' : '' ?>">
            <i class="ph ph-currency-dollar"></i> Categorias Financeiras
        </a>
        <a href="?tab=task_categorias" class="settings-nav-link <?= $tab == 'task_categorias' ? 'active' : '' ?>">
            <i class="ph ph-list-checks"></i> Categorias Tarefas
        </a>
    </aside>

    <!-- CONTEÚDO ATIVO -->
    <main class="settings-content">
        
        <!-- ====================================================== -->
        <!-- ===== ABA: SERVIÇOS ===== -->
        <!-- ====================================================== -->
        <?php if ($tab == 'servicos'): ?>
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
                                <th style="text-align: center; width: 160px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servicos as $s): ?>
                            <tr>
                                <td>
                                    <span class="txt-name-main"><?= htmlspecialchars($s['nome']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="btn-actions-wrapper">
                                        <a href="gerenciar.php?id=<?= $s['id'] ?>" class="btn btn-primary btn--sm btn-icon-table" title="Configurar Fluxo">
                                            <i class="ph ph-sliders"></i>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('Tem certeza? Isso pode afetar dados antigos.');" style="margin: 0;">
                                            <input type="hidden" name="acao" value="excluir_servico">
                                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn--sm btn-icon-table" style="color: var(--red);" title="Excluir Serviço">
                                                <i class="ph ph-trash"></i>
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
                    <p>Nenhum serviço cadastrado na agência.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ====================================================== -->
        <!-- ===== ABA: USUÁRIOS ===== -->
        <!-- ====================================================== -->
        <?php elseif ($tab == 'usuarios'): ?>
            <?php if ($acao == 'listar'): ?>
                <div class="card">
                    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                        <h3 class="card-title">Equipe e Clientes</h3>
                        <span class="badge badge-gray"><?= count($usuarios) ?> Registros</span>
                    </div>
                    <?php if (count($usuarios) > 0): ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th style="text-align: center;">Perfil</th>
                                        <th style="text-align: right; width: 120px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td><span class="txt-name-main"><?= htmlspecialchars($u['nome']) ?></span></td>
                                        <td style="color: var(--text-2);"><?= htmlspecialchars($u['email']) ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($u['perfil'] == 'admin'): ?>
                                                <span class="badge badge-red">Admin</span>
                                            <?php elseif ($u['perfil'] == 'equipe'): ?>
                                                <span class="badge badge-blue">Equipe</span>
                                            <?php else: ?>
                                                <span class="badge badge-gray">Cliente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div class="btn-actions-wrapper" style="justify-content: flex-end;">
                                                <a href="?tab=usuarios&acao=editar_usuario&id=<?= $u['id'] ?>" class="btn btn-secondary btn--sm btn-icon-table" title="Editar Usuário"><i class="ph ph-pencil-simple"></i></a>
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir o usuário <?= htmlspecialchars($u['nome']) ?>?');">
                                                    <input type="hidden" name="acao" value="excluir_usuario">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-ghost btn--sm btn-icon-table" style="color: var(--red);" title="Excluir Usuário"><i class="ph ph-trash"></i></button>
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
                            <i class="ph ph-users empty-state-icon"></i>
                            <p>Nenhum usuário cadastrado.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="card user-form-card" style="margin-left: 0;">
                    <div class="card-header"><h3 class="card-title"><?= $id > 0 ? 'Editar Usuário' : 'Novo Usuário' ?></h3></div>
                    <form method="POST" action="?tab=usuarios">
                        <input type="hidden" name="acao" value="salvar_usuario">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="form-group"><label>Nome Completo *</label><input type="text" name="nome" value="<?= htmlspecialchars($user_edit['nome']) ?>" required class="form-control"></div>
                        <div class="form-group"><label>E-mail de Acesso *</label><input type="email" name="email" value="<?= htmlspecialchars($user_edit['email']) ?>" required class="form-control"></div>
                        <div class="form-group"><label>Nível de Acesso (Perfil)</label><select name="perfil" class="form-control"><option value="equipe" <?= $user_edit['perfil'] == 'equipe' ? 'selected' : '' ?>>Membro da Equipe (Padrão)</option><option value="admin" <?= $user_edit['perfil'] == 'admin' ? 'selected' : '' ?>>Administrador Geral</option><option value="cliente" <?= $user_edit['perfil'] == 'cliente' ? 'selected' : '' ?>>Cliente Final</option></select></div>
                        <div class="senha-box"><label><?= $id > 0 ? 'Redefinir Senha' : 'Senha de Acesso' ?></label><input type="password" name="senha" placeholder="<?= $id > 0 ? 'Deixe em branco para manter a atual' : 'Digite a senha (padrão: 123456)' ?>" class="form-control"></div>
                        <div style="margin-top: 24px; text-align: right;"><button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Usuário</button></div>
                    </form>
                </div>
            <?php endif; ?>

        <!-- ====================================================== -->
        <!-- ===== ABA: TEMA ===== -->
        <!-- ====================================================== -->
        <?php elseif ($tab == 'tema'): ?>
            <div class="card user-form-card" style="margin-left: 0;">
                <div class="card-header"><h3 class="card-title">Aparência do Sistema</h3></div>
                <form method="POST" action="?tab=tema">
                    <input type="hidden" name="acao" value="salvar_tema">
                    <?php $tema_atual = $_SESSION['tema_ui'] ?? 'dark'; ?>
                    <div class="form-group mb-20">
                        <label class="mb-2">Escolha o Tema Padrão</label>
                        <div class="briefing-grid-2">
                            <div><input type="radio" name="tema_ui" value="dark" id="tema_dark" class="radio-pill-input" <?= $tema_atual == 'dark' ? 'checked' : '' ?>><label for="tema_dark" class="radio-pill-label"><i class="ph ph-moon mr-2"></i> Tema Escuro</label></div>
                            <div><input type="radio" name="tema_ui" value="light" id="tema_light" class="radio-pill-input" <?= $tema_atual == 'light' ? 'checked' : '' ?>><label for="tema_light" class="radio-pill-label"><i class="ph ph-sun mr-2"></i> Tema Claro</label></div>
                        </div>
                    </div>
                    <div class="text-right mt-3"><button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Preferência</button></div>
                </form>
            </div>
            
        <!-- ====================================================== -->
        <!-- ===== ABA: CATEGORIAS FINANCEIRAS ===== -->
        <!-- ====================================================== -->
        <?php elseif ($tab == 'fin_categorias'): ?>
            <div class="card">
                <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                    <h3 class="card-title">Categorias Financeiras</h3>
                    <span class="badge badge-gray"><?= count($fin_categorias) ?> Registros</span>
                </div>
                
                <?php if (count($fin_categorias) > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome da Categoria</th>
                                    <th style="text-align: right; width: 140px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fin_categorias as $cat): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="ph <?= htmlspecialchars($cat['icone']) ?>" style="color: <?= htmlspecialchars($cat['cor']) ?>; font-size: 18px;"></i>
                                            <span class="txt-name-main"><?= htmlspecialchars($cat['nome']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="btn-actions-wrapper" style="justify-content: flex-end;">
                                            <button type="button" class="btn btn-secondary btn--sm btn-icon-table" onclick="editarCategoriaFin(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome'], ENT_QUOTES) ?>', '<?= $cat['cor'] ?>', '<?= $cat['icone'] ?>')" title="Editar Categoria">
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria?');" style="margin: 0;">
                                                <input type="hidden" name="acao" value="excluir_fin_categoria">
                                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-ghost btn--sm btn-icon-table" style="color: var(--red);" title="Excluir Categoria">
                                                    <i class="ph ph-trash"></i>
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
                        <i class="ph ph-tag empty-state-icon"></i>
                        <p>Nenhuma categoria de pagamento cadastrada.<br>Cadastre para organizar impostos, saúde, infraestrutura, etc.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        <!-- ====================================================== -->
        <!-- ===== ABA: CATEGORIAS DE TAREFAS ===== -->
        <!-- ====================================================== -->
        <?php elseif ($tab == 'task_categorias'): ?>
            <div class="card">
                <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                    <h3 class="card-title">Categorias de Tarefas</h3>
                    <span class="badge badge-gray"><?= count($task_categorias) ?> Registros</span>
                </div>
                
                <?php if (count($task_categorias) > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nome da Categoria</th>
                                    <th style="text-align: right; width: 140px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($task_categorias as $cat): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="ph <?= htmlspecialchars($cat['icone'] ?? 'ph-list-checks') ?>" style="color: <?= htmlspecialchars($cat['cor'] ?? '#6366f1') ?>; font-size: 18px;"></i>
                                            <span class="txt-name-main"><?= htmlspecialchars($cat['nome']) ?></span>
                                        </div>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="btn-actions-wrapper" style="justify-content: flex-end;">
                                            <button type="button" class="btn btn-secondary btn--sm btn-icon-table" onclick="editarCategoriaTask(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['nome'], ENT_QUOTES) ?>', '<?= $cat['cor'] ?>', '<?= $cat['icone'] ?>')" title="Editar Categoria">
                                                <i class="ph ph-pencil-simple"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta categoria? As tarefas associadas perderão a tag, mas não serão apagadas.');" style="margin: 0;">
                                                <input type="hidden" name="acao" value="excluir_task_categoria">
                                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                <button type="submit" class="btn btn-ghost btn--sm btn-icon-table" style="color: var(--red);" title="Excluir Categoria">
                                                    <i class="ph ph-trash"></i>
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
                        <i class="ph ph-list-checks empty-state-icon"></i>
                        <p>Nenhuma categoria de tarefa cadastrada.<br>Cadastre para organizar demandas como Design, Reuniões, Edição de Vídeo.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
        
    </main>
</div>

<!-- ===== MODAIS ===== -->
<div id="modalNovoServico" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalServico()"><i class="ph ph-x"></i></button>
        <h3 class="modal-title">Cadastrar Novo Serviço</h3>
        <form method="POST" action="?tab=servicos">
            <input type="hidden" name="acao" value="novo_servico">
            <div class="form-group mb-20">
                <label>Nome do Serviço *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Gestão de Tráfego...">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-floppy-disk"></i> Salvar Serviço</button>
        </form>
    </div>
</div>

<div id="modalNovoFin" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalFin()"><i class="ph ph-x"></i></button>
        <h3 class="modal-title">Nova Categoria Financeira</h3>
        <form method="POST" action="?tab=fin_categorias">
            <input type="hidden" name="acao" value="salvar_fin_categoria">
            <input type="hidden" name="id" value="">
            
            <div class="form-group mb-20">
                <label>Nome da Categoria *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Impostos, Saúde, Infraestrutura...">
            </div>
            
            <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group mb-20">
                    <label>Cor</label>
                    <input type="color" name="cor" class="form-control" value="#6366f1" style="height: 44px; padding: 5px; cursor: pointer;">
                </div>
                <div class="form-group mb-20">
                    <label>Ícone</label>
                    <select name="icone" class="form-control">
                        <option value="ph-tag">Tag (Padrão)</option>
                        <option value="ph-house">Casa / Moradia</option>
                        <option value="ph-fork-knife">Alimentação</option>
                        <option value="ph-car">Carro / Transporte</option>
                        <option value="ph-heart">Saúde / Coração</option>
                        <option value="ph-graduation-cap">Educação</option>
                        <option value="ph-game-controller">Lazer / Jogos</option>
                        <option value="ph-bank">Banco / Impostos</option>
                        <option value="ph-package">Fornecedores</option>
                        <option value="ph-device-mobile">Serviços / Celular</option>
                        <option value="ph-shopping-cart">Compras / Mercado</option>
                        <option value="ph-airplane-tilt">Viagens</option>
                        <option value="ph-dots-three">Outros</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-floppy-disk"></i> Salvar Categoria</button>
        </form>
    </div>
</div>

<div id="modalNovoTask" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalTask()"><i class="ph ph-x"></i></button>
        <h3 class="modal-title">Nova Categoria de Tarefa</h3>
        <form method="POST" action="?tab=task_categorias">
            <input type="hidden" name="acao" value="salvar_task_categoria">
            <input type="hidden" name="id" value="">
            
            <div class="form-group mb-20">
                <label>Nome da Categoria *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Edição de Vídeo, Criativos, Reunião...">
            </div>
            
            <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group mb-20">
                    <label>Cor</label>
                    <input type="color" name="cor" class="form-control" value="#6366f1" style="height: 44px; padding: 5px; cursor: pointer;">
                </div>
                <div class="form-group mb-20">
                    <label>Ícone</label>
                    <select name="icone" class="form-control">
                        <option value="ph-list-checks">Checklist (Padrão)</option>
                        <option value="ph-video-camera">Vídeo / Captação</option>
                        <option value="ph-image">Imagem / Design</option>
                        <option value="ph-pen-nib">Redação / Copy</option>
                        <option value="ph-users">Reunião / Cliente</option>
                        <option value="ph-magnifying-glass">Análise / Revisão</option>
                        <option value="ph-gear">Operacional / Sistema</option>
                        <option value="ph-instagram-logo">Redes Sociais</option>
                        <option value="ph-envelope-simple">E-mail / Contato</option>
                        <option value="ph-article">Blog / Artigo</option>
                        <option value="ph-currency-dollar">Orçamento / Vendas</option>
                        <option value="ph-star">Importante / Especial</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-floppy-disk"></i> Salvar Categoria</button>
        </form>
    </div>
</div>

<script>
function abrirModalServico() {
    const modal = document.getElementById('modalNovoServico');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function fecharModalServico() {
    const modal = document.getElementById('modalNovoServico');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 300);
}

function abrirModalFin() {
    const modal = document.getElementById('modalNovoFin');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function fecharModalFin() {
    const modal = document.getElementById('modalNovoFin');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 300);
}

function abrirModalTask() {
    const modal = document.getElementById('modalNovoTask');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}
function fecharModalTask() {
    const modal = document.getElementById('modalNovoTask');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 300);
}

// Funções para resetar os modais para modo "Novo"
function abrirModalFinNovo() {
    document.querySelector('#modalNovoFin input[name="id"]').value = '';
    document.querySelector('#modalNovoFin input[name="nome"]').value = '';
    document.querySelector('#modalNovoFin input[name="cor"]').value = '#6366f1';
    document.querySelector('#modalNovoFin select[name="icone"]').value = 'ph-tag';
    document.querySelector('#modalNovoFin .modal-title').innerText = 'Nova Categoria Financeira';
    abrirModalFin();
}

function abrirModalTaskNovo() {
    document.querySelector('#modalNovoTask input[name="id"]').value = '';
    document.querySelector('#modalNovoTask input[name="nome"]').value = '';
    document.querySelector('#modalNovoTask input[name="cor"]').value = '#6366f1';
    document.querySelector('#modalNovoTask select[name="icone"]').value = 'ph-list-checks';
    document.querySelector('#modalNovoTask .modal-title').innerText = 'Nova Categoria de Tarefa';
    abrirModalTask();
}

// Funções para popular os modais para modo "Editar"
function editarCategoriaFin(id, nome, cor, icone) {
    document.querySelector('#modalNovoFin input[name="id"]').value = id;
    document.querySelector('#modalNovoFin input[name="nome"]').value = nome;
    document.querySelector('#modalNovoFin input[name="cor"]').value = cor;
    document.querySelector('#modalNovoFin select[name="icone"]').value = icone;
    document.querySelector('#modalNovoFin .modal-title').innerText = 'Editar Categoria Financeira';
    abrirModalFin();
}

function editarCategoriaTask(id, nome, cor, icone) {
    document.querySelector('#modalNovoTask input[name="id"]').value = id;
    document.querySelector('#modalNovoTask input[name="nome"]').value = nome;
    document.querySelector('#modalNovoTask input[name="cor"]').value = cor;
    document.querySelector('#modalNovoTask select[name="icone"]').value = icone;
    document.querySelector('#modalNovoTask .modal-title').innerText = 'Editar Categoria de Tarefa';
    abrirModalTask();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>