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
    </div>
</div>

<div class="card" style="padding: 0; overflow-x: auto;">
    <table class="editable-grid" id="mainTable">
        <thead>
            <tr>
                <th style="width: 40px;"></th>
                <th style="width: 18%;">Cliente</th>
                <th style="width: 25%;">Tarefa (Tema)</th>
                <th style="width: 120px;">Prazo</th>
                <th style="width: 110px;">Prio</th>
                <th style="width: 15%;">Responsável</th>
                <th style="width: 15%;">Status</th>
                <th style="text-align: center;">Entrega</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr style="background: var(--bg-hover);">
                <td><i class="ph ph-plus-circle" style="color: var(--blue); margin-left: 10px; font-size: 18px;"></i></td>
                <td>
                    <form id="formQuick" method="POST" style="display:flex; gap: 5px;">
                        <input type="hidden" name="acao" value="quick_add">
                        <select name="contrato_id" class="gn-select" style="font-weight: bold; border: 1px solid var(--border-mid);">
                            <option value="">Interno...</option>
                            <?php foreach($contratos as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                </td>
                <td colspan="6">
                        <input type="text" name="tema" class="gn-input" placeholder="O que precisa ser feito? + Enter" required style="border: 1px solid var(--border-mid); font-weight: bold;">
                    </form>
                </td>
            </tr>

            <?php foreach ($tarefas as $t): 
                $hoje = date('Y-m-d');
                $classe_prazo = '';
                if($t['status_geral'] != 'finalizado'){
                    if($t['data_publicacao'] < $hoje) $classe_prazo = 'prazo-vencido';
                    elseif($t['data_publicacao'] == $hoje) $classe_prazo = 'prazo-hoje';
                    elseif($t['data_publicacao'] == date('Y-m-d', strtotime('+1 day'))) $classe_prazo = 'prazo-amanha';
                }
            ?>
            <tr class="task-row <?= $classe_prazo ?>" 
                data-cliente="<?= $t['cliente_nome'] ?: 'Interno' ?>" 
                data-responsavel="<?= $t['responsavel_id'] ? 'Atribuído' : 'Sem Resp.' ?>"
                data-status="<?= $status_lista[$t['status_geral']] ?>"
                data-data="<?= date('d/m/Y', strtotime($t['data_publicacao'])) ?>">
                
                <td style="text-align: center;">
                    <button onclick="abrirSide(<?= $t['id'] ?>)" class="btn-ghost"><i class="ph ph-eye" style="font-size: 18px;"></i></button>
                    <textarea id="hidden_rot_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['objetivo']) ?></textarea>
                    <input type="hidden" id="hidden_link_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['link_arte_final']) ?>">
                    <input type="hidden" id="hidden_tema_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['tema']) ?>">
                </td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'contrato_id', this.value)" class="gn-select" style="font-weight: 600;">
                        <option value="">Interno</option>
                        <?php foreach($contratos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $t['contrato_id'] == $c['id'] ? 'selected' : '' ?>><?= $c['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td><input type="text" class="gn-input" value="<?= htmlspecialchars($t['tema']) ?>" onchange="salvar(<?= $t['id'] ?>, 'tema', this.value)"></td>
                
                <td><input type="date" class="gn-input" value="<?= $t['data_publicacao'] ?>" onchange="salvar(<?= $t['id'] ?>, 'data_publicacao', this.value)"></td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'prioridade', this.value)" class="gn-select prio-<?= $t['prioridade'] ?>">
                        <option value="baixa" <?= $t['prioridade']=='baixa'?'selected':'' ?>>Baixa</option>
                        <option value="media" <?= $t['prioridade']=='media'?'selected':'' ?>>Média</option>
                        <option value="alta" <?= $t['prioridade']=='alta'?'selected':'' ?>>Alta</option>
                        <option value="urgente" <?= $t['prioridade']=='urgente'?'selected':'' ?>>Urgente</option>
                    </select>
                </td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'responsavel_id', this.value)" class="gn-select">
                        <option value="">-</option>
                        <?php foreach($usuarios as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $t['responsavel_id']==$u['id']?'selected':'' ?>><?= $u['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td>
                    <select onchange="salvar(<?= $t['id'] ?>, 'status_geral', this.value)" class="gn-select">
                        <?php foreach($status_lista as $k => $v): ?>
                            <option value="<?= $k ?>" <?= $t['status_geral']==$k?'selected':'' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>

                <td style="text-align: center;">
                    <?php if($t['link_arte_final']): ?>
                        <a href="<?= $t['link_arte_final'] ?>" target="_blank" class="btn-ghost" style="color: #1fa463;"><i class="ph-fill ph-google-drive-logo" style="font-size: 20px;"></i></a>
                    <?php else: ?>
                        <button onclick="abrirSide(<?= $t['id'] ?>)" class="btn-ghost" style="color: var(--text-muted);"><i class="ph ph-link-break" style="font-size: 20px;"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="overlay" id="overlay" onclick="fecharSide()"></div>
<div class="side-modal" id="sideModal">
    <div class="side-modal-header">
        <h3 id="sideTitulo" style="margin:0; font-size: 16px;">Detalhes</h3>
        <button onclick="fecharSide()" class="btn-ghost"><i class="ph ph-x" style="font-size: 20px;"></i></button>
    </div>
    <div class="side-modal-body">
        <input type="hidden" id="sideId">
        
        <label style="font-weight: 700; display: block; margin-bottom: 8px;">Link do Google Drive:</label>
        <input type="text" id="sideLink" class="gn-input" placeholder="Cole o link do Drive aqui..." onchange="updateDrivePreview(this.value)" style="border: 1px solid var(--border-mid);">
        
        <div id="drivePreview"></div>

        <label style="font-weight: 700; display: block; margin-top: 25px; margin-bottom: 8px;">Roteiro / Descrição:</label>
        <textarea id="sideRoteiro" class="gn-textarea" style="height: 300px;"></textarea>
    </div>
    <div class="side-modal-footer" style="padding: 24px; border-top: 1px solid var(--border-light); background: rgba(0,0,0,0.1);">
        <button onclick="salvarTudoSide()" class="btn-save-lg">
            <i class="ph-fill ph-floppy-disk" style="font-size: 20px;"></i> 
            SALVAR TODAS AS ALTERAÇÕES
        </button>
    </div>
</div>

<script>
// --- Lógica de Edição e Memória ---
const originalBody = document.getElementById('tableBody').innerHTML;

document.addEventListener('DOMContentLoaded', () => {
    const lastGroup = localStorage.getItem('planejamento_group');
    
    // CORREÇÃO: Só manda agrupar no carregamento se o cache for diferente de 'none'
    if(lastGroup && lastGroup !== 'none') {
        document.getElementById('groupBySelect').value = lastGroup;
        agruparTabela(false);
    }
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
    document.getElementById('sideTitulo').innerText = document.getElementById('hidden_tema_'+id).value;
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

function agruparTabela(save) {
    const crit = document.getElementById('groupBySelect').value;
    
    if(save) {
        localStorage.setItem('planejamento_group', crit);
    }
    
    // CORREÇÃO: Se escolheu "Lista Simples" manualmente, dá reload. Se foi automático do load, não faz nada.
    if(crit === 'none') { 
        if(save) window.location.reload(); 
        return; 
    }

    const tbody = document.getElementById('tableBody');
    const rows = Array.from(tbody.querySelectorAll('.task-row'));
    const groups = {};

    rows.forEach(r => {
        const val = r.getAttribute('data-'+crit);
        if(!groups[val]) groups[val] = [];
        groups[val].push(r);
    });

    const quickAdd = tbody.querySelector('tr:first-child');
    tbody.innerHTML = '';
    tbody.appendChild(quickAdd);

    Object.keys(groups).sort().forEach(g => {
        const header = document.createElement('tr');
        header.className = 'group-header';
        header.innerHTML = `<td colspan="8"><i class="ph ph-caret-right" style="margin-right: 5px;"></i> ${g} (${groups[g].length})</td>`;
        tbody.appendChild(header);
        groups[g].forEach(r => tbody.appendChild(r));
    });
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>