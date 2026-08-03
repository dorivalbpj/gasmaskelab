<?php
// modules/planejamento/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

// --- AJAX: ATUALIZAÇÕES RÁPIDAS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'atualizar_campo') {
        $id = $_POST['id_tarefa'];
        $campo = $_POST['campo'];
        $valor = empty($_POST['valor']) ? null : $_POST['valor'];
        
        $campos = ['responsavel_id', 'prioridade', 'data_publicacao', 'status_geral', 'link_arte_final', 'tipo', 'cliente_id', 'tema'];
        if (in_array($campo, $campos)) {
            $pdo->prepare("UPDATE planejamento SET {$campo} = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$valor, $id]);
            echo "ok"; exit;
        }
    }

    if ($_POST['acao'] == 'salvar_roteiro') {
        $pdo->prepare("UPDATE planejamento SET roteiro = ? WHERE id = ?")->execute([$_POST['roteiro'], $_POST['id_tarefa']]);
        echo "ok"; exit;
    }

    if ($_POST['acao'] == 'salvar_legenda') {
        $pdo->prepare("UPDATE planejamento SET legenda = ? WHERE id = ?")->execute([$_POST['legenda'], $_POST['id_tarefa']]);
        echo "ok"; exit;
    }

    if ($_POST['acao'] == 'salvar_inspiracao') {
        $pdo->prepare("UPDATE planejamento SET inspiracao = ? WHERE id = ?")->execute([$_POST['inspiracao'], $_POST['id_tarefa']]);
        echo "ok"; exit;
    }

    if ($_POST['acao'] == 'quick_add') {
        $tema = trim($_POST['tema']);
        $cliente_id = empty($_POST['cliente_id']) ? null : $_POST['cliente_id'];
        
        if ($tema) {
            $sql = "INSERT INTO planejamento (tema, cliente_id, prioridade, status_geral, data_publicacao) 
        VALUES (?, ?, 'media', 'a_fazer', CURDATE())";
            $pdo->prepare($sql)->execute([$tema, $cliente_id]);
        }
        header("Location: index.php"); exit;
    }
}

// Dados para os selects
$usuarios = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetchAll();
$usuarios_map = [];
foreach($usuarios as $u) {
    $usuarios_map[$u['id']] = $u['nome'];
}

// --- GERADOR DINÂMICO DE CORES PARA OS RESPONSÁVEIS ---
echo '<style>';
// Uma paleta de 10 cores sólidas e modernas
$paleta = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#0284c7'];
$c_index = 0;
foreach($usuarios as $u) {
    $cor = $paleta[$c_index % count($paleta)];
    // Cria uma classe CSS para cada ID de usuário
    echo ".pill-resp-{$u['id']} { background-color: {$cor} !important; color: #fff !important; border-color: transparent !important; } \n";
    $c_index++;
}
echo '</style>';

// Buscar CLIENTES diretamente da tabela clientes
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();

// ---GERADOR DINÂMICO DE CORES PARA OS CLIENTES ---
echo '<style>';
// Paleta estendida com 15 cores modernas para dar mais variação
$paleta_clientes = ['#059669', '#2563eb', '#7c3aed', '#db2777', '#dc2626', '#d97706', '#65a30d', '#0d9488', '#0284c7', '#4f46e5', '#c026d3', '#e11d48', '#ea580c', '#ca8a04', '#4d7c0f'];
$c_index_cli = 0;

// Cor padrão para os projetos internos (quando não tem cliente)
echo ".pill-cliente-interno { background-color: #334155 !important; color: #fff !important; border-color: transparent !important; } \n";

foreach($clientes as $c) {
    $cor_cli = $paleta_clientes[$c_index_cli % count($paleta_clientes)];
    echo ".pill-cliente-{$c['id']} { background-color: {$cor_cli} !important; color: #fff !important; border-color: transparent !important; } \n";
    $c_index_cli++;
}
echo '</style>';

