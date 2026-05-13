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
        
        $campos = ['responsavel_id', 'prioridade', 'data_publicacao', 'status_geral', 'link_arte_final', 'tipo', 'contrato_id', 'tema'];
        if (in_array($campo, $campos)) {
            $pdo->prepare("UPDATE planejamento SET {$campo} = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$valor, $id]);
            echo "ok"; exit;
        }
    }

    if ($_POST['acao'] == 'salvar_roteiro') {
        $pdo->prepare("UPDATE planejamento SET objetivo = ? WHERE id = ?")->execute([$_POST['roteiro'], $_POST['id_tarefa']]);
        echo "ok"; exit;
    }

    if ($_POST['acao'] == 'quick_add') {
        $tema = trim($_POST['tema']);
        $contrato_id = empty($_POST['contrato_id']) ? null : $_POST['contrato_id'];
        $escopo = $contrato_id ? 'cliente' : 'interno';
        
        if ($tema) {
            $sql = "INSERT INTO planejamento (tema, contrato_id, escopo, prioridade, status_geral, data_publicacao) 
                    VALUES (?, ?, ?, 'media', 'pendente', CURDATE())";
            $pdo->prepare($sql)->execute([$tema, $contrato_id, $escopo]);
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
$contratos = $pdo->query("SELECT c.id, cli.nome FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id WHERE c.status != 'finalizado' ORDER BY cli.nome ASC")->fetchAll();

$status_lista = [
    'pendente' => 'Pendente', 'roteiro_em_producao' => 'Roteiro', 'roteiro_aguardando_aprovacao' => 'Aguard. Roteiro',
    'roteiro_em_revisao' => 'Ajuste Roteiro', 'peca_em_producao' => 'Arte', 'peca_aguardando_aprovacao' => 'Aguard. Arte',
    'peca_em_revisao' => 'Ajuste Arte', 'pronto_para_postar' => 'Agendar', 'finalizado' => 'Finalizado'
];

// Busca principal
$tarefas = $pdo->query("SELECT p.*, cli.nome as cliente_nome 
                        FROM planejamento p 
                        LEFT JOIN contratos c ON p.contrato_id = c.id 
                        LEFT JOIN clientes cli ON c.cliente_id = cli.id 
                        ORDER BY p.data_publicacao ASC")->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/planejamento.css">

<style>
    /* === NOTION UI/UX STYLES === */
    .notion-table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
    .notion-table th { text-align: left; padding: 12px 10px; color: var(--text-muted); font-weight: 500; border-bottom: 1px solid var(--border); font-size: 13px; }
    .notion-table td { padding: 4px 10px; border-bottom: 1px solid var(--border-light); vertical-align: middle; position: relative; }
    .notion-table tr.task-row:hover { background: rgba(255,255,255,0.02); }
    
    /* Inputs e Selects Silenciosos */
    .silent-input, .silent-select { width: 100%; background: transparent; border: 1px solid transparent; border-radius: 4px; padding: 6px 8px; font-family: inherit; font-size: inherit; color: var(--text-primary); transition: all 0.2s; outline: none; }
    .silent-input:hover, .silent-select:hover { background: rgba(255,255,255,0.05); border-color: var(--border-mid); }
    .silent-input:focus, .silent-select:focus { background: rgba(0,0,0,0.2); border-color: var(--blue); color: var(--text-primary); box-shadow: 0 0 0 2px rgba(26,110,232,0.15); font-weight: 500; }
    .silent-select:focus { background: #1a1a1a; border-color: var(--blue); }
    .silent-select option { background: #1a1a1a; color: var(--text-primary); padding: 8px; }
    
    /* Melhorias no Pills para melhor aparência */
    .pill { display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 16px; font-size: 13px; font-weight: 600; height: auto; line-height: 1.2; border: 1px solid transparent; width: 100%; }
    .pill:focus { outline: 2px solid var(--blue); outline-offset: 1px; }
    
    /* Cores de Status - TEMA ESCURO */
    .pill-status-pendente { background: rgba(255,255,255,0.1); color: #e5e7eb; }
    .pill-status-roteiro_em_producao { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .pill-status-roteiro_aguardando_aprovacao { background: rgba(245,158,11,0.2); color: #fcd34d; }
    .pill-status-roteiro_em_revisao { background: rgba(234,88,12,0.2); color: #fdba74; }
    .pill-status-peca_em_producao { background: rgba(168,85,247,0.2); color: #d8b4fe; }
    .pill-status-peca_aguardando_aprovacao { background: rgba(245,158,11,0.2); color: #fcd34d; }
    .pill-status-peca_em_revisao { background: rgba(234,88,12,0.2); color: #fdba74; }
    .pill-status-pronto_para_postar { background: rgba(99,102,241,0.2); color: #c7d2fe; }
    .pill-status-finalizado { background: rgba(16,185,129,0.2); color: #6ee7b7; }
    
    /* Cores de Prioridade - TEMA ESCURO */
    .pill-prio-baixa { background: rgba(255,255,255,0.1); color: #9ca3af; }
    .pill-prio-media { background: rgba(59,130,246,0.2); color: #93c5fd; }
    .pill-prio-alta { background: rgba(249,115,22,0.2); color: #fdba74; }
    .pill-prio-urgente { background: rgba(239,68,68,0.2); color: #fca5a5; }

    /* Cores de Responsável */
    .pill-resp-vazio { background: transparent; color: var(--text-muted); }
    .pill-resp-atribuido { background: rgba(99,102,241,0.2); color: #c7d2fe; border-color: rgba(99,102,241,0.3); }

    /* Botão Expandir */
    .btn-expand-task { background: transparent; border: 1px solid transparent; color: var(--blue); font-size: 18px; cursor: pointer; padding: 6px 8px; border-radius: 4px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
    .btn-expand-task:hover { background: rgba(255,255,255,0.05); border-color: var(--border-mid); transform: scale(1.1); }
    .btn-expand-task:active { transform: scale(0.95); }

    /* Quick Add - Nova Tarefa - TEMA ESCURO SUTIL */
    .quick-add-btn { background: transparent; cursor: pointer; transition: all 0.2s; border: none; border-bottom: 1px solid var(--border-light); }
    .quick-add-btn:hover { background: rgba(255,255,255,0.02); }
    .quick-add-btn td { padding: 12px 10px; color: var(--text-muted); font-size: 13px; font-weight: 500; display: flex; align-items: center; justify-content: center; gap: 8px; letter-spacing: 0; }
    .quick-add-btn td:hover { color: var(--text-primary); }
    .quick-add-btn i { font-size: 16px; font-weight: normal; color: var(--blue); }

    /* Adaptação de Agrupamento */
    .group-header td { background: var(--bg-surface); font-weight: 700; font-size: 14px; color: var(--text-primary); padding: 16px 10px 8px; border-bottom: 1px solid var(--border-mid); }
    
    /* Novo Header da Sidebar */
    .side-modal-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 15px; border-bottom: 1px solid var(--border-mid); padding: 24px 24px 20px; background: var(--bg-card); }
    .side-modal-title-input { font-size: 20px; font-weight: 700; font-family: 'Inter', sans-serif; padding: 0; height: auto; border: none; background: transparent; width: 100%; color: var(--text-primary); outline: none; }
    .side-modal-title-input:focus { background: rgba(255,255,255,0.05); border-radius: 4px; padding: 4px; margin: -4px; }

    /* Resizable and Sortable Columns */
    .notion-table th.resizable { resize: horizontal; overflow: hidden; position: relative; }
    .notion-table th.sortable { cursor: pointer; user-select: none; transition: background 0.2s; white-space: nowrap; }
    .notion-table th.sortable:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
    .notion-table th.sortable:hover i { opacity: 1 !important; }
    .notion-table td { white-space: nowrap; }
    .notion-table td:nth-child(2) { white-space: normal; } /* Tema pode quebrar linha */
</style>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Planejamento</h2>
        <p class="page-subtitle">Gestão operacional de tarefas.</p>
    </div>
    
    <div style="display: flex; gap: 10px;">
        <select id="groupBySelect" class="gn-select" onchange="agruparTabela(true)" style="width: auto; height: 40px; border: 1px solid var(--border-mid);">
            <option value="none">Lista Simples</option>
            <option value="cliente">Agrupar por Cliente</option>
            <option value="responsavel">Agrupar por Responsável</option>
            <option value="status">Agrupar por Status</option>
            <option value="data">Agrupar por Data</option>
        </select>
        <button class="btn btn-primary" onclick="document.getElementById('rowNewTask').style.display='table-row'; document.getElementById('inputNewTema').focus();" style="height: 40px; display: flex; align-items: center; gap: 8px;"><i class="ph ph-plus"></i> Nova Tarefa</button>
    </div>
</div>

<!-- Formulário real invisível para garantir funcionamento HTML -->
<form id="realQuickAddForm" method="POST" style="display:none;">
    <input type="hidden" name="acao" value="quick_add">
    <input type="hidden" name="contrato_id" id="hiddenQuickContrato">
    <input type="hidden" name="tema" id="hiddenQuickTema">
</form>

<div class="card" style="padding: 0; overflow-x: auto;">
    <table class="notion-table" id="mainTable">
        <thead>
            <tr>
                <th class="sortable resizable" style="width: 15%;"onclick="sortTable('cliente')">Cliente <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 30%;" onclick="sortTable('tema')">Tarefa (Tema) <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;"onclick="sortTable('data')">Prazo <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;"onclick="sortTable('prioridade')">Prio <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 15%;"onclick="sortTable('responsavel')">Responsável <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;"onclick="sortTable('status')">Status <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th style="text-align: center; width: 80px;">Mais</th>
            </tr>
        </thead>
        <tbody id="tableBody">
        <!-- Quick Add (Nova Tarefa no topo) -->
        <tr id="rowNewTask" style="display: none; background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-mid);">
            <td>
                <select id="quickContratoId" class="silent-select" style="font-weight: 600; border: 1px solid var(--border-mid); background: transparent; color: var(--text-primary);">
                    <option value="">Interno...</option>
                    <?php foreach($contratos as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td colspan="6">
                <input type="text" id="inputNewTema" class="silent-input" placeholder="O que precisa ser feito? + Enter" style="border: 1px solid var(--border-mid); font-weight: bold; background: transparent; color: var(--text-primary);" onkeydown="if(event.key === 'Enter') { event.preventDefault(); quickAddSubmit(); }">
            </td>
        </tr>

            <?php foreach ($tarefas as $t): 
                $hoje = date('Y-m-d');
                $estilo_data = '';
                if($t['status_geral'] != 'finalizado') {
                    if($t['data_publicacao'] < $hoje) {
                        $estilo_data = 'color: #fca5a5; font-weight: bold; background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.3);';
                    } elseif($t['data_publicacao'] == $hoje) {
                        $estilo_data = 'color: #fcd34d; font-weight: bold; background: rgba(245,158,11,0.2); border-color: rgba(245,158,11,0.3);';
                    } else {
                        $estilo_data = 'color: var(--text-primary);';
                    }
                }
                
                $resp_nome = ($t['responsavel_id'] && isset($usuarios_map[$t['responsavel_id']])) ? $usuarios_map[$t['responsavel_id']] : 'Sem Resp.';
            ?>
            <tr class="task-row" 
                data-cliente="<?= htmlspecialchars($t['cliente_nome'] ?: 'Interno') ?>" 
                data-tema="<?= htmlspecialchars($t['tema']) ?>"
                data-data="<?= $t['data_publicacao'] ?>"
                data-prioridade="<?= $t['prioridade'] ?>"
                data-responsavel="<?= htmlspecialchars($resp_nome) ?>"
                data-status="<?= htmlspecialchars($status_lista[$t['status_geral']]) ?>">

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'contrato_id', this.value)" class="silent-select" style="font-weight: 600;">
                        <option value="">Interno</option>
                        <?php foreach($contratos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $t['contrato_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td>
                    <input type="text" id="input_tema_<?= $t['id'] ?>" class="silent-input" value="<?= htmlspecialchars($t['tema']) ?>" onchange="salvar(<?= $t['id'] ?>, 'tema', this.value); document.getElementById('hidden_tema_<?= $t['id'] ?>').value = this.value;" style="font-weight: 600; color: var(--text-primary);">
                    
                    <textarea id="hidden_rot_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['objetivo']) ?></textarea>
                    <input type="hidden" id="hidden_link_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['link_arte_final']) ?>">
                    <input type="hidden" id="hidden_tema_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['tema']) ?>">
                </td>
                
                <td><input type="date" class="silent-input" value="<?= $t['data_publicacao'] ?>" onchange="salvar(<?= $t['id'] ?>, 'data_publicacao', this.value)" style="<?= $estilo_data ?>"></td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'prioridade', this.value); this.className='silent-select pill pill-prio-'+this.value" class="silent-select pill pill-prio-<?= $t['prioridade'] ?>">
                        <option value="baixa" <?= $t['prioridade']=='baixa'?'selected':'' ?>>Baixa</option>
                        <option value="media" <?= $t['prioridade']=='media'?'selected':'' ?>>Média</option>
                        <option value="alta" <?= $t['prioridade']=='alta'?'selected':'' ?>>Alta</option>
                        <option value="urgente" <?= $t['prioridade']=='urgente'?'selected':'' ?>>Urgente</option>
                    </select>
                </td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'responsavel_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-resp-atribuido' : 'pill-resp-vazio')" class="silent-select pill <?= $t['responsavel_id'] ? 'pill-resp-atribuido' : 'pill-resp-vazio' ?>">
                        <option value="">-</option>
                        <?php foreach($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $t['responsavel_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'status_geral', this.value); this.className='silent-select pill pill-status-'+this.value" class="silent-select pill pill-status-<?= $t['status_geral'] ?>">
                        <?php foreach($status_lista as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $t['status_geral']==$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td style="text-align: center;">
                <button onclick="abrirSide(<?= $t['id'] ?>)" class="btn-ghost" style="padding: 4px; color: <?= $t['link_arte_final'] ? '#1fa463' : 'var(--text-muted)' ?>; opacity: <?= $t['link_arte_final'] ? '1' : '0.6' ?>;" title="Adicionar/Editar Entrega">
                    <i class="<?= $t['link_arte_final'] ? 'ph-fill ph-check-circle' : 'ph ph-plus-circle' ?>" style="font-size: 22px;"></i>
                </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

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
        
        <label style="font-weight: 700; display: block; margin-bottom: 8px;">Link do Google Drive:</label>
        <input type="text" id="sideLink" class="silent-input" placeholder="Cole o link do Drive / Canva aqui..." onchange="updateDrivePreview(this.value)" style="border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2);">
        
        <div id="drivePreview"></div>

        <label style="font-weight: 700; display: block; margin-top: 25px; margin-bottom: 8px;">Roteiro / Descrição:</label>
        <textarea id="sideRoteiro" class="silent-input" style="height: 300px; border: 1px solid var(--border-mid); background: rgba(0,0,0,0.2); resize: vertical; padding: 12px;" placeholder="Use este espaço como documento do seu planejamento..."></textarea>
    </div>
    <div class="side-modal-footer" style="padding: 24px; border-top: 1px solid var(--border-mid); background: var(--bg-card);">
        <button onclick="salvarTudoSide()" class="btn-save-lg">
            <i class="ph-fill ph-floppy-disk" style="font-size: 20px;"></i> 
            SALVAR TODAS AS ALTERAÇÕES
        </button>
    </div>
</div>

<script>
// --- Lógica de Edição e Memória ---
const originalBody = document.getElementById('tableBody').innerHTML;

function quickAddSubmit() {
    const tema = document.getElementById('inputNewTema').value.trim();
    if(!tema) return;
    
    document.getElementById('hiddenQuickTema').value = tema;
    document.getElementById('hiddenQuickContrato').value = document.getElementById('quickContratoId').value;
    
    document.getElementById('inputNewTema').disabled = true;
    document.getElementById('inputNewTema').value = 'Adicionando...';
    
    document.getElementById('realQuickAddForm').submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const lastGroup = localStorage.getItem('planejamento_group');
    
    if(lastGroup && lastGroup !== 'none') {
        document.getElementById('groupBySelect').value = lastGroup;
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
    document.getElementById('sideId').value = id;
    document.getElementById('sideTituloInput').value = document.getElementById('hidden_tema_'+id).value;
    document.getElementById('sideRoteiro').value = document.getElementById('hidden_rot_'+id).value;
    document.getElementById('sideLink').value = document.getElementById('hidden_link_'+id).value;
    updateDrivePreview(document.getElementById('hidden_link_'+id).value);

    document.getElementById('overlay').classList.add('open');
    document.getElementById('sideModal').classList.add('open');
}

function fecharSide() {
    document.getElementById('overlay').classList.remove('open');
    document.getElementById('sideModal').classList.remove('open');
}

function updateDrivePreview(url) {
    const container = document.getElementById('drivePreview');
    if(url.includes('drive.google.com')) {
        container.innerHTML = `<div class="drive-card">
            <i class="ph-fill ph-google-drive-logo drive-icon"></i>
            <div>
                <strong style="color: var(--text-main);">Arquivo no Google Drive</strong><br>
                <a href="${url}" target="_blank" style="font-size: 11px; color: var(--blue);">Abrir em nova aba</a>
            </div>
        </div>`;
    } else {
        container.innerHTML = '';
    }
}

function salvarTudoSide() {
    const id = document.getElementById('sideId').value;
    const roteiro = document.getElementById('sideRoteiro').value;
    const link = document.getElementById('sideLink').value;

    salvar(id, 'link_arte_final', link);
    
    let fd = new FormData();
    fd.append('acao', 'salvar_roteiro');
    fd.append('id_tarefa', id);
    fd.append('roteiro', roteiro);
    
    // Mostra um feedback visual no botão antes de recarregar
    const btn = document.querySelector('.btn-save-lg');
    btn.innerHTML = '<i class="ph ph-spinner ph-spin" style="font-size: 20px;"></i> SALVANDO...';
    
    fetch('index.php', {method: 'POST', body: fd}).then(() => window.location.reload());
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

    // Reseta icones visualmente
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

        if (currentSortCol === 'prioridade') {
            const pMap = {'urgente': 4, 'alta': 3, 'media': 2, 'baixa': 1};
            valA = pMap[valA] || 0;
            valB = pMap[valB] || 0;
            return currentSortAsc ? valA - valB : valB - valA;
        }

        if (valA < valB) return currentSortAsc ? -1 : 1;
        if (valA > valB) return currentSortAsc ? 1 : -1;
        return 0;
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
            const val = r.getAttribute('data-'+crit);
            if(!groups[val]) groups[val] = [];
            groups[val].push(r);
        });
        Object.keys(groups).sort().forEach(g => {
            const header = document.createElement('tr');
            header.className = 'group-header';
            header.innerHTML = `<td colspan="7"><i class="ph ph-caret-right" style="margin-right: 5px;"></i> ${g} <span style="color: var(--text-muted); font-size: 13px; font-weight: normal; margin-left: 5px;">(${groups[g].length})</span></td>`;
            tbody.appendChild(header);
            groups[g].forEach(r => tbody.appendChild(r));
        });
    }
}

function agruparTabela(save) {
    const crit = document.getElementById('groupBySelect').value;
    
    if(save) {
        localStorage.setItem('planejamento_group', crit);
    }
    
    sortTable(currentSortCol || 'data', false);
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>