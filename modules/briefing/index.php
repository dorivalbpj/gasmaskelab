<?php
// modules/briefing/index.php

// ============================================
// INCLUSÃO DAS CONFIGURAÇÕES
// ============================================
require_once '../../config/session.php';
require_once '../../includes/functions.php';

// ============================================
// VERIFICAÇÃO DE PERMISSÃO
// ============================================
requireLogin();
if (!isAdmin()) {
    die("<div class='empty-state'><h2>Acesso negado</h2><p>Apenas administradores podem acessar.</p></div>");
}

// ============================================
// BUSCA DOS BRIEFINGS
// ============================================
try {
    $stmt = $pdo->query("SELECT * FROM briefings ORDER BY criado_em DESC");
    $briefings = $stmt->fetchAll();
} catch (PDOException $e) {
    $briefings = [];
    // Em desenvolvimento, mostra o erro
    if (APP_DEBUG) {
        echo "Erro ao buscar briefings: " . $e->getMessage();
    }
}

// ============================================
// CONFIGURAÇÃO DO LINK DO BRIEFING
// ============================================
// Usando a função helper url() para gerar o link correto
$link_publico_briefing = url('publico/briefing.php');

// ============================================
// INCLUSÃO DO HEADER E SIDEBAR
// ============================================
require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- ============================================ -->
<!-- DEBUG (apenas em desenvolvimento) -->
<!-- ============================================ -->
<?php if (APP_DEBUG): ?>
<!-- 
    Ambiente: <?= AMBIENTE_ATUAL ?> 
    BASE_URL: <?= BASE_URL ?> 
    Link Briefing: <?= $link_publico_briefing ?>
-->
<?php endif; ?>

<!-- ============================================ -->
<!-- CABEÇALHO DA PÁGINA -->
<!-- ============================================ -->
<div class="cabecalho">
    <div>
        <h2 class="page-title">Caixa de Entrada (Briefings)</h2>
        <p class="page-subtitle">Solicitações de orçamento e novos leads do link público.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="copiarLinkBriefing('<?= $link_publico_briefing ?>', this)">
        <i class="ph ph-whatsapp-logo"></i> Copiar Zap do Briefing
    </button>
</div>

<!-- ============================================ -->
<!-- CARD PRINCIPAL -->
<!-- ============================================ -->
<div class="card">
    
    <!-- ========================================== -->
    <!-- BARRA DE FILTROS -->
    <!-- ========================================== -->
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
                <option value="proposta_aceita">Proposta Aceita</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TÍTULO DA TABELA -->
    <!-- ========================================== -->
    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Briefings Recebidos</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($briefings) ?> Registros</span>
    </div>

    <!-- ========================================== -->
    <!-- TABELA DE BRIEFINGS -->
    <!-- ========================================== -->
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
                            <?php elseif($b['status'] == 'proposta_criada'): ?>
                                <span class="badge badge-green">Proposta Criada</span>
                            <?php elseif($b['status'] == 'proposta_aceita'): ?>
                                <span class="badge badge-purple">Proposta Aceita</span>
                            <?php else: ?>
                                <span class="badge badge-gray"><?= htmlspecialchars($b['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <a href="ver.php?id=<?= $b['id'] ?>" class="btn btn-secondary btn--sm btn-icon-table" title="Ver Detalhes">
                                    <i class="ph ph-eye"></i>
                                </a>
                                
                                <?php if($b['status'] == 'novo'): ?>
                                    <form method="POST" action="ver.php?id=<?= $b['id'] ?>" style="margin: 0;" onsubmit="return confirm('Deseja gerar uma proposta automática para este briefing?');">
                                        <input type="hidden" name="acao" value="gerar_proposta">
                                        <button type="submit" class="btn btn-ghost btn--sm btn-icon-table btn-icon-purple" title="Gerar Proposta Automática">
                                            <i class="ph ph-magic-wand"></i>
                                        </button>
                                    </form>
                                <?php elseif($b['status'] == 'proposta_aceita'): ?>
                                    <a href="../propostas/index.php" class="btn btn-ghost btn--sm btn-icon-table btn-icon-green" title="Ir para Propostas">
                                        <i class="ph ph-folder-open"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
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

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
function copiarLinkBriefing(link, btn) {
    const msg = `Olá! Tudo bem?\n\nPara podermos desenhar uma proposta sob medida e alinhar perfeitamente o escopo do seu projeto, peço que preencha rapidamente o nosso Briefing Comercial no link abaixo:\n\n🔗 ${link}\n\nLeva menos de 3 minutinhos e nos ajuda a sermos muito mais assertivos. Qualquer dúvida, estou por aqui!`;

    navigator.clipboard.writeText(msg).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="ph-fill ph-check-circle"></i> Copiado!';
        btn.classList.add('btn-wpp-green');
        setTimeout(() => {
            btn.innerHTML = original;
            btn.classList.remove('btn-wpp-green');
        }, 2000);
    }).catch(() => {
        // Fallback para navegadores que não suportam clipboard API
        alert('Copie o link manualmente:\n\n' + link);
    });
}

function filtrarTabelaAoVivo() {
    const filtroTexto = document.getElementById('filtroTexto').value.toLowerCase().trim();
    const filtroData = document.getElementById('filtroData').value;
    const filtroStatus = document.getElementById('filtroStatus').value;
    
    const linhas = document.querySelectorAll('.linha-dado');
    let visiveis = 0;

    linhas.forEach(linha => {
        const texto = linha.getAttribute('data-busca') || '';
        const data = linha.getAttribute('data-data') || '';
        const status = linha.getAttribute('data-status') || '';
        
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

    const contador = document.getElementById('contadorRegistros');
    const msgSemResultados = document.getElementById('msgSemResultados');
    const tabela = document.getElementById('tabelaBriefings');

    if (contador) {
        contador.innerText = visiveis + ' Registros';
    }
    
    if (msgSemResultados) {
        msgSemResultados.style.display = visiveis === 0 ? 'block' : 'none';
    }
    
    if (tabela) {
        tabela.style.display = visiveis === 0 ? 'none' : 'table';
    }
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroData').value = '';
    document.getElementById('filtroStatus').value = '';
    filtrarTabelaAoVivo();
}

// Executa o filtro ao carregar a página (garante consistência)
document.addEventListener('DOMContentLoaded', function() {
    filtrarTabelaAoVivo();
});
</script>

<?php require_once '../../includes/layout/footer.php'; ?>