$categorias_fixas = ['Carrossel', 'Video', 'Estático', 'Roteiro', 'Captação', 'Operacional', 'Social', 'Design', 'Email', 'Blog', 'Thumb', 'Orçamento', 'Pessoal'];

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

// Busca principal
$tarefas = $pdo->query("
    SELECT p.*, c.nome as cliente_nome 
    FROM planejamento p 
    LEFT JOIN clientes c ON p.cliente_id = c.id 
    ORDER BY p.data_publicacao ASC
")->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/planejamento.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title">Planejamento</h2>
        <p class="page-subtitle">Gestão operacional de tarefas.</p>
    </div>
    
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <!-- CONTROLE DE ZOOM -->
        <div class="zoom-control" style="display: flex; align-items: center; gap: 5px; margin-right: 10px;">
            <i class="ph ph-magnifying-glass-minus" style="color: var(--text-muted); font-size: 18px;"></i>
            <input type="range" id="tableZoom" min="60" max="130" value="100" oninput="document.getElementById('mainTable').style.zoom = this.value + '%'; localStorage.setItem('planejamento_zoom', this.value);" style="width: 80px; cursor: pointer;">
            <i class="ph ph-magnifying-glass-plus" style="color: var(--text-muted); font-size: 18px;"></i>
        </div>

        <select id="groupBySelect" class="gn-select" onchange="agruparTabela(true)" style="width: auto; height: 40px; border: 1px solid var(--border-mid);">
            <option value="none">Lista Simples</option>
            <option value="cliente">Agrupar por Cliente</option>
            <option value="categoria">Agrupar por Categoria</option>
            <option value="responsavel">Agrupar por Responsável</option>
            <option value="status">Agrupar por Status</option>
            <option value="data">Agrupar por Data</option>
        </select>
        <button class="btn btn-primary" onclick="document.getElementById('rowNewTask').style.display='table-row'; document.getElementById('inputNewTema').focus();" style="height: 40px; display: flex; align-items: center; gap: 8px;"><i class="ph ph-plus"></i> Nova Tarefa</button>
    </div>
</div>

<!-- Formulário real invisível para o quick add -->
<form id="realQuickAddForm" method="POST" style="display:none;">
    <input type="hidden" name="acao" value="quick_add">
    <input type="hidden" name="cliente_id" id="hiddenQuickCliente">
    <input type="hidden" name="tema" id="hiddenQuickTema">
</form>

<div class="card" style="padding: 0; overflow-x: auto;">
    <table class="notion-table" id="mainTable">
        <thead>
            <tr>
                <th class="sortable resizable" style="width: 13%;" onclick="sortTable('cliente')">Cliente <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 9%;" onclick="sortTable('categoria')">Categoria <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 28%;" onclick="sortTable('tema')">Tarefa <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('data')">Prazo <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('prioridade')">Prio <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 13%;" onclick="sortTable('responsavel')">Responsável <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('status')">Status <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th style="text-align: center; width: 60px;">+</th>
            </tr>
        </thead>
        <tbody id="tableBody">

        <!-- Quick Add -->
        <tr id="rowNewTask" style="display: none; background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-mid);">
            <td>
                <select id="quickClienteId" onchange="this.className='silent-select pill '+(this.value ? 'pill-cliente-'+this.value : 'pill-cliente-interno')" class="silent-select pill pill-cliente-interno" style="font-weight: 600; border: 1px solid var(--border-mid); color: #fff;">
                    <option value="">Interno...</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td colspan="7">
                <input type="text" id="inputNewTema" class="silent-input" placeholder="O que precisa ser feito? + Enter" style="border: 1px solid var(--border-mid); font-weight: bold; background: transparent; color: var(--text-primary);" onkeydown="if(event.key === 'Enter') { event.preventDefault(); quickAddSubmit(); }">
            </td>
        </tr>

        <?php foreach ($tarefas as $t): 
            $hoje = date('Y-m-d');
            $estilo_data = '';
            if($t['status_geral'] != 'finalizado') {
                if($t['data_publicacao'] < $hoje && !empty($t['data_publicacao'])) {
                    $estilo_data = 'prazo-vencido';
                } elseif($t['data_publicacao'] == $hoje) {
                    $estilo_data = 'prazo-hoje';
                } else {
                    $estilo_data = 'prazo-normal';
                }
            }
            $resp_nome = ($t['responsavel_id'] && isset($usuarios_map[$t['responsavel_id']])) ? $usuarios_map[$t['responsavel_id']] : 'Sem Resp.';
            $categoria = $t['tipo'] ?? '';
            $tem_link = !empty($t['link_arte_final']);
            $esta_finalizado = ($t['status_geral'] == 'finalizado');
        ?>
        <tr class="task-row<?= $esta_finalizado ? ' tarefa-finalizada' : '' ?>" 
            data-cliente="<?= htmlspecialchars($t['cliente_nome'] ?: 'Interno') ?>" 
            data-categoria="<?= htmlspecialchars($categoria) ?>"
            data-tema="<?= htmlspecialchars($t['tema'] ?? '') ?>"
            data-data="<?= $t['data_publicacao'] ?? '' ?>"
            data-prioridade="<?= $t['prioridade'] ?? '' ?>"
            data-responsavel="<?= htmlspecialchars($resp_nome) ?>"
            data-status="<?= htmlspecialchars($status_lista[$t['status_geral']] ?? $t['status_geral']) ?>"
            data-status-key="<?= htmlspecialchars($t['status_geral']) ?>"
            data-finalizado="<?= $esta_finalizado ? '1' : '0' ?>">

            <!-- Cliente -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'cliente_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-cliente-'+this.value : 'pill-cliente-interno'); this.closest('tr').setAttribute('data-cliente', this.options[this.selectedIndex].text); agruparTabela(false);" class="silent-select pill <?= $t['cliente_id'] ? 'pill-cliente-'.$t['cliente_id'] : 'pill-cliente-interno' ?>" style="font-weight: 600;">
                    <option value="">Interno</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $t['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Categoria -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'tipo', this.value); this.closest('tr').setAttribute('data-categoria', this.value); agruparTabela(false);" class="silent-select" style="font-size: 12px;">
                    <option value="">—</option>
                    <?php foreach($categorias_fixas as $cat): ?>
                        <option value="<?= $cat ?>" <?= $categoria == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Tarefa -->
            <td>
                <input type="text" id="input_tema_<?= $t['id'] ?>" class="silent-input" value="<?= htmlspecialchars($t['tema'] ?? '') ?>" onchange="salvar(<?= $t['id'] ?>, 'tema', this.value); document.getElementById('hidden_tema_<?= $t['id'] ?>').value = this.value;" style="font-weight: 600; color: var(--text-primary);">
                
                <!-- Campos hidden para o side modal -->
                <textarea id="hidden_rot_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['roteiro'] ?? '') ?></textarea>
                <textarea id="hidden_leg_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['legenda'] ?? '') ?></textarea>
                <textarea id="hidden_ins_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['inspiracao'] ?? '') ?></textarea>
                <input type="hidden" id="hidden_link_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['link_arte_final'] ?? '') ?>">
                <input type="hidden" id="hidden_tema_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['tema'] ?? '') ?>">
            </td>
            
            <!-- Prazo -->
            <td><input type="date" class="silent-input <?= $estilo_data ?>" value="<?= $t['data_publicacao'] ?? '' ?>" onchange="salvar(<?= $t['id'] ?>, 'data_publicacao', this.value); this.closest('tr').setAttribute('data-data', this.value); recalcularEstiloLinha(this.closest('tr')); agruparTabela(false);"></td>

            <!-- Prioridade -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'prioridade', this.value); this.className='silent-select pill pill-prio-'+this.value; this.closest('tr').setAttribute('data-prioridade', this.value); agruparTabela(false);" class="silent-select pill pill-prio-<?= $t['prioridade'] ?>">
                    <option value="baixa" <?= $t['prioridade']=='baixa'?'selected':'' ?>>Baixa</option>
                    <option value="media" <?= $t['prioridade']=='media'?'selected':'' ?>>Média</option>
                    <option value="alta" <?= $t['prioridade']=='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= $t['prioridade']=='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </td>

            <!-- Responsável -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'responsavel_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-resp-'+this.value : 'pill-resp-vazio'); this.closest('tr').setAttribute('data-responsavel', this.options[this.selectedIndex].text); agruparTabela(false);" class="silent-select pill <?= $t['responsavel_id'] ? 'pill-resp-'.$t['responsavel_id'] : 'pill-resp-vazio' ?>">
                    <option value="">-</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $t['responsavel_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Status -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'status_geral', this.value); this.className='silent-select pill pill-status-'+this.value; atualizarStatusLinha(this);" class="silent-select pill pill-status-<?= $t['status_geral'] ?>">
                    <?php foreach($status_lista as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $t['status_geral']==$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Abrir Side Modal -->
            <td style="text-align: center;">
                <button onclick="abrirSide(<?= $t['id'] ?>)" class="btn-ghost" style="padding: 4px; color: <?= $tem_link ? '#1fa463' : 'var(--text-muted)' ?>; opacity: <?= $tem_link ? '1' : '0.6' ?>;" title="Abrir detalhes">
                    <i class="<?= $tem_link ? 'ph-fill ph-check-circle' : 'ph ph-plus-circle' ?>" style="font-size: 22px;"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Side Modal -->
<div class="overlay" id="overlay" onclick="fecharSide()"></div>
<div class="side-modal" id="sideModal">
    <div class="side-modal-header">
        <div style="flex: 1;">
            <input type="text" id="sideTituloInput" class="side-modal-title-input" onchange="salvar(document.getElementById('sideId').value, 'tema', this.value); document.getElementById('hidden_tema_'+document.getElementById('sideId').value).value = this.value; document.getElementById('input_tema_'+document.getElementById('sideId').value).value = this.value;">
        </div>
        <button onclick="fecharSide()" class="btn-ghost" style="padding: 4px; flex-shrink: 0;"><i class="ph ph-x" style="font-size: 20px;"></i></button>
    </div>
    <div class="side-modal-body">
        <input type="hidden" id="sideId">

        <div class="side-section">
            <label class="side-section-label">Link de Entrega (Drive / Canva)</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" id="sideLink" class="silent-input" placeholder="Cole o link aqui..." onchange="updateDrivePreview(this.value)" style="flex: 1; border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2);">
                <a id="btnAbrirLink" href="#" target="_blank" class="btn-abrir-link" style="flex-shrink: 0;">
                    <i class="ph ph-arrow-square-out"></i> Abrir
                </a>
            </div>
            <div id="drivePreview"></div>
        </div>

        <div class="side-section">
            <label class="side-section-label">Descrição / Roteiro</label>
            <textarea id="sideRoteiro" class="silent-input" style="height: 160px; border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2); resize: vertical; padding: 12px; width: 100%; box-sizing: border-box;" placeholder="Roteiro, briefing ou instruções..."></textarea>
        </div>

        <div class="side-section">
            <label class="side-section-label">Legenda do Post</label>
            <textarea id="sideLegenda" class="silent-input" style="height: 160px; border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2); resize: vertical; padding: 12px; width: 100%; box-sizing: border-box;" placeholder="Texto da legenda para publicar..."></textarea>
        </div>

        <div class="side-section">
            <label class="side-section-label">Inspiração (links de referência)</label>
            <textarea id="sideInspiracao" class="silent-input" style="height: 80px; border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2); resize: vertical; padding: 12px; width: 100%; box-sizing: border-box;" placeholder="Links de posts de inspiração..."></textarea>
        </div>
    </div>
    <div class="side-modal-footer">
        <button onclick="salvarTudoSide()" class="btn-save-lg">
            <i class="ph-fill ph-floppy-disk" style="font-size: 20px;"></i> 
            SALVAR TODAS AS ALTERAÇÕES
        </button>
    </div>
</div>

<script>
// Pega a data de hoje diretamente do servidor para não dar conflito de fuso horário
const dataHoje = '<?= date('Y-m-d') ?>';

// Detecta se está no formato mobile (mesmo breakpoint do CSS)
function isMobileView() {
    return window.innerWidth <= 768;
}

// Mapa status_geral (chave) -> rótulo, usado para atualizar tudo em tempo real
const statusMap = <?= json_encode($status_lista, JSON_UNESCAPED_UNICODE) ?>;

// ==========================================
// ATUALIZAÇÃO EM TEMPO REAL (sem precisar dar F5)
// ==========================================

// Recalcula a cor do prazo (vencido/hoje/normal) e o "apagado" de finalizado
function recalcularEstiloLinha(row) {
    const dateInput = row.querySelector('td input[type="date"]');
    const statusKey = row.getAttribute('data-status-key');
    const finalizado = statusKey === 'finalizado';

    if (dateInput) {
        dateInput.classList.remove('prazo-vencido', 'prazo-hoje', 'prazo-normal');
        if (!finalizado) {
            const val = dateInput.value;
            if (val && val < dataHoje) {
                dateInput.classList.add('prazo-vencido');
            } else if (val === dataHoje) {
                dateInput.classList.add('prazo-hoje');
            } else {
                dateInput.classList.add('prazo-normal');
            }
        }
    }

    row.setAttribute('data-finalizado', finalizado ? '1' : '0');
    row.classList.toggle('tarefa-finalizada', finalizado);
}

// Chamado quando o STATUS muda: atualiza tudo (cor de prazo, apagado, agrupamento) na hora
function atualizarStatusLinha(select) {
    const row = select.closest('tr');
    const statusKey = select.value;
    row.setAttribute('data-status-key', statusKey);
    row.setAttribute('data-status', statusMap[statusKey] || statusKey);
    recalcularEstiloLinha(row);
    agruparTabela(false);
}

// FUNÇÃO NOVA: Formata a data para o padrão Brasileiro
function formatarDataVisao(dataStr) {
    if (!dataStr || dataStr.indexOf('-') === -1) return dataStr;
    
    const partes = dataStr.split('-');
    if (partes.length !== 3) return dataStr;
    
    const ano = partes[0];
    const mes = partes[1];
    const dia = partes[2];
    const anoAtual = new Date().getFullYear().toString();
    
    // Se for o ano atual, exibe só DIA/MÊS. Se for diferente, exibe DIA/MÊS/ANO.
    if (ano === anoAtual) {
        return `${dia}/${mes}`;
    } else {
        return `${dia}/${mes}/${ano}`;
    }
}

function quickAddSubmit() {
    const tema = document.getElementById('inputNewTema').value.trim();
    if(!tema) return;
    document.getElementById('hiddenQuickTema').value = tema;
    document.getElementById('hiddenQuickCliente').value = document.getElementById('quickClienteId').value;
    document.getElementById('inputNewTema').disabled = true;
    document.getElementById('inputNewTema').value = 'Adicionando...';
    document.getElementById('realQuickAddForm').submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const groupKey = isMobileView() ? 'planejamento_group_mobile' : 'planejamento_group';
    const lastGroup = localStorage.getItem(groupKey);

    if (lastGroup && lastGroup !== 'none') {
        document.getElementById('groupBySelect').value = lastGroup;
    } else if (!lastGroup && isMobileView()) {
        // No celular, sem preferência salva ainda: já abre organizado por data
        // (assim "Hoje" e "Atrasadas" ficam em evidência e o resto some até precisar)
        document.getElementById('groupBySelect').value = 'data';
    }

    // Restaura o zoom salvo da última vez
    const lastZoom = localStorage.getItem('planejamento_zoom');
    if (lastZoom) {
        document.getElementById('tableZoom').value = lastZoom;
        document.getElementById('mainTable').style.zoom = lastZoom + '%';
    }

    sortTable('data', false);
});

