<?php
// publico/contrato.php

require_once '../config/database.php';
require_once '../includes/functions.php';

// ======== AUTO-FIX MÁGICO DO BANCO DE DADOS ========
try {
    $pdo->exec("ALTER TABLE contratos ADD COLUMN cpf_cnpj_aceite VARCHAR(20) NULL AFTER token, ADD COLUMN aceito_em DATETIME NULL AFTER cpf_cnpj_aceite, ADD COLUMN aceito_ip VARCHAR(50) NULL AFTER aceito_em");
    $pdo->exec("ALTER TABLE clientes ADD COLUMN cpf_cnpj VARCHAR(20) NULL AFTER telefone");
    
    // Garante que a tabela parcelas existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS `parcelas` (
        `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `contrato_id` int(11) NOT NULL,
        `descricao` varchar(255) NOT NULL,
        `valor` decimal(10,2) NOT NULL,
        `data_vencimento` date NOT NULL,
        `data_pagamento` date DEFAULT NULL,
        `status` enum('pendente','pago','atrasado') DEFAULT 'pendente',
        FOREIGN KEY (`contrato_id`) REFERENCES `contratos` (`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) { }
// ===================================================

// --- FUNÇÕES DE VALIDAÇÃO MATEMÁTICA (RECEITA FEDERAL) ---
function validaCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/is', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

function validaCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', (string) $cnpj);
    if (strlen($cnpj) != 14 || preg_match('/(\d)\1{13}/', $cnpj)) return false;
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) { $soma += $cnpj[$i] * $j; $j = ($j == 2) ? 9 : $j - 1; }
    $resto = $soma % 11;
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) return false;
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) { $soma += $cnpj[$i] * $j; $j = ($j == 2) ? 9 : $j - 1; }
    $resto = $soma % 11;
    return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}
// ---------------------------------------------------------

$token = $_GET['token'] ?? '';
$mensagem = '';

if (empty($token)) die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Acesso inválido. Token não informado.</div>");

$stmt = $pdo->prepare("SELECT c.*, cli.nome as cliente_nome, cli.email as cliente_email 
                       FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id 
                       WHERE c.token = ?");
$stmt->execute([$token]);
$contrato = $stmt->fetch();

if (!$contrato) die("<div style='padding: 50px; text-align: center; color: #fff; font-family: sans-serif;'>Contrato não encontrado ou link inválido.</div>");

// CORREÇÃO: O valor gravado no banco já é o valor mensal da parcela.
$valor_parcela = isset($contrato['valor']) ? (float)$contrato['valor'] : 0;
$meses_duracao = (isset($contrato['duracao_meses']) && $contrato['duracao_meses'] > 0) ? (int)$contrato['duracao_meses'] : 1;

// --- PROCESSAMENTO DA ASSINATURA (ETAPA 2) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assinar_contrato']) && $contrato['status'] == 'aguardando_aceite_cliente') {
    $cpf_cnpj = $_POST['cpf_cnpj'] ?? '';
    $endereco_completo = $_POST['endereco_completo'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'];
    $cpf_cnpj_limpo = preg_replace('/[^0-9]/', '', $cpf_cnpj);
    
    if (empty($cpf_cnpj_limpo)) {
        $mensagem = 'vazio';
    } else {
        $documento_valido = false;
        if (strlen($cpf_cnpj_limpo) == 11) $documento_valido = validaCPF($cpf_cnpj_limpo);
        elseif (strlen($cpf_cnpj_limpo) == 14) $documento_valido = validaCNPJ($cpf_cnpj_limpo);

        if (!$documento_valido) {
            $mensagem = 'doc_invalido';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Atualiza o Contrato
                $stmt_aceite = $pdo->prepare("UPDATE contratos SET status = 'aguardando_pagamento', cpf_cnpj_aceite = ?, aceito_em = NOW(), aceito_ip = ? WHERE id = ?");
                $stmt_aceite->execute([$cpf_cnpj, $ip, $contrato['id']]);
                
                // 2. Grava o log
                $desc_log = "Contrato assinado digitalmente. Doc: $cpf_cnpj | IP: $ip";
                $pdo->prepare("INSERT INTO contrato_log (contrato_id, descricao) VALUES (?, ?)")->execute([$contrato['id'], $desc_log]);
                
                // 3. Atualiza a Ficha do Cliente no CRM
                $pdo->prepare("UPDATE clientes SET cpf_cnpj = ?, endereco_completo = ? WHERE id = ?")->execute([$cpf_cnpj, $endereco_completo, $contrato['cliente_id']]);

                // 4. MÁGICA FINANCEIRA: GERADOR DE PARCELAS AUTOMÁTICAS
                // Limpa possíveis parcelas antigas (se houver duplicidade)
                $pdo->prepare("DELETE FROM parcelas WHERE contrato_id = ?")->execute([$contrato['id']]);
                
                for ($i = 0; $i < $meses_duracao; $i++) {
                    $data_vencimento = date('Y-m-d', strtotime("+$i months"));
                    $num_parcela = $i + 1;
                    $desc_parcela = "Mensalidade $num_parcela/$meses_duracao - " . $contrato['codigo_agc'];

                    $stmt_parc = $pdo->prepare("INSERT INTO parcelas (contrato_id, descricao, valor, data_vencimento, status) VALUES (?, ?, ?, ?, 'pendente')");
                    $stmt_parc->execute([$contrato['id'], $desc_parcela, $valor_parcela, $data_vencimento]);
                }

                $pdo->commit();

                $contrato['status'] = 'aguardando_pagamento';
                $contrato['cpf_cnpj_aceite'] = $cpf_cnpj;
                $mensagem = 'sucesso';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = 'erro';
            }
        }
    }
}

