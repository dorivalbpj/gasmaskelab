<?php
// publico/aprovacoes.php

require_once '../config/database.php';

$token = $_GET['token'] ?? '';
$mensagem = '';

if (empty($token)) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Erro</title><link rel='stylesheet' href='../assets/css/public.css'></head><body class='public-body'><div class='public-container'><div class='empty-state'><div class='empty-icon'><i class='ph-fill ph-warning-circle'></i></div><h3>Acesso inválido</h3><p>Este link está quebrado ou incompleto.</p></div></div></body></html>");
}

$stmt = $pdo->prepare("SELECT c.*, cli.nome as cliente_nome FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id WHERE c.token = ?");
$stmt->execute([$token]);
$contrato = $stmt->fetch();

if (!$contrato) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Erro</title><link rel='stylesheet' href='../assets/css/public.css'></head><body class='public-body'><div class='public-container'><div class='empty-state'><div class='empty-icon'><i class='ph-fill ph-magnifying-glass'></i></div><h3>Contrato não encontrado</h3><p>O link pode ter expirado ou sido revogado.</p></div></div></body></html>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $id_tarefa = $_POST['id_tarefa'];
    $fase_atual = $_POST['fase'];

    if ($_POST['acao'] == 'aprovar') {
        $novo_status = ($fase_atual == 'roteiro') ? 'peca_em_producao' : 'pronto_para_postar';
        $pdo->prepare("UPDATE planejamento SET status_geral = ?, feedback_cliente = NULL, data_ultima_acao = NOW() WHERE id = ? AND contrato_id = ?")->execute([$novo_status, $id_tarefa, $contrato['id']]);
        $mensagem = 'aprovado';
    } elseif ($_POST['acao'] == 'reprovar') {
        $novo_status = ($fase_atual == 'roteiro') ? 'roteiro_em_revisao' : 'peca_em_revisao';
        $feedback = $_POST['feedback_cliente'] ?? 'Ajuste solicitado pelo cliente (sem detalhes).';
        $pdo->prepare("UPDATE planejamento SET status_geral = ?, feedback_cliente = ?, data_ultima_acao = NOW() WHERE id = ? AND contrato_id = ?")->execute([$novo_status, $feedback, $id_tarefa, $contrato['id']]);
        $mensagem = 'reprovado';
    }
}

$sql = "SELECT * FROM planejamento
        WHERE contrato_id = ? AND status_geral IN ('roteiro_aguardando_aprovacao', 'peca_aguardando_aprovacao')
        ORDER BY data_publicacao ASC";
