<?php
// modules/ia/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

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
            <select class="form-control">
                <option>João — Barbearia Vintage</option>
                <option>Ana — Studio Pilates</option>
                <option>Marcos — Pet Shop Au</option>
            </select>
        </div>

        <div class="form-group">
            <label><i class="ph ph-note"></i> Assunto do briefing</label>
            <textarea class="form-control" readonly>Tema: promoção de corte + barba no combo de aniversário.
Tom: descontraído, direto, com senso de urgência.
Cores da marca: preto, dourado, bege.</textarea>
            <span class="form-hint">Extraído automaticamente do briefing do cliente.</span>
        </div>

        <div class="form-group">
            <label><i class="ph ph-crop"></i> Formato</label>
            <div class="grid-servicos-proposta">
                <input type="radio" name="formato" id="fmt1" class="card-checkbox-input" checked>
                <label for="fmt1" class="card-checkbox-label">Instagram Feed<br><small>1080×1350</small></label>

                <input type="radio" name="formato" id="fmt2" class="card-checkbox-input">
                <label for="fmt2" class="card-checkbox-label">Instagram Quadrado<br><small>1080×1080</small></label>

                <input type="radio" name="formato" id="fmt3" class="card-checkbox-input">
                <label for="fmt3" class="card-checkbox-label">TikTok / Reels<br><small>1080×1920</small></label>
            </div>
        </div>

        <div class="form-group">
            <label><i class="ph ph-images"></i> Quantidade de imagens</label>
            <div class="qtd-stepper">
                <button type="button" class="btn btn-secondary btn-sm btn-icon-table">−</button>
                <span class="qtd-valor">5</span>
                <button type="button" class="btn btn-secondary btn-sm btn-icon-table">+</button>
            </div>
        </div>

        <div class="form-group">
            <label><i class="ph ph-sparkle"></i> Modelo padrão de geração</label>
            <div class="grid-servicos-proposta">
                <input type="radio" name="modelo" id="mod1" class="card-checkbox-input" checked>
                <label for="mod1" class="card-checkbox-label">Nano Banana 2<br><small>Rápido e barato</small></label>

                <input type="radio" name="modelo" id="mod2" class="card-checkbox-input">
                <label for="mod2" class="card-checkbox-label">Nano Banana Pro<br><small>Texto mais nítido</small></label>
            </div>
        </div>

        <button type="button" class="btn btn-primary" style="width: 100%; justify-content: center; height: 45px;">
            <i class="ph ph-magic-wand"></i> Gerar Carrossel
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Resultado</h3>
            <span class="badge badge-gray">Instagram Feed · 1080×1350</span>
        </div>

        <div class="alert alert-success">
            <i class="ph-fill ph-check-circle"></i> 4 de 5 imagens prontas · 1 com erro
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 18px; gap: 12px;">
            <div class="metric-card">
                <div class="metric-value">5</div>
                <div class="metric-label">Imagens</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">R$ 0,23</div>
                <div class="metric-label">Custo estimado</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">NB2</div>
                <div class="metric-label">Modelo padrão</div>
            </div>
        </div>

        <div class="grid-carrossel">
            <div class="slide-card">
                <div class="slide-thumb">
                    <span class="slide-status status-pronto">Pronto</span>
                    <span class="slide-model-tag">NB2</span>
                    <i class="ph ph-image"></i>
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide 1</span>
                    <span class="slide-versao">v1</span>
                </div>
                <div class="slide-acoes">
                    <button class="btn btn-ghost btn-sm">Melhorar</button>
                    <button class="btn btn-secondary btn-sm">Baixar</button>
                </div>
            </div>

            <div class="slide-card">
                <div class="slide-thumb">
                    <span class="slide-status status-pronto">Pronto</span>
                    <span class="slide-model-tag">NB2</span>
                    <i class="ph ph-image"></i>
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide 2</span>
                    <span class="slide-versao">v1</span>
                </div>
                <div class="slide-acoes">
                    <button class="btn btn-ghost btn-sm">Melhorar</button>
                    <button class="btn btn-secondary btn-sm">Baixar</button>
                </div>
            </div>

            <div class="slide-card">
                <div class="slide-thumb">
                    <span class="slide-status status-pronto">Pronto</span>
                    <span class="slide-model-tag tag-pro">PRO</span>
                    <i class="ph ph-image"></i>
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide 3</span>
                    <span class="slide-versao">v2</span>
                </div>
                <div class="slide-acoes">
                    <button class="btn btn-ghost btn-sm">Gerar de novo</button>
                    <button class="btn btn-secondary btn-sm">Baixar</button>
                </div>
            </div>

            <div class="slide-card">
                <div class="slide-thumb">
                    <span class="slide-status status-gerando">Gerando</span>
                    <span class="slide-model-tag">NB2</span>
                    <i class="ph ph-circle-notch"></i>
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide 4</span>
                    <span class="slide-versao">—</span>
                </div>
                <div class="slide-acoes">
                    <button class="btn btn-ghost btn-sm" disabled>Aguarde</button>
                </div>
            </div>

            <div class="slide-card">
                <div class="slide-thumb thumb-erro">
                    <span class="slide-status status-erro">Erro</span>
                    <span class="slide-model-tag">NB2</span>
                    <i class="ph ph-warning"></i>
                </div>
                <div class="slide-info">
                    <span class="slide-nome">Slide 5</span>
                    <span class="slide-versao">—</span>
                </div>
                <div class="slide-acoes">
                    <button class="btn btn-ghost btn-sm">Tentar de novo</button>
                </div>
            </div>
        </div>

        <div class="form-actions-bar">
            <button type="button" class="btn btn-ghost"><i class="ph ph-download-simple"></i> Baixar todas</button>
            <button type="button" class="btn btn-primary"><i class="ph ph-check"></i> Aprovar Carrossel</button>
        </div>
    </div>

</div>

<?php require_once '../../includes/layout/footer.php'; ?>
