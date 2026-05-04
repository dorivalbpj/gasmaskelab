<?php
// modules/planejamento/aprovar.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Busca a tarefa e os dados do cliente
$stmt = $pdo->prepare("SELECT p.*, c.codigo_agc, cli.id as cliente_id, cli.nome as cliente_nome 
                       FROM planejamento p 
                       JOIN contratos c ON p.contrato_id = c.id 
                       JOIN clientes cli ON c.cliente_id = cli.id 
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
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold;'>✅ Roteiro Aprovado! A equipe já foi notificada para iniciar a arte.</div>";
            $item['status_roteiro'] = 'aprovado';
            
        } elseif ($acao == 'revisar_roteiro' && $item['status_roteiro'] == 'aguardando_aprovacao') {
            $comentario = $_POST['comentario'] ?? '';
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'em_revisao', status_geral = 'roteiro_em_revisao', roteiro_comentario = ?, roteiro_revisoes = roteiro_revisoes + 1 WHERE id = ?")->execute([$comentario, $id]);
            $mensagem = "<div style='color: #856404; padding: 15px; background: #fff3cd; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold;'>⚠️ Alteração solicitada. Nossa equipe vai revisar o texto e te avisar.</div>";
            $item['status_roteiro'] = 'em_revisao';
        }
        
        // --- AÇÕES DA ARTE ---
        elseif ($acao == 'aprovar_peca' && $item['status_peca'] == 'aguardando_aprovacao') {
            $pdo->prepare("UPDATE planejamento SET status_peca = 'aprovado', status_geral = 'peca_aprovada', peca_aprovado_em = NOW() WHERE id = ?")->execute([$id]);
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold;'>✅ Arte Aprovada! O material está pronto para ir ao ar.</div>";
            $item['status_peca'] = 'aprovado';
            
        } elseif ($acao == 'revisar_peca' && $item['status_peca'] == 'aguardando_aprovacao') {
            $comentario = $_POST['comentario'] ?? '';
            $pdo->prepare("UPDATE planejamento SET status_peca = 'em_revisao', status_geral = 'peca_em_revisao', peca_comentario = ?, peca_revisoes = peca_revisoes + 1 WHERE id = ?")->execute([$comentario, $id]);
            $mensagem = "<div style='color: #856404; padding: 15px; background: #fff3cd; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: bold;'>⚠️ Ajuste de arte solicitado. Nossa equipe fará a correção.</div>";
            $item['status_peca'] = 'em_revisao';
        }
    } catch (Exception $e) {
        $mensagem = "<div style='color: red; padding: 15px; background: #ffe6e6; border-radius: 6px; margin-bottom: 20px;'>Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
?>
<div style="max-width: 800px; margin: 40px auto; font-family: sans-serif;">
    <div style="text-align: center; margin-bottom: 30px;">
        <h1 style="margin: 0;">Área de Aprovação</h1>
        <p style="color: #666;">Revise o material abaixo elaborado pela Gasmaske Lab.</p>
    </div>

    <?= $mensagem ?>

    <div class="card" style="border-top: 5px solid #000;">
        <h2 style="margin-top: 0;"><?= htmlspecialchars($item['tema']) ?></h2>
        <p style="color: #666; font-size: 14px;"><strong>Formato:</strong> <?= htmlspecialchars($item['tipo']) ?> | <strong>Semana:</strong> <?= htmlspecialchars($item['semana']) ?></p>

        <?php if ($item['status_roteiro'] == 'aguardando_aprovacao'): ?>
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #007bff;">Roteiro / Legenda</h3>
                <div style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 4px; font-family: monospace; font-size: 14px; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($item['roteiro']) ?></div>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="aprovar_roteiro">
                        <button type="submit" style="width: 100%; background: #28a745; color: white; padding: 15px; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer;">✅ Aprovar Roteiro</button>
                    </form>
                </div>

                <form method="POST" style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 20px;">
                    <input type="hidden" name="acao" value="revisar_roteiro">
                    <label style="display:block; font-size: 14px; color: #666; margin-bottom: 5px;">Precisa mudar algo? Escreva abaixo:</label>
                    <textarea name="comentario" rows="3" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px;"></textarea>
                    <button type="submit" style="background: #ffc107; color: #000; padding: 10px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Solicitar Alteração</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($item['status_peca'] == 'aguardando_aprovacao'): ?>
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 6px; margin-top: 20px;">
                <h3 style="margin-top: 0; color: #e83e8c;">Arte Visual / Vídeo</h3>
                <p>Clique no link abaixo para visualizar a arte ou vídeo produzido:</p>
                <div style="background: #000; padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px;">
                    <a href="<?= htmlspecialchars($item['link_peca']) ?>" target="_blank" style="color: #0df; font-weight: bold; text-decoration: none; font-size: 18px;">🔗 ABRIR MATERIAL</a>
                </div>
                
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="acao" value="aprovar_peca">
                        <button type="submit" style="width: 100%; background: #28a745; color: white; padding: 15px; border: none; border-radius: 6px; font-weight: bold; font-size: 16px; cursor: pointer;">✅ Aprovar Arte</button>
                    </form>
                </div>

                <form method="POST" style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 20px;">
                    <input type="hidden" name="acao" value="revisar_peca">
                    <label style="display:block; font-size: 14px; color: #666; margin-bottom: 5px;">Ajustes na imagem ou vídeo:</label>
                    <textarea name="comentario" rows="3" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; margin-bottom: 10px;"></textarea>
                    <button type="submit" style="background: #ffc107; color: #000; padding: 10px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Solicitar Ajuste na Arte</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($item['status_roteiro'] != 'aguardando_aprovacao' && $item['status_peca'] != 'aguardando_aprovacao'): ?>
            <div style="text-align: center; padding: 30px; color: #666;">
                Nenhum item pendente de aprovação no momento.
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="/gasmaske/index.php" style="color: #007bff; text-decoration: none;">Voltar ao Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>