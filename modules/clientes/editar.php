<?php
// modules/clientes/editar.php - VERSÃO COMPLETA

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/functions_avatar.php';

requireLogin();

$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Atualiza o avatar baseado no Instagram
    if (!empty($_POST['user_insta'])) {
        salvarAvatarCliente($id, $_POST['user_insta'], $pdo);
    } else {
        // Se removeu o Instagram, remove o avatar
        $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = NULL WHERE id = ?");
        $stmt->execute([$id]);
    }

    header("Location: visualizar.php?id=" . $id); 
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

<div class="cabecalho">
    <div class="cabecalho-cliente">
        <?php if (!empty($cliente['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($cliente['avatar_url']) ?>" 
                 alt="Avatar do Instagram" 
                 class="avatar-cliente">
        <?php else: ?>
            <div class="avatar-placeholder">
                <i class="ph ph-user"></i>
            </div>
        <?php endif; ?>
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

<form method="POST">
    <div class="grid-2col">
        
        <!-- Coluna Esquerda -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Card: Dados do Cliente -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="ph ph-user"></i> Dados do Cliente</h3>
                </div>
                <div class="card-body">
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

<?php require_once '../../includes/layout/footer.php'; ?>