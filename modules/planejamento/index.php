<?php
// modules/planejamento/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$mensagem = '';

// ======== 🤖 O ROBÔ INVISÍVEL (SLA 48H) ========
$stmt_sla = $pdo->query("SELECT id, status_geral, data_ultima_acao FROM planejamento WHERE status_geral IN ('roteiro_aguardando_aprovacao', 'peca_aguardando_aprovacao')");
$tarefas_sla = $stmt_sla->fetchAll();

foreach ($tarefas_sla as $tsla) {
    if (!empty($tsla['data_ultima_acao'])) {
        $data_inicio = strtotime($tsla['data_ultima_acao']);
        $agora       = time();
        $horas_passadas = ($agora - $data_inicio) / 3600;

        if ($horas_passadas >= 48) {
            $novo_status = ($tsla['status_geral'] == 'roteiro_aguardando_aprovacao') ? 'peca_em_producao' : 'pronto_para_postar';
            $pdo->prepare("UPDATE planejamento SET status_geral = ?, data_ultima_acao = NOW() WHERE id = ?")->execute([$novo_status, $tsla['id']]);
        }
    }
}
// ===============================================

// --- MUDANÇA RÁPIDA DE QUALQUER CAMPO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_campo') {
    $id_tarefa = $_POST['id_tarefa'];
    $campo     = $_POST['campo'];
    $valor     = empty($_POST['valor']) ? null : $_POST['valor'];

    $campos_permitidos = ['responsavel_id', 'prioridade', 'data_publicacao', 'status_geral'];

    if (in_array($campo, $campos_permitidos)) {
        $extra_sql = ($campo == 'status_geral') ? ", data_ultima_acao = NOW()" : "";
        $pdo->prepare("UPDATE planejamento SET {$campo} = ? {$extra_sql} WHERE id = ?")->execute([$valor, $id_tarefa]);
        $mensagem = 'success';
    }
}

// Listas para os Dropdowns (SEM EMOJIS)
$status_lista = [
    'pendente'                      => 'Pendente',
    'roteiro_em_producao'           => 'Roteiro em Produção',
    'roteiro_aguardando_aprovacao'  => 'Aguardando (Roteiro)',
    'roteiro_em_revisao'            => 'Ajuste Roteiro',
    'peca_em_producao'              => 'Arte em Produção',
    'peca_aguardando_aprovacao'     => 'Aguardando (Arte)',
    'peca_em_revisao'               => 'Ajuste Arte',
    'pronto_para_postar'            => 'Pronto (Agendar)',
    'finalizado'                    => 'Finalizado',
];

$prioridade_lista = [
    'baixa'   => 'Baixa',
    'media'   => 'Média',
    'alta'    => 'Alta',
    'urgente' => 'Urgente',
];

// Mapeamento de status → classe de badge
$status_badge = [
    'pendente'                     => 'badge-gray',
    'roteiro_em_producao'          => 'badge-blue',
    'roteiro_aguardando_aprovacao' => 'badge-yellow',
    'roteiro_em_revisao'           => 'badge-yellow',
    'peca_em_producao'             => 'badge-purple',
    'peca_aguardando_aprovacao'    => 'badge-yellow',
    'peca_em_revisao'              => 'badge-yellow',
    'pronto_para_postar'           => 'badge-blue',
    'finalizado'                   => 'badge-green',
];

// Mapeamento de prioridade → classe de badge
$prioridade_badge = [
    'baixa'   => 'badge-gray',
    'media'   => 'badge-blue',
    'alta'    => 'badge-yellow',
    'urgente' => 'badge-red',
];

$usuarios = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetchAll();

$sql = "SELECT p.*, c.codigo_agc, cli.nome as cliente_nome
        FROM planejamento p
        LEFT JOIN contratos c ON p.contrato_id = c.id
        LEFT JOIN clientes cli ON c.cliente_id = cli.id
        ORDER BY
            CASE p.status_geral WHEN 'finalizado' THEN 2 ELSE 1 END,
            p.data_publicacao ASC";
$tarefas = $pdo->query($sql)->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Master Task List</h2>
        <p class="page-subtitle">Gestão de produção com edição rápida e SLA automático.</p>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="ph ph-plus" style="font-size: 16px;"></i> Nova Tarefa</a>
</div>

