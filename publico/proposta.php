<?php
// publico/proposta.php

require_once '../config/database.php';
require_once '../includes/functions.php';

$token = $_GET['token'] ?? '';
$mensagem = '';

if (empty($token)) die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Acesso inválido.</div>");

// Busca a proposta e o cliente
$stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome, c.email as cliente_email FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.token = ?");
$stmt->execute([$token]);
$proposta = $stmt->fetch();

if (!$proposta) die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Proposta não encontrada.</div>");

$valor_base = $proposta['valor'];
$duracao = $proposta['duracao_meses'];
if ($proposta['tipo_cobranca'] == 'mensal') {
    $valor_total_contrato = $valor_base * $duracao;
    $texto_cobranca = "Mensalidade";
} else {
    $valor_total_contrato = $valor_base;
    $texto_cobranca = "Valor Único do Projeto";
}

// --- LOGICA DE ACEITE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aceitar']) && $proposta['status'] == 'enviada') {
    try {
        $pdo->beginTransaction();
        
        // 1. Gera os dados do contrato invisível para o cliente (Status: Rascunho)
        $token_contrato = bin2hex(random_bytes(16));

        $stmt_insert = $pdo->prepare("INSERT INTO contratos (cliente_id, valor, duracao_meses, status, token) VALUES (?, ?, ?, 'rascunho', ?)");
        $stmt_insert->execute([$proposta['cliente_id'], $valor_total_contrato, $duracao, $token_contrato]);
        
        $novo_contrato_id = $pdo->lastInsertId();
        
        // 2. Gera o código AGC
        $codigo_agc = "AGC-" . str_pad($novo_contrato_id, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE contratos SET codigo_agc = ? WHERE id = ?")->execute([$codigo_agc, $novo_contrato_id]);

        // 3. ATUALIZA A PROPOSTA
        $pdo->prepare("UPDATE propostas SET status = 'aceita', contrato_id = ? WHERE id = ?")
            ->execute([$novo_contrato_id, $proposta['id']]);
        
        $pdo->commit();
        $mensagem = 'sucesso';
        $proposta['status'] = 'aceita';
        
    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $mensagem = 'erro';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta Comercial - Gasmaske Lab</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Redes Sociais no Sucesso */
        .social-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--bg-hover);
            color: var(--text-primary);
            font-size: 24px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid var(--border-mid);
        }
        .social-circle:hover {
            transform: translateY(-3px);
            background: var(--red);
            border-color: var(--red);
            color: #fff;
            box-shadow: 0 10px 20px rgba(255, 63, 52, 0.3);
        }

        .proposal-content {
            background: var(--bg-base);
            padding: 25px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .pricing-box {
            background: linear-gradient(145deg, var(--bg-hover) 0%, var(--bg-base) 100%);
            padding: 30px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-mid);
            position: relative;
            overflow: hidden;
        }
        .pricing-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--blue);
        }
    </style>
</head>
<body class="public-body">
    <div class="public-container">
        
        <div class="public-header" style="text-align: center; margin-bottom: 40px;">
            <div class="brand-wrapper">
                <img src="../assets/img/logo-h.png" class="logo-img logo-h" alt="Gasmaske Lab" style="max-width: 200px; margin-bottom: 15px;">
            </div>
            <h1 style="font-size: 24px; color: var(--text-primary); margin: 0 0 5px 0;">Proposta Comercial</h1>
            <p class="public-subtitle" style="font-size: 14px;">Documento <strong>#<?= htmlspecialchars($proposta['codigo_proposta']) ?></strong> &bull; Preparado para <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong></p>
        </div>

        <?php if ($mensagem == 'sucesso' || ($proposta['status'] == 'aceita' && $mensagem != 'erro')): ?>
            <div class="card" style="border: 1px solid var(--border-mid); text-align: center; padding: 50px 30px; background: var(--bg-surface);">
                <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: rgba(34, 197, 94, 0.1); margin-bottom: 25px;">
                    <i class="ph-fill ph-check-circle" style="font-size: 48px; color: var(--green);"></i>
                </div>
                
                <h2 style="margin: 0 0 15px 0; color: var(--text-primary); font-size: 28px; letter-spacing: -0.5px;">Proposta Aceita! 🎉</h2>
                
                <p style="color: var(--text-secondary); font-size: 16px; line-height: 1.6; max-width: 400px; margin: 0 auto 30px auto;">
                    Excelente escolha! Nossa equipe foi notificada e <strong>entraremos em contato em breve</strong> para alinharmos os próximos passos e enviar o contrato oficial para assinatura.
                </p>

                <div style="border-top: 1px dashed var(--border-mid); padding-top: 30px; margin-top: 20px;">
                    <p style="color: var(--text-primary); font-weight: 600; font-size: 14px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px;">
                        Enquanto isso, conecte-se com a gente:
                    </p>
                    
                    <div style="display: flex; justify-content: center; gap: 15px;">
                        <a href="https://instagram.com/gasmaskelab" target="_blank" class="social-circle" title="Instagram">
                            <i class="ph ph-instagram-logo"></i>
                        </a>
                        <a href="https://www.tiktok.com/@gasmaskelab" target="_blank" class="social-circle" title="TikTok">
                            <i class="ph ph-tiktok-logo"></i>
                        </a>
                        <a href="https://linkedin.com/company/gasmaskelab" target="_blank" class="social-circle" title="LinkedIn">
                            <i class="ph ph-linkedin-logo"></i>
                        </a>
                    </div>
                </div>
            </div>

        <?php elseif ($mensagem == 'erro'): ?>
            <div class="alert alert-danger" style="text-align: center;"><i class="ph-fill ph-warning-circle"></i> Ocorreu um erro ao processar o aceite. Por favor, contate a agência.</div>
        
        <?php elseif ($proposta['status'] == 'enviada'): ?>
            <div class="card" style="padding: 35px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px;">
                    <i class="ph-fill ph-target" style="font-size: 24px; color: var(--red);"></i>
                    <h3 style="margin: 0; color: var(--text-primary); font-size: 18px;">Escopo do Projeto</h3>
                </div>
                
                <div class="proposal-content">
                    <?= nl2br(htmlspecialchars($proposta['descricao'])) ?>
                </div>

                <div class="pricing-box">
                    <p style="margin: 0 0 5px 0; font-size: 12px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Resumo do Investimento</p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="color: var(--text-secondary); font-size: 15px;"><?= $texto_cobranca ?>:</span>
                        <strong style="color: var(--text-primary); font-size: 18px;"><?= money($valor_base) ?></strong>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 20px; border-top: 1px solid var(--border);">
                        <span style="font-weight: 800; color: var(--text-primary); font-size: 14px;">VALOR TOTAL DO ACORDO:</span>
                        <div style="text-align: right;">
                            <strong style="color: var(--blue); font-size: 28px; letter-spacing: -1px;"><?= money($valor_total_contrato) ?></strong>
                            <?php if($duracao > 1): ?>
                                <br><span style="font-size: 12px; color: var(--text-muted);">(Contrato de <?= $duracao ?> meses)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <form method="POST" style="margin-top: 35px;">
                    <button type="submit" name="aceitar" value="1" class="btn btn-primary" style="width: 100%; justify-content: center; height: 55px; font-size: 16px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; background: var(--red); color: #fff;">
                        <i class="ph ph-handshake" style="font-size: 20px;"></i> ACEITAR PROPOSTA
                    </button>
                    <p style="text-align: center; font-size: 12px; color: var(--text-muted); margin-top: 15px;">
                        Ao clicar em aceitar, nossa equipe será notificada para redigir o seu contrato.
                    </p>
                </form>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>