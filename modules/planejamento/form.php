<?php
// modules/planejamento/form.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

$id = $_GET['id'] ?? 0;
$mensagem = '';

// Valores padrão
$tarefa = [
    'contrato_id' => '', 'escopo' => 'cliente', 'prioridade' => 'media',
    'responsavel_id' => $_SESSION['usuario_id'], 'tema' => '', 'tipo' => '',
    'objetivo' => '', 'copy_legenda' => '', 'referencia_visual' => '', 'link_arte_final' => '',
    'formato' => '', 'roteiro_texto' => '', 'status_geral' => 'pendente',
    'data_publicacao' => date('Y-m-d'), 'feedback_cliente' => ''
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM planejamento WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if ($res) $tarefa = array_merge($tarefa, $res);
}

// Processamento (Salvar)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $escopo = $_POST['escopo'] ?? 'cliente';
    $contrato_id = ($escopo == 'interno') ? null : ($_POST['contrato_id'] ?: null);
    $status_final = $_POST['status_geral'] ?? 'pendente';
    $copy_legenda = trim($_POST['copy_legenda'] ?? '');
    $link_arte_final = trim($_POST['link_arte_final'] ?? '');

    // Automações de SLA removidas para uso interno focado em To-Do List simples.

    $dados = [
        $contrato_id, $escopo, $_POST['prioridade'] ?? 'media', $_POST['responsavel_id'] ?: null,
        $_POST['tema'] ?? '', $_POST['tipo'] ?? '', $_POST['objetivo'] ?? '',
        $copy_legenda, $_POST['referencia_visual'] ?? '', $link_arte_final, $_POST['formato'] ?? '',
        $_POST['roteiro_texto'] ?? '', $status_final, $_POST['data_publicacao'] ?: null
    ];

    try {
        if ($id) {
            $sql = "UPDATE planejamento SET 
                    contrato_id=?, escopo=?, prioridade=?, responsavel_id=?, tema=?, tipo=?, 
                    objetivo=?, copy_legenda=?, referencia_visual=?, link_arte_final=?, formato=?, roteiro_texto=?, 
                    status_geral=?, data_publicacao=? WHERE id=?";
            $dados[] = $id;
            $pdo->prepare($sql)->execute($dados);
            
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Tarefa atualizada com sucesso!</div>";
            
            $tarefa['status_geral'] = $status_final; 
            $tarefa['link_arte_final'] = $link_arte_final;
            $tarefa['copy_legenda'] = $copy_legenda;

        } else {
            $sql = "INSERT INTO planejamento 
                    (contrato_id, escopo, prioridade, responsavel_id, tema, tipo, objetivo, copy_legenda, referencia_visual, link_arte_final, formato, roteiro_texto, status_geral, data_publicacao, data_ultima_acao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute($dados);
            $novo_id = $pdo->lastInsertId();
            header("Location: form.php?id=$novo_id&msg=sucesso");
            exit;
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

if(isset($_GET['msg']) && $_GET['msg'] == 'sucesso') {
    $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Tarefa criada com sucesso!</div>";
}

$contratos = $pdo->query("SELECT c.id, c.codigo_agc, cli.nome FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id WHERE c.status != 'finalizado' ORDER BY c.id DESC")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome FROM usuarios ORDER BY nome ASC")->fetchAll();
$categorias_existentes = $pdo->query("SELECT DISTINCT tipo FROM planejamento WHERE tipo IS NOT NULL AND tipo != '' ORDER BY tipo ASC")->fetchAll(PDO::FETCH_COLUMN);

$status_lista = [
    'pendente'                      => 'Pendente',
    'roteiro_em_producao'           => 'Roteiro em Produção',
    'roteiro_aguardando_aprovacao'  => 'Aguardando (Roteiro)',
    'roteiro_em_revisao'            => 'Ajuste de Roteiro',
    'peca_em_producao'              => 'Arte em Produção',
    'peca_aguardando_aprovacao'     => 'Aguardando (Arte)',
    'peca_em_revisao'               => 'Ajuste de Arte',
    'pronto_para_postar'            => 'Pronto (Agendar)',
    'finalizado'                    => 'Finalizado',
];

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Editar Tarefa' : 'Nova Tarefa Rápida' ?></h2>
        <p class="page-subtitle">Cadastro simplificado para controle interno.</p>
    </div>
    <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar para Lista</a>
</div>

<?= $mensagem ?>

<form method="POST">
    <div class="card" style="margin-bottom: 20px;">
        <div class="dashboard-grid" style="grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>O que precisa ser feito? (Tarefa) *</label>
                <input type="text" name="tema" class="form-control" value="<?= htmlspecialchars($tarefa['tema']) ?>" required placeholder="Ex: Post Carrossel..." style="font-weight: 600;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Categoria</label>
                <input type="text" name="tipo" list="lista_categorias" class="form-control" value="<?= htmlspecialchars($tarefa['tipo']) ?>" placeholder="Ex: Social Media" autocomplete="off">
                <datalist id="lista_categorias">
                    <?php foreach($categorias_existentes as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Escopo</label>
                <select name="escopo" id="escopo_select" onchange="toggleContrato()" class="form-control" required>
                    <option value="cliente" <?= $tarefa['escopo'] == 'cliente' ? 'selected' : '' ?>>🎯 Cliente</option>
                    <option value="interno" <?= $tarefa['escopo'] == 'interno' ? 'selected' : '' ?>>🏢 Interno</option>
                </select>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px;">
            <div class="form-group" id="campo_contrato" style="margin-bottom: 0; display: <?= $tarefa['escopo'] == 'interno' ? 'none' : 'block' ?>;">
                <label>Cliente</label>
                <select name="contrato_id" class="form-control">
                    <option value="">Selecione...</option>
                    <?php foreach($contratos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $tarefa['contrato_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Prazo</label>
                <input type="date" name="data_publicacao" class="form-control" value="<?= htmlspecialchars($tarefa['data_publicacao']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Responsável</label>
                <select name="responsavel_id" class="form-control" required>
                    <option value="">Atribuir a...</option>
                    <?php foreach($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id'] == $tarefa['responsavel_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Status</label>
                <select name="status_geral" class="form-control">
                    <?php foreach ($status_lista as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>" <?= $tarefa['status_geral'] == $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="dashboard-grid" style="grid-template-columns: 1fr; gap: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Descrição / Briefing (Opcional)</label>
                <textarea name="objetivo" rows="3" class="form-control" placeholder="Detalhes do que precisa ser feito..."><?= htmlspecialchars($tarefa['objetivo']) ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Link da Entrega (Drive, Canva, etc)</label>
                <input type="text" name="link_arte_final" class="form-control" value="<?= htmlspecialchars($tarefa['link_arte_final']) ?>" placeholder="https://...">
            </div>
        </div>
        
        <input type="hidden" name="prioridade" value="<?= htmlspecialchars($tarefa['prioridade']) ?>">
        <input type="hidden" name="formato" value="<?= htmlspecialchars($tarefa['formato']) ?>">
        <textarea name="copy_legenda" style="display:none;"><?= htmlspecialchars($tarefa['copy_legenda']) ?></textarea>
        <textarea name="referencia_visual" style="display:none;"><?= htmlspecialchars($tarefa['referencia_visual']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; height: 50px; font-size: 16px;"><i class="ph ph-floppy-disk"></i> Salvar Tarefa</button>
</form>

<script>
function toggleContrato() {
    var escopo = document.getElementById('escopo_select').value;
    var campoContrato = document.getElementById('campo_contrato');
    if (escopo === 'interno') {
        campoContrato.style.display = 'none';
        document.querySelector('select[name="contrato_id"]').value = '';
    } else {
        campoContrato.style.display = 'block';
    }
}
toggleContrato();
</script>

<?php require_once '../../includes/layout/footer.php'; ?>