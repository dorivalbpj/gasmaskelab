<?php
// publico/contrato.php

require_once '../config/database.php';
require_once '../includes/functions.php';

// ======== AUTO-FIX MÁGICO DO BANCO DE DADOS ========
try {
    $pdo->exec("ALTER TABLE contratos ADD COLUMN cpf_cnpj_aceite VARCHAR(20) NULL AFTER token, ADD COLUMN aceito_em DATETIME NULL AFTER cpf_cnpj_aceite, ADD COLUMN aceito_ip VARCHAR(50) NULL AFTER aceito_em");
    $pdo->exec("ALTER TABLE clientes ADD COLUMN cpf_cnpj VARCHAR(20) NULL AFTER telefone");
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
} catch (PDOException $e) {}
// ===================================================

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

$token = $_GET['token'] ?? '';
$mensagem = '';

if (empty($token)) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Erro</title><link rel='stylesheet' href='../assets/css/public.css'></head><body class='public-body'><div class='public-container'><div class='empty-state'><div class='empty-icon'><i class='ph-fill ph-warning-circle'></i></div><h3>Token não informado</h3><p>Este link está incompleto ou foi adulterado.</p></div></div></body></html>");
}

$stmt = $pdo->prepare("SELECT c.*, cli.nome as cliente_nome, cli.email as cliente_email
                       FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id
                       WHERE c.token = ?");
$stmt->execute([$token]);
$contrato = $stmt->fetch();

if (!$contrato) {
    die("<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'><title>Erro</title><link rel='stylesheet' href='../assets/css/public.css'></head><body class='public-body'><div class='public-container'><div class='empty-state'><div class='empty-icon'><i class='ph-fill ph-magnifying-glass'></i></div><h3>Contrato não encontrado</h3><p>O link pode ter expirado ou sido revogado.</p></div></div></body></html>");
}

$valor_parcela  = isset($contrato['valor']) ? (float)$contrato['valor'] : 0;
$meses_duracao  = (isset($contrato['duracao_meses']) && $contrato['duracao_meses'] > 0) ? (int)$contrato['duracao_meses'] : 1;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assinar_contrato']) && $contrato['status'] == 'aguardando_aceite_cliente') {
    $cpf_cnpj         = $_POST['cpf_cnpj'] ?? '';
    $endereco_completo = $_POST['endereco_completo'] ?? '';
    $ip               = $_SERVER['REMOTE_ADDR'];
    $cpf_cnpj_limpo   = preg_replace('/[^0-9]/', '', $cpf_cnpj);

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
                $pdo->prepare("UPDATE contratos SET status = 'aguardando_pagamento', cpf_cnpj_aceite = ?, aceito_em = NOW(), aceito_ip = ? WHERE id = ?")->execute([$cpf_cnpj, $ip, $contrato['id']]);
                $pdo->prepare("INSERT INTO contrato_log (contrato_id, descricao) VALUES (?, ?)")->execute([$contrato['id'], "Contrato assinado digitalmente. Doc: $cpf_cnpj | IP: $ip"]);
                $pdo->prepare("UPDATE clientes SET cpf_cnpj = ?, endereco_completo = ? WHERE id = ?")->execute([$cpf_cnpj, $endereco_completo, $contrato['cliente_id']]);
                $pdo->prepare("DELETE FROM parcelas WHERE contrato_id = ?")->execute([$contrato['id']]);

                for ($i = 0; $i < $meses_duracao; $i++) {
                    $data_vencimento = date('Y-m-d', strtotime("+$i months"));
                    $num_parcela = $i + 1;
                    $pdo->prepare("INSERT INTO parcelas (contrato_id, descricao, valor, data_vencimento, status) VALUES (?, ?, ?, ?, 'pendente')")
                        ->execute([$contrato['id'], "Mensalidade $num_parcela/$meses_duracao - " . $contrato['codigo_agc'], $valor_parcela, $data_vencimento]);
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

$chave_pix     = "58714373000104";
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
    <title>Assinatura de Contrato — Gasmaske Lab</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="../assets/css/public.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="public-body">

    <!-- Header -->
    <header class="public-header">
        <div class="public-header-inner">
            <img src="../assets/img/logo-h.png" class="logo-img" style="height:40px;">
            <span class="public-badge">Assinatura Digital</span>
        </div>
        <div class="public-header-bar"></div>
    </header>

    <!-- Hero -->
    <div class="page-hero">
        <h1>Instrumento de Serviços</h1>
        <p>Leia, assine e inicie sua parceria com a Gasmaske Lab</p>
        <span class="contract-code"><?= htmlspecialchars($contrato['codigo_agc']) ?></span>
    </div>

    <div class="public-container" style="max-width:680px;">

        <!-- Alertas de erro -->
        <?php if ($mensagem == 'erro'): ?>
            <div class="alert alert-danger">
                <i class="ph-fill ph-warning-circle"></i>
                <div>Erro ao processar assinatura. Tente novamente.</div>
            </div>
        <?php elseif ($mensagem == 'vazio'): ?>
            <div class="alert alert-warning">
                <i class="ph-fill ph-warning-circle"></i>
                <div>Informe o CPF/CNPJ e o endereço para assinar.</div>
            </div>
        <?php elseif ($mensagem == 'doc_invalido'): ?>
            <div class="alert alert-danger">
                <i class="ph-fill ph-x-circle"></i>
                <div>
                    <strong>Documento inválido!</strong><br>
                    O CPF ou CNPJ digitado não é válido na Receita Federal.
                </div>
            </div>
        <?php endif; ?>

        <?php if ($contrato['status'] == 'aguardando_aceite_cliente'): ?>

            <!-- Indicador de etapas -->
            <div style="display:flex; align-items:center; justify-content:center; gap:8px; padding:20px 0 24px; font-family:'Syne',sans-serif; font-size:0.8rem; font-weight:700;">
                <div class="step-dot active">1</div>
                <span style="color:var(--text-primary);">Seus Dados</span>
                <div style="flex:1; max-width:60px; height:1px; background:var(--border-mid);"></div>
                <div class="step-dot" id="dot-step2">2</div>
                <span style="color:var(--text-muted);" id="label-step2">Revisar e Assinar</span>
            </div>

            <!-- STEP 1: Dados legais -->
            <div id="step-1">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="ph-fill ph-identification-card"></i></div>
                        <div>
                            <h3>Seus Dados Legais</h3>
                            <p>Necessários para validar juridicamente o documento</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group" style="text-align:center;">
                            <label>CPF ou CNPJ *</label>
                            <input type="text" id="doc_input" class="form-control"
                                style="max-width:300px; margin:0 auto; text-align:center; font-size:1.1rem; font-weight:600; letter-spacing:1px;"
                                placeholder="Digite apenas números..."
                                onkeyup="verificarDocumento(this.value)">
                            <div id="doc-status" class="doc-status"></div>
                        </div>

                        <div id="box-endereco" class="address-box" style="margin-top:20px;">
                            <p style="font-size:0.8rem; font-weight:600; text-align:center; margin-bottom:16px; color:var(--text-primary);">Confirme seu Endereço</p>
                            <div id="campos-endereco">
                                <div class="form-grid" style="margin-bottom:14px;">
                                    <div class="form-group col-full">
                                        <label>CEP *</label>
                                        <input type="text" id="cep" class="form-control" required
                                            placeholder="00000-000" maxlength="9"
                                            onkeyup="buscarCep(this.value)">
                                    </div>
                                    <div class="form-group col-full">
                                        <label>Logradouro *</label>
                                        <input type="text" id="logradouro" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Número *</label>
                                        <input type="text" id="numero" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Complemento</label>
                                        <input type="text" id="complemento" class="form-control" placeholder="Apto, Sala...">
                                    </div>
                                    <div class="form-group col-full">
                                        <label>Bairro *</label>
                                        <input type="text" id="bairro" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Cidade *</label>
                                        <input type="text" id="cidade" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>UF *</label>
                                        <input type="text" id="uf" class="form-control" required maxlength="2">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-full btn-lg"
                                onclick="irParaAssinatura()">
                                Confirmar e Ler Contrato <i class="ph ph-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Leitura e assinatura -->
            <div id="step-2" style="display:none;">
                <div class="card">
                    <div class="card-header">
                        <div class="card-icon"><i class="ph-fill ph-scroll"></i></div>
                        <div>
                            <h3>Revisão e Assinatura</h3>
                            <p>Leia o contrato completo antes de assinar</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="viewer_contrato" class="contract-viewer"></div>

                        <form method="POST" action="" style="margin-top:20px;">
                            <input type="hidden" name="assinar_contrato" value="1">
                            <input type="hidden" name="cpf_cnpj" id="final_doc">
                            <input type="hidden" name="endereco_completo" id="final_end">

                            <div class="sign-consent" style="margin-bottom:16px;">
                                <input type="checkbox" id="consent-check" required>
                                <p>Li e concordo com todos os termos deste contrato. Declaro que os dados informados são autênticos e dou fé à minha assinatura digital vinculada ao IP <strong><?= $_SERVER['REMOTE_ADDR'] ?></strong>.</p>
                            </div>

                            <button type="submit" name="aceitar" value="1" class="btn btn-primary btn-full btn-lg">
                                <i class="ph-fill ph-pen-nib"></i> Finalizar e Assinar Agora
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($contrato['status'] == 'aguardando_pagamento'): ?>

            <!-- Contrato assinado, aguardando pagamento -->
            <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="ph-fill ph-check-circle" style="font-size:1.3rem;"></i>
                <div>
                    <strong>Contrato Assinado!</strong><br>
                    Documento validado: <strong><?= htmlspecialchars($contrato['cpf_cnpj_aceite']) ?></strong>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-icon" style="background:rgba(26,158,92,0.1); color:var(--green);">
                        <i class="ph-fill ph-currency-circle-dollar"></i>
                    </div>
                    <div>
                        <h3>Pagamento da 1ª Parcela</h3>
                        <p>Confirme o pagamento para iniciar o projeto</p>
                    </div>
                </div>
                <div class="pix-card">
                    <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:4px;">Valor da Parcela</p>
                    <div class="pix-amount"><?= money($valor_parcela) ?></div>

                    <div class="pix-qr">
                        <img src="<?= $link_qrcode ?>" alt="QR Code PIX">
                    </div>

                    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:10px;">
                        Ou copie o código Pix abaixo:
                    </p>
                    <div id="btn-pix" class="pix-copy" onclick="copiarPix('<?= htmlspecialchars($pix_string) ?>')">
                        <?= htmlspecialchars($pix_string) ?>
                    </div>
                    <p style="font-size:0.75rem; color:var(--text-muted); margin-top:10px;">
                        Toque no código acima para copiar
                    </p>
                </div>
            </div>

        <?php else: ?>

            <div class="card">
                <div class="success-card">
                    <div class="success-icon-wrap" style="background:rgba(26,110,232,0.1); border-color:rgba(26,110,232,0.2); color:var(--blue);">
                        <i class="ph-fill ph-check-circle"></i>
                    </div>
                    <h2>Tudo Certo!</h2>
                    <p>Este contrato já foi assinado e validado anteriormente.</p>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <footer class="site-footer">
        <img src="../assets/img/logo-h.png" alt="Gasmaske Lab">
        <p>© <?= date('Y') ?> Gasmaske Lab · CNPJ 58.714.373/0001-04</p>
    </footer>

    <script>
        let textoBase = `<?= str_replace(["\r", "\n"], ["", "\\n"], addslashes($contrato['texto_contrato'] ?? '')) ?>`;

        function formatarDoc(v) {
            v = v.replace(/\D/g,"");
            if (v.length <= 11) {
                v = v.replace(/(\d{3})(\d)/,"$1.$2"); v = v.replace(/(\d{3})(\d)/,"$1.$2"); v = v.replace(/(\d{3})(\d{1,2})$/,"$1-$2");
            } else {
                v = v.replace(/^(\d{2})(\d)/,"$1.$2"); v = v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3"); v = v.replace(/\.(\d{3})(\d)/,".$1/$2"); v = v.replace(/(\d{4})(\d)/,"$1-$2"); v = v.substring(0, 18);
            }
            return v;
        }

        function setDocStatus(text, type) {
            const el = document.getElementById('doc-status');
            el.textContent = text;
            el.className = 'doc-status visible ' + type;
        }

        function verificarDocumento(valor) {
            const input = document.getElementById('doc_input');
            input.value = formatarDoc(valor);
            const limpo = valor.replace(/\D/g, "");
            const box = document.getElementById('box-endereco');

            if (limpo.length === 11) {
                setDocStatus('CPF identificado. Preencha o endereço abaixo.', 'info');
                box.classList.add('visible');
                document.getElementById('cep').focus();
            } else if (limpo.length === 14) {
                setDocStatus('Buscando empresa na Receita Federal...', 'info');
                box.classList.add('visible');
                document.getElementById('campos-endereco').classList.add('loading');

                fetch(`https://brasilapi.com.br/api/cnpj/v1/${limpo}`)
                    .then(r => { if(!r.ok) throw new Error(); return r.json(); })
                    .then(data => {
                        document.getElementById('cep').value        = data.cep || '';
                        document.getElementById('logradouro').value = data.logradouro || '';
                        document.getElementById('numero').value     = data.numero || '';
                        document.getElementById('complemento').value= data.complemento || '';
                        document.getElementById('bairro').value     = data.bairro || '';
                        document.getElementById('cidade').value     = data.municipio || '';
                        document.getElementById('uf').value         = data.uf || '';
                        setDocStatus('Empresa encontrada! Confirme o endereço.', 'success');
                        document.getElementById('campos-endereco').classList.remove('loading');
                    })
                    .catch(() => {
                        setDocStatus('Falha ao buscar CNPJ. Preencha manualmente.', 'error');
                        document.getElementById('campos-endereco').classList.remove('loading');
                    });
            } else {
                const s = document.getElementById('doc-status');
                s.className = 'doc-status';
                box.classList.remove('visible');
            }
        }

        function buscarCep(valor) {
            const cep = valor.replace(/\D/g, "");
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('logradouro').value = data.logradouro;
                            document.getElementById('bairro').value     = data.bairro;
                            document.getElementById('cidade').value     = data.localidade;
                            document.getElementById('uf').value         = data.uf;
                            document.getElementById('numero').focus();
                        }
                    });
            }
        }

        function irParaAssinatura() {
            const doc  = document.getElementById('doc_input').value;
            const rua  = document.getElementById('logradouro').value;
            const num  = document.getElementById('numero').value;
            const comp = document.getElementById('complemento').value;
            const bai  = document.getElementById('bairro').value;
            const cid  = document.getElementById('cidade').value;
            const uf   = document.getElementById('uf').value;
            const cep  = document.getElementById('cep').value;

            if (!doc || !rua || !num || !bai || !cid || !uf || !cep) {
                alert("Preencha todos os campos obrigatórios do endereço.");
                return;
            }

            const enderecoCompleto = `${rua}, Nº ${num}${comp ? ' ('+comp+')' : ''} — ${bai}. ${cid} — ${uf}. CEP: ${cep}`;

            let textoRefletido = textoBase;
            if (textoRefletido.includes("CONTRATANTE")) {
                textoRefletido = textoRefletido.replace("CONTRATANTE", "CONTRATANTE\nDocumento: " + doc + "\nEndereço: " + enderecoCompleto);
            } else {
                textoRefletido = "DADOS DO CONTRATANTE:\nDocumento: " + doc + "\nEndereço: " + enderecoCompleto + "\n\n" + textoRefletido;
            }

            document.getElementById('viewer_contrato').innerText = textoRefletido;
            document.getElementById('final_doc').value = doc;
            document.getElementById('final_end').value = enderecoCompleto;

            // Atualiza step indicator
            document.getElementById('dot-step2').classList.add('active');
            document.getElementById('label-step2').style.color = 'var(--text-primary)';

            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function copiarPix(texto) {
            navigator.clipboard.writeText(texto).then(() => {
                const btn = document.getElementById('btn-pix');
                btn.classList.add('copied');
                btn.innerHTML = '<strong style="font-size:0.9rem; color:var(--green); display:block; text-align:center; padding:6px 0;"><i class="ph-fill ph-check-circle"></i> Código Copiado!</strong>';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<?= addslashes(htmlspecialchars($pix_string)) ?>';
                }, 2500);
            });
        }
    </script>

</body>
</html>