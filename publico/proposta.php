<?php
// publico/proposta.php
require_once '../config/database.php';

$token = $_GET['token'] ?? '';
if (!$token) die("Documento indisponível.");

// Puxa a proposta
$stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.token = ?");
$stmt->execute([$token]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposta || ($proposta['status'] != 'enviada' && $proposta['status'] != 'rascunho' && $proposta['status'] != 'aceita')) {
    die("Proposta inválida ou expirada.");
}

// ==========================================
// ENDPOINT AJAX EMBUTIDO (Aceitar Proposta)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'aceitar_proposta') {
    header('Content-Type: application/json');
    
    if ($proposta['status'] === 'aceita') {
        echo json_encode(['success' => false, 'error' => 'Este projeto já foi aprovado anteriormente.']);
        exit;
    }
    
    $upd = $pdo->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
    if($upd->execute([$proposta['id']])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro interno ao selar acordo.']);
    }
    exit;
}
// ==========================================

$valor_formatado = number_format($proposta['valor'], 2, ',', '.');
$data_validade = date('d/m/Y', strtotime($proposta['data_validade']));
$ja_aceita = ($proposta['status'] === 'aceita');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estratégia – Gasmaske Lab</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS separado -->
    <link rel="stylesheet" href="../assets/css/proposta.css">
