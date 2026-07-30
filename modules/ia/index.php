<?php
// modules/ia/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Busca os clientes ativos no banco para popular o select
$stmt = $pdo->query("SELECT id, nome, briefing_ia FROM clientes WHERE status = 'ativo' ORDER BY nome ASC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cria um array JSON para o Javascript conseguir puxar o briefing do cliente na hora
$briefings_json = [];
foreach($clientes as $c) {
    $briefings_json[$c['id']] = $c['briefing_ia'] ?? 'Nenhum contexto de IA cadastrado para este cliente.';
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Gasmaske IA</h2>
        <p class="page-subtitle">Use IA para gerar carrosséis, posts e criativos diretamente do briefing.</p>
    </div>
</div>

<div class="layout-carrossel">

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Configuração</h3>
        </div>

        <div class="form-group">
            <label><i class="ph ph-user"></i> Cliente</label>
            <select class="form-control" id="selectCliente" onchange="atualizarContexto()">
                <option value="">Selecione um cliente...</option>
                <?php foreach($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Nova área visual para o operador conferir as regras do cliente (Readonly) -->
        <div class="form-group" id="boxContexto" style="display: none;">
            <label><i class="ph ph-robot"></i> Cérebro do Cliente (Regras de IA)</label>
            <textarea class="form-control" id="txtContextoVisual" readonly style="background: #f9f9f9; font-size: 13px; color: var(--text-secondary); height: 80px;"></textarea>
        </div>

        <div class="form-group">
            <label><i class="ph ph-note"></i> Assunto do carrossel (O que vamos criar hoje?)</label>
            <textarea class="form-control" id="txtAssunto" rows="4" placeholder="Ex: promoção de corte + barba no combo de aniversário. Tom descontraído."></textarea>
            <span class="form-hint">Digite apenas a pauta do dia. As regras da marca já serão aplicadas automaticamente.</span>
        </div>

        <div class="form-group">
            <label><i class="ph ph-crop"></i> Formato</label>
            <div class="grid-servicos-proposta">
                <input type="radio" name="formato" id="fmt1" value="1080x1350" class="card-checkbox-input" checked>
                <label for="fmt1" class="card-checkbox-label">Instagram Feed<br><small>1080×1350</small></label>

                <input type="radio" name="formato" id="fmt2" value="1080x1080" class="card-checkbox-input">
                <label for="fmt2" class="card-checkbox-label">Instagram Quadrado<br><small>1080×1080</small></label>

                <input type="radio" name="formato" id="fmt3" value="1080x1920" class="card-checkbox-input">
                <label for="fmt3" class="card-checkbox-label">TikTok / Reels<br><small>1080×1920</small></label>
            </div>
        </div>

        <div class="form-group">
            <label><i class="ph ph-images"></i> Quantidade de imagens</label>
            <div class="qtd-stepper">
                <button type="button" class="btn btn-secondary btn-sm btn-icon-table" onclick="mudarQtd(-1)">−</button>
                <span class="qtd-valor" id="qtdValor">5</span>
                <button type="button" class="btn btn-secondary btn-sm btn-icon-table" onclick="mudarQtd(1)">+</button>
            </div>
        </div>

        <div class="form-group">
            <label><i class="ph ph-sparkle"></i> Modelo padrão de geração</label>
            <div class="grid-servicos-proposta">
                <input type="radio" name="modelo" id="mod1" value="nano_banana_2" class="card-checkbox-input" checked>
                <label for="mod1" class="card-checkbox-label">Nano Banana 2<br><small>Rápido e barato</small></label>

                <input type="radio" name="modelo" id="mod2" value="nano_banana_pro" class="card-checkbox-input">
                <label for="mod2" class="card-checkbox-label">Nano Banana Pro<br><small>Texto mais nítido</small></label>
            </div>
        </div>

        <button type="button" id="btnGerar" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">
            <i class="ph ph-magic-wand"></i> Gerar Carrossel
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Resultado</h3>
            <span class="badge badge-gray" id="badgeFormato">Aguardando geração</span>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 18px; gap: 12px;">
            <div class="metric-card">
                <div class="metric-value" id="statImagens">-</div>
                <div class="metric-label">Imagens</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" id="statCusto">R$ 0,00</div>
                <div class="metric-label">Custo estimado</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" id="statModelo">-</div>
                <div class="metric-label">Modelo padrão</div>
            </div>
        </div>

        <div class="grid-carrossel" id="gridCarrossel">
            <div class="empty-state empty-state-padded" style="grid-column: 1 / -1;">
                <i class="ph ph-magic-wand empty-state-icon"></i>
                Configure os dados ao lado e clique em Gerar para iniciar.
            </div>
        </div>

        <div class="form-actions-bar">
            <button type="button" class="btn btn-ghost" onclick="alert('Função de baixar zip em breve')"><i class="ph ph-download-simple"></i> Baixar todas</button>
            <button type="button" class="btn btn-primary" onclick="alert('Aprovado!')"><i class="ph ph-check"></i> Aprovar Carrossel</button>
        </div>
    </div>

</div>

<script>
// --- LÓGICA DE INTERFACE ---
const dadosBriefing = <?= json_encode($briefings_json) ?>;

function atualizarContexto() {
    const clienteId = document.getElementById('selectCliente').value;
    const box = document.getElementById('boxContexto');
    const txt = document.getElementById('txtContextoVisual');
    
    if (clienteId && dadosBriefing[clienteId]) {
        txt.value = dadosBriefing[clienteId];
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
        txt.value = '';
    }
}

function mudarQtd(valor) {
    const span = document.getElementById('qtdValor');
    let atual = parseInt(span.innerText);
    let novo = atual + valor;
    if (novo >= 1 && novo <= 10) {
        span.innerText = novo;
    }
}


// --- LÓGICA DE COMUNICAÇÃO (POLLING E FILA) ---
let pollingInterval = null;
let carrosselAtualId = null;

document.getElementById('btnGerar').addEventListener('click', async function() {
    const btn = this;
    const cliente_id = document.getElementById('selectCliente').value;
    const assunto = document.getElementById('txtAssunto').value.trim();
    
    const formato = document.querySelector('input[name="formato"]:checked').value; 
    const modelo = document.querySelector('input[name="modelo"]:checked').value;
    const quantidade = document.getElementById('qtdValor').innerText;

    if (!cliente_id || assunto === '') {
        alert("Selecione o cliente e digite o assunto do carrossel!");
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Colocando na fila...';

    // Atualiza badges visuais preventivamente
    document.getElementById('badgeFormato').innerText = formato;
    document.getElementById('statImagens').innerText = quantidade;
    document.getElementById('statModelo').innerText = modelo === 'nano_banana_pro' ? 'PRO' : 'NB2';
    document.getElementById('gridCarrossel').innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #666;"><i class="ph ph-circle-notch ph-spin" style="font-size: 32px;"></i><br>Montando estrutura...</div>';

    const formData = new FormData();
    formData.append('cliente_id', cliente_id);
    formData.append('assunto', assunto);
    formData.append('formato', formato);
    formData.append('quantidade', quantidade);
    formData.append('modelo', modelo);

    try {
        const res = await fetch('ajax_criar_fila.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.sucesso) {
            carrosselAtualId = data.carrossel_id;
            iniciarPolling();
        } else {
            alert("Erro: " + data.erro);
            document.getElementById('gridCarrossel').innerHTML = '<div class="alert alert-danger">Falha ao iniciar.</div>';
        }
    } catch (e) {
        alert("Erro de conexão.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph ph-magic-wand"></i> Gerar Carrossel';
    }
});

function iniciarPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    checarStatus();
    pollingInterval = setInterval(checarStatus, 3000);
}

async function checarStatus() {
    if (!carrosselAtualId) return;

    try {
        const res = await fetch(`ajax_checar_status.php?id=${carrosselAtualId}`);
        const data = await res.json();

        if (data.sucesso) {
            renderizarCards(data.slides, data.geral);
            
            if (data.geral.status === 'concluido' || data.geral.status === 'erro') {
                clearInterval(pollingInterval);
            }
        }
    } catch (e) {
        console.error("Erro ao checar status:", e);
    }
}

function renderizarCards(slides, geral) {
    const grid = document.getElementById('gridCarrossel');
    grid.innerHTML = ''; 

    document.getElementById('statCusto').innerText = `R$ ${parseFloat(geral.custo_total).toFixed(2).replace('.', ',')}`;

    slides.forEach(slide => {
        let icone, classeStatus, acoes, thumbClass = '';
        
        if (slide.status === 'pronto') {
            classeStatus = 'status-pronto';
            icone = `<img src="${slide.url_imagem}" style="width:100%; height:100%; object-fit:cover; border-radius:4px; z-index: 1;">`;
            acoes = `<button class="btn btn-ghost btn-sm" onclick="alert('Função melhorar em breve')">Melhorar</button>
                     <a href="${slide.url_imagem}" download class="btn btn-secondary btn-sm">Baixar</a>`;
        } else if (slide.status === 'gerando') {
            classeStatus = 'status-gerando';
            icone = `<i class="ph ph-circle-notch ph-spin"></i>`;
            acoes = `<button class="btn btn-ghost btn-sm" disabled>Aguarde</button>`;
        } else if (slide.status === 'erro') {
            classeStatus = 'status-erro';
            thumbClass = 'thumb-erro';
            icone = `<i class="ph ph-warning"></i>`;
            acoes = `<button class="btn btn-ghost btn-sm" onclick="alert('Erro: ${slide.erro_mensagem}')">Ver Erro</button>`;
        } else {
            classeStatus = 'status-gerando'; 
            icone = `<i class="ph ph-hourglass"></i>`;
            acoes = `<button class="btn btn-ghost btn-sm" disabled>Fila</button>`;
        }

        const tagClass = slide.modelo_usado === 'nano_banana_pro' ? 'tag-pro' : '';
        const tagText = slide.modelo_usado === 'nano_banana_pro' ? 'PRO' : 'NB2';

        grid.innerHTML += `
            <div class="slide-card">
                <div class="slide-thumb ${thumbClass}">
                    <span class="slide-status ${classeStatus}">${slide.status.toUpperCase()}</span>
                    <span class="slide-model-tag ${tagClass}">${tagText}</span>
                    ${icone}
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide ${slide.numero_slide}</span>
                    <span class="slide-versao">v${slide.versao_atual}</span>
                </div>
                <div class="slide-acoes">
                    ${acoes}
                </div>
            </div>
        `;
    });
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>