// --- DADOS REAIS DA CONTA DA AGÊNCIA (PIX) ---
$chave_pix = "58714373000104"; 
$titular_conta = "Viviane de Souza Araujo";
$cidade_agencia = "VILA VELHA";

$pix_string = "";
$link_qrcode = "";
if ($contrato['status'] == 'aguardando_pagamento') {
    if (function_exists('gerarPayloadPix')) {
        $pix_string = gerarPayloadPix($chave_pix, $valor_parcela, $titular_conta, $cidade_agencia);
    } else {
        $pix_string = "00020101021226840014br.gov.bcb.pix01145871437300010452040000530398654" . strlen(number_format($valor_parcela, 2, '.', '')) . number_format($valor_parcela, 2, '.', '') . "5802BR5918VIVIANE S ARAUJO6009VILA VELH62070503***6304";
    }
    $link_qrcode = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($pix_string);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura de Contrato - Gasmaske Lab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .pix-copia-cola { cursor: pointer; transition: 0.2s; position: relative; }
        .pix-copia-cola:hover { background: var(--bg-hover) !important; border-color: var(--text-primary) !important; }
        .pix-copia-cola:active { transform: scale(0.98); }
        
        .form-grid-endereco { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; margin-top: 15px; }
        .form-grid-endereco .full-width { grid-column: span 2; }
        .loading-api { opacity: 0.5; pointer-events: none; }

        /* Estilo da caixa do contrato */
        .contract-viewer {
            background: var(--bg-base); padding: 30px; border-radius: 8px; height: 500px; 
            overflow-y: auto; font-family: monospace; white-space: pre-wrap; font-size: 13px; 
            color: var(--text-secondary); line-height: 1.6; border: 1px solid var(--border-mid);
        }
    </style>
</head>
<body class="public-body">
    <div class="public-container" style="max-width: 900px;">
        
        <div class="public-header" style="text-align: center; margin-bottom: 30px;">
            <img src="../assets/img/logo-h.png" class="logo-h" alt="Gasmaske Lab" style="max-width: 200px; margin-bottom: 15px;">
            <p class="public-subtitle">Instrumento Particular de Prestação de Serviços</p>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 5px;">Contrato: <strong><?= htmlspecialchars($contrato['codigo_agc']) ?></strong></p>
        </div>

        <?php if ($mensagem == 'erro'): ?>
            <div class="alert alert-danger" style="text-align: center;"><i class="ph-fill ph-warning-circle"></i> Erro ao processar assinatura. Tente novamente.</div>
        <?php elseif ($mensagem == 'vazio'): ?>
            <div class="alert alert-warning" style="text-align: center;"><i class="ph-fill ph-warning-circle"></i> Informe o documento e o endereço para assinar.</div>
        <?php elseif ($mensagem == 'doc_invalido'): ?>
            <div class="alert alert-danger" style="text-align: center; background: rgba(255, 63, 52, 0.1); border: 1px solid var(--red); color: var(--red); padding: 15px; border-radius: 8px; margin-bottom: 25px;">
                <strong style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="ph-fill ph-x-circle" style="font-size: 20px;"></i> Documento Inválido!
                </strong>
                <p style="margin: 5px 0 0 0; font-size: 13px;">O CPF ou CNPJ digitado não é válido na Receita Federal.</p>
            </div>
        <?php endif; ?>

        <?php if ($contrato['status'] == 'aguardando_aceite_cliente'): ?>
            
            <div id="step-1">
                <div class="card" style="padding: 35px; border-top: 3px solid var(--blue);">
                    <h3 class="public-section-title" style="border: none; font-size: 18px; margin-bottom: 10px;"><i class="ph-fill ph-identification-card"></i> 01. Seus Dados Legais</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 25px;">Informe seus dados para validar juridicamente este instrumento e liberar a leitura do contrato.</p>
                    
                    <div class="form-group" style="text-align: center; margin-bottom: 25px;">
                        <label style="color: var(--blue);">CPF ou CNPJ *</label>
                        <input type="text" id="doc_input" class="form-control" placeholder="Digite apenas números..." required style="width: 100%; max-width: 350px; margin: 0 auto; text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 1px;" onkeyup="verificarDocumento(this.value)">
                        <span id="doc-status" style="display: block; font-size: 12px; color: var(--text-muted); margin-top: 8px;">Aguardando documento para buscar endereço...</span>
                    </div>

                    <div id="box-endereco" style="display: none; background: var(--bg-hover); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-mid); margin-bottom: 25px;">
                        <label style="display: block; font-size: 14px; font-weight: bold; color: var(--text-primary); text-align: center; margin-bottom: 15px;">Confirme seu Endereço</label>
                        
                        <div class="form-grid-endereco" id="campos-endereco">
                            <div class="form-group full-width" style="margin-bottom: 0;">
                                <label>CEP *</label>
                                <input type="text" id="cep" class="form-control" required placeholder="00000-000" onkeyup="buscarCep(this.value)" maxlength="9">
                            </div>
                            <div class="form-group full-width" style="margin-bottom: 0;">
                                <label>Logradouro (Rua/Av) *</label>
                                <input type="text" id="logradouro" class="form-control" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Número *</label>
                                <input type="text" id="numero" class="form-control" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Complemento</label>
                                <input type="text" id="complemento" class="form-control" placeholder="Apto, Sala...">
                            </div>
                            <div class="form-group full-width" style="margin-bottom: 0;">
                                <label>Bairro *</label>
                                <input type="text" id="bairro" class="form-control" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Cidade *</label>
                                <input type="text" id="cidade" class="form-control" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label>Estado (UF) *</label>
                                <input type="text" id="uf" class="form-control" required maxlength="2">
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary" style="width: 100%; margin-top: 20px; height: 50px; font-size: 14px;" onclick="irParaAssinatura()">
                            Confirmar Dados e Ler Contrato <i class="ph ph-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div id="step-2" style="display: none;">
                <div class="card" style="padding: 30px;">
                    <h3 class="public-section-title" style="border: none; font-size: 18px; margin-bottom: 10px;"><i class="ph-fill ph-scroll"></i> 02. Revisão e Assinatura</h3>
                    <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 20px;">Seus dados foram inseridos no documento abaixo. Leia atentamente.</p>
                    
                    <div id="viewer_contrato" class="contract-viewer">
                        </div>

                    <form method="POST" action="" style="margin-top: 30px;">
                        <input type="hidden" name="assinar_contrato" value="1">
                        <input type="hidden" name="cpf_cnpj" id="final_doc">
                        <input type="hidden" name="endereco_completo" id="final_end">
                        
                        <div style="background: var(--red-glow); padding: 20px; border-radius: 8px; border: 1px solid var(--red-border); margin-bottom: 20px;">
                            <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; color: var(--text-primary);">
                                <input type="checkbox" required style="margin-top: 4px; accent-color: var(--red);">
                                <span style="font-size: 13px;">Li e concordo com todos os termos, cláusulas e obrigações descritas neste contrato. Declaro que os dados informados são autênticos e dou fé à minha assinatura digital vinculada ao IP <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong>.</span>
                            </label>
                        </div>

                        <button type="submit" name="aceitar" value="1" class="btn btn-primary" style="width: 100%; height: 60px; font-size: 18px; font-weight: 800; letter-spacing: 1px; justify-content: center;">
                            <i class="ph-fill ph-pen-nib" style="font-size: 22px;"></i> FINALIZAR E ASSINAR AGORA
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($contrato['status'] == 'aguardando_pagamento'): ?>
            
            <div class="alert alert-success" style="text-align: center; padding: 25px; border-radius: 12px; margin-bottom: 24px;">
                <i class="ph-fill ph-check-circle" style="font-size: 40px; color: var(--green); margin-bottom: 10px;"></i>
                <h2 style="margin: 0 0 10px 0;">Contrato Assinado com Sucesso!</h2>
                <p style="margin: 0; font-size: 15px;">Documento validado: <strong style="letter-spacing: 1px;"><?= htmlspecialchars($contrato['cpf_cnpj_aceite']) ?></strong></p>
            </div>

            <div class="card" style="text-align: center; padding: 40px 30px; border-top: 4px solid var(--green);">
                <h3 style="margin: 0 0 10px 0; color: var(--text-primary);">Pagamento da 1ª Parcela</h3>
                <p style="color: var(--text-secondary); font-size: 14px; margin-bottom: 25px;">Escaneie o QR Code abaixo no app do seu banco para confirmar a contratação e dar início imediato ao projeto.</p>
                
                <div style="margin: 20px 0; display: flex; justify-content: center;">
                    <img src="<?= $link_qrcode ?>" alt="QR Code PIX" style="background: white; padding: 15px; border-radius: 12px; width: 200px; height: 200px;">
                </div>

                <p style="color: var(--text-muted); margin-bottom: 5px; font-size: 13px;">Valor da Parcela:</p>
                <h2 style="margin: 0 0 20px 0; color: var(--green); font-size: 40px;"><?= money($valor_parcela) ?></h2>

                <p style="color: var(--text-muted); margin-bottom: 8px; font-size: 13px;">Clique no código abaixo para Copiar e Colar:</p>
                
                <div id="btn-pix" class="pix-copia-cola" onclick="copiarPix('<?= $pix_string ?>')" style="background: var(--bg-base); border: 2px dashed var(--border-mid); color: var(--text-primary); padding: 18px; font-size: 12px; font-family: monospace; border-radius: 8px; word-break: break-all; margin-bottom: 25px; user-select: none;">
                    <?= $pix_string ?>
                </div>
            </div>

        <?php else: ?>
            <div class="card" style="text-align: center; padding: 40px; border-color: var(--blue); background: var(--bg-hover);">
                <i class="ph-fill ph-check-circle" style="font-size: 60px; color: var(--blue); margin-bottom: 15px;"></i>
                <h2 style="margin: 0 0 10px 0; color: var(--text-primary);">Tudo Certo!</h2>
                <p style="color: var(--text-secondary); margin: 0; font-size: 15px;">Este contrato já foi assinado e validado.</p>
            </div>
        <?php endif; ?>

    </div>

       <script>
        // O texto original do banco de dados (guardado no JS para substituirmos)
        let textoBase = `<?= str_replace(["\r", "\n"], ["", "\\n"], addslashes($contrato['texto_contrato'] ?? '')) ?>`;

        // Formatação visual do documento
        function formatarDoc(v) {
            v = v.replace(/\D/g,""); 
            if (v.length <= 11) { 
                v = v.replace(/(\d{3})(\d)/,"$1.$2"); v = v.replace(/(\d{3})(\d)/,"$1.$2"); v = v.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
            } else { 
                v = v.replace(/^(\d{2})(\d)/,"$1.$2"); v = v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3"); v = v.replace(/\.(\d{3})(\d)/,".$1/$2"); v = v.replace(/(\d{4})(\d)/,"$1-$2"); v = v.substring(0, 18); 
            }
            return v;
        }

        // Validação Mágica da Receita
        function verificarDocumento(valor) {
            // CORRIGIDO: Agora o ID bate exatamente com o HTML (doc_input)
            const input = document.getElementById('doc_input');
            input.value = formatarDoc(valor);
            
            const limpo = valor.replace(/\D/g, "");
            const status = document.getElementById('doc-status');
            const box = document.getElementById('box-endereco');

            if (limpo.length === 11) {
                status.innerHTML = '<span style="color: var(--blue);">CPF identificado. Preencha seu endereço abaixo.</span>';
                box.style.display = 'block';
                document.getElementById('cep').focus();
            } else if (limpo.length === 14) {
                status.innerHTML = '<span style="color: var(--yellow);"><i class="ph ph-spinner"></i> Buscando empresa na Receita Federal...</span>';
                box.style.display = 'block';
                document.getElementById('campos-endereco').classList.add('loading-api');
                
                fetch(`https://brasilapi.com.br/api/cnpj/v1/${limpo}`)
                    .then(response => { if (!response.ok) throw new Error(); return response.json(); })
                    .then(data => {
                        document.getElementById('cep').value = data.cep || '';
                        document.getElementById('logradouro').value = data.logradouro || '';
                        document.getElementById('numero').value = data.numero || '';
                        document.getElementById('complemento').value = data.complemento || '';
                        document.getElementById('bairro').value = data.bairro || '';
                        document.getElementById('cidade').value = data.municipio || '';
                        document.getElementById('uf').value = data.uf || '';
                        
                        status.innerHTML = '<span style="color: var(--green);"><i class="ph-fill ph-check-circle"></i> Empresa encontrada! Confirme o endereço.</span>';
                        document.getElementById('campos-endereco').classList.remove('loading-api');
                    })
                    .catch(() => {
                        status.innerHTML = '<span style="color: var(--red);">Falha ao buscar CNPJ. Preencha manualmente.</span>';
                        document.getElementById('campos-endereco').classList.remove('loading-api');
                    });
            } else {
                status.innerHTML = 'Aguardando documento...';
                box.style.display = 'none';
            }
        }

        // ViaCEP automático
        function buscarCep(valor) {
            let cep = valor.replace(/\D/g, "");
            if(cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(res => res.json())
                    .then(data => {
                        if(!data.erro) {
                            document.getElementById('logradouro').value = data.logradouro;
                            document.getElementById('bairro').value = data.bairro;
                            document.getElementById('cidade').value = data.localidade;
                            document.getElementById('uf').value = data.uf;
                            document.getElementById('numero').focus();
                        }
                    });
            }
        }

        // Transição da Etapa 1 para Etapa 2
        function irParaAssinatura() {
            // CORRIGIDO: Atualizado o ID aqui também
            const doc = document.getElementById('doc_input').value;
            const rua = document.getElementById('logradouro').value;
            const num = document.getElementById('numero').value;
            const comp = document.getElementById('complemento').value;
            const bairro = document.getElementById('bairro').value;
            const cidade = document.getElementById('cidade').value;
            const uf = document.getElementById('uf').value;
            const cep = document.getElementById('cep').value;

            // Validação básica para não pular etapa com campo vazio
            if(!doc || !rua || !num || !bairro || !cidade || !uf || !cep) {
                alert("Por favor, preencha todos os campos obrigatórios do endereço.");
                return;
            }

            const enderecoCompleto = `${rua}, Nº ${num} ${comp ? '('+comp+')' : ''} - ${bairro}. ${cidade} - ${uf}. CEP: ${cep}`;
            
            // MÁGICA: Substitui a palavra "CONTRATANTE" genérica pelos dados reais
            let textoRefletido = textoBase;
            if (textoRefletido.includes("CONTRATANTE")) {
                textoRefletido = textoRefletido.replace(
                    "CONTRATANTE", 
                    "CONTRATANTE\nDocumento: " + doc + "\nEndereço: " + enderecoCompleto
                );
            } else {
                // Fallback caso ele apague a palavra CONTRATANTE no editor
                textoRefletido = "DADOS DO CONTRATANTE:\nDocumento: " + doc + "\nEndereço: " + enderecoCompleto + "\n\n" + textoRefletido;
            }
            
            document.getElementById('viewer_contrato').innerText = textoRefletido;
            
            // Alimenta os inputs ocultos que vão salvar no banco de dados via PHP
            document.getElementById('final_doc').value = doc;
            document.getElementById('final_end').value = enderecoCompleto;
            
            // Troca de telas
            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
            window.scrollTo(0,0);
        }

        // Função do PIX
        function copiarPix(texto) {
            navigator.clipboard.writeText(texto).then(() => {
                const btn = document.getElementById('btn-pix');
                const original = btn.innerHTML;
                btn.innerHTML = '<div style="font-size: 16px; font-weight: bold; color: var(--green); text-align: center;"><i class="ph-fill ph-check-circle"></i> Código Copiado!</div>';
                btn.style.borderColor = 'var(--green)';
                btn.style.background = 'rgba(34, 197, 94, 0.1)';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.borderColor = 'var(--border-mid)';
                    btn.style.background = 'var(--bg-base)';
                }, 2500);
            });
        }
    </script>
</body>
</html>