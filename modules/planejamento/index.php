<?php
// modules/planejamento/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

// --- AJAX: ATUALIZAÇÕES RÁPIDAS COM LOG ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // Atualizar campo e registrar log
    if ($_POST['acao'] == 'atualizar_campo') {
        $id = $_POST['id_tarefa'];
        $campo = $_POST['campo'];
        $valor = empty($_POST['valor']) ? null : $_POST['valor'];
        
        $campos = ['responsavel_id', 'prioridade', 'data_publicacao', 'status_geral', 'link_arte_final', 'tipo', 'cliente_id', 'tema'];
        if (in_array($campo, $campos)) {
            $stmt = $pdo->prepare("SELECT {$campo} FROM planejamento WHERE id = ?");
            $stmt->execute([$id]);
            $valor_antigo = $stmt->fetchColumn();

            if ($valor_antigo != $valor) {
                $usuario_log = $_SESSION['usuario_id'] ?? 1;
                $pdo->prepare("INSERT INTO planejamento_logs (tarefa_id, usuario_id, campo, valor_antigo, valor_novo) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id, $usuario_log, $campo, $valor_antigo, $valor]);
            }

            $pdo->prepare("UPDATE planejamento SET {$campo} = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$valor, $id]);
            echo "ok"; exit;
        }
    }

    // Carregar logs para o modal global
    if ($_POST['acao'] == 'carregar_logs_globais') {
        $stmt = $pdo->query("SELECT l.*, u.nome as usuario_nome, p.tema as tarefa_tema 
                             FROM planejamento_logs l 
                             LEFT JOIN usuarios u ON l.usuario_id = u.id 
                             LEFT JOIN planejamento p ON l.tarefa_id = p.id 
                             ORDER BY l.criado_em DESC LIMIT 50");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Reverter alteração (Rollback)
    if ($_POST['acao'] == 'reverter_log') {
        $log_id = $_POST['log_id'];
        $stmt = $pdo->prepare("SELECT tarefa_id, campo, valor_antigo FROM planejamento_logs WHERE id = ?");
        $stmt->execute([$log_id]);
        $log = $stmt->fetch();

        if ($log) {
            $pdo->prepare("UPDATE planejamento SET {$log['campo']} = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$log['valor_antigo'], $log['tarefa_id']]);
        }
        echo "ok"; exit;
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

    // Quick add com auto-atribuição
    if ($_POST['acao'] == 'quick_add') {
        $tema = trim($_POST['tema']);
        $cliente_id = empty($_POST['cliente_id']) ? null : $_POST['cliente_id'];
        $resp_id = $_SESSION['usuario_id'] ?? null;
        
        if ($tema) {
            $sql = "INSERT INTO planejamento (tema, cliente_id, prioridade, status_geral, data_publicacao, responsavel_id) 
                    VALUES (?, ?, 'media', 'a_fazer', CURDATE(), ?)";
            $pdo->prepare($sql)->execute([$tema, $cliente_id, $resp_id]);
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

$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();
$task_categorias = $pdo->query("SELECT * FROM task_categorias ORDER BY nome ASC")->fetchAll();
$cat_map = [];

// Gerando as classes CSS de forma organizada (evitando styles inline)
echo '<style>';
$paleta = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#0284c7'];
$c_index = 0;
foreach($usuarios as $u) {
    $cor = $paleta[$c_index % count($paleta)];
    echo ".pill-resp-{$u['id']} { background-color: {$cor} !important; color: #fff !important; border-color: transparent !important; } \n";
    $c_index++;
}

$paleta_clientes = ['#059669', '#2563eb', '#7c3aed', '#db2777', '#dc2626', '#d97706', '#65a30d', '#0d9488', '#0284c7', '#4f46e5', '#c026d3', '#e11d48', '#ea580c', '#ca8a04', '#4d7c0f'];
$c_index_cli = 0;
echo ".pill-cliente-interno { background-color: #334155 !important; color: #fff !important; border-color: transparent !important; } \n";

foreach($clientes as $c) {
    $cor_cli = $paleta_clientes[$c_index_cli % count($paleta_clientes)];
    echo ".pill-cliente-{$c['id']} { background-color: {$cor_cli} !important; color: #fff !important; border-color: transparent !important; } \n";
    $c_index_cli++;
}

echo ".pill-cat-vazio { background-color: transparent !important; color: var(--text-primary) !important; border: 1px solid var(--border-mid) !important; } \n";
foreach($task_categorias as $cat) {
    $cat_map[$cat['nome']] = $cat;
    echo ".pill-cat-{$cat['id']} { background-color: {$cat['cor']} !important; color: #fff !important; border-color: transparent !important; } \n";
}

// Classes para os botões da tabela
echo '
.table-action-cell {
    text-align: center;
    white-space: nowrap;
}
.btn-table-action {
    padding: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-copy-leg {
    color: var(--text-muted);
}
.btn-copy-leg:hover {
    color: var(--text-primary);
}
.btn-detalhes-haslink {
    color: #1fa463;
    opacity: 1;
}
.btn-detalhes-nolink {
    color: var(--text-muted);
    opacity: 0.6;
}
.btn-detalhes-nolink:hover {
    opacity: 1;
}
';
echo '</style>';

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

<div class="header-planejamento">
    <!-- Bloco da Esquerda: Título e Números Rápidos -->
    <div class="header-title-block">
        <h2 class="page-title">Planejamento</h2>
        <div class="header-stats" id="headerStats">
            <span class="stat-badge danger hidden" onclick="scrollToAtrasadas()" id="badgeAtrasadas" title="Ir para tarefas atrasadas">
                <i class="ph-fill ph-warning-circle"></i> <span id="countAtrasadas">0</span> Atrasadas
            </span>
            <span class="stat-badge warning hidden" onclick="scrollToHoje()" id="badgeHoje" title="Ir para tarefas de hoje">
                <i class="ph-fill ph-clock"></i> <span id="countHoje">0</span> Para Hoje
            </span>
        </div>
    </div>
    
    <!-- Bloco do Meio: Filtros e Controles -->
    <div class="header-controls">
        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-2); font-size: 13px; cursor: pointer; white-space: nowrap;">
            <input type="checkbox" id="chkMostrarArquivados" onchange="agruparTabela(false)"> 
            Mostrar Arquivados
        </label>

        <div class="search-wrapper">
            <i class="ph ph-magnifying-glass search-icon"></i>
            <input type="text" id="inputBusca" class="gn-select input-busca" placeholder="Buscar tarefa..." onkeyup="buscarTabela()">
        </div>

        <div class="zoom-control-box">
            <i class="ph ph-magnifying-glass-minus text-muted" style="font-size: 18px;"></i>
            <input type="range" id="tableZoom" class="zoom-slider" min="60" max="130" value="100" oninput="document.getElementById('mainTable').style.zoom = this.value + '%'; localStorage.setItem('planejamento_zoom', this.value);">
            <i class="ph ph-magnifying-glass-plus text-muted" style="font-size: 18px;"></i>
        </div>

        <select id="groupBySelect" class="gn-select w-auto" style="height: 44px;" onchange="agruparTabela(true)">
            <option value="none">Lista Simples</option>
            <option value="cliente">Agrupar por Cliente</option>
            <option value="categoria">Agrupar por Categoria</option>
            <option value="responsavel">Agrupar por Responsável</option>
            <option value="status">Agrupar por Status</option>
            <option value="data">Agrupar por Data</option>
        </select>
    </div>

    <!-- Bloco da Direita: Ações de Impacto -->
    <div class="action-buttons">
        <button class="btn btn-secondary btn-h44" onclick="abrirModalLogsGlobais()">
            <i class="ph ph-clock-counter-clockwise"></i> Logs
        </button>
        <button class="btn btn-primary btn-h44" onclick="document.getElementById('rowNewTask').style.display='table-row'; document.getElementById('inputNewTema').focus();">
            <i class="ph ph-plus"></i> Nova Tarefa
        </button>
    </div>
</div>

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
                <th class="sortable resizable" style="width: 11%;" onclick="sortTable('categoria')">Categoria <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 24%;" onclick="sortTable('tema')">Tarefa <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('data')">Prazo <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('prioridade')">Prio <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 13%;" onclick="sortTable('responsavel')">Responsável <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th class="sortable resizable" style="width: 10%;" onclick="sortTable('status')">Status <i class="ph ph-arrows-down-up" style="margin-left:4px; opacity:0.5;"></i></th>
                <th style="text-align: center; width: 80px;">Ações</th>
            </tr>
        </thead>
        <tbody id="tableBody">

        <tr id="rowNewTask" style="display: none; background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border-mid);">
            <td>
                <input list="clientesDatalist" id="quickClienteInput" class="silent-input pill pill-cliente-interno" placeholder="Buscar cliente..." style="font-weight: 600; border: 1px solid var(--border-mid); color: #fff; width: 100%;">
                <datalist id="clientesDatalist">
                    <option data-id="" value="Interno"></option>
                    <?php foreach($clientes as $c): ?>
                        <option data-id="<?= $c['id'] ?>" value="<?= htmlspecialchars($c['nome']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
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
            
            // Tratamento dinâmico da Categoria
            $categoria = $t['tipo'] ?? '';
            $cat_id = isset($cat_map[$categoria]) ? $cat_map[$categoria]['id'] : 'vazio';

            $tem_link = !empty($t['link_arte_final']);
            $esta_finalizado = ($t['status_geral'] == 'finalizado');
            
            // Classes dos botões calculadas via PHP para manter limpo
            $classe_btn_detalhes = $tem_link ? 'btn-table-action btn-detalhes-haslink' : 'btn-table-action btn-detalhes-nolink';
            $icone_btn_detalhes = $tem_link ? 'ph-fill ph-check-circle' : 'ph ph-plus-circle';
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

            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'cliente_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-cliente-'+this.value : 'pill-cliente-interno'); this.closest('tr').setAttribute('data-cliente', this.options[this.selectedIndex].text); agruparTabela(false);" class="silent-select pill <?= $t['cliente_id'] ? 'pill-cliente-'.$t['cliente_id'] : 'pill-cliente-interno' ?>" style="font-weight: 600;">
                    <option value="">Interno</option>
                    <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $t['cliente_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <!-- O select agora pega a cor real cadastrada no banco -->
                <select onchange="salvar(<?= $t['id'] ?>, 'tipo', this.value); this.className='silent-select pill '+(this.value ? 'pill-cat-'+this.options[this.selectedIndex].getAttribute('data-id') : 'pill-cat-vazio'); this.closest('tr').setAttribute('data-categoria', this.value); agruparTabela(false);" class="silent-select pill <?= $cat_id != 'vazio' ? 'pill-cat-'.$cat_id : 'pill-cat-vazio' ?>" style="font-size: 12px; font-weight: 600;">
                    <option value="" data-id="vazio">—</option>
                    <?php foreach($task_categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['nome']) ?>" data-id="<?= $cat['id'] ?>" <?= $categoria == $cat['nome'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <input type="text" id="input_tema_<?= $t['id'] ?>" class="silent-input" value="<?= htmlspecialchars($t['tema'] ?? '') ?>" onchange="salvar(<?= $t['id'] ?>, 'tema', this.value); document.getElementById('hidden_tema_<?= $t['id'] ?>').value = this.value; this.closest('tr').setAttribute('data-tema', this.value);" style="font-weight: 600; color: var(--text-primary);">
                
                <textarea id="hidden_rot_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['roteiro'] ?? '') ?></textarea>
                <textarea id="hidden_leg_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['legenda'] ?? '') ?></textarea>
                <textarea id="hidden_ins_<?= $t['id'] ?>" style="display:none;"><?= htmlspecialchars($t['inspiracao'] ?? '') ?></textarea>
                <input type="hidden" id="hidden_link_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['link_arte_final'] ?? '') ?>">
                <input type="hidden" id="hidden_tema_<?= $t['id'] ?>" value="<?= htmlspecialchars($t['tema'] ?? '') ?>">
            </td>
            <td><input type="date" class="silent-input <?= $estilo_data ?>" value="<?= $t['data_publicacao'] ?? '' ?>" onchange="salvar(<?= $t['id'] ?>, 'data_publicacao', this.value); this.closest('tr').setAttribute('data-data', this.value); recalcularEstiloLinha(this.closest('tr')); agruparTabela(false);"></td>
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'prioridade', this.value); this.className='silent-select pill pill-prio-'+this.value; this.closest('tr').setAttribute('data-prioridade', this.value); agruparTabela(false);" class="silent-select pill pill-prio-<?= $t['prioridade'] ?>">
                    <option value="baixa" <?= $t['prioridade']=='baixa'?'selected':'' ?>>Baixa</option>
                    <option value="media" <?= $t['prioridade']=='media'?'selected':'' ?>>Média</option>
                    <option value="alta" <?= $t['prioridade']=='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= $t['prioridade']=='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </td>
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'responsavel_id', this.value); this.className='silent-select pill '+(this.value ? 'pill-resp-'+this.value : 'pill-resp-vazio'); this.closest('tr').setAttribute('data-responsavel', this.options[this.selectedIndex].text); agruparTabela(false);" class="silent-select pill <?= $t['responsavel_id'] ? 'pill-resp-'.$t['responsavel_id'] : 'pill-resp-vazio' ?>">
                    <option value="">-</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $t['responsavel_id']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select onchange="salvar(<?= $t['id'] ?>, 'status_geral', this.value); this.className='silent-select pill pill-status-'+this.value; atualizarStatusLinha(this);" class="silent-select pill pill-status-<?= $t['status_geral'] ?>">
                    <?php foreach($status_lista as $k => $v): ?>
                        <option value="<?= $k ?>" <?= $t['status_geral']==$k?'selected':'' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="table-action-cell">
                <button onclick="copiarLegendaTabela(<?= $t['id'] ?>, this)" class="btn-table-action btn-copy-leg" title="Copiar Legenda">
                    <i class="ph ph-copy" style="font-size: 20px;"></i>
                </button>
                <button onclick="abrirSide(<?= $t['id'] ?>)" class="<?= $classe_btn_detalhes ?>" title="Abrir detalhes">
                    <i class="<?= $icone_btn_detalhes ?>" style="font-size: 22px;"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Side Modal da Tarefa -->
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label class="side-section-label" style="margin-bottom: 0;">Legenda do Post</label>
                <button type="button" class="btn btn-secondary" onclick="copiarLegendaModal()" id="btnCopiarLegendaModal" style="padding: 4px 10px; font-size: 12px; height: auto;">
                    <i class="ph ph-copy"></i> Copiar
                </button>
            </div>
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

<!-- TELA/MODAL GLOBAL DE LOGS -->
<div class="modal-overlay" id="modalLogsGlobais" style="z-index: 2000;">
    <div class="modal-box" style="max-width: 700px; max-height: 85vh; display: flex; flex-direction: column;">
        <button class="modal-close-btn" onclick="document.getElementById('modalLogsGlobais').classList.remove('active')"><i class="ph ph-x"></i></button>
        <h3 style="margin-top: 0; color: var(--text);"><i class="ph ph-list-dashes"></i> Histórico de Alterações</h3>
        <p style="font-size: 13px; color: var(--text-3); margin-bottom: 20px;">Acompanhe o que a equipe alterou e desfaça (rollback) se necessário.</p>
        
        <div id="conteudoLogsGlobais" style="overflow-y: auto; flex: 1; padding-right: 10px;"></div>
    </div>
</div>

<!-- IA FLUTUANTE FIIOTE -->
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
            Em breve vou estar por aqui pra te ajudar.<br>
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
    if (!container.contains(e.target)) {
        document.getElementById('aiBubble').classList.remove('active');
        document.getElementById('aiButton').classList.remove('active');
    }
});

setTimeout(() => {
    const notif = document.getElementById('aiNotif');
    if (notif) notif.classList.remove('hidden');
}, 3000);

const dataHoje = '<?= date('Y-m-d') ?>';
const statusMap = <?= json_encode($status_lista, JSON_UNESCAPED_UNICODE) ?>;

function isMobileView() {
    return window.innerWidth <= 768;
}

// --- ATUALIZAR CONTADORES DO CABEÇALHO ---
function atualizarContadores() {
    let atrasadas = 0;
    let hoje = 0;
    const rows = document.querySelectorAll('.task-row');
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status-key');
        if (status === 'finalizado' || status === 'arquivado') return;
        
        const dataStr = row.getAttribute('data-data');
        if (!dataStr) return;

        if (dataStr < dataHoje) atrasadas++;
        else if (dataStr === dataHoje) hoje++;
    });

    const badgeAtrasadas = document.getElementById('badgeAtrasadas');
    const badgeHoje = document.getElementById('badgeHoje');

    if (atrasadas > 0) {
        document.getElementById('countAtrasadas').textContent = atrasadas;
        badgeAtrasadas.classList.remove('hidden');
    } else {
        badgeAtrasadas.classList.add('hidden');
    }

    if (hoje > 0) {
        document.getElementById('countHoje').textContent = hoje;
        badgeHoje.classList.remove('hidden');
    } else {
        badgeHoje.classList.add('hidden');
    }
}

// --- SCROLL PARA DATAS ATRASADAS ---
function scrollToAtrasadas() {
    document.getElementById('groupBySelect').value = 'data';
    agruparTabela(false);
    
    setTimeout(() => {
        document.querySelectorAll('.group-header[data-grupo-atrasado="1"]').forEach(h => {
            h.classList.remove('collapsed');
            let nextRow = h.nextElementSibling;
            while (nextRow && !nextRow.classList.contains('group-header')) {
                nextRow.classList.remove('linha-colapsada');
                nextRow = nextRow.nextElementSibling;
            }
        });
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }, 100);
}

// --- SCROLL PARA TAREFAS DE HOJE ---
function scrollToHoje() {
    document.getElementById('groupBySelect').value = 'data';
    agruparTabela(false);
    
    setTimeout(() => {
        const headers = document.querySelectorAll('.group-header');
        for(let h of headers) {
            if(h.textContent.includes('Hoje')) {
                h.classList.remove('collapsed');
                let nextRow = h.nextElementSibling;
                while (nextRow && !nextRow.classList.contains('group-header')) {
                    nextRow.classList.remove('linha-colapsada');
                    nextRow = nextRow.nextElementSibling;
                }
                h.scrollIntoView({ behavior: 'smooth', block: 'center' });
                break;
            }
        }
    }, 100);
}

// --- BUSCA GLOBAL ---
function buscarTabela() {
    const termo = document.getElementById('inputBusca').value.toLowerCase();
    const mostrarArquivados = document.getElementById('chkMostrarArquivados').checked;
    
    document.querySelectorAll('.task-row').forEach(row => {
        const statusKey = row.getAttribute('data-status-key');
        if (!mostrarArquivados && statusKey === 'arquivado') {
            row.style.display = 'none';
            return;
        }

        const textoLinha = `
            ${row.getAttribute('data-cliente') || ''} 
            ${row.getAttribute('data-categoria') || ''} 
            ${row.getAttribute('data-tema') || ''} 
            ${row.getAttribute('data-responsavel') || ''} 
            ${row.getAttribute('data-status') || ''}
        `.toLowerCase();
        
        if (textoLinha.includes(termo)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    document.querySelectorAll('.group-header').forEach(header => {
        let nextRow = header.nextElementSibling;
        let temVisivel = false;
        while (nextRow && nextRow.classList.contains('task-row')) {
            if (nextRow.style.display !== 'none') {
                temVisivel = true;
                break;
            }
            nextRow = nextRow.nextElementSibling;
        }
        header.style.display = temVisivel ? '' : 'none';
    });
}

function recalcularEstiloLinha(row) {
    const dateInput = row.querySelector('td input[type="date"]');
    const statusKey = row.getAttribute('data-status-key');
    const finalizado = statusKey === 'finalizado';

    if (dateInput) {
        dateInput.classList.remove('prazo-vencido', 'prazo-hoje', 'prazo-normal');
        if (!finalizado) {
            const val = dateInput.value;
            if (val && val < dataHoje) dateInput.classList.add('prazo-vencido');
            else if (val === dataHoje) dateInput.classList.add('prazo-hoje');
            else dateInput.classList.add('prazo-normal');
        }
    }

    row.setAttribute('data-finalizado', finalizado ? '1' : '0');
    row.classList.toggle('tarefa-finalizada', finalizado);
    
    atualizarContadores();
}

function atualizarStatusLinha(select) {
    const row = select.closest('tr');
    const statusKey = select.value;
    row.setAttribute('data-status-key', statusKey);
    row.setAttribute('data-status', statusMap[statusKey] || statusKey);
    recalcularEstiloLinha(row);
    agruparTabela(false);
}

function formatarDataVisao(dataStr) {
    if (!dataStr || dataStr.indexOf('-') === -1) return dataStr;
    const partes = dataStr.split('-');
    if (partes.length !== 3) return dataStr;
    const ano = partes[0], mes = partes[1], dia = partes[2];
    const anoAtual = new Date().getFullYear().toString();
    return (ano === anoAtual) ? `${dia}/${mes}` : `${dia}/${mes}/${ano}`;
}

function quickAddSubmit() {
    const tema = document.getElementById('inputNewTema').value.trim();
    if(!tema) return;
    
    const clienteNome = document.getElementById('quickClienteInput').value;
    const option = document.querySelector(`#clientesDatalist option[value="${clienteNome}"]`);
    const clienteId = option ? option.getAttribute('data-id') : '';

    document.getElementById('hiddenQuickTema').value = tema;
    document.getElementById('hiddenQuickCliente').value = clienteId;
    document.getElementById('inputNewTema').disabled = true;
    document.getElementById('inputNewTema').value = 'Adicionando...';
    document.getElementById('realQuickAddForm').submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const groupKey = isMobileView() ? 'planejamento_group_mobile' : 'planejamento_group';
    const lastGroup = localStorage.getItem(groupKey);

    if (lastGroup && lastGroup !== 'none') document.getElementById('groupBySelect').value = lastGroup;
    else if (!lastGroup && isMobileView()) document.getElementById('groupBySelect').value = 'data';

    const lastZoom = localStorage.getItem('planejamento_zoom');
    if (lastZoom) {
        document.getElementById('tableZoom').value = lastZoom;
        document.getElementById('mainTable').style.zoom = lastZoom + '%';
    }

    sortTable('data', false);
    atualizarContadores();
});

function salvar(id, campo, valor) {
    let fd = new FormData();
    fd.append('acao', 'atualizar_campo');
    fd.append('id_tarefa', id);
    fd.append('campo', campo);
    fd.append('valor', valor);
    fetch('index.php', {method: 'POST', body: fd});
}

// LOGS GLOBAIS
function abrirModalLogsGlobais() {
    document.getElementById('modalLogsGlobais').classList.add('active');
    const lista = document.getElementById('conteudoLogsGlobais');
    lista.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="ph ph-spinner ph-spin" style="font-size: 24px;"></i><br>Buscando histórico...</div>';

    let fd = new FormData();
    fd.append('acao', 'carregar_logs_globais');

    fetch('index.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if (data.length === 0) {
            lista.innerHTML = '<div class="empty-state">Nenhuma alteração registrada ainda.</div>';
            return;
        }
        
        lista.innerHTML = data.map(log => {
            let tarefaTexto = log.tarefa_tema ? log.tarefa_tema : `Tarefa #${log.tarefa_id} (Deletada)`;
            return `
            <div style="background: var(--bg-elevated); border: 1px solid var(--border-mid); border-radius: var(--r-md); padding: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 11px; color: var(--text-3); margin-bottom: 4px;">
                        <i class="ph ph-calendar-blank"></i> ${log.criado_em} &nbsp;|&nbsp; 
                        <i class="ph ph-user"></i> <strong>${log.usuario_nome || 'Sistema'}</strong>
                    </div>
                    <div style="font-size: 14px; color: var(--text); font-weight: 600; margin-bottom: 4px;">
                        ${tarefaTexto}
                    </div>
                    <div style="font-size: 12px; color: var(--text-2);">
                        Alterou <span class="badge badge-blue">${log.campo}</span><br>
                        De: <em><strike>${log.valor_antigo || '(vazio)'}</strike></em> ➔ Para: <strong>${log.valor_novo || '(vazio)'}</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="reverterLog(${log.id})" title="Desfazer e voltar pro valor antigo">
                    <i class="ph ph-arrow-u-up-left"></i> Desfazer
                </button>
            </div>
            `;
        }).join('');
    });
}

function reverterLog(log_id) {
    if(!confirm('Tem certeza que deseja reverter esta alteração?')) return;
    
    let fd = new FormData();
    fd.append('acao', 'reverter_log');
    fd.append('log_id', log_id);

    fetch('index.php', { method: 'POST', body: fd }).then(() => {
        window.location.reload();
    });
}

function abrirSide(id) {
    const link = document.getElementById('hidden_link_'+id).value;
    
    document.getElementById('sideId').value = id;
    document.getElementById('sideTituloInput').value = document.getElementById('hidden_tema_'+id).value;
    document.getElementById('sideRoteiro').value = document.getElementById('hidden_rot_'+id).value;
    document.getElementById('sideLegenda').value = document.getElementById('hidden_leg_'+id).value;
    document.getElementById('sideInspiracao').value = document.getElementById('hidden_ins_'+id).value;
    document.getElementById('sideLink').value = link;
    
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
        btn.innerHTML = '<i class="ph-fill ph-check-circle" style="font-size: 20px;"></i> SALVO COM SUCESSO!';
        btn.style.background = 'var(--gn-green)';
        
        document.getElementById('hidden_rot_'+id).value = roteiro;
        document.getElementById('hidden_leg_'+id).value = legenda;
        document.getElementById('hidden_ins_'+id).value = inspiracao;
        document.getElementById('hidden_link_'+id).value = link;

        // Atualiza a cor do botão de detalhes na tabela se houver link
        const tr = document.querySelector(`tr input[id="hidden_tema_${id}"]`).closest('tr');
        if(tr) {
            const btnDetails = tr.querySelector('.btn-table-action[title="Abrir detalhes"]');
            if(btnDetails) {
                const icon = btnDetails.querySelector('i');
                if(link && link.trim() !== '') {
                    btnDetails.className = 'btn-table-action btn-detalhes-haslink';
                    icon.className = 'ph-fill ph-check-circle';
                } else {
                    btnDetails.className = 'btn-table-action btn-detalhes-nolink';
                    icon.className = 'ph ph-plus-circle';
                }
            }
        }

        setTimeout(() => {
            btn.innerHTML = '<i class="ph-fill ph-floppy-disk" style="font-size: 20px;"></i> SALVAR TODAS AS ALTERAÇÕES';
            btn.style.background = '';
            btn.disabled = false;
            fecharSide();
        }, 1200);
    });
}