</head>
<body>

    <div class="marquee-wrapper">
        <div class="marquee-content">
            ESTRATÉGIA B2B &bull; DESIGN DE ALTA PERFORMANCE &bull; ACELERAÇÃO DE NEGÓCIOS &bull; GASMASKE LAB &bull; ESTRATÉGIA B2B &bull; DESIGN DE ALTA PERFORMANCE &bull; ACELERAÇÃO DE NEGÓCIOS &bull; GASMASKE LAB &bull; 
        </div>
    </div>

    <!-- Hero Section com Vídeo de Fundo -->
    <section class="hero-section">
        <!-- Vídeo de fundo do YouTube -->
        <div class="video-background">
            <div id="youtube-bg"></div>
            <div class="overlay-white"></div>
        </div>
        
        <div class="hero-content">
            <div class="navbar-premium" data-aos="fade-down" data-aos-duration="1000">
                <img src="../assets/img/logo-h.png" alt="Gasmaske Lab" class="logo-img">
                <div class="nav-ref">Ref. <?= htmlspecialchars($proposta['codigo_proposta']) ?><br><?= date('d/m/Y') ?></div>
            </div>

            <h1 class="hero-title" data-aos="fade-up">
                <?= htmlspecialchars($proposta['titulo']) ?>
            </h1>
            <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">
                Inteligência e arquitetura de marca desenvolvida exclusivamente para o ecossistema de negócios da <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>.
            </p>
        </div>
    </section>

    <section class="proposal-content">
        
        <div class="success-screen" id="successBlock">
            <i class="ph-fill ph-seal-check success-icon"></i>
            <h2 style="margin-top:0;">Acordo Selado.</h2>
            <p style="margin: 0 auto;">Deixe as fórmulas prontas e o "design fofo" para os amadores. O setup estratégico do seu projeto já começou a rodar nos bastidores. Nosso time assumiu o controle e fará o contato oficial de onboarding em breve.</p>
        </div>

        <div data-aos="fade-up" data-aos-duration="1000">
            <?= $proposta['descricao'] ?>
        </div>

        <h2 data-aos="fade-up">Roadmap Tático</h2>
        <div class="roadmap-container" data-aos="fade-up">
            <div class="roadmap-item">
                <div class="roadmap-phase">Fase 1</div>
                <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Onboarding & Auditoria</h4>
                <p style="font-size: 1rem; margin:0;">Mergulho profundo na sua operação e alinhamento de expectativas.</p>
            </div>
            <div class="roadmap-item">
                <div class="roadmap-phase">Fase 2</div>
                <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Desenvolvimento de Escopo</h4>
                <p style="font-size: 1rem; margin:0;">Criação de identidade visual e infraestrutura de alta performance.</p>
            </div>
            <div class="roadmap-item">
                <div class="roadmap-phase">Fase 3</div>
                <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Rollout & Gestão Mensal</h4>
                <p style="font-size: 1rem; margin:0;">Lançamento das campanhas, acompanhamento contínuo e refinamento.</p>
            </div>
        </div>
        
        <div class="investment-card" data-aos="zoom-in-up" data-aos-duration="800">
            <div class="row">
                <div class="col-md-7">
                    <div style="font-family: 'Space Mono'; font-size: 0.85rem; color: var(--red); text-transform: uppercase;">Investimento</div>
                    <div class="val-amount"><span>R$</span> <?= $valor_formatado ?></div>
                    <p style="color: rgba(255,255,255,0.7); margin: 0;">Fatura <strong><?= ucfirst($proposta['tipo_cobranca']) ?></strong> &bull; Ciclo de <strong><?= $proposta['duracao_meses'] ?> meses</strong></p>
                </div>
                <div class="col-md-5 mt-4 mt-md-0 d-flex flex-column justify-content-center">
                    <p style="color: rgba(255,255,255,0.6); font-size: 0.95rem; line-height: 1.6;">
                        O arranque operacional começa imediatamente após a compensação da primeira fatura.<br><br>
                        Condição válida até: <strong style="color: #FFF;"><?= $data_validade ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div style="font-size: 0.9rem; color: var(--text-muted);">
            Gasmaske Lab &copy; <?= date('Y') ?><br>
            Vila Velha - ES
        </div>
        <div><img src="../assets/img/logo-h.png" class="logo-img" style="height:20px;"></div>
    </footer>

    <div class="sticky-cta" id="stickyCta" style="<?= $ja_aceita ? 'display:none;' : '' ?>">
        <div>
            <div style="font-family: 'DM Serif Display', serif; font-size: 1.2rem; color: var(--dark); line-height: 1.2;">
                Pronto para o próximo nível?
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Aceite a proposta para iniciar.</div>
        </div>
        <button class="btn-sticky" data-bs-toggle="modal" data-bs-target="#modalAceite">Aceitar proposta &rarr;</button>
    </div>

    <div class="modal fade" id="modalAceite" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-family: 'DM Serif Display'; font-size: 1.5rem;">Selar Acordo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="font-size: 0.95rem; color: var(--text-muted);">Você está prestes a aprovar o plano estratégico desenhado para a <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>.</p>
                    
                    <div style="background: var(--gray-mid); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Mensalidade</span>
                            <strong style="font-family: 'Space Mono';">R$ <?= $valor_formatado ?> <span style="font-size: 0.8rem;">/mês</span></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Ciclo total</span>
                            <strong style="font-family: 'Space Mono';"><?= $proposta['duracao_meses'] ?>x R$ <?= $valor_formatado ?> = R$ <?= number_format($proposta['valor'] * $proposta['duracao_meses'], 2, ',', '.') ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Faturamento</span>
                            <strong style="font-family: 'Space Mono';"><?= ucfirst($proposta['tipo_cobranca']) ?></strong>
                        </div>
                    </div>

                    <div class="form-check custom-checkbox">
                        <input class="form-check-input" type="checkbox" id="termosCheck">
                        <label class="form-check-label" for="termosCheck" style="font-size: 0.9rem;">
                            Li e concordo com os termos de prestação de serviços estabelecidos neste documento.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn-premium" id="btnConfirmar" onclick="confirmarAceite()">Confirmar Início</button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container">
        <div id="liveToast" class="custom-toast">
            <i class="ph-fill ph-info" id="toastIcon" style="font-size: 1.2rem; color: var(--red);"></i>
            <span id="toastMsg">Mensagem aqui.</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../assets/js/proposta.js"></script>
</body>
</html>