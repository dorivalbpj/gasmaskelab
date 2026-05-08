<?php
// publico/proposta.php
require_once '../config/database.php';

$token = $_GET['token'] ?? '';
if (!$token) die("Documento indisponível.");

$stmt = $pdo->prepare("SELECT p.*, c.nome as cliente_nome FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.token = ?");
$stmt->execute([$token]);
$proposta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$proposta || ($proposta['status'] != 'enviada' && $proposta['status'] != 'rascunho' && $proposta['status'] != 'aceita')) {
    die("Proposta inválida ou expirada.");
}

// Lógica de Aceite AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'aceitar_proposta') {
    header('Content-Type: application/json');
    if ($proposta['status'] === 'aceita') {
        echo json_encode(['success' => false, 'error' => 'Esta estratégia já foi validada.']);
        exit;
    }
    $upd = $pdo->prepare("UPDATE propostas SET status = 'aceita' WHERE id = ?");
    if($upd->execute([$proposta['id']])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro interno ao validar a estratégia.']);
    }
    exit;
}

$valor_base = (float)$proposta['valor'];
$tipo_cobranca = $proposta['tipo_cobranca'] ?? 'mensal'; // 'unico' ou 'mensal'
$valor_fmt = number_format($valor_base, 2, ',', '.');
$data_validade = date('d/m/Y', strtotime($proposta['data_validade']));
$ja_aprovada = ($proposta['status'] === 'aceita');
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;600;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --red: #FF3B2F; --dark: #111111; --off-white: #F4F4F0;
            --gray-mid: #E8E8E2; --text-main: #1C1C1C; --text-muted: #666666;
            --glass: rgba(244, 244, 240, 0.85);
        }

        body { background-color: var(--off-white); color: var(--text-main); font-family: 'Inter', sans-serif; overflow-x: hidden; padding-bottom: 100px; }

        .marquee-wrapper { background: var(--dark); color: var(--off-white); padding: 8px 0; font-family: 'Space Mono', monospace; font-size: 0.7rem; letter-spacing: 2px; text-transform: uppercase; overflow: hidden; white-space: nowrap; position: relative; z-index: 100; }
        .marquee-content { display: inline-block; animation: marquee 20s linear infinite; }
        @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        .navbar-premium { padding: 40px 5%; display: flex; justify-content: space-between; align-items: center; }
        .logo-img { height: 35px; width: auto; filter: invert(1) brightness(0.1); }
        .nav-ref { font-family: 'Space Mono', monospace; font-size: 0.75rem; color: var(--text-muted); text-align: right; }

        .hero-section { padding: 60px 5% 100px; position: relative; }
        .hero-title { font-family: 'DM Serif Display', serif; font-size: clamp(3.5rem, 8vw, 7rem); line-height: 0.95; color: var(--dark); margin-bottom: 2rem; letter-spacing: -2px; max-width: 1200px; }
        .hero-subtitle { font-size: 1.3rem; color: var(--text-muted); max-width: 600px; font-weight: 400; line-height: 1.6; }

        .proposal-content { padding: 100px 5%; max-width: 1200px; margin: 0 auto; }
        .proposal-content h2 { font-family: 'DM Serif Display', serif; font-size: clamp(2rem, 4vw, 3.5rem); color: var(--dark); margin: 6rem 0 3rem; letter-spacing: -1px; }
        .proposal-content h2:first-child { margin-top: 0; }
        .proposal-content p { font-size: 1.15rem; line-height: 1.8; color: var(--text-muted); margin-bottom: 2rem; max-width: 800px; }

        .proposal-content ul { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; padding: 0; margin: 3rem 0; list-style: none; }
        .proposal-content li { background: #FFFFFF; border: 1px solid var(--gray-mid); border-radius: 12px; padding: 40px 30px; transition: all 0.3s ease; }
        .proposal-content li:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.04); border-color: rgba(255,59,47,0.3); }
        .proposal-content li strong { display: block; font-family: 'DM Serif Display', serif; font-size: 1.6rem; color: var(--dark); margin-bottom: 1rem; }

        .roadmap-container { border-left: 2px solid var(--gray-mid); padding-left: 40px; margin: 4rem 0; }
        .roadmap-item { position: relative; margin-bottom: 3rem; }
        .roadmap-item::before { content: ''; position: absolute; left: -47px; top: 5px; width: 12px; height: 12px; background: var(--off-white); border: 2px solid var(--red); border-radius: 50%; }
        .roadmap-phase { font-family: 'Space Mono', monospace; font-size: 0.8rem; color: var(--red); text-transform: uppercase; margin-bottom: 5px; }

        .investment-card { background: var(--dark); border-radius: 20px; padding: 60px; color: #FFF; margin-top: 4rem; position: relative; overflow: hidden; }
        .val-amount { font-family: 'DM Serif Display', serif; font-size: clamp(3.5rem, 6vw, 6rem); line-height: 1; margin: 1rem 0; }
        .val-amount span { font-size: 0.4em; vertical-align: super; color: var(--red); font-family: 'Space Mono', monospace; }

        .success-screen { display: <?= $ja_aprovada ? 'block' : 'none' ?>; background: #FFFFFF; border: 1px solid var(--gray-mid); border-radius: 16px; padding: 50px; text-align: center; margin-bottom: 4rem; }
        
        .sticky-cta { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--glass); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(0,0,0,0.05); padding: 15px 30px; border-radius: 100px; display: <?= $ja_aprovada ? 'none' : 'flex' ?>; justify-content: space-between; align-items: center; gap: 30px; z-index: 99; box-shadow: 0 20px 40px rgba(0,0,0,0.08); width: 90%; max-width: 650px; }
        .btn-sticky { background: var(--red); color: #fff; font-weight: 600; padding: 12px 24px; border-radius: 50px; text-decoration: none; border: none; white-space: nowrap;}
        
        @media (max-width: 768px) {
            .navbar-premium { padding: 30px 5%; } .hero-section { padding: 40px 5% 60px; } .investment-card { padding: 40px 30px; }
            .sticky-cta { width: 95%; flex-direction: column; border-radius: 24px; gap: 15px; text-align: center; } .btn-sticky { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>

    <div class="marquee-wrapper">
        <div class="marquee-content">
            ESTRATÉGIA B2B &bull; DESIGN DE ALTA PERFORMANCE &bull; ACELERAÇÃO DE NEGÓCIOS &bull; GASMASKE LAB &bull; ESTRATÉGIA B2B &bull; DESIGN DE ALTA PERFORMANCE &bull; ACELERAÇÃO DE NEGÓCIOS &bull; GASMASKE LAB &bull; 
        </div>
    </div>

    <nav class="navbar-premium" data-aos="fade-down" data-aos-duration="1000">
        <img src="../assets/img/logo-h.png" alt="Gasmaske Lab" class="logo-img">
        <div class="nav-ref">Ref. <?= htmlspecialchars($proposta['codigo_proposta']) ?><br><?= date('d/m/Y') ?></div>
    </nav>

    <section class="hero-section">
        <h1 class="hero-title" data-aos="fade-up">
            <?= htmlspecialchars($proposta['titulo']) ?>
        </h1>
        <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">
            Inteligência e arquitetura de marca desenvolvida exclusivamente para o ecossistema de negócios da <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>.
        </p>
    </section>

    <section class="proposal-content">
        
        <div class="success-screen" id="successBlock">
            <i class="ph-fill ph-check-circle" style="font-size: 4rem; color: var(--red);"></i>
            <h2 style="margin-top:20px;">ROTA ESTRATÉGICA VALIDADA.</h2>
            <p style="margin: 20px auto;">O sinal verde para o projeto foi dado. Nossa equipe está preparando o Contrato de Prestação de Serviços oficial. Entraremos em contato para o onboarding em breve.</p>
        </div>

        <div data-aos="fade-up" data-aos-duration="1000">
            <?= $proposta['descricao'] ?>
        </div>

        <h2 data-aos="fade-up">Roadmap Tático</h2>
        <div class="roadmap-container" data-aos="fade-up">
            <?php if ($tipo_cobranca === 'unico'): ?>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 1</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Onboarding & Briefing</h4>
                    <p style="font-size: 1rem; margin:0;">Alinhamento criativo e coleta de referências para a construção da base do projeto.</p>
                </div>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 2</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Desenvolvimento Criativo</h4>
                    <p style="font-size: 1rem; margin:0;">Execução técnica, design de alta performance e refinamento da solução.</p>
                </div>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 3</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Entrega & Handover</h4>
                    <p style="font-size: 1rem; margin:0;">Apresentação do material final e entrega de todos os ativos digitais do projeto.</p>
                </div>
            <?php else: ?>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 1</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Onboarding & Auditoria</h4>
                    <p style="font-size: 1rem; margin:0;">Mergulho profundo na operação e auditoria das estruturas atuais.</p>
                </div>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 2</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Execução Estratégica</h4>
                    <p style="font-size: 1rem; margin:0;">Implementação de processos e criação de infraestrutura de autoridade.</p>
                </div>
                <div class="roadmap-item">
                    <div class="roadmap-phase">Fase 3</div>
                    <h4 style="font-family: 'DM Serif Display'; margin-bottom: 5px;">Aceleração & Otimização</h4>
                    <p style="font-size: 1rem; margin:0;">Gestão ativa, acompanhamento de métricas e refinamento contínuo dos resultados.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="investment-card" data-aos="zoom-in-up" data-aos-duration="800">
            <div class="row">
                <div class="col-md-7">
                    <div style="font-family: 'Space Mono'; font-size: 0.85rem; color: var(--red); text-transform: uppercase;">
                        <?= $tipo_cobranca === 'unico' ? 'Investimento Único do Projeto' : 'Investimento Mensal (Recorrente)' ?>
                    </div>
                    <div class="val-amount"><span>R$</span> <?= $valor_fmt ?></div>
                    
                    <?php if ($tipo_cobranca === 'unico'): ?>
                        <p style="color: rgba(255,255,255,0.7); margin: 0;">
                            Condição: <strong>50% Inicial para reserva de pauta + 50% na Entrega</strong>
                        </p>
                    <?php else: ?>
                        <p style="color: rgba(255,255,255,0.7); margin: 0;">
                            Ciclo contratual de <strong><?= (int)$proposta['duracao_meses'] ?> meses</strong>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-5 mt-4 mt-md-0 d-flex flex-column justify-content-center">
                    <?php if ($tipo_cobranca === 'unico'): ?>
                        <p style="color: rgba(255,255,255,0.6); font-size: 0.95rem; line-height: 1.6;">
                            O desenvolvimento inicia após a compensação do sinal. O saldo remanescente libera a entrega dos arquivos finais.<br><br>
                            Condição válida até: <strong style="color: #FFF;"><?= $data_validade ?></strong>
                        </p>
                    <?php else: ?>
                        <p style="color: rgba(255,255,255,0.6); font-size: 0.95rem; line-height: 1.6;">
                            O setup operacional inicia após a compensação da primeira fatura mensal. Os pagamentos subsequentes ocorrem via boleto ou PIX.<br><br>
                            Condição válida até: <strong style="color: #FFF;"><?= $data_validade ?></strong>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <footer style="padding: 40px 5%; border-top: 1px solid var(--gray-mid); display: flex; justify-content: space-between; align-items: center; margin-top: 60px;">
        <div style="font-size: 0.9rem; color: var(--text-muted);">
            Gasmaske Lab &copy; <?= date('Y') ?><br>
            Vila Velha - ES
        </div>
        <div><img src="../assets/img/logo-h.png" class="logo-img" style="height:20px;"></div>
    </footer>

    <div class="sticky-cta" id="stickyCta">
        <div class="d-none d-md-block text-start">
            <div style="font-family: 'DM Serif Display', serif; font-size: 1.2rem; color: var(--dark); line-height: 1.2;">
                R$ <?= $valor_fmt ?> <span style="font-family: 'Inter'; font-size: 0.8rem; color: var(--text-muted);"><?= $tipo_cobranca === 'unico' ? '(Pagamento Único)' : '/mês' ?></span>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">Valide a proposta para emitirmos o contrato.</div>
        </div>
        <button class="btn-sticky" data-bs-toggle="modal" data-bs-target="#modalAceite">VALIDAR ESTRATÉGIA &rarr;</button>
    </div>

    <div class="modal fade" id="modalAceite" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 0; border: 2px solid var(--dark);">
                <div class="modal-body p-5">
                    <h3 style="font-weight: 800; margin-bottom: 20px; text-transform: uppercase;">Dar o OK no Projeto</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 30px;">
                        Ao confirmar, você valida o plano para a <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>. Este OK autoriza nossa equipe a preparar o Contrato Oficial de Prestação de Serviços.
                    </p>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="termosCheck" style="border-radius:0; border-color: var(--dark);">
                        <label class="form-check-label" for="termosCheck" style="font-size: 0.85rem; font-weight: 600;">
                            ESTOU DE ACORDO COM O ESCOPO E AUTORIZO O CONTRATO.
                        </label>
                    </div>

                    <button class="btn-sticky w-100" id="btnConfirmar" onclick="confirmarAceite()" style="border-radius: 0;">CONFIRMAR ESTRATÉGIA</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true });

        async function confirmarAceite() {
            const checkbox = document.getElementById('termosCheck');
            if(!checkbox.checked) { alert('Valide a autorização para continuar.'); return; }
            const btn = document.getElementById('btnConfirmar');
            btn.innerHTML = 'NOTIFICANDO EQUIPE...'; btn.disabled = true;
            try {
                const formData = new FormData();
                formData.append('action', 'aceitar_proposta');
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalAceite')).hide();
                    document.getElementById('stickyCta').style.display = 'none';
                    document.getElementById('successBlock').style.display = 'block';
                    document.getElementById('successBlock').scrollIntoView({ behavior: 'smooth' });
                } else {
                    alert(data.error);
                    btn.innerHTML = 'CONFIRMAR ESTRATÉGIA'; btn.disabled = false;
                }
            } catch(e) {
                alert('Erro de conexão.');
                btn.innerHTML = 'CONFIRMAR ESTRATÉGIA'; btn.disabled = false;
            }
        }
    </script>
</body>
</html>