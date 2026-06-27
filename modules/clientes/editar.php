<?php
// modules/clientes/editar.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE clientes SET nome = ?, email = ?, telefone = ?, cpf_cnpj = ?, endereco = ?, user_insta = ?, user_fb = ?, user_tt = ?, user_li = ?, user_yt = ?, posts_semanais = ?, videos_semana = ?, estaticos_semana = ?, roteiros = ?, captacao_mensal = ?, trafego_pago = ?, link_drive = ?, link_referencias = ?, observacoes = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['nome'] ?? '', $_POST['email'] ?? '', $_POST['telefone'] ?? '', $_POST['cpf_cnpj'] ?? '', $_POST['endereco'] ?? '',
        $_POST['user_insta'] ?? '', $_POST['user_fb'] ?? '', $_POST['user_tt'] ?? '', $_POST['user_li'] ?? '', $_POST['user_yt'] ?? '',
        $_POST['posts_semanais'] ?? 0, $_POST['videos_semana'] ?? 0, $_POST['estaticos_semana'] ?? 0, $_POST['roteiros'] ?? 0, $_POST['captacao_mensal'] ?? 0, $_POST['trafego_pago'] ?? 0,
        $_POST['link_drive'] ?? '', $_POST['link_referencias'] ?? '', $_POST['observacoes'] ?? '', $id
    ]);
    header("Location: visualizar.php?id=" . $id); exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="flex-between mb-20">
    <h2 class="page-title">Editar: <?= htmlspecialchars($cliente['nome'] ?? '') ?></h2>
</div>

<form method="POST">
    <div class="dashboard-grid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Dados do Cliente</h3></div>
            <div class="form-group"><label>Empresa</label><input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>"></div>
            <div class="form-group"><label>E-mail</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>"></div>
            <div class="form-group"><label>Telefone</label><input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>"></div>
            <div class="form-group"><label>CPF / CNPJ</label><input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>"></div>
            <div class="form-group"><label>Endereço</label><textarea name="endereco" class="form-control"><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea></div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Operação Mensal</h3></div>
            <div class="form-group"><label>Posts/Semana</label><input type="number" name="posts_semanais" class="form-control" value="<?= htmlspecialchars($cliente['posts_semanais'] ?? 0) ?>"></div>
            <div class="form-group"><label>Vídeos/Semana</label><input type="number" name="videos_semana" class="form-control" value="<?= htmlspecialchars($cliente['videos_semana'] ?? 0) ?>"></div>
            <div class="form-group"><label>Estáticos/Semana</label><input type="number" name="estaticos_semana" class="form-control" value="<?= htmlspecialchars($cliente['estaticos_semana'] ?? 0) ?>"></div>
            <div class="form-group"><label>Roteiros (Qtd)</label><input type="number" name="roteiros" class="form-control" value="<?= htmlspecialchars($cliente['roteiros'] ?? 0) ?>"></div>
            <div class="form-group"><label>Diárias Captação</label><input type="number" name="captacao_mensal" class="form-control" value="<?= htmlspecialchars($cliente['captacao_mensal'] ?? 0) ?>"></div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Salvar</button>
</form>