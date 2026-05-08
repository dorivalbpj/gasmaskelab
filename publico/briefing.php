<?php
// publico/briefing.php
require_once '../config/database.php';

$mensagem = '';
$servicos = $pdo->query("SELECT * FROM servicos ORDER BY nome ASC")->fetchAll();
$perguntas_db = $pdo->query("SELECT * FROM servico_perguntas ORDER BY id ASC")->fetchAll();
$perguntas_por_servico = [];
foreach ($perguntas_db as $p) {
    $perguntas_por_servico[$p['servico_id']][] = $p;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $empresa  = trim($_POST['empresa'] ?? '');
    $email    = trim($_POST['email'] ?? '');
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
    $textos_perguntas  = $_POST['perguntas_texto'] ?? [];

    $bloco_respostas = "\n\n--- DADOS DE PRESENÇA DIGITAL ---\n" . ($string_redes ?: "Não informado") . "\n";
    if (!empty($respostas_cliente)) {
        $bloco_respostas .= "\n--- RESPOSTAS DO BRIEFING ---\n";
        foreach ($respostas_cliente as $id_pergunta => $resposta) {
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
        } catch (Exception $e) {
            $mensagem = 'erro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Briefing Comercial — Gasmaske Lab</title>
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
            <span class="public-badge">Briefing Comercial</span>
        </div>
        <div class="public-header-bar"></div>
    </header>

    <!-- Hero -->
    <div class="page-hero">
        <h1>Diagnóstico e Estratégia</h1>
        <p>Preencha o formulário para recebermos sua proposta personalizada</p>
    </div>

    <div class="public-container">

        <?php if ($mensagem == 'sucesso'): ?>

            <div class="card">
                <div class="success-card">
                    <div class="success-icon-wrap"><i class="ph-fill ph-check-circle"></i></div>
                    <h2>Briefing Recebido!</h2>
                    <p>Nossa equipa vai analisar seu perfil e preparar uma proposta estratégica personalizada.</p>
                    <a href="https://instagram.com/gasmaskelab" target="_blank" class="btn btn-dark">
                        <i class="ph ph-instagram-logo"></i> Conheça nosso Instagram
                    </a>
                </div>
            </div>

        <?php else: ?>

            <?php if ($mensagem == 'erro'): ?>
                <div class="alert alert-danger">
                    <i class="ph-fill ph-warning-circle"></i>
                    <div>Ocorreu um erro ao enviar. Por favor, tente novamente.</div>
                </div>
            <?php endif; ?>

            <form method="POST" id="briefing-form">

                <!-- SEÇÃO 1: Identificação -->
                <div class="card mb-24">
                    <div class="card-header">
                        <div class="card-icon"><i class="ph-fill ph-identification-card"></i></div>
                        <div>
                            <h3>Identificação</h3>
                            <p>Quem é você e sua empresa?</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-grid" style="margin-bottom:14px;">
                            <div class="form-group col-full">
                                <label>Nome do Responsável *</label>
                                <input type="text" name="nome" class="form-control" required placeholder="João Silva">
                            </div>
                            <div class="form-group col-full">
                                <label>Empresa / Projeto</label>
                                <input type="text" name="empresa" class="form-control" placeholder="Nome da empresa">
                            </div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>E-mail *</label>
                                <input type="email" name="email" class="form-control" required placeholder="contato@empresa.com">
                            </div>
                            <div class="form-group">
                                <label>WhatsApp *</label>
                                <input type="tel" name="telefone" class="form-control" required placeholder="(00) 00000-0000">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 2: Presença Digital -->
                <div class="card mb-24">
                    <div class="card-header">
                        <div class="card-icon"><i class="ph-fill ph-globe"></i></div>
                        <div>
                            <h3>Presença Digital</h3>
                            <p>Onde você já está presente?</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="container-redes">
                            <div class="rede-row">
                                <select name="redes_tipos[]" class="form-control">
                                    <option value="Instagram">Instagram</option>
                                    <option value="Site">Site / Loja</option>
                                    <option value="TikTok">TikTok</option>
                                    <option value="LinkedIn">LinkedIn</option>
                                    <option value="YouTube">YouTube</option>
                                </select>
                                <input type="text" name="redes_links[]" class="form-control" placeholder="@usuario ou URL">
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary btn-full" style="margin-top:10px; border-style:dashed;" onclick="addRede()">
                            <i class="ph ph-plus"></i> Adicionar outra rede
                        </button>
                    </div>
                </div>

                <!-- SEÇÃO 3: Objetivos e Serviços -->
                <div class="card mb-24">
                    <div class="card-header">
                        <div class="card-icon"><i class="ph-fill ph-rocket-launch"></i></div>
                        <div>
                            <h3>Objetivos</h3>
                            <p>O que você precisa? (selecione quantos quiser)</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php foreach ($servicos as $s): ?>
                            <input type="checkbox"
                                name="servicos[]"
                                value="<?= htmlspecialchars($s['nome']) ?>"
                                id="srv_<?= $s['id'] ?>"
                                class="servico-checkbox"
                                onchange="togglePerguntas(<?= $s['id'] ?>)">
                            <label for="srv_<?= $s['id'] ?>" class="servico-card">
                                <div class="servico-icon">
                                    <i class="ph ph-squares-four"></i>
                                </div>
                                <?= htmlspecialchars($s['nome']) ?>
                            </label>

                            <?php if (isset($perguntas_por_servico[$s['id']])): ?>
                                <div id="bloco_perguntas_<?= $s['id'] ?>" class="perguntas-bloco">
                                    <?php foreach ($perguntas_por_servico[$s['id']] as $pergunta): ?>
                                        <div class="form-group">
                                            <label><?= htmlspecialchars($pergunta['pergunta']) ?></label>
                                            <input type="hidden" name="perguntas_texto[<?= $pergunta['id'] ?>]"
                                                value="<?= htmlspecialchars($pergunta['pergunta']) ?>">

                                            <?php if ($pergunta['tipo_resposta'] == 'texto_curto'): ?>
                                                <input type="text" name="respostas[<?= $pergunta['id'] ?>]"
                                                    class="form-control req-<?= $s['id'] ?>"
                                                    placeholder="Sua resposta...">

                                            <?php elseif ($pergunta['tipo_resposta'] == 'texto_longo'): ?>
                                                <textarea name="respostas[<?= $pergunta['id'] ?>]"
                                                    class="form-control req-<?= $s['id'] ?>"
                                                    rows="3" placeholder="Detalhe..."></textarea>

                                            <?php elseif ($pergunta['tipo_resposta'] == 'sim_nao'): ?>
                                                <select name="respostas[<?= $pergunta['id'] ?>]"
                                                    class="form-control req-<?= $s['id'] ?>">
                                                    <option value="">Selecione...</option>
                                                    <option value="Sim">Sim</option>
                                                    <option value="Não">Não</option>
                                                </select>

                                            <?php elseif ($pergunta['tipo_resposta'] == 'multipla_escolha'): ?>
                                                <div class="pill-grid">
                                                    <?php
                                                        $opcoes = explode(',', $pergunta['opcoes']);
                                                        foreach ($opcoes as $index => $opcao):
                                                            $opcao = trim($opcao);
                                                            if (!empty($opcao)):
                                                                $id_radio = "opt_" . $pergunta['id'] . "_" . $index;
                                                    ?>
                                                        <input type="radio"
                                                            name="respostas[<?= $pergunta['id'] ?>]"
                                                            id="<?= $id_radio ?>"
                                                            value="<?= htmlspecialchars($opcao) ?>">
                                                        <label for="<?= $id_radio ?>"><?= htmlspecialchars($opcao) ?></label>
                                                    <?php endif; endforeach; ?>
                                                </div>

                                            <?php elseif ($pergunta['tipo_resposta'] == 'selecao_multipla'): ?>
                                                <div class="pill-grid">
                                                    <?php
                                                        $opcoes = explode(',', $pergunta['opcoes']);
                                                        foreach ($opcoes as $index => $opcao):
                                                            $opcao = trim($opcao);
                                                            if (!empty($opcao)):
                                                                $id_check = "chk_" . $pergunta['id'] . "_" . $index;
                                                    ?>
                                                        <input type="checkbox"
                                                            name="respostas[<?= $pergunta['id'] ?>][]"
                                                            id="<?= $id_check ?>"
                                                            value="<?= htmlspecialchars($opcao) ?>">
                                                        <label for="<?= $id_check ?>"><?= htmlspecialchars($opcao) ?></label>
                                                    <?php endif; endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    <i class="ph-fill ph-paper-plane-tilt"></i> Solicitar Diagnóstico
                </button>

            </form>

        <?php endif; ?>

    </div>

    <footer class="site-footer">
        <img src="../assets/img/logo-h.png" alt="Gasmaske Lab">
        <p>Agência de Inteligência Criativa e Performance Digital</p>
        <p style="margin-top:4px;">© <?= date('Y') ?> Gasmaske Lab · CNPJ 58.714.373/0001-04</p>
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
                block.querySelectorAll('input[type="text"].req-' + id + ', textarea.req-' + id + ', select.req-' + id)
                    .forEach(i => i.required = cb.checked);
            }
        }
    </script>

</body>
</html>