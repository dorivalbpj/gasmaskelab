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
                    VALUES (?, ?, 'media', 'pendente', CURDATE())";
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

// Buscar CLIENTES diretamente da tabela clientes
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();

$categorias_fixas = ['Carrossel', 'Video', 'Estático', 'Roteiro', 'Captação', 'Operacional', 'Social', 'Design', 'Email', 'Blog', 'Thumb', 'Orçamento', 'Pessoal'];

$status_lista = [
    'pendente' => 'Pendente', 'roteiro_em_producao' => 'Roteiro', 'roteiro_aguardando_aprovacao' => 'Aguard. Roteiro',
    'roteiro_em_revisao' => 'Ajuste Roteiro', 'peca_em_producao' => 'Arte', 'peca_aguardando_aprovacao' => 'Aguard. Arte',
    'peca_em_revisao' => 'Ajuste Arte', 'pronto_para_postar' => 'Agendar', 'finalizado' => 'Finalizado',
    'A fazer' => 'A fazer', 'Aguardar' => 'Aguardar', 'Postar' => 'Postar'
];

// Busca principal — agora com cliente_id diretamente da tabela clientes
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
    
    <div style="display: flex; gap: 10px;">
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
                <select id="quickClienteId" class="silent-select" style="font-weight: 600; border: 1px solid var(--border-mid); background: transparent; color: var(--text-primary);">
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
                if($t['data_publicacao'] < $hoje) {
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
        ?>
        <tr class="task-row" 
            data-cliente="<?= htmlspecialchars($t['cliente_nome'] ?: 'Interno') ?>" 
            data-categoria="<?= htmlspecialchars($categoria) ?>"
            data-tema="<?= htmlspecialchars($t['tema'] ?? '') ?>"
            data-data="<?= $t['data_publicacao'] ?? '' ?>"
            data-prioridade="<?= $t['prioridade'] ?? '' ?>"
            data-responsavel="<?= htmlspecialchars($resp_nome) ?>"
            data-status="<?= htmlspecialchars($status_lista[$t['status_geral']] ?? $t['status_geral']) ?>">

            <!-- Cliente -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'cliente_id', this.value)" class="silent-select" style="font-weight: 600;">
                    <option value="">Interno</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $t['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Categoria -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'tipo', this.value); this.closest('tr').setAttribute('data-categoria', this.value);" class="silent-select" style="font-size: 12px;">
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
            <td><input type="date" class="silent-input <?= $estilo_data ?>" value="<?= $t['data_publicacao'] ?? '' ?>" onchange="salvar(<?= $t['id'] ?>, 'data_publicacao', this.value)"></td>

            <!-- Prioridade -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'prioridade', this.value); this.className='silent-select pill pill-prio-'+this.value" class="silent-select pill pill-prio-<?= $t['prioridade'] ?>">
                    <option value="baixa" <?= $t['prioridade']=='baixa'?'selected':'' ?>>Baixa</option>
                    <option value="media" <?= $t['prioridade']=='media'?'selected':'' ?>>Média</option>
                    <option value="alta" <?= $t['prioridade']=='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= $t['prioridade']=='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </td>

            <!-- Responsável -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'responsavel_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-resp-atribuido' : 'pill-resp-vazio')" class="silent-select pill <?= $t['responsavel_id'] ? 'pill-resp-atribuido' : 'pill-resp-vazio' ?>">
                    <option value="">-</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $t['responsavel_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Status -->
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'status_geral', this.value); this.className='silent-select pill pill-status-'+this.value" class="silent-select pill pill-status-<?= $t['status_geral'] ?>">
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
    fetch('index.php', {method: 'POST', body: fd1});

    let fd2 = new FormData();
    fd2.append('acao', 'salvar_legenda');
    fd2.append('id_tarefa', id);
    fd2.append('legenda', legenda);
    fetch('index.php', {method: 'POST', body: fd2});

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
            const val = r.getAttribute('data-'+crit) || '(vazio)';
            if(!groups[val]) groups[val] = [];
            groups[val].push(r);
        });
        Object.keys(groups).sort().forEach(g => {
            const header = document.createElement('tr');
            header.className = 'group-header';
            header.innerHTML = `<td colspan="8"><i class="ph ph-caret-right" style="margin-right: 5px;"></i> ${g} <span style="color: var(--text-muted); font-size: 13px; font-weight: normal; margin-left: 5px;">(${groups[g].length})</span></td>`;
            tbody.appendChild(header);
            groups[g].forEach(r => tbody.appendChild(r));
        });
    }
}

function agruparTabela(save) {
    const crit = document.getElementById('groupBySelect').value;
    if(save) localStorage.setItem('planejamento_group', crit);
    sortTable(currentSortCol || 'data', false);
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>