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
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Roteiro salvo com sucesso!</div>";
            $item['roteiro'] = $roteiro;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_roteiro_cliente') {
            $pdo->prepare("UPDATE planejamento SET status_roteiro = 'aguardando_aprovacao', status_geral = 'roteiro_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Roteiro liberado! O cliente já pode ver e aprovar.</div>";
            $item['status_roteiro'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'roteiro_aguardando_aprovacao';

        } elseif ($acao == 'salvar_peca') {
            $link_peca = $_POST['link_peca'] ?? '';
            $novo_status = ($item['status_geral'] == 'roteiro_aprovado') ? 'peca_em_producao' : $item['status_geral'];
            
            $pdo->prepare("UPDATE planejamento SET link_peca = ?, status_geral = ? WHERE id = ?")->execute([$link_peca, $novo_status, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Link da peça salvo com sucesso!</div>";
            $item['link_peca'] = $link_peca;
            $item['status_geral'] = $novo_status;

        } elseif ($acao == 'enviar_peca_cliente') {
            $pdo->prepare("UPDATE planejamento SET status_peca = 'aguardando_aprovacao', status_geral = 'peca_aguardando_aprovacao' WHERE id = ?")->execute([$id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Arte liberada para o cliente!</div>";
            $item['status_peca'] = 'aguardando_aprovacao';
            $item['status_geral'] = 'peca_aguardando_aprovacao';
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-warning'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- O CAMINHO DO CSS FOI CORRIGIDO PARA PUXAR O ARQUIVO EXATO DO SEU SISTEMA -->
<link rel="stylesheet" href="../../assets/css/planejamento.css?v=<?= time() ?>">

<div style="max-width: 1200px; margin: 0 auto; width: 100%; padding-top: 10px;">

    <!-- CABEÇALHO NO PADRÃO DO SISTEMA -->
    <div class="header-planejamento">
        <div class="header-title-block">
            <h2 class="page-title">Mesa de Trabalho</h2>
            <div class="header-stats" style="margin-top: 5px;">
                <span class="badge badge-gray" style="font-size: 11px;">Tarefa #<?= $item['id'] ?></span>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-secondary btn-h44">
                <i class="ph ph-arrow-left"></i> Voltar ao Planejamento
            </a>
        </div>
    </div>

    <?= $mensagem ?>

    <!-- RESUMO DA TAREFA -->
    <div class="item-resumo">
        <div class="grid-info">
            <div>
                <p class="label">Cliente</p>
                <span class="valor"><?= htmlspecialchars($item['cliente_nome'] ?? 'Interno') ?></span>
            </div>
            <div>
                <p class="label">Categoria</p>
                <span class="valor"><?= htmlspecialchars($item['tipo']) ?></span>
            </div>
            <div>
                <p class="label">Data Prevista</p>
                <span class="valor"><?= $item['data_publicacao'] ? date('d/m/Y', strtotime($item['data_publicacao'])) : 'Sem data' ?></span>
            </div>
            <div>
                <p class="label">Status Geral</p>
                <span class="badge badge-blue" style="font-size: 11px; padding: 4px 8px; margin-top: 4px;">
                    <?= str_replace('_', ' ', $item['status_geral']) ?>
                </span>
            </div>
        </div>
        
        <div class="tema-box">
            <p class="label">Tema / Título:</p>
            <div class="tema"><?= htmlspecialchars($item['tema']) ?></div>
            
            <?php if(!empty($item['descricao'])): ?>
                <div class="descricao" style="margin-top: 15px; border-top: 1px dashed var(--border-mid); padding-top: 15px;">
                    <p class="label" style="margin-bottom: 5px;">Briefing / Instruções adicionais:</p>
                    <?= nl2br(htmlspecialchars($item['descricao'])) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- COLUNAS DE ROTEIRO E ARTE -->
    <div class="item-colunas">
        
        <!-- COLUNA 1: ROTEIRO -->
        <div class="card-coluna card-coluna-roteiro">
            <h3>
                <span style="display: flex; align-items: center; gap: 8px;"><i class="ph-fill ph-file-text" style="color: var(--gn-blue);"></i> 1. Roteiro / Copy</span>
                <span class="resp-badge"><i class="ph ph-user"></i> <?= htmlspecialchars($item['nome_resp_roteiro'] ?? 'Sem Resp.') ?></span>
            </h3>
            
            <span class="status-label">Status do Roteiro: <?= str_replace('_', ' ', $item['status_roteiro']) ?></span>

            <form method="POST">
                <input type="hidden" name="acao" value="salvar_roteiro">
                <textarea name="roteiro" rows="14" placeholder="Escreva o roteiro do vídeo ou a legenda do post aqui..."><?= htmlspecialchars($item['roteiro'] ?? '') ?></textarea>
                
                <button type="submit" class="btn-save-lg" style="padding: 12px; font-size: 14px;">
                    <i class="ph-fill ph-floppy-disk"></i> Salvar
                </button>
            </form>

            <?php if(!empty($item['roteiro']) && $item['status_roteiro'] == 'pendente'): ?>
                <form method="POST" style="margin-top: 10px;">
                    <input type="hidden" name="acao" value="enviar_roteiro_cliente">
                    <button type="submit" class="btn-enviar" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="ph-fill ph-paper-plane-right"></i> Enviar p/ Aprovação
                    </button>
                </form>
            <?php endif; ?>
            
            <?php if($item['status_roteiro'] == 'aprovado'): ?>
                <div class="aprovado-badge"><i class="ph-fill ph-check-circle"></i> Roteiro Aprovado pelo Cliente!</div>
            <?php endif; ?>
        </div>

        <!-- COLUNA 2: ARTE -->
        <div class="card-coluna card-coluna-arte">
            <h3>
                <span style="display: flex; align-items: center; gap: 8px;"><i class="ph-fill ph-image" style="color: var(--gn-pink);"></i> 2. Arte / Vídeo</span>
                <span class="resp-badge"><i class="ph ph-user"></i> <?= htmlspecialchars($item['nome_resp_peca'] ?? 'Sem Resp.') ?></span>
            </h3>

            <span class="status-label">Status da Arte: <?= str_replace('_', ' ', $item['status_peca']) ?></span>

            <?php if($item['status_roteiro'] == 'aprovado'): ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="salvar_peca">
                    <label style="display:block; font-size: 12px; font-weight: 700; color: var(--text-2); margin-bottom: 8px; text-transform: uppercase;">Link de Entrega (Drive/Canva)</label>
                    <input type="url" name="link_peca" class="silent-input" style="border: 1px solid var(--border-mid); margin-bottom: 15px; padding: 12px;" value="<?= htmlspecialchars($item['link_peca'] ?? '') ?>" placeholder="https://...">
                    
                    <button type="submit" class="btn-save-lg" style="padding: 12px; font-size: 14px; background: var(--gn-pink);">
                        <i class="ph-fill ph-floppy-disk"></i> Salvar Link
                    </button>
                </form>

                <?php if(!empty($item['link_peca']) && $item['status_peca'] == 'pendente'): ?>
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="acao" value="enviar_peca_cliente">
                        <button type="submit" class="btn-enviar btn-enviar-arte" style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="ph-fill ph-paper-plane-right"></i> Enviar Arte p/ Aprovação
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if($item['status_peca'] == 'aprovado'): ?>
                    <div class="aprovado-badge"><i class="ph-fill ph-check-circle"></i> Arte Aprovada pelo Cliente!</div>
                <?php endif; ?>

            <?php else: ?>
                <div class="bloqueado">
                    <i class="ph ph-lock-key" style="font-size: 32px; margin-bottom: 10px; color: var(--text-3);"></i>
                    <p>O cliente precisa <strong>aprovar o roteiro</strong> primeiro para liberar a produção da arte visual.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- IA FLUTUANTE FIIOTE (MANTENDO O PADRÃO DO SISTEMA) -->
<div class="ai-floating-container">
    <div class="ai-bubble" id="aiBubble">
        <div class="ai-bubble-header">
            <div class="ai-bubble-avatar">
                <i class="ph-fill ph-robot"></i>
            </div>
            <div>
                <div class="ai-bubble-name">Gasmaske <span>IA</span></div>
                <div class="ai-bubble-status">Online</div>
            </div>
        </div>
        <p class="ai-bubble-text">
            E aí🤖<br>
            Precisa de ajuda com o roteiro ou copy desta tarefa?<br>
            <strong>#GasmaskeLab</strong>
        </p>
        <div class="ai-bubble-time">● Hoje, agora</div>
    </div>

    <button class="ai-button" id="aiButton" onclick="toggleAI()">
        <i class="ph-fill ph-robot"></i>
        <span class="ai-tooltip">Falar com a Gasmaske IA</span>
        <span class="ai-notif hidden" id="aiNotif">1</span>
    </button>
</div>

<script>
// --- FUNÇÕES DA IA FLUTUANTE ---
function toggleAI() {
    const bubble = document.getElementById('aiBubble');
    const button = document.getElementById('aiButton');
    const notif = document.getElementById('aiNotif');
    
    bubble.classList.toggle('active');
    button.classList.toggle('active');
    
    if (bubble.classList.contains('active')) {
        notif.classList.add('hidden');
    }
}

document.addEventListener('click', function(e) {
    const container = document.querySelector('.ai-floating-container');
    if (container && !container.contains(e.target)) {
        const bubble = document.getElementById('aiBubble');
        const button = document.getElementById('aiButton');
        if (bubble) bubble.classList.remove('active');
        if (button) button.classList.remove('active');
    }
});

setTimeout(() => {
    const notif = document.getElementById('aiNotif');
    if (notif) notif.classList.remove('hidden');
}, 3000);
</script>

<?php require_once '../../includes/layout/footer.php'; ?>