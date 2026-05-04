<?php
// modules/briefing/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) {
    die("<div class='empty-state'><h2>Acesso negado</h2><p>Apenas administradores podem acessar.</p></div>");
}

$stmt = $pdo->query("SELECT * FROM briefings ORDER BY criado_em DESC");
$briefings = $stmt->fetchAll();

// Monta a base da URL para o link do WhatsApp
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$link_publico_briefing = $protocolo . "://" . $dominio . "/gasmaske/publico/briefing.php";

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>


<div class="cabecalho">
    <div>
        <h2 class="page-title">Caixa de Entrada (Briefings)</h2>
        <p class="page-subtitle">Solicitações de orçamento e novos leads do link público.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="copiarLinkBriefing('<?= $link_publico_briefing ?>', this)">
        <i class="ph ph-whatsapp-logo"></i> Copiar Zap do Briefing
    </button>
</div>

<div class="card">
    
    <div class="filter-bar-container">
        <div class="filter-col-lg">
            <label class="filter-label">Buscar Cliente / Empresa</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" id="filtroTexto" class="form-control input-pl-40" placeholder="Digite para buscar..." onkeyup="filtrarTabelaAoVivo()">
            </div>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Data Específica</label>
            <input type="date" id="filtroData" class="form-control" onchange="filtrarTabelaAoVivo()">
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Status</label>
            <select id="filtroStatus" class="form-control" onchange="filtrarTabelaAoVivo()">
                <option value="">Todos</option>
                <option value="novo">Novo</option>
                <option value="proposta_criada">Proposta Criada</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Briefings Recebidos</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($briefings) ?> Registros</span>
    </div>

    <?php if (count($briefings) > 0): ?>
        <div class="table-wrapper">
            <table id="tabelaBriefings">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Nome / Empresa</th>
                        <th>Contato</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($briefings as $b): ?>
                    <?php 
                        $data_pura = date('Y-m-d', strtotime($b['criado_em']));
                        $texto_busca = strtolower($b['nome'] . " " . $b['empresa'] . " " . $b['email']);
                    ?>
                    <tr class="linha-dado" data-busca="<?= htmlspecialchars($texto_busca) ?>" data-data="<?= $data_pura ?>" data-status="<?= $b['status'] ?>">
                        <td><span class="txt-date-sm"><?= dataBR($b['criado_em']) ?></span></td>
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($b['nome']) ?></span>
                            <?php if($b['empresa']): ?>
                                <span class="txt-meta-sm"><?= htmlspecialchars($b['empresa']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="txt-contact-main"><?= htmlspecialchars($b['email']) ?></span>
                            <span class="txt-contact-sub"><?= htmlspecialchars($b['telefone']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if($b['status'] == 'novo'): ?>
                                <span class="badge badge-blue">Novo</span>
                            <?php else: ?>
                                <span class="badge badge-green">Proposta Criada</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="ver.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn--sm">Ver Detalhes</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div id="msgSemResultados" class="empty-state empty-state-padded" style="display: none;">
                <i class="ph ph-magnifying-glass empty-state-icon"></i>
                Nenhum briefing encontrado para estes filtros.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-tray empty-state-icon"></i>
            Nenhum briefing recebido ainda.
        </div>
    <?php endif; ?>
</div>

<script>
function copiarLinkBriefing(link, btn) {
    const msg = `Olá! Tudo bem?\n\nPara podermos desenhar uma proposta sob medida e alinhar perfeitamente o escopo do seu projeto, peço que preencha rapidamente o nosso Briefing Comercial no link abaixo:\n\n🔗 ${link}\n\nLeva menos de 3 minutinhos e nos ajuda a sermos muito mais assertivos. Qualquer dúvida, estou por aqui!`;

    navigator.clipboard.writeText(msg).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ph-fill ph-check-circle"></i> Copiado!';
        btn.classList.add('btn-wpp-green');
        setTimeout(() => {
            btn.innerHTML = original;
        }, 2000);
    });
}

function filtrarTabelaAoVivo() {
    const filtroTexto = document.getElementById('filtroTexto').value.toLowerCase();
    const filtroData = document.getElementById('filtroData').value;
    const filtroStatus = document.getElementById('filtroStatus').value;
    
    const linhas = document.querySelectorAll('.linha-dado');
    let visiveis = 0;

    linhas.forEach(linha => {
        const texto = linha.getAttribute('data-busca');
        const data = linha.getAttribute('data-data');
        const status = linha.getAttribute('data-status');
        
        let mostra = true;

        if (filtroTexto !== '' && !texto.includes(filtroTexto)) mostra = false;
        if (filtroData !== '' && data !== filtroData) mostra = false;
        if (filtroStatus !== '' && status !== filtroStatus) mostra = false;

        if (mostra) {
            linha.style.display = '';
            visiveis++;
        } else {
            linha.style.display = 'none';
        }
    });

    document.getElementById('contadorRegistros').innerText = visiveis + ' Registros';
    document.getElementById('msgSemResultados').style.display = visiveis === 0 ? 'block' : 'none';
    document.getElementById('tabelaBriefings').style.display = visiveis === 0 ? 'none' : 'table';
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroData').value = '';
    document.getElementById('filtroStatus').value = '';
    filtrarTabelaAoVivo();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>