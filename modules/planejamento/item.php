<?php
// modules/planejamento/item.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Busca os dados completos da tarefa
$stmt = $pdo->prepare("SELECT p.*, c.codigo_agc, c.token as contrato_token, cli.nome as cliente_nome, 
                        u1.nome as nome_resp_roteiro, u2.nome as nome_resp_peca 
                       FROM planejamento p 
                       JOIN contratos c ON p.contrato_id = c.id 
                       JOIN clientes cli ON c.cliente_id = cli.id 
                       LEFT JOIN usuarios u1 ON p.responsavel_roteiro = u1.id 
                       LEFT JOIN usuarios u2 ON p.responsavel_peca = u2.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) die("Tarefa não encontrada.");

// --- LÓGICA PARA SALVAR ROTEIRO E ARTE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao == 'salvar_roteiro') {
            $roteiro = $_POST['roteiro'] ?? '';
            // Se o roteiro foi salvo, muda o status geral para "roteiro em producao"
            $novo_status = ($item['status_geral'] == 'criado') ? 'roteiro_em_producao' : $item['status_geral'];
            
            $pdo->prepare("UPDATE planejamento SET roteiro = ?, status_geral = ? WHERE id = ?")->execute([$roteiro, $novo_status, $id]);
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 4px; margin-bottom: 15px;'>Roteiro salvo com sucesso!</div>";
            $item['roteiro'] = $roteiro;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_roteiro_cliente') {
            // Manda para o cliente aprovar o roteiro
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'aguardando_aprovacao', status_geral = 'roteiro_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 4px; margin-bottom: 15px;'>Roteiro liberado! O cliente já pode ver e aprovar pelo link do painel dele.</div>";
            $item['status_roteiro'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'roteiro_aguardando_aprovacao';

        } elseif ($acao == 'salvar_peca') {
            $link_peca = $_POST['link_peca'] ?? '';
            $novo_status = ($item['status_geral'] == 'roteiro_aprovado') ? 'peca_em_producao' : $item['status_geral'];
            
            $pdo->prepare("UPDATE planejamento SET link_peca = ?, status_geral = ? WHERE id = ?")->execute([$link_peca, $novo_status, $id]);
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 4px; margin-bottom: 15px;'>Link da peça salvo com sucesso!</div>";
            $item['link_peca'] = $link_peca;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_peca_cliente') {
            // Manda para o cliente aprovar a peça visual
            $pdo->prepare("UPDATE planejamento SET status_peca = 'aguardando_aprovacao', status_geral = 'peca_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div style='color: green; padding: 15px; background: #e6ffe6; border-radius: 4px; margin-bottom: 15px;'>Arte liberada! O cliente já pode ver e aprovar.</div>";
            $item['status_peca'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'peca_aguardando_aprovacao';
        }
    } catch (Exception $e) {
        $mensagem = "<div style='color: red; padding: 15px; background: #ffe6e6; border-radius: 4px; margin-bottom: 15px;'>Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0;">Mesa de Trabalho - Tarefa #<?= $item['id'] ?></h2>
    <a href="index.php" style="color: #666; text-decoration: none; font-weight: bold;">← Voltar ao Planejamento</a>
</div>

<?= $mensagem ?>

<div class="card" style="border-left: 5px solid #000;">
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
        <div>
            <p style="margin: 0; color: #666; font-size: 12px;">Cliente / Contrato</p>
            <strong style="font-size: 15px;"><?= htmlspecialchars($item['cliente_nome']) ?> (<?= $item['codigo_agc'] ?>)</strong>
        </div>
        <div>
            <p style="margin: 0; color: #666; font-size: 12px;">Formato</p>
            <strong style="font-size: 15px;"><?= htmlspecialchars($item['tipo']) ?></strong>
        </div>
        <div>
            <p style="margin: 0; color: #666; font-size: 12px;">Semana</p>
            <strong style="font-size: 15px;"><?= htmlspecialchars($item['semana']) ?></strong>
        </div>
        <div>
            <p style="margin: 0; color: #666; font-size: 12px;">Status Geral</p>
            <strong style="font-size: 14px; text-transform: uppercase; color: #007bff;"><?= str_replace('_', ' ', $item['status_geral']) ?></strong>
        </div>
    </div>
    
    <div style="margin-top: 15px; background: #f9f9f9; padding: 15px; border-radius: 4px;">
        <p style="margin: 0 0 5px 0; color: #666; font-size: 12px;">Tema Principal:</p>
        <strong style="font-size: 18px;"><?= htmlspecialchars($item['tema']) ?></strong>
        
        <?php if($item['descricao']): ?>
            <p style="margin: 10px 0 0 0; color: #444; font-size: 14px; line-height: 1.5;">
                <strong style="color:#000;">Briefing / Instruções:</strong><br>
                <?= nl2br(htmlspecialchars($item['descricao'])) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<div style="display: flex; gap: 20px;">
    
    <div class="card" style="flex: 1; border-top: 4px solid #007bff;">
        <h3 style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
            <span>1. Roteiro / Copy</span>
            <span style="font-size: 12px; background: #eee; padding: 3px 8px; border-radius: 12px; color: #666;">
                Resp: <?= htmlspecialchars($item['nome_resp_roteiro'] ?? 'Ninguém') ?>
            </span>
        </h3>
        
        <div style="margin-bottom: 15px;">
            <strong style="font-size: 12px; color: #666; text-transform: uppercase;">Status do Roteiro: <?= str_replace('_', ' ', $item['status_roteiro']) ?></strong>
        </div>

        <form method="POST">
            <input type="hidden" name="acao" value="salvar_roteiro">
            <textarea name="roteiro" rows="12" style="width: 100%; padding: 15px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; font-family: monospace; font-size: 14px; line-height: 1.5; margin-bottom: 15px;" placeholder="Escreva o roteiro do vídeo ou a legenda do post aqui..."><?= htmlspecialchars($item['roteiro'] ?? '') ?></textarea>
            
            <button type="submit" style="background: #333; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">Salvar Rascunho do Roteiro</button>
        </form>

        <?php if(!empty($item['roteiro']) && $item['status_roteiro'] == 'pendente'): ?>
            <form method="POST" style="margin-top: 10px;">
                <input type="hidden" name="acao" value="enviar_roteiro_cliente">
                <button type="submit" style="background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">📤 Enviar Roteiro p/ Cliente Aprovar</button>
            </form>
        <?php endif; ?>
        
        <?php if($item['status_roteiro'] == 'aprovado'): ?>
            <div style="margin-top: 15px; text-align: center; color: #28a745; font-weight: bold; background: #e6ffe6; padding: 10px; border-radius: 4px;">
                ✅ Roteiro Aprovado pelo Cliente!
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="flex: 1; border-top: 4px solid #e83e8c;">
        <h3 style="margin-top: 0; display: flex; justify-content: space-between; align-items: center;">
            <span>2. Arte / Vídeo</span>
            <span style="font-size: 12px; background: #eee; padding: 3px 8px; border-radius: 12px; color: #666;">
                Resp: <?= htmlspecialchars($item['nome_resp_peca'] ?? 'Ninguém') ?>
            </span>
        </h3>

        <div style="margin-bottom: 15px;">
            <strong style="font-size: 12px; color: #666; text-transform: uppercase;">Status da Arte: <?= str_replace('_', ' ', $item['status_peca']) ?></strong>
        </div>

        <?php if($item['status_roteiro'] == 'aprovado'): ?>
            <form method="POST">
                <input type="hidden" name="acao" value="salvar_peca">
                <label style="display:block; font-size: 13px; font-weight: bold; margin-bottom: 5px;">Link da Arte (Drive, Canva, Frame.io)</label>
                <input type="url" name="link_peca" value="<?= htmlspecialchars($item['link_peca'] ?? '') ?>" placeholder="https://..." style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; margin-bottom: 15px;">
                
                <button type="submit" style="background: #333; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">Salvar Link da Arte</button>
            </form>

            <?php if(!empty($item['link_peca']) && $item['status_peca'] == 'pendente'): ?>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="acao" value="enviar_peca_cliente">
                    <button type="submit" style="background: #e83e8c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%;">📤 Enviar Arte p/ Cliente Aprovar</button>
                </form>
            <?php endif; ?>
            
            <?php if($item['status_peca'] == 'aprovado'): ?>
                <div style="margin-top: 15px; text-align: center; color: #28a745; font-weight: bold; background: #e6ffe6; padding: 10px; border-radius: 4px;">
                    ✅ Arte Aprovada pelo Cliente!
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="background: #f9f9f9; padding: 30px 20px; text-align: center; border: 1px dashed #ccc; border-radius: 6px; color: #888;">
                <p style="margin:0;">O cliente precisa aprovar o roteiro primeiro para liberar a produção da arte visual.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>