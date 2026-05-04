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
    
    // --- 🤖 AUTOMAÇÃO DE STATUS (ESTEIRA INTELIGENTE) ---
    $status_final = $_POST['status_geral'] ?? 'pendente';
    $copy_legenda = trim($_POST['copy_legenda'] ?? '');
    $link_arte_final = trim($_POST['link_arte_final'] ?? '');
    $iniciar_relogio = false;

    // Regra 1: Colou a Arte? Ignora o resto e joga pra Aprovação de Arte
    if (!empty($link_arte_final) && in_array($status_final, ['pendente', 'roteiro_em_producao', 'roteiro_aguardando_aprovacao', 'roteiro_em_revisao', 'roteiro_aprovado', 'peca_em_producao', 'peca_em_revisao'])) {
        $status_final = 'peca_aguardando_aprovacao';
        $iniciar_relogio = true;
    } 
    // Regra 2: Escreveu o Roteiro (e não tem arte ainda)? Joga pra Aprovação de Roteiro
    elseif (!empty($copy_legenda) && empty($link_arte_final) && in_array($status_final, ['pendente', 'roteiro_em_producao', 'roteiro_em_revisao'])) {
        $status_final = 'roteiro_aguardando_aprovacao';
        $iniciar_relogio = true;
    }
    // ----------------------------------------------------

    $dados = [
        $contrato_id, $escopo, $_POST['prioridade'] ?? 'media', $_POST['responsavel_id'] ?: null,
        $_POST['tema'] ?? '', $_POST['tipo'] ?? '', $_POST['objetivo'] ?? '',
        $copy_legenda, $_POST['referencia_visual'] ?? '', $link_arte_final, $_POST['formato'] ?? '',
        $_POST['roteiro_texto'] ?? '', $status_final, $_POST['data_publicacao'] ?: null
    ];

    try {
        if ($id) {
            // Se a automação ativou, reseta o relógio do SLA 48h
            $sql_relogio = $iniciar_relogio ? ", data_ultima_acao = NOW()" : "";
            
            $sql = "UPDATE planejamento SET 
                    contrato_id=?, escopo=?, prioridade=?, responsavel_id=?, tema=?, tipo=?, 
                    objetivo=?, copy_legenda=?, referencia_visual=?, link_arte_final=?, formato=?, roteiro_texto=?, 
                    status_geral=?, data_publicacao=? {$sql_relogio} WHERE id=?";
            $dados[] = $id;
            $pdo->prepare($sql)->execute($dados);
            
            $msg_extra = $iniciar_relogio ? " <b>(Avançado automaticamente para aprovação!)</b>" : "";
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Tarefa atualizada com sucesso!$msg_extra</div>";
            
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

// Status List (SEM EMOJIS)
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
        <h2 class="page-title"><?= $id ? 'Editar Tarefa' : 'Nova Tarefa' ?></h2>
        <p class="page-subtitle">Preencha o escopo e deixe a automação fazer o resto.</p>
    </div>
    <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar para Lista</a>
</div>

<?= $mensagem ?>

<form method="POST">
    
    <div class="card">
        <h3 class="card-title" style="margin-bottom: 20px;"><i class="ph ph-info" style="font-size: 16px; margin-right: 5px;"></i> Classificação e Escopo</h3>
        
        <div class="dashboard-grid" style="grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Tipo de Escopo</label>
                <select name="escopo" id="escopo_select" onchange="toggleContrato()" class="form-control" required>
                    <option value="cliente" <?= $tarefa['escopo'] == 'cliente' ? 'selected' : '' ?>>🎯 Cliente / Projeto</option>
                    <option value="interno" <?= $tarefa['escopo'] == 'interno' ? 'selected' : '' ?>>🏢 Interno (Agência)</option>
                </select>
            </div>

            <div class="form-group" id="campo_contrato" style="margin-bottom: 0; display: <?= $tarefa['escopo'] == 'interno' ? 'none' : 'block' ?>;">
                <label>Contrato / Cliente</label>
                <select name="contrato_id" class="form-control">
                    <option value="">Selecione o Cliente...</option>
                    <?php foreach($contratos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $tarefa['contrato_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['codigo_agc'] . " - " . $c['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label>Prazo / Data Limite</label>
                <input type="date" name="data_publicacao" class="form-control" value="<?= htmlspecialchars($tarefa['data_publicacao']) ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label>Prioridade</label>
                <select name="prioridade" class="form-control">
                    <option value="baixa" <?= $tarefa['prioridade'] == 'baixa' ? 'selected' : '' ?>>Baixa</option>
                    <option value="media" <?= $tarefa['prioridade'] == 'media' ? 'selected' : '' ?>>Média</option>
                    <option value="alta" <?= $tarefa['prioridade'] == 'alta' ? 'selected' : '' ?>>Alta</option>
                    <option value="urgente" <?= $tarefa['prioridade'] == 'urgente' ? 'selected' : '' ?>>Urgente</option>
                </select>
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
                <label>Status Atual</label>
                <select name="status_geral" class="form-control" style="background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.4); color: var(--blue); font-weight: 700;">
                    <?php foreach ($status_lista as $valor => $rotulo): ?>
                        <option value="<?= $valor ?>" <?= $tarefa['status_geral'] == $valor ? 'selected' : '' ?>><?= $rotulo ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title" style="margin-bottom: 20px;"><i class="ph ph-text-align-left" style="font-size: 16px; margin-right: 5px;"></i> Briefing da Tarefa</h3>
        
        <div class="form-group">
            <label>Título / Tema da Tarefa *</label>
            <input type="text" name="tema" class="form-control" value="<?= htmlspecialchars($tarefa['tema']) ?>" required placeholder="Ex: Post de Promoção do Mês" style="font-size: 16px; font-weight: 600;">
        </div>

        <div class="dashboard-grid dashboard-grid--equal" style="margin-bottom: 20px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Categoria / Tipo</label>
                <input type="text" name="tipo" list="lista_categorias" class="form-control" value="<?= htmlspecialchars($tarefa['tipo']) ?>" placeholder="Ex: Social Media, Tráfego..." autocomplete="off">
                <datalist id="lista_categorias">
                    <?php foreach($categorias_existentes as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Formato Específico (Opcional)</label>
                <input type="text" name="formato" class="form-control" value="<?= htmlspecialchars($tarefa['formato']) ?>" placeholder="Ex: Reels 1080x1920">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label>Instruções da Agência</label>
            <textarea name="objetivo" rows="3" class="form-control" placeholder="Direcionamento para o designer e copywriter..."><?= htmlspecialchars($tarefa['objetivo']) ?></textarea>
        </div>
    </div>

    <div class="card" style="border-top: 2px solid var(--blue);">
        <h3 class="card-title" style="margin-bottom: 20px; color: var(--blue);"><i class="ph ph-magic-wand" style="font-size: 16px; margin-right: 5px;"></i> Produção & Automação</h3>
        <p class="text-muted" style="font-size: 12px; margin-top: -12px; margin-bottom: 24px;">O preenchimento dos campos abaixo avança o status da tarefa automaticamente e notifica o cliente na central dele.</p>
        
        <?php if (!empty($tarefa['feedback_cliente'])): ?>
        <div class="alert alert-danger" style="margin-bottom: 24px;">
            <strong style="display: flex; align-items: center; gap: 8px; font-size: 14px; margin-bottom: 8px;">
                <i class="ph-fill ph-warning-circle" style="font-size: 20px;"></i> Feedback do Cliente (Ajuste Solicitado)
            </strong>
            <div style="background: var(--bg-base); padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--red-border); font-family: monospace; white-space: pre-wrap; color: var(--text-primary); margin-bottom: 8px;">
                <?= htmlspecialchars($tarefa['feedback_cliente']) ?>
            </div>
            <span style="font-size: 11px; color: var(--red);">Substitua o texto/link da produção abaixo e salve para enviar para nova aprovação.</span>
        </div>
        <?php endif; ?>

        <div class="dashboard-grid dashboard-grid--equal" style="margin-bottom: 24px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label style="color: var(--blue); display: flex; align-items: center; gap: 5px;"><i class="ph ph-text-aa"></i> Conteúdo / Roteiro / Legenda</label>
                <p class="text-muted" style="font-size: 11px; margin-top: -4px; margin-bottom: 8px;">Preencher isso avança para "Aguardando Aprovação de Roteiro".</p>
                <textarea name="copy_legenda" class="form-control" rows="8" style="font-family: monospace;"><?= htmlspecialchars($tarefa['copy_legenda']) ?></textarea>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label style="display: flex; align-items: center; gap: 5px;"><i class="ph ph-images"></i> Referências Visuais</label>
                <p class="text-muted" style="font-size: 11px; margin-top: -4px; margin-bottom: 8px;">Links de inspiração (Pinterest, Google Drive, etc).</p>
                <textarea name="referencia_visual" class="form-control" rows="8"><?= htmlspecialchars($tarefa['referencia_visual']) ?></textarea>
            </div>
        </div>

        <div class="form-group" style="background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); border: 1px dashed var(--yellow); margin-bottom: 30px;">
            <label style="color: var(--yellow); display: flex; align-items: center; gap: 5px;"><i class="ph ph-image"></i> Link da Arte / Peça Finalizada</label>
            <p class="text-muted" style="font-size: 11px; margin-top: -4px; margin-bottom: 12px;">Cole aqui o link do Canva ou Drive. <b>Envia direto para "Aguardando Aprovação de Arte" e inicia o SLA.</b></p>
            <input type="text" name="link_arte_final" class="form-control" value="<?= htmlspecialchars($tarefa['link_arte_final']) ?>" placeholder="https://...">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 50px; font-size: 15px; letter-spacing: 1px;"><i class="ph ph-floppy-disk"></i> Salvar & Processar Automações</button>
    </div>
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