<?php
// modules/crm/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Busca os leads
$stmt = $pdo->query("SELECT * FROM leads ORDER BY data_proximo_contato ASC, criado_em DESC");
$leads = $stmt->fetchAll();

$link_publico_briefing = url('publico/briefing.php');

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<!-- Importa as classes externas sem injetar CSS inline -->
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/clientes.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title">CRM / Oportunidades</h2>
        <p class="page-subtitle">Controle de pré-venda, follow-ups e novos leads.</p>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Lead</a>
</div>

<div class="card">
    <div class="filter-bar-container">
        <div class="filter-col-lg">
            <label class="filter-label">Buscar Lead ou Empresa</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" id="filtroTexto" class="form-control input-pl-40" placeholder="Digite para buscar..." onkeyup="filtrarLeads()">
            </div>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Data de Criação</label>
            <input type="date" id="filtroData" class="form-control" onchange="filtrarLeads()">
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Status</label>
            <select id="filtroStatus" class="form-control" onchange="filtrarLeads()">
                <option value="">Todos</option>
                <option value="contato_inicial">Contato Inicial</option>
                <option value="aguardando_briefing">Aguardando Briefing</option>
                <option value="em_negociacao">Em Negociação</option>
                <option value="ganho">Ganho</option>
                <option value="perdido">Perdido</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Funil de Vendas</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($leads) ?> Registros</span>
    </div>

    <?php if (count($leads) > 0): ?>
        <div class="table-wrapper">
            <table id="tabelaClientes">
                <thead>
                    <tr>
                        <th>Nome / Empresa</th>
                        <th>Contato</th>
                        <th class="text-center">Próximo Passo</th>
                        <th class="text-center">Status / Temperatura</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <?php 
                        $texto_busca = strtolower($lead['nome'] . " " . $lead['empresa']);
                        $hoje = date('Y-m-d');
                        $atrasado = (!empty($lead['data_proximo_contato']) && $lead['data_proximo_contato'] < $hoje) ? 'text-red' : '';
                        $data_pura = date('Y-m-d', strtotime($lead['criado_em']));
                    ?>
                    <tr class="linha-lead" data-busca="<?= htmlspecialchars($texto_busca) ?>" data-status="<?= $lead['status'] ?>" data-data="<?= $data_pura ?>">
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($lead['nome']) ?></span>
                            <span class="txt-meta-sm"><?= htmlspecialchars($lead['empresa'] ?? '—') ?></span>
                        </td>
                        <td>
                            <span class="txt-contact-main"><?= htmlspecialchars($lead['telefone']) ?></span>
                            <span class="txt-contact-sub"><?= htmlspecialchars($lead['email']) ?></span>
                        </td>
                        <td class="text-center">
                            <?php if ($lead['data_proximo_contato']): ?>
                                <span class="txt-name-main <?= $atrasado ?>"><i class="ph ph-calendar"></i> <?= dataBR($lead['data_proximo_contato']) ?></span>
                            <?php else: ?>
                                <span class="txt-meta-sm">Não agendado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                                $badge_status = 'badge-gray';
                                if ($lead['status'] == 'contato_inicial') $badge_status = 'badge-blue';
                                if ($lead['status'] == 'aguardando_briefing') $badge_status = 'badge-yellow';
                                if ($lead['status'] == 'em_negociacao') $badge_status = 'badge-purple';
                                if ($lead['status'] == 'ganho') $badge_status = 'badge-green';
                                if ($lead['status'] == 'perdido') $badge_status = 'badge-red';

                                $badge_temp = 'badge-gray';
                                if ($lead['temperatura'] == 'frio') $badge_temp = 'badge-blue';
                                if ($lead['temperatura'] == 'morno') $badge_temp = 'badge-yellow';
                                if ($lead['temperatura'] == 'quente') $badge_temp = 'badge-red';
                            ?>
                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">
                                <span class="badge <?= $badge_status ?>"><?= str_replace('_', ' ', $lead['status']) ?></span>
                                <span class="badge <?= $badge_temp ?>"><i class="ph-fill ph-thermometer"></i> <?= $lead['temperatura'] ?></span>
                            </div>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                
                                <a href="form.php?id=<?= $lead['id'] ?>" class="btn btn-secondary btn-icon-table" title="Editar Lead">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                
                                <button type="button" class="btn btn-ghost btn-icon-table btn-icon-wpp" onclick="pedirBriefing('<?= addslashes($lead['nome']) ?>', '<?= $link_publico_briefing ?>?lead_id=<?= $lead['id'] ?>', this)" title="Pedir Briefing via WhatsApp">
                                    <i class="ph ph-whatsapp-logo"></i>
                                </button>

                                <?php if ($lead['status'] == 'ganho'): ?>
                                    <a href="../clientes/form.php?lead_id=<?= $lead['id'] ?>" class="btn btn-ghost btn-icon-table btn-icon-green" title="Efetivar como Cliente">
                                        <i class="ph ph-check-circle"></i>
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
                Nenhum lead encontrado para estes filtros.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-users empty-state-icon"></i>
            Nenhum lead cadastrado ainda.
        </div>
    <?php endif; ?>
</div>

<script>
function pedirBriefing(nome, link, btn) {
    const primeiroNome = nome.split(' ')[0];
    const msg = `Fala, ${primeiroNome}! Tudo bem?\n\nPara eu desenhar uma estratégia comercial perfeita e montar sua proposta, preciso que você preencha rapidamente nosso briefing aqui:\n\n🔗 ${link}`;
    
    navigator.clipboard.writeText(msg).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="ph-fill ph-check-circle"></i>';
        btn.style.background = 'rgba(37, 211, 102, 0.15)';
        
        setTimeout(() => { 
            btn.innerHTML = originalHTML; 
            btn.style.background = 'rgba(37, 211, 102, 0.05)';
        }, 2000);
    });
}

function filtrarLeads() {
    const texto = document.getElementById('filtroTexto').value.toLowerCase();
    const statusFiltro = document.getElementById('filtroStatus').value;
    const dataFiltro = document.getElementById('filtroData').value;
    
    const linhas = document.querySelectorAll('.linha-lead');
    let visiveis = 0;

    linhas.forEach(linha => {
        const busca = linha.getAttribute('data-busca');
        const status = linha.getAttribute('data-status');
        const data = linha.getAttribute('data-data');
        
        let mostra = true;

        if (texto !== '' && !busca.includes(texto)) mostra = false;
        if (statusFiltro !== '' && status !== statusFiltro) mostra = false;
        if (dataFiltro !== '' && data !== dataFiltro) mostra = false;

        if (mostra) {
            linha.style.display = '';
            visiveis++;
        } else {
            linha.style.display = 'none';
        }
    });

    document.getElementById('contadorRegistros').innerText = visiveis + ' Registros';
    
    const tabela = document.getElementById('tabelaClientes');
    const msgSem = document.getElementById('msgSemResultados');
    
    if (msgSem) {
        msgSem.style.display = visiveis === 0 ? 'block' : 'none';
        tabela.style.display = visiveis === 0 ? 'none' : 'table';
    }
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroStatus').value = '';
    document.getElementById('filtroData').value = '';
    filtrarLeads();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>