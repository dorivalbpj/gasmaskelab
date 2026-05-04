<?php
// modules/equipe/gerenciar.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// ======== AUTO-FIX MÁGICO DO BANCO DE DADOS ========
try {
    // Tabela de Perguntas
    $pdo->exec("CREATE TABLE IF NOT EXISTS servico_perguntas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        servico_id INT NOT NULL,
        pergunta VARCHAR(255) NOT NULL,
        tipo_resposta VARCHAR(50) NOT NULL DEFAULT 'texto_curto',
        opcoes TEXT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE
    )");
    
    // NOVA Tabela de Pacotes (Propostas)
    $pdo->exec("CREATE TABLE IF NOT EXISTS servico_pacotes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        servico_id INT NOT NULL,
        nome VARCHAR(150) NOT NULL,
        tipo VARCHAR(50) DEFAULT 'pacote', 
        valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        descricao TEXT,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) { }
// ===================================================

$id = $_GET['id'] ?? 0;
$mensagem = '';

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ?");
$stmt->execute([$id]);
$servico = $stmt->fetch();

if (!$servico) die("<div class='empty-state-padded'><h2>Serviço não encontrado</h2><a href='servicos.php' class='btn btn-secondary'>Voltar aos Serviços</a></div>");

// --- PROCESSAMENTO DOS FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    
    // 1. Salvar Cláusulas do Contrato
    if ($_POST['acao'] == 'salvar_clausulas') {
        $clausulas = $_POST['clausulas_padrao'] ?? '';
        $pdo->prepare("UPDATE servicos SET clausulas_padrao = ? WHERE id = ?")->execute([$clausulas, $id]);
        $servico['clausulas_padrao'] = $clausulas;
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cláusulas contratuais atualizadas!</div>";
    }
    
    // 2. Ações de Perguntas
    elseif ($_POST['acao'] == 'nova_pergunta') {
        $pergunta = trim($_POST['pergunta'] ?? '');
        $tipo_resposta = trim($_POST['tipo_resposta'] ?? 'texto_curto');
        $opcoes = trim($_POST['opcoes'] ?? '');
        if (!empty($pergunta)) {
            $pdo->prepare("INSERT INTO servico_perguntas (servico_id, pergunta, tipo_resposta, opcoes) VALUES (?, ?, ?, ?)")
                ->execute([$id, $pergunta, $tipo_resposta, $opcoes]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pergunta adicionada!</div>";
        }
    } elseif ($_POST['acao'] == 'excluir_pergunta') {
        $pdo->prepare("DELETE FROM servico_perguntas WHERE id = ?")->execute([$_POST['item_id'] ?? 0]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pergunta removida!</div>";
    }

    // 3. Ações de Pacotes
    elseif ($_POST['acao'] == 'novo_pacote') {
        $nome = trim($_POST['nome'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'pacote');
        $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        
        if (!empty($nome)) {
            $pdo->prepare("INSERT INTO servico_pacotes (servico_id, nome, tipo, valor, descricao) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, $nome, $tipo, $valor, $descricao]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pacote comercial adicionado!</div>";
        }
    } elseif ($_POST['acao'] == 'excluir_pacote') {
        $pdo->prepare("DELETE FROM servico_pacotes WHERE id = ?")->execute([$_POST['item_id'] ?? 0]);
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Pacote removido!</div>";
    }
}

// Buscas auxiliares
$perguntas = $pdo->prepare("SELECT * FROM servico_perguntas WHERE servico_id = ? ORDER BY id ASC");
$perguntas->execute([$id]); $perguntas = $perguntas->fetchAll();

$pacotes = $pdo->prepare("SELECT * FROM servico_pacotes WHERE servico_id = ? ORDER BY tipo DESC, valor ASC");
$pacotes->execute([$id]); $pacotes = $pacotes->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Ecossistema: <?= htmlspecialchars($servico['nome']) ?></h2>
        <p class="page-subtitle">Gerencie o fluxo completo: Briefing (Perguntas), Proposta (Pacotes) e Contrato (Cláusulas).</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="servicos.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar</a>
    </div>
</div>

<?= $mensagem ?>

<div class="card" style="border-top: 3px solid var(--blue);">
    <div class="card-header">
        <h3 class="card-title"><i class="ph ph-currency-dollar"></i> Pacotes de Venda (Para a Proposta)</h3>
        <button type="button" class="btn btn-primary btn--sm" onclick="abrirModal('modalNovoPacote')">+ Novo Pacote</button>
    </div>
    
    <?php if (count($pacotes) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nome do Pacote / Adicional</th>
                        <th>Tipo</th>
                        <th>Valor Sugerido</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacotes as $pac): ?>
                    <tr>
                        <td>
                            <strong class="txt-name-main" style="margin-bottom: 4px;"><?= htmlspecialchars($pac['nome']) ?></strong>
                            <span class="txt-meta-sm"><?= htmlspecialchars(substr($pac['descricao'], 0, 80)) ?>...</span>
                        </td>
                        <td>
                            <?php if($pac['tipo'] == 'pacote'): ?>
                                <span class="badge badge-blue">Pacote Completo</span>
                            <?php else: ?>
                                <span class="badge badge-purple">Módulo Adicional</span>
                            <?php endif; ?>
                        </td>
                        <td><strong style="color: var(--green);">R$ <?= number_format($pac['valor'], 2, ',', '.') ?></strong></td>
                        <td class="text-center">
                            <form method="POST" onsubmit="return confirm('Excluir este pacote?');" style="margin: 0; display: inline-block;">
                                <input type="hidden" name="acao" value="excluir_pacote">
                                <input type="hidden" name="item_id" value="<?= $pac['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn--sm" style="color: var(--red); padding: 6px 10px;">
                                    <i class="ph ph-trash" style="font-size: 18px;"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted" style="font-size: 13px;">Crie os módulos comerciais (Ex: Essencial, Growth, Premium) para preencher a proposta com 1 clique.</p>
    <?php endif; ?>
</div>

<div class="card" style="border-top: 3px solid var(--yellow);">
    <div class="card-header">
        <h3 class="card-title"><i class="ph ph-list-dashes"></i> Perguntas do Briefing (Portal Público)</h3>
        <button type="button" class="btn btn-primary btn--sm" onclick="abrirModal('modalNovaPergunta')">+ Nova Pergunta</button>
    </div>
    
    <?php if (count($perguntas) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Pergunta para o Cliente</th>
                        <th>Tipo de Campo</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($perguntas as $p): ?>
                    <tr>
                        <td>
                            <strong class="txt-name-main"><?= htmlspecialchars($p['pergunta']) ?></strong>
                            <?php if (($p['tipo_resposta'] == 'multipla_escolha' || $p['tipo_resposta'] == 'selecao_multipla') && !empty($p['opcoes'])): ?>
                                <span class="txt-meta-sm" style="margin-top: 4px;"><strong>Opções:</strong> <?= htmlspecialchars($p['opcoes']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                                $badge_tipo = 'badge-gray'; $nome_tipo = 'Texto Curto';
                                if ($p['tipo_resposta'] == 'texto_longo') { $badge_tipo = 'badge-blue'; $nome_tipo = 'Parágrafo Livre'; }
                                if ($p['tipo_resposta'] == 'sim_nao') { $badge_tipo = 'badge-purple'; $nome_tipo = 'Sim ou Não'; }
                                if ($p['tipo_resposta'] == 'multipla_escolha') { $badge_tipo = 'badge-yellow'; $nome_tipo = 'Escolha Única'; }
                                if ($p['tipo_resposta'] == 'selecao_multipla') { $badge_tipo = 'badge-green'; $nome_tipo = 'Múltipla Seleção'; }
                            ?>
                            <span class="badge <?= $badge_tipo ?>"><?= $nome_tipo ?></span>
                        </td>
                        <td class="text-center">
                            <form method="POST" onsubmit="return confirm('Excluir esta pergunta?');" style="margin: 0; display: inline-block;">
                                <input type="hidden" name="acao" value="excluir_pergunta">
                                <input type="hidden" name="item_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-ghost btn--sm" style="color: var(--red); padding: 6px 10px;">
                                    <i class="ph ph-trash" style="font-size: 18px;"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted" style="font-size: 13px;">Nenhuma pergunta cadastrada. O cliente apenas selecionará o serviço no briefing.</p>
    <?php endif; ?>
</div>

<div class="card" style="border-top: 3px solid var(--purple);">
    <div class="card-header">
        <h3 class="card-title"><i class="ph ph-scroll"></i> Cláusulas Jurídicas (Para o Contrato)</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="acao" value="salvar_clausulas">
        <div class="form-group">
            <label>Cláusulas Padrão deste Serviço</label>
            <p class="text-muted" style="font-size: 11px; margin-top: -5px; margin-bottom: 10px;">Estas cláusulas serão injetadas automaticamente no contrato quando este serviço for contratado.</p>
            <textarea name="clausulas_padrao" class="form-control" rows="8" placeholder="Ex: CLÁUSULA 1 - DO ESCOPO: O serviço compreende..."><?= htmlspecialchars($servico['clausulas_padrao'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="ph ph-floppy-disk"></i> Salvar Cláusulas</button>
    </form>
</div>


<div id="modalNovoPacote" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModal('modalNovoPacote')"><i class="ph ph-x"></i></button>
        <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Adicionar Pacote de Venda</h3>
        
        <form method="POST">
            <input type="hidden" name="acao" value="novo_pacote">
            <div class="form-group">
                <label>Nome do Pacote (Botão) *</label>
                <input type="text" name="nome" class="form-control" required placeholder="Ex: P1. Essencial">
            </div>
            <div class="form-group">
                <label>Tipo *</label>
                <select name="tipo" class="form-control" required>
                    <option value="pacote">Pacote Completo (Substitui o texto da proposta)</option>
                    <option value="adicional">Módulo Adicional (Soma ao texto da proposta)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Valor Sugerido (R$) *</label>
                <input type="number" step="0.01" name="valor" class="form-control" required value="0.00">
            </div>
            <div class="form-group" style="margin-bottom: 24px;">
                <label>Copy de Vendas (Texto da Proposta) *</label>
                <textarea name="descricao" class="form-control" rows="6" required placeholder="Cole aqui a carta de vendas e os entregáveis deste pacote..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">Salvar Pacote</button>
        </form>
    </div>
</div>

<div id="modalNovaPergunta" class="modal-overlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModal('modalNovaPergunta')"><i class="ph ph-x"></i></button>
        <h3 style="margin: 0 0 20px 0; font-size: 20px; color: var(--text-primary);">Adicionar Pergunta ao Briefing</h3>
        
        <form method="POST">
            <input type="hidden" name="acao" value="nova_pergunta">
            <div class="form-group">
                <label>A sua Pergunta *</label>
                <input type="text" name="pergunta" class="form-control" required placeholder="Ex: Quais serviços extras deseja?">
            </div>
            <div class="form-group">
                <label>Tipo de Resposta Esperada *</label>
                <select name="tipo_resposta" id="select_tipo_resposta" class="form-control" required onchange="toggleCampoOpcoes()">
                    <option value="texto_curto">Texto Curto (1 Linha)</option>
                    <option value="texto_longo">Parágrafo Livre (Múltiplas Linhas)</option>
                    <option value="sim_nao">Sim ou Não (Caixa de Seleção)</option>
                    <option value="multipla_escolha">Escolha Única (Apenas 1 opção de botão)</option>
                    <option value="selecao_multipla">Múltipla Seleção (Pode marcar vários botões)</option>
                </select>
            </div>
            <div class="form-group" id="bloco_opcoes" style="display: none; margin-bottom: 24px; padding: 15px; background: var(--bg-hover); border: 1px dashed var(--border-mid); border-radius: var(--radius-sm);">
                <label style="color: var(--yellow);">Alternativas da Pergunta *</label>
                <p style="font-size: 11px; color: var(--text-muted); margin-top: 0; margin-bottom: 8px;">Separe as opções por vírgula. Ex: <strong style="color: var(--text-secondary);">Opção A, Opção B, Opção C</strong></p>
                <input type="text" name="opcoes" id="input_opcoes" class="form-control" placeholder="Tiktok, Google Ads, E-mail...">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px; margin-top: 15px;">Salvar Pergunta</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id) { const modal = document.getElementById(id); modal.style.display = 'flex'; setTimeout(() => modal.classList.add('active'), 10); }
function fecharModal(id) { const modal = document.getElementById(id); modal.classList.remove('active'); setTimeout(() => modal.style.display = 'none', 300); }

function toggleCampoOpcoes() {
    const select = document.getElementById('select_tipo_resposta');
    const bloco = document.getElementById('bloco_opcoes');
    const input = document.getElementById('input_opcoes');
    if (select.value === 'multipla_escolha' || select.value === 'selecao_multipla') {
        bloco.style.display = 'block'; input.required = true;
    } else {
        bloco.style.display = 'none'; input.required = false; input.value = ''; 
    }
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>