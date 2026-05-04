<?php
// publico/aprovacoes.php

require_once '../config/database.php';

$token = $_GET['token'] ?? '';
$mensagem = '';

if (empty($token)) {
    die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Acesso inválido. Link quebrado.</div>");
}

$stmt = $pdo->prepare("SELECT c.*, cli.nome as cliente_nome FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id WHERE c.token = ?");
$stmt->execute([$token]);
$contrato = $stmt->fetch();

if (!$contrato) {
    die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Contrato não encontrado.</div>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    $id_tarefa = $_POST['id_tarefa'];
    $fase_atual = $_POST['fase']; 
    
    if ($_POST['acao'] == 'aprovar') {
        $novo_status = ($fase_atual == 'roteiro') ? 'peca_em_producao' : 'pronto_para_postar';
        $pdo->prepare("UPDATE planejamento SET status_geral = ?, feedback_cliente = NULL, data_ultima_acao = NOW() WHERE id = ? AND contrato_id = ?")->execute([$novo_status, $id_tarefa, $contrato['id']]);
        $mensagem = "<div class='alert alert-success'>✅ Aprovado com sucesso! Já enviámos para a equipa.</div>";
    } 
    elseif ($_POST['acao'] == 'reprovar') {
        $novo_status = ($fase_atual == 'roteiro') ? 'roteiro_em_revisao' : 'peca_em_revisao';
        $feedback = $_POST['feedback_cliente'] ?? 'Ajuste solicitado pelo cliente (sem detalhes).';
        $pdo->prepare("UPDATE planejamento SET status_geral = ?, feedback_cliente = ?, data_ultima_acao = NOW() WHERE id = ? AND contrato_id = ?")->execute([$novo_status, $feedback, $id_tarefa, $contrato['id']]);
        $mensagem = "<div class='alert alert-warning'>⚠️ Ajuste solicitado! A nossa equipa vai rever e enviar novamente.</div>";
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
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de Aprovações - Gasmaske</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="public-body">
    <div class="public-container" style="max-width: 600px;">
        
        <div class="public-header">
            <h1 class="public-logo">GASMASKE<span>.</span></h1>
            <p class="public-subtitle">Central de Aprovações - <?= htmlspecialchars($contrato['cliente_nome']) ?></p>
        </div>

        <?= $mensagem ?>

        <?php if (count($tarefas) > 0): ?>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 25px; font-size: 14px;">Tens <strong><?= count($tarefas) ?></strong> item(ns) a aguardar a tua avaliação.</p>

            <?php foreach ($tarefas as $t): 
                $eh_roteiro = ($t['status_geral'] == 'roteiro_aguardando_aprovacao');
                $fase = $eh_roteiro ? 'roteiro' : 'arte';
            ?>
                <div class="card" style="margin-bottom: 25px; padding: 25px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 1px solid var(--border-mid); padding-bottom: 15px;">
                        <div>
                            <span class="badge <?= $eh_roteiro ? 'badge-blue' : 'badge-purple' ?>" style="margin-bottom: 8px;">
                                <?= $eh_roteiro ? '📝 Aprovação de Roteiro' : '🎨 Aprovação de Arte' ?>
                            </span>
                            <strong style="display: block; font-size: 16px; color: var(--text-primary);"><?= htmlspecialchars($t['tema']) ?></strong>
                        </div>
                        <div style="text-align: right;">
                            <span style="color: var(--text-muted); font-size: 11px; text-transform: uppercase; display: block;">Prazo</span>
                            <strong style="color: var(--text-secondary); font-size: 13px;"><?= date('d/m/Y', strtotime($t['data_publicacao'])) ?></strong>
                        </div>
                    </div>

                    <?php if ($eh_roteiro): ?>
                        <label style="font-weight: 600; font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block;">Conteúdo Sugerido:</label>
                        <div style="background: var(--bg-hover); border: 1px dashed var(--border-mid); padding: 15px; border-radius: var(--radius-md); font-family: monospace; font-size: 14px; color: var(--text-primary); white-space: pre-wrap; margin-bottom: 25px; max-height: 300px; overflow-y: auto;">
                            <?= htmlspecialchars($t['copy_legenda']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$eh_roteiro): ?>
                        <a href="<?= htmlspecialchars($t['link_arte_final']) ?>" target="_blank" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-bottom: 20px; border-color: var(--blue); color: var(--blue);">
                            🔍 Visualizar Arte (Abrir Link)
                        </a>
                        
                        <label style="font-weight: 600; font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: block;">Legenda da Arte:</label>
                        <div style="background: var(--bg-hover); padding: 15px; border-radius: var(--radius-md); font-size: 13px; color: var(--text-secondary); white-space: pre-wrap; margin-bottom: 25px; max-height: 150px; overflow-y: auto;">
                            <?= htmlspecialchars($t['copy_legenda'] ?: 'Nenhuma legenda registada.') ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                        <input type="hidden" name="fase" value="<?= $fase ?>">
                        
                        <button type="submit" name="acao" value="aprovar" class="btn btn-primary" style="width: 100%; justify-content: center; height: 50px; margin-bottom: 10px;" onclick="return confirm('Confirmar aprovação?');">
                            ✅ APROVAR E AVANÇAR
                        </button>
                        <button type="button" class="btn btn-ghost" style="width: 100%; justify-content: center; color: var(--red);" onclick="abrirFeedback(<?= $t['id'] ?>)">
                            ❌ Precisa de Ajustes
                        </button>
                    </form>

                    <div id="feedback_box_<?= $t['id'] ?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border-mid);">
                        <form method="POST">
                            <input type="hidden" name="id_tarefa" value="<?= $t['id'] ?>">
                            <input type="hidden" name="fase" value="<?= $fase ?>">
                            <input type="hidden" name="acao" value="reprovar">
                            
                            <label style="font-weight: bold; font-size: 13px; color: var(--red); display: block; margin-bottom: 10px;">O que precisa de ser alterado?</label>
                            <textarea name="feedback_cliente" rows="3" class="form-control" placeholder="Descreve o que não gostaste ou o que precisa de mudar..." required style="margin-bottom: 15px;"></textarea>
                            
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn btn-secondary" style="flex: 1; justify-content: center;" onclick="fecharFeedback(<?= $t['id'] ?>)">Cancelar</button>
                                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; background: var(--red);">Enviar Ajuste</button>
                            </div>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 40px; margin-bottom: 15px;">🎉</div>
                <h3 style="margin: 0 0 10px 0; color: var(--text-primary);">Tudo em dia!</h3>
                <p style="color: var(--text-muted); margin: 0;">Não tens nenhum item pendente de aprovação no momento.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function abrirFeedback(id) {
            document.getElementById('feedback_box_' + id).style.display = 'block';
        }
        function fecharFeedback(id) {
            document.getElementById('feedback_box_' + id).style.display = 'none';
        }
    </script>
</body>
</html>