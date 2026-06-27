<?php
// modules/planejamento/aprovar.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Busca a tarefa e os dados do cliente
$stmt = $pdo->prepare("SELECT p.*, cli.id as cliente_id, cli.nome as cliente_nome 
                       FROM planejamento p 
                       LEFT JOIN clientes cli ON p.cliente_id = cli.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) die("Tarefa não encontrada.");

// Trava de Segurança: Apenas o Admin da agência ou o próprio Cliente dono da tarefa podem ver essa tela
if (!isAdmin() && (!isset($_SESSION['cliente_id']) || $_SESSION['cliente_id'] != $item['cliente_id'])) {
    die("Acesso negado. Esta tarefa pertence a outro cliente.");
}

// Ações do Cliente
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        // --- AÇÕES DO ROTEIRO ---
        if ($acao == 'aprovar_roteiro' && $item['status_roteiro'] == 'aguardando_aprovacao') {
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'aprovado', status_geral = 'roteiro_aprovado', roteiro_aprovado_em = NOW() WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='msg-sucesso'>✅ Roteiro Aprovado! A equipe já foi notificada para iniciar a arte.</div>";
            $item['status_roteiro'] = 'aprovado';
            
        } elseif ($acao == 'revisar_roteiro' && $item['status_roteiro'] == 'aguardando_aprovacao') {
            $comentario = $_POST['comentario'] ?? '';
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'em_revisao', status_geral = 'roteiro_em_revisao', roteiro_comentario = ?, roteiro_revisoes = roteiro_revisoes + 1 WHERE id = ?")->execute([$comentario, $id]);
            $mensagem = "<div class='msg-alerta'>⚠️ Alteração solicitada. Nossa equipe vai revisar o texto e te avisar.</div>";
            $item['status_roteiro'] = 'em_revisao';
        }
        
        // --- AÇÕES DA ARTE ---
        elseif ($acao == 'aprovar_peca' && $item['status_peca'] == 'aguardando_aprovacao') {
            $pdo->prepare("UPDATE planejamento SET status_peca = 'aprovado', status_geral = 'peca_aprovada', peca_aprovado_em = NOW() WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='msg-sucesso'>✅ Arte Aprovada! O material está pronto para ir ao ar.</div>";
            $item['status_peca'] = 'aprovado';
            
        } elseif ($acao == 'revisar_peca' && $item['status_peca'] == 'aguardando_aprovacao') {
            $comentario = $_POST['comentario'] ?? '';
            $pdo->prepare("UPDATE planejamento SET status_peca = 'em_revisao', status_geral = 'peca_em_revisao', peca_comentario = ?, peca_revisoes = peca_revisoes + 1 WHERE id = ?")->execute([$comentario, $id]);
            $mensagem = "<div class='msg-alerta'>⚠️ Ajuste de arte solicitado. Nossa equipe fará a correção.</div>";
            $item['status_peca'] = 'em_revisao';
        }
    } catch (Exception $e) {
        $mensagem = "<div class='msg-erro'>Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
?>
<div class="planejamento-container">
    <div class="planejamento-titulo">
        <h1>Área de Aprovação</h1>
        <p>Revise o material abaixo elaborado pela Gasmaske Lab.</p>
    </div>

    <?= $mensagem ?>

    <div class="card-aprovacao">
        <h2><?= htmlspecialchars($item['tema']) ?></h2>
        <p class="meta">
            <strong>Formato:</strong> <?= htmlspecialchars($item['tipo']) ?> 
            | <strong>Semana:</strong> <?= htmlspecialchars($item['semana']) ?>
        </p>

        <?php if ($item['status_roteiro'] == 'aguardando_aprovacao'): ?>
            <div class="bloco-aprovacao">
                <h3 style="color: var(--gn-blue);">Roteiro / Legenda</h3>
                <div class="conteudo"><?= htmlspecialchars($item['roteiro']) ?></div>
                
                <div class="flex gap-15 mt-20">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="aprovar_roteiro">
                        <button type="submit" class="btn-aprovar">✅ Aprovar Roteiro</button>
                    </form>
                </div>

                <form method="POST" class="form-revisao">
                    <input type="hidden" name="acao" value="revisar_roteiro">
                    <label>Precisa mudar algo? Escreva abaixo:</label>
                    <textarea name="comentario" rows="3" required></textarea>
                    <button type="submit" class="btn-revisar">Solicitar Alteração</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($item['status_peca'] == 'aguardando_aprovacao'): ?>
            <div class="bloco-aprovacao">
                <h3 style="color: var(--gn-pink);">Arte Visual / Vídeo</h3>
                <p>Clique no link abaixo para visualizar a arte ou vídeo produzido:</p>
                <div class="link-material">
                    <a href="<?= htmlspecialchars($item['link_peca']) ?>" target="_blank">🔗 ABRIR MATERIAL</a>
                </div>
                
                <div class="flex gap-15">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="aprovar_peca">
                        <button type="submit" class="btn-aprovar">✅ Aprovar Arte</button>
                    </form>
                </div>

                <form method="POST" class="form-revisao">
                    <input type="hidden" name="acao" value="revisar_peca">
                    <label>Ajustes na imagem ou vídeo:</label>
                    <textarea name="comentario" rows="3" required></textarea>
                    <button type="submit" class="btn-revisar">Solicitar Ajuste na Arte</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($item['status_roteiro'] != 'aguardando_aprovacao' && $item['status_peca'] != 'aguardando_aprovacao'): ?>
            <div class="sem-pendencias">
                Nenhum item pendente de aprovação no momento.
            </div>
        <?php endif; ?>
        
        <div class="link-voltar">
            <a href="<?= BASE_URL ?>index.php">Voltar ao Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>