<?php if ($mensagem === 'success'): ?>
    <div class="alert alert-success"><i class="ph-fill ph-check-circle"></i> Tarefa atualizada com sucesso!</div>
<?php endif; ?>

<div class="card">
    <form id="formUpdateRapido" method="POST" style="display:none;">
        <input type="hidden" name="acao"      value="atualizar_campo">
        <input type="hidden" name="id_tarefa" id="upd_id">
        <input type="hidden" name="campo"     id="upd_campo">
        <input type="hidden" name="valor"     id="upd_valor">
    </form>

    <?php if (count($tarefas) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Contexto / Cliente</th>
                        <th>Título &amp; Categoria</th>
                        <th style="width: 150px;">Responsável</th>
                        <th style="width: 120px;">Prioridade</th>
                        <th style="width: 140px;">Prazo</th>
                        <th style="width: 190px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tarefas as $t): ?>

                    <?php
                        $finalizado = ($t['status_geral'] == 'finalizado');
                        $vencido    = (!$finalizado && !empty($t['data_publicacao']) && strtotime($t['data_publicacao']) < strtotime(date('Y-m-d')));
                        $badge_status    = $status_badge[$t['status_geral']] ?? 'badge-gray';
                        $badge_prioridade = $prioridade_badge[$t['prioridade']] ?? 'badge-gray';
                    ?>

                    <tr class="task-row<?= $finalizado ? ' task-finalizado' : '' ?>"
                        onclick="abrirSeNaoForInput(event, <?= $t['id'] ?>)">

                        <td>
                            <?php if ($t['escopo'] == 'interno'): ?>
                                <span class="badge badge-gray"><i class="ph ph-buildings"></i> Interno</span>
                            <?php else: ?>
                                <span class="task-codigo"><?= htmlspecialchars($t['codigo_agc']) ?></span>
                                <span class="task-cliente"><?= htmlspecialchars($t['cliente_nome']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="task-tema"><?= htmlspecialchars($t['tema'] ?: 'Sem título') ?></span>
                            <?php if ($t['tipo']): ?>
                                <span class="tag-tipo"><?= htmlspecialchars($t['tipo']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <select class="select-inline"
                                    onchange="salvarEdicaoRapida(<?= $t['id'] ?>, 'responsavel_id', this.value)">
                                <option value="">— Nenhum</option>
                                <?php foreach ($usuarios as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $u['id'] == $t['responsavel_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <select class="select-inline select-prioridade <?= $badge_prioridade ?>"
                                    onchange="salvarEdicaoRapida(<?= $t['id'] ?>, 'prioridade', this.value)">
                                <?php foreach ($prioridade_lista as $val => $rot): ?>
                                    <option value="<?= $val ?>" <?= $t['prioridade'] == $val ? 'selected' : '' ?>>
                                        <?= $rot ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <td>
                            <input type="date"
                                   class="input-inline<?= $vencido ? ' date-vencida' : '' ?>"
                                   value="<?= htmlspecialchars($t['data_publicacao']) ?>"
                                   onchange="salvarEdicaoRapida(<?= $t['id'] ?>, 'data_publicacao', this.value)">
                        </td>

                        <td>
                            <select class="select-status <?= $badge_status ?>"
                                    onchange="salvarEdicaoRapida(<?= $t['id'] ?>, 'status_geral', this.value)">
                                <?php foreach ($status_lista as $val => $rot): ?>
                                    <option value="<?= $val ?>" <?= $t['status_geral'] == $val ? 'selected' : '' ?>>
                                        <?= $rot ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <i class="ph ph-kanban" style="font-size: 48px; color: var(--border-mid); margin-bottom: 16px; display: block;"></i>
            <p style="margin: 0;">Sua lista de tarefas está vazia.</p>
        </div>
    <?php endif; ?>
</div>

<script>
function salvarEdicaoRapida(id, campo, valor) {
    document.getElementById('upd_id').value    = id;
    document.getElementById('upd_campo').value = campo;
    document.getElementById('upd_valor').value = valor;
    document.getElementById('formUpdateRapido').submit();
}

function abrirSeNaoForInput(evento, id) {
    const tag = evento.target.tagName.toLowerCase();
    if (tag !== 'select' && tag !== 'option' && tag !== 'input') {
        window.location.href = 'form.php?id=' + id;
    }
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>