function salvar(id, campo, valor) {
    let fd = new FormData();
    fd.append('acao', 'atualizar_campo');
    fd.append('id_tarefa', id);
    fd.append('campo', campo);
    fd.append('valor', valor);
    fetch('index.php', {method: 'POST', body: fd});
}

function abrirSide(id) {
    const link = document.getElementById('hidden_link_'+id).value;
    
    document.getElementById('sideId').value = id;
    document.getElementById('sideTituloInput').value = document.getElementById('hidden_tema_'+id).value;
    document.getElementById('sideRoteiro').value = document.getElementById('hidden_rot_'+id).value;
    document.getElementById('sideLegenda').value = document.getElementById('hidden_leg_'+id).value;
    document.getElementById('sideInspiracao').value = document.getElementById('hidden_ins_'+id).value;
    document.getElementById('sideLink').value = link;
    
    // Atualiza o botão de abrir link
    const btnAbrir = document.getElementById('btnAbrirLink');
    if(link && link.trim() !== '') {
        btnAbrir.href = link;
        btnAbrir.style.display = 'inline-flex';
        btnAbrir.disabled = false;
    } else {
        btnAbrir.href = '#';
        btnAbrir.style.display = 'none';
    }
    
    updateDrivePreview(link);

    document.getElementById('overlay').classList.add('open');
    document.getElementById('sideModal').classList.add('open');
}

