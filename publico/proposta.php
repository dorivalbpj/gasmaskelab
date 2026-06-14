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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Roboto:wght@300;400;500&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/proposta.css">
</head>
<body>

<header class="hero">
  <div class="topbar">
    <div class="logo-wrap">
      <img src="../assets/img/logo-h.png" alt="Gasmaske Lab" class="logo-img"
           onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
      <span class="logo-text" style="display:none;">GASMASKE LAB</span>
    </div>
    <div class="topbar-right">
      PROPOSTA COMERCIAL<br>
      Ref. <?= htmlspecialchars($proposta['codigo_proposta']) ?><br>
      <?= date('d/m/Y') ?>
    </div>
  </div>

  <div class="hero-body">
    <div class="hero-label">Inteligência Estratégica · Performance B2B</div>
    <h1 class="hero-title">
      <?= htmlspecialchars($proposta['titulo']) ?>
    </h1>
    <p class="hero-sub">Proposta e arquitetura de marca desenvolvida exclusivamente para o ecossistema de negócios da <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>.</p>
  </div>

  <div class="hero-footer">
    <div class="hero-stat">
      <div class="hero-stat-val">100%</div>
      <div class="hero-stat-label">Foco em Posicionamento Premium</div>
    </div>
    <div class="hero-stat">
      <div class="hero-stat-val"><?= (int)$proposta['duracao_meses'] ?> meses</div>
      <div class="hero-stat-label">Ciclo inicial do projeto</div>
    </div>
    <div class="hero-stat">
      <div class="hero-stat-val">R$ <?= $valor_fmt ?></div>
      <div class="hero-stat-label"><?= $tipo_cobranca === 'unico' ? 'Investimento Total' : 'Mensalidade da Operação' ?></div>
    </div>
  </div>
</header>

<div id="successBlock" style="display: <?= $ja_aprovada ? 'block' : 'none' ?>; padding: 60px 60px 0;">
    <div class="tl-close" style="border-left-color: #1A9E5C;">
      <div class="tl-close-mark" style="border-color: #1A9E5C;"><div style="width:12px;height:12px;border-radius:50%;background:#1A9E5C;"></div></div>
      <div>
        <h4 style="margin-bottom:8px;">ROTA ESTRATÉGICA VALIDADA</h4>
        <p style="margin:0;">O sinal verde para o projeto foi dado. Nossa equipe está preparando o Contrato Oficial de Prestação de Serviços. Entraremos em contato para o onboarding em breve.</p>
      </div>
    </div>
</div>

<section id="escopo">
  <div class="section-header">
    <span class="section-num">01</span>
    <h2 class="section-title">A Estratégia e o Escopo</h2>
  </div>
  <div class="dynamic-content">
    <?= $proposta['descricao'] ?>
  </div>
</section>

<section id="timeline">
  <div class="section-header">
    <span class="section-num">02</span>
    <h2 class="section-title">Roadmap Tático</h2>
  </div>

  <?php if ($tipo_cobranca === 'unico'): ?>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 1</span>
      <span class="tl-phase-title">Onboarding & Briefing</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Alinhamento criativo e coleta de referências para a construção da base do projeto.</p></div>
    </div>
  </div>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 2</span>
      <span class="tl-phase-title">Desenvolvimento Criativo</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Execução técnica, design de alta performance e refinamento da solução.</p></div>
    </div>
  </div>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 3</span>
      <span class="tl-phase-title">Entrega & Handover</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Apresentação do material final e entrega de todos os ativos digitais do projeto.</p></div>
    </div>
  </div>
  <?php else: ?>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 1</span>
      <span class="tl-phase-title">Onboarding & Auditoria</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Mergulho profundo na operação e auditoria das estruturas atuais.</p></div>
    </div>
  </div>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 2</span>
      <span class="tl-phase-title">Execução Estratégica</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Implementação de processos e criação de infraestrutura de autoridade.</p></div>
    </div>
  </div>
  <div class="tl-phase">
    <div class="tl-phase-header">
      <span class="tl-phase-num">FASE 3</span>
      <span class="tl-phase-title">Aceleração & Otimização</span>
    </div>
    <div class="tl-items">
      <div class="tl-item"><p>Gestão ativa, acompanhamento de métricas e refinamento contínuo dos resultados.</p></div>
    </div>
  </div>
  <?php endif; ?>
</section>

