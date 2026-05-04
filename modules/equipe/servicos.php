<?php
// modules/equipe/servicos.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'novo_servico') {
        $nome = trim($_POST['nome'] ?? '');
        $descricao_padrao = trim($_POST['descricao_padrao'] ?? '');
        $clausulas_padrao = trim($_POST['clausulas_padrao'] ?? '');
        
        if (!empty($nome)) {
            $stmt = $pdo->prepare("INSERT INTO servicos (nome, descricao_padrao, clausulas_padrao) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $descricao_padrao, $clausulas_padrao]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Serviço adicionado com sucesso!</div>";
        }
    } elseif ($_POST['acao'] == 'excluir_servico') {
        $id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM servicos WHERE id = ?")->execute([$id]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Serviço removido!</div>";
    }
}

$servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Gestão de Serviços</h2>
        <p class="page-subtitle">Cadastre e configure o ecossistema de cada serviço da agência.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalServico()">
        <i class="ph ph-plus"></i> Novo Serviço
    </button>
</div>

<?= $mensagem ?>

<div class="card">
    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Catálogo de Serviços</h3>
        <span class="badge badge-gray"><?= count($servicos) ?> Registros</span>
    </div>
    
    <?php if (count($servicos) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nome do Serviço</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicos as $s): ?>
                    <tr>
                        <td>
                            <strong class="txt-name-main" style="margin-bottom: 4px;"><?= htmlspecialchars($s['nome']) ?></strong>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                
                                <a href="gerenciar.php?id=<?= $s['id'] ?>" class="btn btn-primary btn--sm" style="padding: 6px 10px;" title="Gerenciar Serviço (Perguntas, Pacotes e Contrato)">
                                    <i class="ph ph-sliders" style="font-size: 18px;"></i> Configurar
                                </a>

                                <form method="POST" onsubmit="return confirm('Tem certeza? Isso pode afetar dados antigos.');" style="margin: 0;">
                                    <input type="hidden" name="acao" value="excluir_servico">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn--sm" style="color: var(--red); padding: 6px 10px;" title="Excluir Serviço">
                                        <i class="ph ph-trash" style="font-size: 18px;"></i>
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-briefcase empty-state-icon"></i>
            Nenhum serviço cadastrado na agência.
        </div>
    <?php endif; ?>
</div>

<div id="modalNovoServico" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalServico()"><i class="ph ph-x"></i></button>
        <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Cadastrar Novo Serviço</h3>
        
        <form method="POST">
            <input type="hidden" name="acao" value="novo_servico">
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label>Nome do Serviço *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: Gestão de Tráfego...">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">
                <i class="ph ph-floppy-disk"></i> Salvar Serviço
            </button>
        </form>
    </div>
</div>

<script>
function abrirModalServico() { const modal = document.getElementById('modalNovoServico'); modal.style.display = 'flex'; setTimeout(() => modal.classList.add('active'), 10); }
function fecharModalServico() { const modal = document.getElementById('modalNovoServico'); modal.classList.remove('active'); setTimeout(() => modal.style.display = 'none', 300); }
</script>

<?php require_once '../../includes/layout/footer.php'; ?>