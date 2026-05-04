<?php
// publico/briefing.php
require_once '../config/database.php';

$mensagem = '';
$servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
$perguntas_db = $pdo->query("SELECT * FROM servico_perguntas ORDER BY id ASC")->fetchAll();
$perguntas_por_servico = [];
foreach($perguntas_db as $p) {
    $perguntas_por_servico[$p['servico_id']][] = $p;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    
    $redes_tipos = $_POST['redes_tipos'] ?? [];
    $redes_links = $_POST['redes_links'] ?? [];
    $redes_finais = [];
    for ($i = 0; $i < count($redes_tipos); $i++) {
        if (!empty($redes_links[$i])) {
            $redes_finais[] = $redes_tipos[$i] . ": " . $redes_links[$i];
        }
    }
    $string_redes = implode(" | ", $redes_finais);
    
    $servicos_selecionados = $_POST['servicos'] ?? [];
    $string_servicos = implode(', ', $servicos_selecionados);
    $respostas_cliente = $_POST['respostas'] ?? [];
    $textos_perguntas = $_POST['perguntas_texto'] ?? [];
    
    $bloco_respostas = "\n\n--- DADOS DE PRESENÇA DIGITAL ---\n" . ($string_redes ?: "Não informado") . "\n";
    if (!empty($respostas_cliente)) {
        $bloco_respostas .= "\n--- RESPOSTAS DO BRIEFING ---\n";
        foreach ($respostas_cliente as $id_pergunta => $resposta) {
            
            // MAGIA AQUI: Se for múltipla seleção, ele vem como Array. Juntamos com vírgula.
            if (is_array($resposta)) {
                $resposta = implode(', ', array_filter($resposta));
            }
            
            if (!empty($resposta)) {
                $pergunta_label = $textos_perguntas[$id_pergunta] ?? 'Pergunta';
                $bloco_respostas .= "• $pergunta_label\n  R: $resposta\n\n";
            }
        }
    }

    $objetivo_final = "Briefing recebido via portal público." . $bloco_respostas;

    if (!empty($nome) && !empty($email)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO briefings (nome, empresa, email, telefone, servicos_desejados, objetivo, status) VALUES (?, ?, ?, ?, ?, ?, 'novo')");
            $stmt->execute([$nome, $empresa, $email, $telefone, $string_servicos, $objetivo_final]);
            $mensagem = 'sucesso';
        } catch (Exception $e) { $mensagem = 'erro'; }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Briefing Comercial | Gasmaske Lab</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="public-page-body">

    <div class="briefing-wrapper">
        <header style="text-align: center; margin-bottom: 50px;">
            <img src="../assets/img/logo-h.png" alt="Gasmaske" style="max-width: 220px;">
            <p style="color: var(--text-secondary); margin-top: 15px; font-size: 16px;">Briefing de Diagnóstico e Estratégia</p>
        </header>

        <?php if ($mensagem == 'sucesso'): ?>
            <div class="card" style="text-align: center; padding: 60px 30px; border-top: 4px solid var(--green);">
                <i class="ph-fill ph-check-circle" style="font-size: 60px; color: var(--green); margin-bottom: 20px;"></i>
                <h2 style="font-size: 28px; color: var(--text-primary);">Recebemos seu Briefing!</h2>
                <p style="color: var(--text-secondary); font-size: 16px; max-width: 450px; margin: 15px auto;">Agora nosso time vai analisar seu perfil e preparar uma proposta estratégica.</p>
                <a href="https://instagram.com/gasmaskelab" target="_blank" class="btn btn-secondary" style="margin-top: 30px;">Conheça nosso Instagram</a>
            </div>
        <?php else: ?>

            <form method="POST">
                <div class="card" style="padding: 30px; margin-bottom: 30px;">
                    <h3 class="form-section-title"><i class="ph-fill ph-identification-card"></i> 01. Identificação</h3>
                    <div class="briefing-grid-2" style="margin-bottom: 15px;">
                        <div class="form-group mb-0">
                            <label>Nome do Responsável *</label>
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: João Silva">
                        </div>
                        <div class="form-group mb-0">
                            <label>Nome da Empresa / Projeto</label>
                            <input type="text" name="empresa" class="form-control" placeholder="Ex: Gasmaske Lab">
                        </div>
                    </div>
                    <div class="briefing-grid-2">
                        <div class="form-group mb-0">
                            <label>E-mail Corporativo *</label>
                            <input type="email" name="email" class="form-control" required placeholder="contato@empresa.com">
                        </div>
                        <div class="form-group mb-0">
                            <label>WhatsApp Comercial *</label>
                            <input type="text" name="telefone" class="form-control" required placeholder="(00) 00000-0000">
                        </div>
                    </div>
                </div>

                <div class="card" style="padding: 30px; margin-bottom: 30px;">
                    <h3 class="form-section-title"><i class="ph-fill ph-globe"></i> 02. Presença Digital</h3>
                    <div id="container-redes">
                        <div class="rede-row">
                            <select name="redes_tipos[]" class="form-control">
                                <option value="Instagram">Instagram</option>
                                <option value="Site">Site / Loja</option>
                                <option value="TikTok">TikTok</option>
                                <option value="LinkedIn">LinkedIn</option>
                            </select>
                            <input type="text" name="redes_links[]" class="form-control" placeholder="@usuario ou url do site">
                        </div>
                    </div>
                    <button type="button" class="btn btn-ghost" onclick="addRede()" style="width: 100%; border: 1px dashed var(--border-mid); margin-top: 10px;">+ Adicionar outra rede</button>
                </div>

                <div class="card" style="padding: 30px; margin-bottom: 40px;">
                    <h3 class="form-section-title"><i class="ph-fill ph-rocket-launch"></i> 03. Objetivos</h3>
                    
                    <?php foreach ($servicos as $s): ?>
                        <div style="margin-bottom: 15px;">
                            <input type="checkbox" name="servicos[]" value="<?= htmlspecialchars($s['nome']) ?>" id="srv_<?= $s['id'] ?>" class="servico-checkbox" onchange="togglePerguntas(<?= $s['id'] ?>)">
                            <label for="srv_<?= $s['id'] ?>" class="servico-card">
                                <i class="ph ph-square" style="font-size: 24px; color: var(--text-muted);"></i>
                                <span style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($s['nome']) ?></span>
                            </label>

                            <?php if (isset($perguntas_por_servico[$s['id']])): ?>
                                <div id="bloco_perguntas_<?= $s['id'] ?>" style="display:none; padding: 20px; background: var(--bg-hover); border-left: 3px solid var(--red); border-radius: var(--radius-sm); margin-bottom: 15px; margin-top: -5px;">
                                    <?php foreach ($perguntas_por_servico[$s['id']] as $pergunta): ?>
                                        <div class="form-group" style="margin-bottom: 15px;">
                                            <label style="color: var(--text-secondary); font-size: 13px;"><?= htmlspecialchars($pergunta['pergunta']) ?></label>
                                            <input type="hidden" name="perguntas_texto[<?= $pergunta['id'] ?>]" value="<?= htmlspecialchars($pergunta['pergunta']) ?>">
                                            
                                            <?php if ($pergunta['tipo_resposta'] == 'texto_curto'): ?>
                                                <input type="text" name="respostas[<?= $pergunta['id'] ?>]" class="form-control req-<?= $s['id'] ?>" placeholder="Sua resposta...">
                                            
                                            <?php elseif ($pergunta['tipo_resposta'] == 'texto_longo'): ?>
                                                <textarea name="respostas[<?= $pergunta['id'] ?>]" class="form-control req-<?= $s['id'] ?>" rows="3" placeholder="Detalhe..."></textarea>
                                            
                                            <?php elseif ($pergunta['tipo_resposta'] == 'sim_nao'): ?>
                                                <select name="respostas[<?= $pergunta['id'] ?>]" class="form-control req-<?= $s['id'] ?>">
                                                    <option value="">Selecione...</option>
                                                    <option value="Sim">Sim</option>
                                                    <option value="Não">Não</option>
                                                </select>

                                            <?php elseif ($pergunta['tipo_resposta'] == 'multipla_escolha'): ?>
                                                <div class="opcoes-grid">
                                                    <?php 
                                                        $opcoes = explode(',', $pergunta['opcoes']);
                                                        foreach($opcoes as $index => $opcao):
                                                            $opcao = trim($opcao);
                                                            if(!empty($opcao)):
                                                                $id_radio = "opt_" . $pergunta['id'] . "_" . $index;
                                                    ?>
                                                        <input type="radio" name="respostas[<?= $pergunta['id'] ?>]" id="<?= $id_radio ?>" value="<?= htmlspecialchars($opcao) ?>" class="radio-pill-input">
                                                        <label for="<?= $id_radio ?>" class="radio-pill-label"><?= htmlspecialchars($opcao) ?></label>
                                                    <?php endif; endforeach; ?>
                                                </div>

                                            <?php elseif ($pergunta['tipo_resposta'] == 'selecao_multipla'): ?>
                                                <div class="opcoes-grid">
                                                    <?php 
                                                        $opcoes = explode(',', $pergunta['opcoes']);
                                                        foreach($opcoes as $index => $opcao):
                                                            $opcao = trim($opcao);
                                                            if(!empty($opcao)):
                                                                $id_check = "chk_" . $pergunta['id'] . "_" . $index;
                                                    ?>
                                                        <input type="checkbox" name="respostas[<?= $pergunta['id'] ?>][]" id="<?= $id_check ?>" value="<?= htmlspecialchars($opcao) ?>" class="radio-pill-input">
                                                        <label for="<?= $id_check ?>" class="radio-pill-label"><?= htmlspecialchars($opcao) ?></label>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; height: 60px; font-size: 18px; font-weight: 800; background: var(--red); color: #fff;">SOLICITAR DIAGNÓSTICO</button>
            </form>
        <?php endif; ?>
    </div>

    <footer class="site-footer">
        <div class="footer-content">
            <img src="../assets/img/logo-h.png" class="footer-logo" alt="Gasmaske Lab">
            <p style="color: var(--text-secondary); font-size: 14px; max-width: 500px; margin: 0 auto;">Agência de Inteligência Criativa e Performance Digital.</p>
            <p class="footer-copy">© <?= date('Y') ?> GASMASKE LAB. CNPJ: 58.714.373/0001-04</p>
        </div>
    </footer>

    <script>
        function addRede() {
            const container = document.getElementById('container-redes');
            const row = document.querySelector('.rede-row').cloneNode(true);
            row.querySelector('input').value = '';
            container.appendChild(row);
        }

        function togglePerguntas(id) {
            const cb = document.getElementById('srv_' + id);
            const block = document.getElementById('bloco_perguntas_' + id);
            if (block) {
                block.style.display = cb.checked ? 'block' : 'none';
                // Remove obrigatoriedade dos botões (pra não travar o form de bobeira)
                block.querySelectorAll('input[type="text"].req-' + id + ', textarea.req-' + id + ', select.req-' + id).forEach(i => i.required = cb.checked);
            }
            const icon = cb.nextElementSibling.querySelector('i');
            icon.className = cb.checked ? 'ph-fill ph-check-square' : 'ph ph-square';
        }
    </script>
</body>
</html>