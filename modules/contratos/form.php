<?php
// modules/contratos/form.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;
$proposta_id = $_GET['proposta_id'] ?? 0;

$contrato = [
    'cliente_id' => '', 'codigo_agc' => '', 'valor' => 0, 
    'duracao_meses' => 1, 'texto_contrato' => '', 'status' => 'rascunho'
];
$mensagem = '';

// --- MOTOR DE GERAÇÃO AUTOMÁTICA (VIA PROPOSTA) ---
if ($proposta_id && !$id) {
    // CORREÇÃO 1: Removido o 'c.empresa' do SQL
    $stmt_prop = $pdo->prepare("SELECT p.*, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt_prop->execute([$proposta_id]);
    $dados_proposta = $stmt_prop->fetch();

    if ($dados_proposta) {
        $contrato['cliente_id'] = $dados_proposta['cliente_id'];
        $contrato['valor'] = $dados_proposta['valor'];
        $contrato['duracao_meses'] = $dados_proposta['duracao_meses'];
        
        // 1. Cabeçalho Padrão
        $txt = "CONTRATO DE PRESTAÇÃO DE SERVIÇOS\n";
        $txt .= "=================================================\n\n";
        $txt .= "CONTRATADA\n";
        $txt .= "GasmaskeLab - Marketing e Desenvolvimento\n";
        $txt .= "CNPJ: 58.714.373/0001-04\n";
        $txt .= "Cidade: Vila Velha/ES\n";
        $txt .= "Representante: Viviane de Souza Araujo\n\n";
        
        $txt .= "CONTRATANTE\n";
        $txt .= "Nome/Empresa: " . $dados_proposta['cliente_nome'] . "\n";
        $txt .= "Contato: " . $dados_proposta['cliente_telefone'] . "\n";
        $txt .= "E-mail: " . $dados_proposta['cliente_email'] . "\n\n";
        
        // 2. Objeto e Valores (Vem da Proposta)
        $txt .= "CLÁUSULA 1ª – DO OBJETO E ESCOPO DOS SERVIÇOS\n";
        $txt .= "O presente contrato tem por objeto a prestação de serviços executados pela CONTRATADA durante o período de vigência de " . $dados_proposta['duracao_meses'] . " mês(es). Os serviços incluem:\n\n";
        $txt .= $dados_proposta['descricao'] . "\n\n";
        
        $txt .= "CLÁUSULA 2ª – DO INVESTIMENTO E PAGAMENTO\n";
        $txt .= "Pela prestação dos serviços, a CONTRATANTE pagará o valor de R$ " . number_format($dados_proposta['valor'], 2, ',', '.') . " mensais.\n";
        $txt .= "Tipo de Cobrança: " . ucfirst($dados_proposta['tipo_cobranca']) . ".\n\n";

        // 3. Cláusulas Padrão Dinâmicas (Vem dos Serviços)
        $txt .= "=================================================\n";
        $txt .= "TERMOS E CONDIÇÕES JURÍDICAS\n";
        $txt .= "=================================================\n\n";

        $servicos_array = array_filter(array_map('trim', explode(',', $dados_proposta['servicos_inclusos'])));
        $clausulas_adicionadas = "";

        foreach ($servicos_array as $nome_servico) {
            $stmt_srv = $pdo->prepare("SELECT clausulas_padrao FROM servicos WHERE nome LIKE ?");
            $stmt_srv->execute(["%" . $nome_servico . "%"]);
            $srv = $stmt_srv->fetch();
            
            if ($srv && !empty(trim($srv['clausulas_padrao']))) {
                $clausulas_adicionadas .= "--- Cláusulas Referentes a: " . mb_strtoupper($nome_servico, 'UTF-8') . " ---\n";
                $clausulas_adicionadas .= $srv['clausulas_padrao'] . "\n\n";
            }
        }
        
        if (empty($clausulas_adicionadas)) {
            $clausulas_adicionadas = "Nenhuma cláusula específica cadastrada para os serviços selecionados.\n";
        }

        $txt .= $clausulas_adicionadas;
        
        $contrato['texto_contrato'] = $txt;
        $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-magic-wand'></i> Contrato gerado automaticamente a partir da proposta! Revise os dados abaixo.</div>";
    }
} elseif ($id) {
    // Modo Edição Padrão
    $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    if ($res) $contrato = array_merge($contrato, $res);
}

// --- SALVAMENTO DO CONTRATO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_contrato'])) {
    $cliente_id = $_POST['cliente_id'] ?? '';
    $valor = str_replace(',', '.', $_POST['valor'] ?? 0);
    $duracao_meses = $_POST['duracao_meses'] ?? 1;
    $texto_contrato = $_POST['texto_contrato'] ?? '';
    $status = $_POST['status'] ?? 'rascunho';

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE contratos SET cliente_id=?, valor=?, duracao_meses=?, texto_contrato=?, status=? WHERE id=?");
            $stmt->execute([$cliente_id, $valor, $duracao_meses, $texto_contrato, $status, $id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Contrato atualizado!</div>";
        } else {
            $token = bin2hex(random_bytes(16));
            $stmt = $pdo->prepare("INSERT INTO contratos (cliente_id, valor, duracao_meses, texto_contrato, status, token) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $valor, $duracao_meses, $texto_contrato, $status, $token]);
            $id = $pdo->lastInsertId();
            
            // Gera o código do contrato (Ex: CTR-001)
            $codigo_agc = "CTR-" . str_pad($id, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE contratos SET codigo_agc = ? WHERE id = ?")->execute([$codigo_agc, $id]);
            $contrato['codigo_agc'] = $codigo_agc;
            
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Contrato criado e salvo!</div>";
        }
        
        // Atualiza a visualização
        $stmt = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch();
        if ($res) $contrato = array_merge($contrato, $res);
        
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'>Erro: " . $e->getMessage() . "</div>";
    }
}

// CORREÇÃO 2: Puxando apenas os campos que existem
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC")->fetchAll();

// FUNÇÃO PARA GERAR A URL DINÂMICA
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
// Ajuste o "/gasmaske" abaixo se a sua pasta raiz mudar quando for para produção
$url_base_dinamica = $protocolo . "://" . $dominio . "/gasmaske/publico/contrato.php?token=";

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title"><?= $id ? 'Editar Contrato (' . htmlspecialchars($contrato['codigo_agc']) . ')' : 'Novo Contrato' ?></h2>
        <p class="page-subtitle">Revise as cláusulas e o escopo antes de enviar para assinatura.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="index.php" class="btn btn-ghost"><i class="ph ph-arrow-left"></i> Voltar</a>
        <?php if ($id && $contrato['status'] == 'rascunho'): ?>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($contrato['cliente_id']) ?>">
                <input type="hidden" name="valor" value="<?= htmlspecialchars($contrato['valor']) ?>">
                <input type="hidden" name="duracao_meses" value="<?= htmlspecialchars($contrato['duracao_meses']) ?>">
                <input type="hidden" name="texto_contrato" value="<?= htmlspecialchars($contrato['texto_contrato']) ?>">
                <input type="hidden" name="status" value="aguardando_aceite_cliente">
                <input type="hidden" name="salvar_contrato" value="1">
                <button type="submit" class="btn btn-primary" style="background: var(--green); border-color: var(--green);">
                    <i class="ph ph-paper-plane-right"></i> Finalizar e Enviar
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?= $mensagem ?>

<form method="POST" id="formContrato">
    <input type="hidden" name="salvar_contrato" value="1">
    
    <div class="dashboard-grid">
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <div class="card">
                <h3 class="card-title"><i class="ph ph-sliders"></i> Configurações</h3>
                <div class="form-group">
                    <label>Cliente Vinculado *</label>
                    <select name="cliente_id" class="form-control" required>
                        <option value="">Selecione um cliente...</option>
                        <?php foreach($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ((string)$c['id'] === (string)$contrato['cliente_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor Mensal (R$) *</label>
                    <input type="number" step="0.01" name="valor" class="form-control" value="<?= number_format((float)$contrato['valor'], 2, '.', '') ?>" required style="font-size: 18px; font-weight: 700; color: var(--green);">
                </div>
                <div class="form-group">
                    <label>Duração do Contrato (Meses) *</label>
                    <input type="number" name="duracao_meses" class="form-control" value="<?= htmlspecialchars($contrato['duracao_meses']) ?>" required min="1">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Status Atual *</label>
                    <select name="status" class="form-control" required>
                        <option value="rascunho" <?= $contrato['status'] == 'rascunho' ? 'selected' : '' ?>>Rascunho (Interno)</option>
                        <option value="aguardando_aceite_cliente" <?= $contrato['status'] == 'aguardando_aceite_cliente' ? 'selected' : '' ?>>Aguardando Assinatura</option>
                        <option value="aguardando_pagamento" <?= $contrato['status'] == 'aguardando_pagamento' ? 'selected' : '' ?>>Aguardando Pagamento</option>
                        <option value="em_andamento" <?= $contrato['status'] == 'em_andamento' ? 'selected' : '' ?>>Em Andamento (Ativo)</option>
                        <option value="finalizado" <?= $contrato['status'] == 'finalizado' ? 'selected' : '' ?>>Finalizado / Encerrado</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; height: 55px; justify-content: center;">
                <i class="ph ph-floppy-disk"></i> Salvar Rascunho
            </button>
            
            <?php if($id): ?>
            <div class="card" style="border-top: 3px solid var(--blue); margin-top: 10px;">
                <h3 class="card-title" style="font-size: 14px;"><i class="ph ph-link"></i> Link para Assinatura</h3>
                <p class="text-muted" style="font-size: 12px; margin-bottom: 15px;">Copie e envie este link para o cliente revisar e assinar.</p>
                <div class="input-icon-wrapper">
                    <input type="text" readonly class="form-control" value="<?= $url_base_dinamica . $contrato['token'] ?>" id="linkContrato" style="font-size: 12px;">
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="card" style="border-top: 3px solid var(--purple);">
            <div class="card-header" style="padding-bottom: 10px;">
                <h3 class="card-title"><i class="ph ph-scroll"></i> Termo do Contrato</h3>
                <span class="badge badge-purple" style="font-size: 11px;">Edição Livre</span>
            </div>
            <p class="text-muted" style="font-size: 12px; margin-top: 0; margin-bottom: 15px;">Este é o texto exato que o cliente lerá antes de assinar. Você pode alterar qualquer cláusula livremente aqui sem afetar o modelo padrão.</p>
            
            <div class="form-group" style="margin-bottom: 0;">
                <textarea name="texto_contrato" class="form-control" rows="30" style="font-family: monospace; font-size: 13px; line-height: 1.6; padding: 20px;"><?= htmlspecialchars($contrato['texto_contrato']) ?></textarea>
            </div>
        </div>
    </div>
</form>

<?php require_once '../../includes/layout/footer.php'; ?>