function fecharSide() {
    document.getElementById('overlay').classList.remove('open');
    document.getElementById('sideModal').classList.remove('open');
}

function updateDrivePreview(url) {
    const container = document.getElementById('drivePreview');
    const btnAbrir = document.getElementById('btnAbrirLink');
    
    if(url && url.trim() !== '') {
        btnAbrir.style.display = 'inline-flex';
        btnAbrir.href = url;
        btnAbrir.disabled = false;
        
        if(url.includes('drive.google.com')) {
            container.innerHTML = `<div class="drive-card">
                <i class="ph-fill ph-google-drive-logo drive-icon"></i>
                <div class="drive-info">
                    <strong>Arquivo no Google Drive</strong><br>
                    <a href="${url}" target="_blank">Abrir em nova aba</a>
                </div>
            </div>`;
        } 
    } else {
        btnAbrir.style.display = 'none';
        container.innerHTML = '';
    }
}

function salvarTudoSide() {
    const id = document.getElementById('sideId').value;
    const roteiro = document.getElementById('sideRoteiro').value;
    const legenda = document.getElementById('sideLegenda').value;
    const inspiracao = document.getElementById('sideInspiracao').value;
    const link = document.getElementById('sideLink').value;

    salvar(id, 'link_arte_final', link);

    let fd1 = new FormData();
    fd1.append('acao', 'salvar_roteiro');
    fd1.append('id_tarefa', id);
    fd1.append('roteiro', roteiro);

    let fd2 = new FormData();
    fd2.append('acao', 'salvar_legenda');
    fd2.append('id_tarefa', id);
    fd2.append('legenda', legenda);

    let fd3 = new FormData();
    fd3.append('acao', 'salvar_inspiracao');
    fd3.append('id_tarefa', id);
    fd3.append('inspiracao', inspiracao);

    const btn = document.querySelector('.btn-save-lg');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin" style="font-size: 20px;"></i> SALVANDO...';
    btn.disabled = true;

    Promise.all([
        fetch('index.php', {method: 'POST', body: fd1}),
        fetch('index.php', {method: 'POST', body: fd2}),
        fetch('index.php', {method: 'POST', body: fd3})
    ]).then(() => {
        window.location.reload();
    });
}

