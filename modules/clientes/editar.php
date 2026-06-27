<?php
// modules/clientes/editar.php - VERSÃO SEM DEPENDÊNCIA GD

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_avatar.php';

requireLogin();

$id = $_GET['id'] ?? 0;

// Função de upload sem GD - apenas move o arquivo original
function uploadAvatar($file, $cliente_id) {
    // Detecta o caminho base do projeto
    $base_path = dirname(__DIR__, 2); // Sobe dois níveis a partir de modules/clientes
    $upload_dir = $base_path . '/uploads/avatars/';
    
    // Cria o diretório se não existir
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Validações...
    $check = @getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'error' => 'Arquivo não é uma imagem válida.'];
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Arquivo muito grande. Máximo: 5MB.'];
    }
    
    // Gera nome único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'avatar_' . $cliente_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move o arquivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Constrói a URL correta
        $doc_root = $_SERVER['DOCUMENT_ROOT'];
        $relative_path = str_replace($doc_root, '', $filepath);
        $relative_path = str_replace('\\', '/', $relative_path);
        
        // Se o caminho começa com /, mantém
        if (substr($relative_path, 0, 1) !== '/') {
            $relative_path = '/' . $relative_path;
        }
        
        return ['success' => true, 'url' => $relative_path];
    }
    
    return ['success' => false, 'error' => 'Erro ao fazer upload do arquivo.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica se deve remover o avatar
    $remover_avatar = isset($_POST['remover_avatar']) && $_POST['remover_avatar'] == '1';
    
    // Processa o upload manual se existir
    if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] === UPLOAD_ERR_OK) {
        $result = uploadAvatar($_FILES['avatar_upload'], $id);
        if ($result['success']) {
            // Atualiza o avatar_url com o upload manual
            $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$result['url'], $id]);
            $_SESSION['mensagem_avatar'] = 'Avatar atualizado com sucesso!';
        } else {
            $_SESSION['erro_avatar'] = $result['error'];
        }
    } elseif ($remover_avatar) {
        // Remove o avatar do banco e do sistema
        $stmt = $pdo->prepare("SELECT avatar_url FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch();
        
        if ($cliente && !empty($cliente['avatar_url'])) {
            // Remove o arquivo físico se for um upload manual
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $cliente['avatar_url'];
            if (strpos($cliente['avatar_url'], '/uploads/avatars/') !== false && file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Atualiza o banco
        $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = NULL WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['mensagem_avatar'] = 'Avatar removido com sucesso!';
    }
    
    // Atualiza todos os dados
    $sql = "UPDATE clientes SET 
            nome = ?, 
            email = ?, 
            telefone = ?, 
            cpf_cnpj = ?, 
            endereco = ?, 
            user_insta = ?, 
            user_fb = ?, 
            user_tt = ?, 
            user_li = ?, 
            user_yt = ?, 
            posts_semanais = ?, 
            videos_semana = ?, 
            estaticos_semana = ?, 
            roteiros = ?, 
            captacao_mensal = ?, 
            trafego_pago = ?, 
            link_drive = ?, 
            link_referencias = ?, 
            observacoes = ? 
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['nome'] ?? '', 
        $_POST['email'] ?? '', 
        $_POST['telefone'] ?? '', 
        $_POST['cpf_cnpj'] ?? '', 
        $_POST['endereco'] ?? '',
        $_POST['user_insta'] ?? '', 
        $_POST['user_fb'] ?? '', 
        $_POST['user_tt'] ?? '', 
        $_POST['user_li'] ?? '', 
        $_POST['user_yt'] ?? '',
        $_POST['posts_semanais'] ?? 0, 
        $_POST['videos_semana'] ?? 0, 
        $_POST['estaticos_semana'] ?? 0, 
        $_POST['roteiros'] ?? 0, 
        $_POST['captacao_mensal'] ?? 0, 
        $_POST['trafego_pago'] ?? 0,
        $_POST['link_drive'] ?? '', 
        $_POST['link_referencias'] ?? '', 
        $_POST['observacoes'] ?? '', 
        $id
    ]);

    // Se não fez upload manual, não removeu e tem Instagram, busca do Instagram
    if (!isset($_FILES['avatar_upload']) || $_FILES['avatar_upload']['error'] !== UPLOAD_ERR_OK) {
        if (!$remover_avatar && !empty($_POST['user_insta'])) {
            // Verifica se já tem avatar manual
            $stmt_check = $pdo->prepare("SELECT avatar_url FROM clientes WHERE id = ?");
            $stmt_check->execute([$id]);
            $current = $stmt_check->fetch();
            
            // Só busca do Instagram se não tiver avatar manual
            if (empty($current['avatar_url']) || strpos($current['avatar_url'], '/uploads/avatars/') === false) {
                salvarAvatarCliente($id, $_POST['user_insta'], $pdo);
            }
        }
    }

    // Redireciona com mensagens
    $redirect = "visualizar.php?id=" . $id;
    if (isset($_SESSION['erro_avatar'])) {
        $redirect .= "&erro_avatar=" . urlencode($_SESSION['erro_avatar']);
        unset($_SESSION['erro_avatar']);
    }
    if (isset($_SESSION['mensagem_avatar'])) {
        $redirect .= "&mensagem=" . urlencode($_SESSION['mensagem_avatar']);
        unset($_SESSION['mensagem_avatar']);
    }
    header("Location: " . $redirect);
    exit;
}

