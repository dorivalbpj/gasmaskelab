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
$stmt = $pdo->prepare("SELECT p.*, cli.nome as cliente_nome, 
                        u1.nome as nome_resp_roteiro, u2.nome as nome_resp_peca 
                       FROM planejamento p 
                       LEFT JOIN clientes cli ON p.cliente_id = cli.id 
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
            $novo_status = ($item['status_geral'] == 'criado') ? 'roteiro_em_producao' : $item['status_geral'];
            
            $pdo->prepare("UPDATE planejamento SET roteiro = ?, status_geral = ? WHERE id = ?")->execute([$roteiro, $novo_status, $id]);
            $mensagem = "<div class='msg-sucesso'>Roteiro salvo com sucesso!</div>";
            $item['roteiro'] = $roteiro;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_roteiro_cliente') {
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'aguardando_aprovacao', status_geral = 'roteiro_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='msg-sucesso'>Roteiro liberado! O cliente já pode ver e aprovar pelo link do painel dele.</div>";
            $item['status_roteiro'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'roteiro_aguardando_aprovacao';

        } elseif ($acao == 'salvar_peca') {
            $link_peca = $_POST['link_peca'] ?? '';
            $novo_status = ($item['status_geral'] == 'roteiro_aprovado') ? 'peca_em_producao' : $item['status_geral'];
            
            $pdo->prepare("UPDATE planejamento SET link_peca = ?, status_geral = ? WHERE id = ?")->execute([$link_peca, $novo_status, $id]);
            $mensagem = "<div class='msg-sucesso'>Link da peça salvo com sucesso!</div>";
            $item['link_peca'] = $link_peca;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_peca_cliente') {
            $pdo->prepare("UPDATE planejamento SET status_peca = 'aguardando_aprovacao', status_geral = 'peca_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='msg-sucesso'>Arte liberada! O cliente já pode ver e aprovar.</div>";
            $item['status_peca'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'peca_aguardando_aprovacao';
        }
    } catch (Exception $e) {
        $mensagem = "<div class='msg-erro'>Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="item-header">
    <h2>Mesa de Trabalho - Tarefa #<?= $item['id'] ?></h2>
    <a href="index.php">← Voltar ao Planejamento</a>
</div>

<?= $mensagem ?>

<div class="item-resumo">
    <div class="grid-info">
        <div>
            <p class="label">Cliente</p>
            <span class="valor"><?= htmlspecialchars($item['cliente_nome'] ?? 'Interno') ?></span>
        </div>
        <div>
            <p class="label">Formato</p>
            <span class="valor"><?= htmlspecialchars($item['tipo']) ?></span>
        </div>
        <div>
            <p class="label">Semana</p>
            <span class="valor"><?= htmlspecialchars($item['semana']) ?></span>
        </div>
        <div>
            <p class="label">Status Geral</p>
            <span class="valor status"><?= str_replace('_', ' ', $item['status_geral']) ?></span>
        </div>
    </div>
    
    <div class="tema-box">
        <p class="label">Tema Principal:</p>
        <div class="tema"><?= htmlspecialchars($item['tema']) ?></div>
        
        <?php if($item['descricao']): ?>
            <div class="descricao">
                <strong>Briefing / Instruções:</strong><br>
                <?= nl2br(htmlspecialchars($item['descricao'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="item-colunas">
    
    <div class="card-coluna card-coluna-roteiro">
        <h3>
            <span>1. Roteiro / Copy</span>
            <span class="resp-badge">Resp: <?= htmlspecialchars($item['nome_resp_roteiro'] ?? 'Ninguém') ?></span>
        </h3>
        
        <span class="status-label">Status do Roteiro: <?= str_replace('_', ' ', $item['status_roteiro']) ?></span>

        <form method="POST">
            <input type="hidden" name="acao" value="salvar_roteiro">
            <textarea name="roteiro" rows="12" placeholder="Escreva o roteiro do vídeo ou a legenda do post aqui..."><?= htmlspecialchars($item['roteiro'] ?? '') ?></textarea>
            
            <button type="submit" class="btn-salvar">Salvar Rascunho do Roteiro</button>
        </form>

        <?php if(!empty($item['roteiro']) && $item['status_roteiro'] == 'pendente'): ?>
            <form method="POST">
                <input type="hidden" name="acao" value="enviar_roteiro_cliente">
                <button type="submit" class="btn-enviar">📤 Enviar Roteiro p/ Cliente Aprovar</button>
            </form>
        <?php endif; ?>
        
        <?php if($item['status_roteiro'] == 'aprovado'): ?>
            <div class="aprovado-badge">✅ Roteiro Aprovado pelo Cliente!</div>
        <?php endif; ?>
    </div>

    <div class="card-coluna card-coluna-arte">
        <h3>
            <span>2. Arte / Vídeo</span>
            <span class="resp-badge">Resp: <?= htmlspecialchars($item['nome_resp_peca'] ?? 'Ninguém') ?></span>
        </h3>

        <span class="status-label">Status da Arte: <?= str_replace('_', ' ', $item['status_peca']) ?></span>

        <?php if($item['status_roteiro'] == 'aprovado'): ?>
            <form method="POST">
                <input type="hidden" name="acao" value="salvar_peca">
                <label style="display:block; font-size: 13px; font-weight: bold; margin-bottom: 5px;">Link da Arte (Drive, Canva, Frame.io)</label>
                <input type="url" name="link_peca" value="<?= htmlspecialchars($item['link_peca'] ?? '') ?>" placeholder="https://...">
                
                <button type="submit" class="btn-salvar">Salvar Link da Arte</button>
            </form>

            <?php if(!empty($item['link_peca']) && $item['status_peca'] == 'pendente'): ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="enviar_peca_cliente">
                    <button type="submit" class="btn-enviar btn-enviar-arte">📤 Enviar Arte p/ Cliente Aprovar</button>
                </form>
            <?php endif; ?>
            
            <?php if($item['status_peca'] == 'aprovado'): ?>
                <div class="aprovado-badge">✅ Arte Aprovada pelo Cliente!</div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bloqueado">
                <p>O cliente precisa aprovar o roteiro primeiro para liberar a produção da arte visual.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/layout/footer.php'; ?>