let currentSortCol = null;
let currentSortAsc = true;

function sortTable(col, toggle = true) {
    if (toggle) {
        currentSortAsc = (currentSortCol === col) ? !currentSortAsc : true;
        currentSortCol = col;
    } else if (!currentSortCol) {
        currentSortCol = 'data';
        currentSortAsc = true;
    }

    document.querySelectorAll('.sortable i').forEach(icon => {
        icon.className = 'ph ph-arrows-down-up';
        icon.style.opacity = '0.5';
    });

    const activeTh = document.querySelector(`th[onclick="sortTable('${currentSortCol}')"] i`);
    if(activeTh) {
        activeTh.className = currentSortAsc ? 'ph ph-sort-ascending' : 'ph ph-sort-descending';
        activeTh.style.opacity = '1';
    }

    const tbody = document.getElementById('tableBody');
    const rows = Array.from(tbody.querySelectorAll('.task-row'));

    rows.sort((a, b) => {
        let valA = a.getAttribute('data-' + currentSortCol).toLowerCase();
        let valB = b.getAttribute('data-' + currentSortCol).toLowerCase();
        let cmp = 0;

        // BLINDAGEM DA DATA: VAZIO SEMPRE NO FINAL
        if (currentSortCol === 'data') {
            let isAEmpty = (!valA || valA === '');
            let isBEmpty = (!valB || valB === '');
            
            if (isAEmpty && !isBEmpty) cmp = 1;
            else if (!isAEmpty && isBEmpty) cmp = -1;
            else if (isAEmpty && isBEmpty) cmp = 0;
            else if (valA < valB) cmp = currentSortAsc ? -1 : 1;
            else if (valA > valB) cmp = currentSortAsc ? 1 : -1;
        } else if (currentSortCol === 'prioridade') {
            const pMap = {'urgente': 4, 'alta': 3, 'media': 2, 'baixa': 1};
            valA = pMap[valA] || 0;
            valB = pMap[valB] || 0;
            cmp = currentSortAsc ? valA - valB : valB - valA;
        } else {
            if (valA < valB) cmp = currentSortAsc ? -1 : 1;
            else if (valA > valB) cmp = currentSortAsc ? 1 : -1;
        }

        // TAREFAS FINALIZADAS: sempre no final do seu "bucket" (mesmo dia/mesmo valor), apagadas
        if (cmp === 0) {
            const finA = a.getAttribute('data-finalizado') === '1';
            const finB = b.getAttribute('data-finalizado') === '1';
            if (finA !== finB) return finA ? 1 : -1;
        }

        return cmp;
    });

    const crit = document.getElementById('groupBySelect').value;
    const rowNewTask = document.getElementById('rowNewTask');
    tbody.innerHTML = '';
    tbody.appendChild(rowNewTask);

    if (crit === 'none') {
        rows.forEach(r => tbody.appendChild(r));
    } else {
        const groups = {};
        rows.forEach(r => {
            const val = r.getAttribute('data-'+crit) || '(vazio)';
            if(!groups[val]) groups[val] = [];
            groups[val].push(r);
        });
        
        Object.keys(groups).sort().forEach(g => {
            // Lógica do colapso: Se for grupo de data e a data for hoje, não colapsa. O resto, colapsa tudo.
            // No mobile, além de "hoje", as tarefas ATRASADAS também já ficam abertas por padrão
            // (são as mais urgentes de ver) - o resto (futuras) fica escondido até o usuário pedir.
            let isCollapsed = true;
            if (crit === 'data') {
                if (g === dataHoje) {
                    isCollapsed = false;
                } else if (isMobileView() && g && g !== '(vazio)' && g < dataHoje) {
                    isCollapsed = false;
                }
            }

            let labelDisplay = g;
            
            // AQUI APLICAMOS A NOVA FORMATAÇÃO DE DATA
            if (crit === 'data') {
                if (g === '(vazio)' || g === '') {
                    labelDisplay = 'Sem data definida';
                } else {
                    const dataFormatada = formatarDataVisao(g);
                    if (g === dataHoje) {
                        labelDisplay = 'Hoje (' + dataFormatada + ')';
                    } else {
                        labelDisplay = dataFormatada;
                    }
                }
            }

            const header = document.createElement('tr');
            header.className = isCollapsed ? 'group-header collapsed' : 'group-header';
            header.innerHTML = `<td colspan="8"><i class="ph ph-caret-down icone-colapso" style="margin-right: 5px;"></i> ${labelDisplay} <span style="color: var(--text-muted); font-size: 13px; font-weight: normal; margin-left: 5px;">(${groups[g].length})</span></td>`;
            tbody.appendChild(header);
            
            // Dentro do grupo, finalizados sempre por último (mantendo a ordem entre eles)
            const naoFinalizados = groups[g].filter(r => r.getAttribute('data-finalizado') !== '1');
            const finalizados = groups[g].filter(r => r.getAttribute('data-finalizado') === '1');
            const listaFinal = naoFinalizados.concat(finalizados);

            listaFinal.forEach(r => {
                if(isCollapsed) r.classList.add('linha-colapsada');
                else r.classList.remove('linha-colapsada');
                tbody.appendChild(r);
            });
        });

        // No mobile, resume o que ficou escondido num botão único pra não precisar caçar cabeçalho por cabeçalho
        if (isMobileView() && crit === 'data') {
            const escondidas = tbody.querySelectorAll('.task-row.linha-colapsada').length;
            if (escondidas > 0) {
                const btnRow = document.createElement('tr');
                btnRow.className = 'mobile-ver-futuras-row';
                const plural = escondidas > 1 ? 's' : '';
                btnRow.innerHTML = `<td colspan="8"><button type="button" class="btn-ver-futuras" onclick="expandirTudoMobile(this)">`
                    + `<i class="ph ph-caret-down"></i> Ver mais ${escondidas} tarefa${plural} futura${plural}`
                    + `</button></td>`;
                tbody.appendChild(btnRow);
            }
        }
    }
}