$stmt_tarefas = $pdo->prepare($sql);
$stmt_tarefas->execute([$contrato['id']]);
$tarefas = $stmt_tarefas->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprovações — <?= htmlspecialchars($contrato['cliente_nome']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/public.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="public-body">

    <!-- Header -->
    <header class="public-header">
        <div class="public-header-inner">
            <img src="../assets/img/logo-h.png" class="logo-img" style="height:40px;">
            <span class="public-badge">Central de Aprovações</span>
        </div>
        <div class="public-header-bar"></div>
    </header>

    <!-- Hero -->
    <div class="page-hero">
        <h1><?= htmlspecialchars($contrato['cliente_nome']) ?></h1>
        <p>Revise e aprove os conteúdos abaixo antes da publicação</p>
    </div>

    <div class="public-container">

        <?php if ($mensagem == 'aprovado'): ?>
            <div class="alert alert-success">
                <i class="ph-fill ph-check-circle"></i>
                <div>
                    <strong>Aprovado com sucesso!</strong><br>
                    <span style="font-size:0.85rem;">Já enviamos para a equipa dar continuidade.</span>
                </div>
            </div>
        <?php elseif ($mensagem == 'reprovado'): ?>
            <div class="alert alert-warning">
                <i class="ph-fill ph-warning-circle"></i>
                <div>
                    <strong>Ajuste solicitado!</strong><br>
                    <span style="font-size:0.85rem;">Nossa equipa vai rever e enviar novamente em breve.</span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($tarefas) > 0): ?>

            <p style="font-size:0.85rem; color:var(--text-muted); text-align:center; margin-bottom:20px;">
                Você tem <strong style="color:var(--text-primary);"><?= count($tarefas) ?></strong> item<?= count($tarefas) > 1 ? 'ns' : '' ?> aguardando sua avaliação
            </p>

            <?php foreach ($tarefas as $t):
                $eh_roteiro = ($t['status_geral'] == 'roteiro_aguardando_aprovacao');
                $fase = $eh_roteiro ? 'roteiro' : 'arte';
            ?>

            <div class="approval-card">

                <!-- Cabeçalho do item -->
                <div class="approval-card-header">
                    <div>
                        <span class="badge <?= $eh_roteiro ? 'badge-blue' : 'badge-red' ?>" style="margin-bottom:8px;">
                            <i class="<?= $eh_roteiro ? 'ph ph-article' : 'ph ph-image' ?>"></i>
                            <?= $eh_roteiro ? 'Roteiro' : 'Arte Final' ?>
                        </span>
                        <strong style="display:block; font-size:0.95rem; line-height:1.3;"><?= htmlspecialchars($t['tema']) ?></strong>
                    </div>
                    <div style="text-align:right; flex-shrink:0;">
                        <span style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; color:var(--text-muted); display:block;">Prazo</span>
                        <strong style="font-size:0.9rem; color:var(--text-primary);"><?= date('d/m', strtotime($t['data_publicacao'])) ?></strong>
                    </div>
                </div>

                <!-- Conteúdo -->
                <div class="approval-card-body">

                    <?php if (!$eh_roteiro && !empty($t['link_arte_final'])): ?>
                        <a href="<?= htmlspecialchars($t['link_arte_final']) ?>" target="_blank" class="art-link">
                            <i class="ph ph-eye"></i> Visualizar Arte
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($t['copy_legenda'])): ?>
                        <p style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.07em; font-weight:600; color:var(--text-muted); margin-bottom:8px;">
                            <?= $eh_roteiro ? 'Conteúdo sugerido' : 'Legenda' ?>
                        </p>
                        <div class="content-preview"><?= htmlspecialchars($t['copy_legenda']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- Ações -->
                <div class="approval-card-footer">

                    <form method="POST" style="display:contents;">
                        <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                        <input type="hidden" name="fase" value="<?= $fase ?>">
                        <button type="submit" name="acao" value="aprovar" class="btn btn-primary btn-full"
                            onclick="return confirm('Confirmar aprovação?');">
                            <i class="ph-fill ph-check-circle"></i> Aprovar e Avançar
                        </button>
                    </form>

                    <button type="button" class="btn btn-danger-ghost btn-full"
                        onclick="toggleFeedback(<?= $t['id'] ?>)">
                        <i class="ph ph-x-circle"></i> Precisa de Ajustes
                    </button>

                    <!-- Feedback oculto -->
                    <div id="feedback_<?= $t['id'] ?>" class="feedback-box" style="display:none;">
                        <form method="POST">
                            <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                            <input type="hidden" name="fase" value="<?= $fase ?>">
                            <input type="hidden" name="acao" value="reprovar">
                            <div class="form-group">
                                <label>O que precisa mudar?</label>
                                <textarea name="feedback_cliente" class="form-control" rows="3"
                                    placeholder="Descreva o que deve ser ajustado..." required></textarea>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button type="button" class="btn btn-secondary" style="flex:1;"
                                    onclick="toggleFeedback(<?= $t['id'] ?>)">Cancelar</button>
                                <button type="submit" class="btn btn-primary" style="flex:1; background:var(--red);">
                                    Enviar Ajuste
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon"><i class="ph-fill ph-check-circle" style="color:var(--green);"></i></div>
                <h3>Tudo em dia!</h3>
                <p>Nenhum item aguardando sua aprovação no momento.</p>
            </div>

        <?php endif; ?>

    </div>

    <footer class="site-footer">
        <img src="../assets/img/logo-h.png" alt="Gasmaske Lab">
        <p>© <?= date('Y') ?> Gasmaske Lab · CNPJ 58.714.373/0001-04</p>
    </footer>

    <script>
        function toggleFeedback(id) {
            const box = document.getElementById('feedback_' + id);
            box.style.display = box.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>