<section id="investimento">
  <div class="section-header">
    <span class="section-num">03</span>
    <h2 class="section-title">Investimento</h2>
  </div>

  <div class="price-block">
    <div>
      <div class="price-label"><?= $tipo_cobranca === 'unico' ? 'Investimento Único do Projeto' : 'Investimento Mensal (Recorrente)' ?></div>
      <div class="price-value"><span>R$</span> <?= $valor_fmt ?></div>
      <p class="price-sub">
        Ciclo contratual de <strong><?= (int)$proposta['duracao_meses'] ?> meses</strong>.
      </p>
    </div>

    <div>
      <div class="payment-split">
        <div class="payment-row highlight">
          <span>Valor <?= $tipo_cobranca === 'unico' ? 'Total' : 'Mensal' ?></span>
          <span class="payment-row-val">R$ <?= $valor_fmt ?></span>
        </div>
      </div>

      <div class="price-note">
        <strong>Condições</strong><br>
        <?php if ($tipo_cobranca === 'unico'): ?>
            50% Inicial para reserva de pauta + 50% na Entrega. O desenvolvimento inicia após a compensação do sinal.
        <?php else: ?>
            O setup operacional inicia após a compensação da primeira fatura mensal. Os pagamentos subsequentes ocorrem via boleto ou PIX.
        <?php endif; ?>
      </div>

      <div class="price-note-secondary">
        Condição válida até: <strong><?= $data_validade ?></strong>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="footer-top">
    <div class="footer-left">
      <div class="logo-wrap">
        <img src="../assets/img/logo-h.png" alt="Gasmaske Lab" class="logo-img"
             onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <span class="logo-text" style="display:none;">GASMASKE LAB</span>
      </div>
      <p>Atenciosamente,<br><strong style="color:var(--ink)">Equipe Comercial</strong></p>
      <div class="validity-badge">Proposta emitida em <?= date('d/m/Y') ?></div>
    </div>

    <div class="footer-contact">
      contato@gasmaskelab.com.br<br>
      gasmaskelab.com.br<br>
      <?php if (!$ja_aprovada): ?>
      <a href="#" class="accept" onclick="openModal(event)">Aceitar proposta &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="footer-social">
    <span class="footer-social-label">Redes</span>
    <a href="https://www.instagram.com/gasmaskelab/" target="_blank" rel="noopener" class="social-link">Instagram</a>
    <a href="https://gasmaskelab.com.br" target="_blank" rel="noopener" class="social-link">Site Oficial</a>
  </div>
</footer>

<?php if (!$ja_aprovada): ?>
<div class="sticky-cta" id="stickyCta">
    <div class="sticky-info">
        <div class="sticky-val">R$ <?= $valor_fmt ?> <span><?= $tipo_cobranca === 'unico' ? '(Único)' : '/mês' ?></span></div>
        <div class="sticky-sub">Valide a proposta para emitirmos o contrato.</div>
    </div>
    <button class="btn-sticky" onclick="openModal(event)">VALIDAR ESTRATÉGIA &rarr;</button>
</div>

<div class="modal-overlay" id="modalAceite">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal()">&times;</button>
    <h3>Dar o OK no Projeto</h3>
    <p>Ao confirmar, você valida o plano para a <strong><?= htmlspecialchars($proposta['cliente_nome']) ?></strong>. Este OK autoriza nossa equipe a preparar o Contrato Oficial de Prestação de Serviços.</p>
    
    <label class="check-container">
      <input type="checkbox" id="termosCheck">
      ESTOU DE ACORDO COM O ESCOPO E AUTORIZO O CONTRATO.
    </label>

    <button class="btn-confirm" id="btnConfirmar" onclick="confirmarAceite()">CONFIRMAR ESTRATÉGIA</button>
  </div>
</div>
<?php endif; ?>

    <script>
        function openModal(e) {
            if(e) e.preventDefault();
            document.getElementById('modalAceite').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('modalAceite').classList.remove('active');
        }

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
                    closeModal();
                    const sticky = document.getElementById('stickyCta');
                    if(sticky) sticky.style.display = 'none';
                    
                    // Oculta o link no rodapé
                    const acceptLink = document.querySelector('.footer-contact .accept');
                    if(acceptLink) acceptLink.style.display = 'none';

                    const successBlock = document.getElementById('successBlock');
                    successBlock.style.display = 'block';
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