function agruparTabela(save) {
    const crit = document.getElementById('groupBySelect').value;
    if(save) {
        const groupKey = isMobileView() ? 'planejamento_group_mobile' : 'planejamento_group';
        localStorage.setItem(groupKey, crit);
    }
    sortTable(currentSortCol || 'data', false);
}

// Expande de uma vez todos os grupos/linhas escondidas (usado pelo botão "Ver mais futuras" no mobile)
function expandirTudoMobile(btn) {
    document.querySelectorAll('.group-header.collapsed').forEach(h => h.classList.remove('collapsed'));
    document.querySelectorAll('.task-row.linha-colapsada').forEach(r => r.classList.remove('linha-colapsada'));
    const row = btn.closest('tr');
    if (row) row.remove();
}

// ==========================================
// SISTEMA DE COLAPSO E CLIQUE MOBILE
// ==========================================
document.getElementById('tableBody').addEventListener('click', function(e) {
    // Ação 1: Colapso dos cabeçalhos de grupos
    const header = e.target.closest('.group-header');
    if (header) {
        header.classList.toggle('collapsed');
        let nextRow = header.nextElementSibling;
        while (nextRow && !nextRow.classList.contains('group-header')) {
            if (nextRow.classList.contains('task-row')) {
                nextRow.classList.toggle('linha-colapsada');
            }
            nextRow = nextRow.nextElementSibling;
        }
        return;
    }

    // Ação 2: Expansão do Card no Mobile
    if (window.innerWidth <= 768) {
        const row = e.target.closest('.task-row');
        // Só expande se não tiver clicado num campo de edição ou botão
        if (row && !['INPUT', 'SELECT', 'BUTTON', 'A', 'TEXTAREA', 'I'].includes(e.target.tagName)) {
            row.classList.toggle('expanded');
        }
    }
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>