// --- FUNÇÕES DE COPIAR LEGENDA ---
function copiarLegendaTabela(id, btnElement) {
    const legenda = document.getElementById('hidden_leg_' + id).value;
    
    if (!legenda || legenda.trim() === '') {
        alert('Esta tarefa ainda não possui uma legenda salva.');
        return;
    }

    navigator.clipboard.writeText(legenda).then(() => {
        const icone = btnElement.querySelector('i');
        const classeOriginal = icone.className;
        
        icone.className = 'ph-fill ph-check-circle';
        btnElement.style.color = '#1fa463'; 

        setTimeout(() => {
            icone.className = classeOriginal;
            btnElement.style.color = '';
        }, 1500);
    }).catch(err => {
        console.error('Erro ao copiar a legenda:', err);
    });
}

function copiarLegendaModal() {
    const legenda = document.getElementById('sideLegenda').value;
    const btn = document.getElementById('btnCopiarLegendaModal');
    
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
    }).catch(err => {
        console.error('Erro ao copiar a legenda:', err);
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

    const expandedGroups = new Set();
    document.querySelectorAll('.group-header:not(.collapsed)').forEach(h => {
        const gKey = h.getAttribute('data-group-key');
        if (gKey) expandedGroups.add(gKey);
    });

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
    
    const mostrarArquivados = document.getElementById('chkMostrarArquivados') ? document.getElementById('chkMostrarArquivados').checked : false;

    let visibleRows = rows.filter(r => {
        const isArquivado = r.getAttribute('data-status-key') === 'arquivado';
        if (!mostrarArquivados && isArquivado) {
            r.style.display = 'none';
            return false;
        }
        return true;
    });

    visibleRows.sort((a, b) => {
        let valA = a.getAttribute('data-' + currentSortCol).toLowerCase();
        let valB = b.getAttribute('data-' + currentSortCol).toLowerCase();
        let cmp = 0;

        if (currentSortCol === 'data') {
            let isAEmpty = (!valA || valA === '');
            let isBEmpty = (!valB || valB === '');
            
            let isAOverdue = (!isAEmpty && valA < dataHoje) ? 1 : 0;
            let isBOverdue = (!isBEmpty && valB < dataHoje) ? 1 : 0;
            
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
        visibleRows.forEach(r => {
            r.style.display = '';
            tbody.appendChild(r);
        });
    } else {
        const groups = {};
        visibleRows.forEach(r => {
            const val = r.getAttribute('data-'+crit) || '(vazio)';
            if(!groups[val]) groups[val] = [];
            groups[val].push(r);
        });
        
        let chavesGrupos = Object.keys(groups);
        
        chavesGrupos.sort((a, b) => {
            if (crit === 'data') {
                let isAEmpty = (a === '(vazio)' || a === '');
                let isBEmpty = (b === '(vazio)' || b === '');
                let isAPast = !isAEmpty && a < dataHoje;
                let isBPast = !isBEmpty && b < dataHoje;

                if (isAEmpty && !isBEmpty) return 1;
                if (!isAEmpty && isBEmpty) return -1;
                
                if (isAPast && !isBPast) return 1;
                if (!isAPast && isBPast) return -1;
                
                return a < b ? -1 : (a > b ? 1 : 0);
            }
            return a < b ? -1 : (a > b ? 1 : 0);
        });

        chavesGrupos.forEach(g => {
            let labelDisplay = g;
            if (crit === 'data') {
                if (g === '(vazio)' || g === '') {
                    labelDisplay = 'Sem data definida';
                } else {
                    const dataFormatada = formatarDataVisao(g);
                    if (g === dataHoje) labelDisplay = 'Hoje (' + dataFormatada + ')';
                    else labelDisplay = dataFormatada;
                }
            }
            
            const naoFinalizados = groups[g].filter(r => r.getAttribute('data-finalizado') !== '1');
            const qtdNaoFinalizados = naoFinalizados.length;

            let badgePendentes = '';
            if (qtdNaoFinalizados > 0) {
                badgePendentes = `<span style="background: var(--gn-orange); color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px; font-weight: bold;">${qtdNaoFinalizados} pendentes</span>`;
            }

            let isCollapsed = true;
            if (expandedGroups.has(g)) {
                isCollapsed = false;
            } else if (crit === 'data') {
                if (g === dataHoje) isCollapsed = false;
                else if (isMobileView() && g && g !== '(vazio)' && g < dataHoje) isCollapsed = false;
            }

            const header = document.createElement('tr');
            header.className = isCollapsed ? 'group-header collapsed' : 'group-header';
            header.setAttribute('data-group-key', g);
            
            if (crit === 'data' && g !== '(vazio)' && g !== '' && g < dataHoje) {
                header.setAttribute('data-grupo-atrasado', '1');
            }

            header.innerHTML = `<td colspan="8">
                <i class="ph ph-caret-down icone-colapso" style="margin-right: 5px;"></i> 
                ${labelDisplay} 
                <span style="color: var(--text-muted); font-size: 13px; font-weight: normal; margin-left: 5px;">(${groups[g].length})</span>
                ${badgePendentes}
            </td>`;
            tbody.appendChild(header);
            
            const finalizados = groups[g].filter(r => r.getAttribute('data-finalizado') === '1');
            const listaFinal = naoFinalizados.concat(finalizados);

            listaFinal.forEach(r => {
                r.style.display = '';
                if(isCollapsed) r.classList.add('linha-colapsada');
                else r.classList.remove('linha-colapsada');
                tbody.appendChild(r);
            });
        });

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
    
    if(document.getElementById('inputBusca').value !== '') {
        buscarTabela();
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

function expandirTudoMobile(btn) {
    document.querySelectorAll('.group-header.collapsed').forEach(h => h.classList.remove('collapsed'));
    document.querySelectorAll('.task-row.linha-colapsada').forEach(r => r.classList.remove('linha-colapsada'));
    const row = btn.closest('tr');
    if (row) row.remove();
}

document.getElementById('tableBody').addEventListener('click', function(e) {
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

    if (window.innerWidth <= 768) {
        const row = e.target.closest('.task-row');
        if (row && !['INPUT', 'SELECT', 'BUTTON', 'A', 'TEXTAREA', 'I'].includes(e.target.tagName)) {
            row.classList.toggle('expanded');
        }
    }
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>