// Busca o cliente
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) die("Cliente não encontrado.");

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- CSS ESPECÍFICO DA PÁGINA -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<style>
.avatar-upload-area {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}
.avatar-preview-box {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid var(--border-color);
    flex-shrink: 0;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-preview-box i {
    font-size: 40px;
    color: var(--text-secondary);
}
.avatar-upload-actions {
    flex: 1;
}
.avatar-upload-actions .btn-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
</style>

<div class="cabecalho">
    <div class="cabecalho-cliente">
        <div class="avatar-preview-box" id="avatarPreviewContainer">
            <?php if (!empty($cliente['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($cliente['avatar_url']) ?>" 
                     alt="Avatar" 
                     id="avatarPreview">
            <?php else: ?>
                <i class="ph ph-user" id="avatarPreview"></i>
            <?php endif; ?>
        </div>
        <div>
            <h2 class="page-title">Editar Cliente</h2>
            <p class="page-subtitle"><?= htmlspecialchars($cliente['nome'] ?? '') ?></p>
        </div>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="visualizar.php?id=<?= $id ?>" class="btn btn-secondary">
            <i class="ph ph-eye"></i> Visualizar
        </a>
        <a href="index.php" class="btn btn-secondary">
            <i class="ph ph-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<?php if (isset($_GET['erro_avatar'])): ?>
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <i class="ph-fill ph-warning-circle"></i> <?= htmlspecialchars($_GET['erro_avatar']) ?>
    </div>
<?php endif; ?>
<?php if (isset($_GET['mensagem'])): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <i class="ph-fill ph-check-circle"></i> <?= htmlspecialchars($_GET['mensagem']) ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="grid-2col">
        
        <!-- Coluna Esquerda -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Card: Dados do Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-user"></i> Dados do Cliente</h3>
                </div>
                <div class="card-body">
                    <!-- Área de Upload do Avatar -->
                    <div class="form-group">
                        <label>Avatar / Foto do Cliente</label>
                        <div class="avatar-upload-area">
                            <div class="avatar-preview-box" id="avatarPreviewContainer2">
                                <?php if (!empty($cliente['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($cliente['avatar_url']) ?>" 
                                         alt="Avatar" 
                                         id="avatarPreview2">
                                <?php else: ?>
                                    <i class="ph ph-user" id="avatarPreview2"></i>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-upload-actions">
                                <div class="btn-group">
                                    <label for="avatar_upload" class="btn btn-secondary" style="cursor: pointer; margin: 0;">
                                        <i class="ph ph-upload"></i> Enviar Foto
                                    </label>
                                    <input type="file" 
                                           id="avatar_upload" 
                                           name="avatar_upload" 
                                           accept="image/*" 
                                           style="display: none;"
                                           onchange="previewAvatarUpload(this)">
                                    <button type="button" class="btn btn-ghost" onclick="removerAvatar()">
                                        <i class="ph ph-x"></i> Remover
                                    </button>
                                </div>
                                <small style="color: var(--text-secondary); display: block; margin-top: 4px;">
                                    <i class="ph ph-info"></i> Formatos: JPG, PNG, GIF, WEBP. Máx: 5MB
                                </small>
                                <small style="color: var(--text-secondary); display: block;">
                                    Se não enviar foto, o sistema buscará do Instagram automaticamente
                                </small>
                            </div>
                        </div>
                        <input type="hidden" name="remover_avatar" id="removerAvatarInput" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Empresa / Nome *</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>E-mail *</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>CPF / CNPJ</label>
                        <input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Endereço</label>
                        <textarea name="endereco" class="form-control" rows="3"><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Card: Redes Sociais -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-share-network"></i> Redes Sociais (@)</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label><i class="ph ph-instagram-logo" style="color: #E4405F;"></i> Instagram</label>
                            <input type="text" name="user_insta" class="form-control" placeholder="@usuario" value="<?= htmlspecialchars($cliente['user_insta'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="ph ph-facebook-logo" style="color: #1877F2;"></i> Facebook</label>
                            <input type="text" name="user_fb" class="form-control" placeholder="@usuario" value="<?= htmlspecialchars($cliente['user_fb'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="ph ph-tiktok-logo"></i> TikTok</label>
                            <input type="text" name="user_tt" class="form-control" placeholder="@usuario" value="<?= htmlspecialchars($cliente['user_tt'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="ph ph-linkedin-logo" style="color: #0A66C2;"></i> LinkedIn</label>
                            <input type="text" name="user_li" class="form-control" placeholder="@usuario" value="<?= htmlspecialchars($cliente['user_li'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label><i class="ph ph-youtube-logo" style="color: #FF0000;"></i> YouTube</label>
                            <input type="text" name="user_yt" class="form-control" placeholder="@usuario" value="<?= htmlspecialchars($cliente['user_yt'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Card: Escopo Operacional -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-chart-bar"></i> Escopo Operacional Mensal</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label>Posts / Semana</label>
                            <input type="number" name="posts_semanais" class="form-control" min="0" value="<?= htmlspecialchars($cliente['posts_semanais'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Vídeos / Semana</label>
                            <input type="number" name="videos_semana" class="form-control" min="0" value="<?= htmlspecialchars($cliente['videos_semana'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Estáticos / Semana</label>
                            <input type="number" name="estaticos_semana" class="form-control" min="0" value="<?= htmlspecialchars($cliente['estaticos_semana'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Roteiros (Qtd)</label>
                            <input type="number" name="roteiros" class="form-control" min="0" value="<?= htmlspecialchars($cliente['roteiros'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Diárias de Captação</label>
                            <input type="number" name="captacao_mensal" class="form-control" min="0" value="<?= htmlspecialchars($cliente['captacao_mensal'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Tráfego Pago</label>
                            <select name="trafego_pago" class="form-control">
                                <option value="0" <?= ($cliente['trafego_pago'] ?? 0) == 0 ? 'selected' : '' ?>>Não</option>
                                <option value="1" <?= ($cliente['trafego_pago'] ?? 0) == 1 ? 'selected' : '' ?>>Sim</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Links & Observações -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-link"></i> Links & Observações</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label><i class="ph ph-folder"></i> Pasta no Drive</label>
                        <input type="url" name="link_drive" class="form-control" placeholder="https://drive.google.com/..." value="<?= htmlspecialchars($cliente['link_drive'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-book"></i> Link de Referências</label>
                        <input type="url" name="link_referencias" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($cliente['link_referencias'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label><i class="ph ph-note"></i> Observações</label>
                        <textarea name="observacoes" class="form-control" rows="4"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Botão Salvar -->
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 0 32px; height: 44px;">
                    <i class="ph ph-check"></i> Salvar Alterações
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function previewAvatarUpload(input) {
    const container = document.getElementById('avatarPreviewContainer2');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            container.innerHTML = `
                <img src="${e.target.result}" 
                     alt="Preview" 
                     id="avatarPreview2"
                     style="width: 100%; height: 100%; object-fit: cover;">
            `;
        };
        reader.readAsDataURL(input.files[0]);
        
        // Marca que não vai remover
        document.getElementById('removerAvatarInput').value = '0';
    }
}

function removerAvatar() {
    if (confirm('Deseja remover o avatar atual?')) {
        document.getElementById('removerAvatarInput').value = '1';
        const container = document.getElementById('avatarPreviewContainer2');
        container.innerHTML = `<i class="ph ph-user" id="avatarPreview2"></i>`;
        document.getElementById('avatar_upload').value = '';
    }
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>