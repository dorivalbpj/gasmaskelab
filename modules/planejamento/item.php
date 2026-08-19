<?php
// modules/planejamento/item.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

// --- AJAX: ATUALIZAÇÕES RÁPIDAS COM LOG ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_campo') {
    $id_tarefa = $_POST['id_tarefa'];
    $campo = $_POST['campo'];
    $valor = empty($_POST['valor']) ? null : $_POST['valor'];
    
    // Todos os campos editáveis que existem no index.php
    $campos_permitidos = ['responsavel_id', 'prioridade', 'data_publicacao', 'status_geral', 'link_arte_final', 'tipo', 'cliente_id', 'tema', 'roteiro', 'legenda', 'inspiracao'];
    
    if (in_array($campo, $campos_permitidos)) {
        // Log de alterações
        $stmt = $pdo->prepare("SELECT {$campo} FROM planejamento WHERE id = ?");
        $stmt->execute([$id_tarefa]);
        $valor_antigo = $stmt->fetchColumn();

        if ($valor_antigo != $valor) {
            $usuario_log = $_SESSION['usuario_id'] ?? 1;
            $pdo->prepare("INSERT INTO planejamento_logs (tarefa_id, usuario_id, campo, valor_antigo, valor_novo) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id_tarefa, $usuario_log, $campo, $valor_antigo, $valor]);
        }

        $pdo->prepare("UPDATE planejamento SET {$campo} = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$valor, $id_tarefa]);
        echo "ok"; exit;
    }
}

$id = $_GET['id'] ?? 0;

// Busca os dados completos da tarefa
$stmt = $pdo->prepare("SELECT p.*, cli.nome as cliente_nome 
                       FROM planejamento p 
                       LEFT JOIN clientes cli ON p.cliente_id = cli.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) die("Tarefa não encontrada.");

// Dados para os selects (Puxados do banco igual no index.php)
$usuarios = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetchAll();
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
$task_categorias = $pdo->query("SELECT * FROM task_categorias ORDER BY nome ASC")->fetchAll();

$status_lista = [
    'a_fazer' => 'A fazer',
    'em_execucao' => 'Em execução',
    'revisao_interna' => 'Revisão Interna',
    'revisao_externa' => 'Revisão externa',
    'aguardar_interno' => 'Aguardar Interno',
    'aguardar_cliente' => 'Aguardar Cliente',
    'postar' => 'Postar',
    'finalizado' => 'Finalizado',
    'arquivado' => 'Arquivado'
];

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/planejamento.css?v=<?= time() ?>">

<div style="max-width: 1200px; margin: 0 auto; width: 100%; padding-top: 10px; padding-bottom: 50px;">

    <!-- CABEÇALHO -->
    <div class="header-planejamento">
        <div class="header-title-block">
            <h2 class="page-title">Visualização da Tarefa <?= $item['id'] ?></h2>
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

    <!-- METADADOS DA TAREFA -->
    <div class="item-resumo" style="background: var(--bg-elevated); border: 1px solid var(--border-mid); border-radius: 8px; padding: 25px; margin-bottom: 25px;">
        
        <!-- GRID DE SELECTS -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <!-- Cliente -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Cliente</label>
                <select onchange="salvarCampo(<?= $item['id'] ?>, 'cliente_id', this.value)" class="gn-select silent-select" style="width: 100%; font-weight: 600;">
                    <option value="">Interno</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $item['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Categoria -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Categoria</label>
                <select onchange="salvarCampo(<?= $item['id'] ?>, 'tipo', this.value)" class="gn-select silent-select" style="width: 100%; font-weight: 600;">
                    <option value="">—</option>
                    <?php foreach($task_categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['nome']) ?>" <?= ($item['tipo'] ?? '') == $cat['nome'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Prazo -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Prazo</label>
                <input type="date" value="<?= $item['data_publicacao'] ?? '' ?>" onchange="salvarCampo(<?= $item['id'] ?>, 'data_publicacao', this.value)" class="silent-input" style="width: 100%; border: 1px solid var(--border-mid); padding: 8px 12px; border-radius: 6px; background: rgba(0,0,0,0.1); color: var(--text-primary);">
            </div>
            <!-- Prioridade -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Prioridade</label>
                <select onchange="salvarCampo(<?= $item['id'] ?>, 'prioridade', this.value)" class="gn-select silent-select" style="width: 100%; font-weight: 600;">
                    <option value="baixa" <?= ($item['prioridade']??'')=='baixa'?'selected':'' ?>>Baixa</option>
                    <option value="media" <?= ($item['prioridade']??'')=='media'?'selected':'' ?>>Média</option>
                    <option value="alta" <?= ($item['prioridade']??'')=='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= ($item['prioridade']??'')=='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </div>
            <!-- Responsável -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Responsável</label>
                <select onchange="salvarCampo(<?= $item['id'] ?>, 'responsavel_id', this.value)" class="gn-select silent-select" style="width: 100%; font-weight: 600;">
                    <option value="">Sem Responsável</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($item['responsavel_id']??'')==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Status Geral -->
            <div>
                <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Status Geral</label>
                <select onchange="salvarCampo(<?= $item['id'] ?>, 'status_geral', this.value)" class="gn-select silent-select" style="width: 100%; font-weight: bold; border-color: var(--gn-blue);">
                    <?php foreach($status_lista as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($item['status_geral']??'')==$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- TEMA E LINK -->
        <div style="margin-bottom: 20px;">
            <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Tema / Título Principal</label>
            <input type="text" value="<?= htmlspecialchars($item['tema'] ?? '') ?>" onchange="salvarCampo(<?= $item['id'] ?>, 'tema', this.value)" class="silent-input" style="width: 100%; font-size: 18px; font-weight: bold; padding: 12px; border: 1px solid var(--border-mid); border-radius: 6px; background: rgba(0,0,0,0.1); color: var(--text-primary);">
        </div>

        <div>
            <label class="label" style="font-size: 11px; text-transform: uppercase; color: var(--text-3); font-weight: bold; margin-bottom: 6px; display: block;">Link de Entrega (Drive / Canva)</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="url" id="linkArteFinal" value="<?= htmlspecialchars($item['link_arte_final'] ?? '') ?>" onchange="salvarCampo(<?= $item['id'] ?>, 'link_arte_final', this.value)" class="silent-input" style="flex: 1; padding: 12px; border: 1px solid var(--border-mid); border-radius: 6px; background: rgba(0,0,0,0.1); color: var(--text-primary);" placeholder="Cole a URL do projeto aqui...">
                <a id="btnAbrirLinkItem" href="<?= htmlspecialchars($item['link_arte_final'] ?? '#') ?>" target="_blank" class="btn btn-secondary" style="display: <?= empty($item['link_arte_final']) ? 'none' : 'inline-flex' ?>; align-items: center; justify-content: center; height: 44px; padding: 0 20px;">
                    <i class="ph ph-arrow-square-out"></i> Abrir Link
                </a>
            </div>
        </div>
    </div>

    <!-- ÁREA DE CONTEÚDO (ROTEIRO, LEGENDA E INSPIRAÇÃO) -->
    <div style="display: flex; flex-direction: column; gap: 25px;">
        
        <!-- Roteiro / Briefing -->
        <div style="background: var(--bg-elevated); border: 1px solid var(--border-mid); border-radius: 8px; padding: 25px;">
            <label class="label" style="font-size: 14px; color: var(--text-primary); font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="ph-fill ph-file-text" style="color: var(--gn-blue);"></i> Descrição / Roteiro / Briefing
            </label>
            <textarea onchange="salvarCampo(<?= $item['id'] ?>, 'roteiro', this.value)" class="silent-input" rows="12" style="width: 100%; padding: 15px; border: 1px solid var(--border-mid); border-radius: 6px; background: rgba(0,0,0,0.1); resize: vertical; color: var(--text-primary); font-family: inherit;" placeholder="Detalhe a tarefa ou escreva o roteiro do vídeo aqui..."><?= htmlspecialchars($item['roteiro'] ?? '') ?></textarea>
        </div>

        <!-- Legenda e Inspiração Lado a Lado -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <div style="background: var(--bg-elevated); border: 1px solid var(--border-mid); border-radius: 8px; padding: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <label class="label" style="font-size: 14px; color: var(--text-primary); font-weight: bold; display: flex; align-items: center; gap: 8px; margin: 0;">
                        <i class="ph-fill ph-chat-centered-text" style="color: var(--gn-pink);"></i> Legenda do Post
                    </label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copiarLegenda()" id="btnCopiarLeg" style="padding: 4px 10px; font-size: 12px; height: auto;">
                        <i class="ph ph-copy"></i> Copiar
                    </button>
                </div>
                <textarea id="textoLegenda" onchange="salvarCampo(<?= $item['id'] ?>, 'legenda', this.value)" class="silent-input" rows="8" style="width: 100%; padding: 15px; border: 1px solid var(--border-mid); border-radius: 6px; background: rgba(0,0,0,0.1); resize: vertical; color: var(--text-primary); font-family: inherit;" placeholder="Texto da legenda para publicar..."><?= htmlspecialchars($item['legenda'] ?? '') ?></textarea>
            </div>

            <div style="background: var(--bg-elevated); border: 1px solid var(--border-mid); border-radius: 8px; padding: 25px;">
                <label class="label" style="font-size: 14px; color: var(--text-primary); font-weight: bold; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="ph-fill ph-lightbulb" style="color: var(--gn-orange);"></i> Inspiração (Links de Referência)
                </label>
                <textarea onchange="salvarCampo(<?= $item['id'] ?>, 'inspiracao', this.value)" class="silent-input" rows="8" style="width: 100%; padding: 15px; border: 1px solid var(--border-mid); border-radius: 6px; background: rgba(0,0,0,0.1); resize: vertical; color: var(--text-primary); font-family: inherit;" placeholder="Cole os links de referência do Instagram/TikTok aqui..."><?= htmlspecialchars($item['inspiracao'] ?? '') ?></textarea>
            </div>
        </div>

    </div>

</div>

<!-- IA FLUTUANTE -->
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
            A tela de edição agora salva automaticamente assim que você clica fora dos campos.<br>
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
// --- SALVAMENTO VIA AJAX (AUTO-SAVE) ---
function salvarCampo(id, campo, valor) {
    let fd = new FormData();
    fd.append('acao', 'atualizar_campo');
    fd.append('id_tarefa', id);
    fd.append('campo', campo);
    fd.append('valor', valor);

    fetch('item.php?id=' + id, {
        method: 'POST',
        body: fd
    })
    .then(res => res.text())
    .then(txt => {
        if(txt.trim() === 'ok') {
            mostrarNotificacao();
            
            // Controle dinâmico do botão de abrir link
            if(campo === 'link_arte_final') {
                const btn = document.getElementById('btnAbrirLinkItem');
                if(valor.trim() !== '') {
                    btn.href = valor;
                    btn.style.display = 'inline-flex';
                } else {
                    btn.href = '#';
                    btn.style.display = 'none';
                }
            }
        }
    }).catch(err => console.error("Erro ao salvar:", err));
}

// --- COPIAR LEGENDA ---
function copiarLegenda() {
    const legenda = document.getElementById('textoLegenda').value;
    const btn = document.getElementById('btnCopiarLeg');
    
    if (!legenda || legenda.trim() === '') {
        alert('A legenda está vazia.');
        return;
    }

    navigator.clipboard.writeText(legenda).then(() => {
        const textoOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="ph-fill ph-check-circle"></i> Copiado!';
        btn.style.color = '#1fa463';
        btn.style.borderColor = '#1fa463';

        setTimeout(() => {
            btn.innerHTML = textoOriginal;
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 2000);
    });
}

// --- TOAST DE NOTIFICAÇÃO ---
function mostrarNotificacao() {
    let toast = document.getElementById('gasmaskeToast');
    if(!toast) {
        toast = document.createElement('div');
        toast.id = 'gasmaskeToast';
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.background = 'var(--gn-green, #10b981)';
        toast.style.color = '#fff';
        toast.style.padding = '12px 24px';
        toast.style.borderRadius = '8px';
        toast.style.fontWeight = '600';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.style.zIndex = '9999';
        toast.style.transition = 'opacity 0.3s ease-in-out';
        toast.innerHTML = '<i class="ph-fill ph-check-circle"></i> Salvo automaticamente!';
        document.body.appendChild(toast);
    }
    
    toast.style.opacity = '1';
    setTimeout(() => toast.style.opacity = '0', 2500);
}

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