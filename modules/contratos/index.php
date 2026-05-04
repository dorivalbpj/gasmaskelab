<?php
// modules/contratos/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Busca os contratos com os nomes dos clientes
$stmt = $pdo->query("SELECT c.*, cli.nome as cliente_nome FROM contratos c JOIN clientes cli ON c.cliente_id = cli.id ORDER BY c.criado_em DESC");
$contratos = $stmt->fetchAll();

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Contratos</h2>
        <p class="page-subtitle">Gestão de acordos, assinaturas e status financeiros.</p>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="ph ph-plus"></i> Novo Contrato</a>
</div>

<div class="card">

    <div class="filter-bar-container">
        <div class="filter-col-lg">
            <label class="filter-label">Buscar Código ou Cliente</label>
            <div class="input-icon-wrapper">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" id="filtroTexto" class="form-control input-pl-40" placeholder="Digite para buscar..." onkeyup="filtrarTabelaAoVivo()">
            </div>
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Data de Criação</label>
            <input type="date" id="filtroData" class="form-control" onchange="filtrarTabelaAoVivo()">
        </div>
        <div class="filter-col-sm">
            <label class="filter-label">Status Atual</label>
            <select id="filtroStatus" class="form-control" onchange="filtrarTabelaAoVivo()">
                <option value="">Todos</option>
                <option value="rascunho">Rascunho</option>
                <option value="aguardando_aceite_cliente">Aguardando Aceite</option>
                <option value="aguardando_pagamento">Aguardando Pagto</option>
                <option value="em_andamento">Em Andamento</option>
                <option value="finalizado">Finalizado</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Todos os Contratos</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($contratos) ?> Registros</span>
    </div>

    <?php if (count($contratos) > 0): ?>
        <div class="table-wrapper">
            <table id="tabelaContratos">
                <thead>
                    <tr>
                        <th>Data / Código</th>
                        <th>Cliente</th>
                        <th>Duração</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contratos as $c): ?>
                    <?php 
                        // Preparando os dados para o filtro JS
                        $data_pura = date('Y-m-d', strtotime($c['criado_em']));
                        $texto_busca = strtolower($c['codigo_agc'] . " " . $c['cliente_nome']);
                    ?>
                    <tr class="linha-dado" data-busca="<?= htmlspecialchars($texto_busca) ?>" data-data="<?= $data_pura ?>" data-status="<?= $c['status'] ?>">
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($c['codigo_agc']) ?></span>
                            <span class="txt-date-sm"><?= dataBR($c['criado_em']) ?></span>
                        </td>
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($c['cliente_nome']) ?></span>
                        </td>
                        <td>
                            <span class="txt-contact-main"><?= $c['duracao_meses'] ?> meses</span>
                        </td>
                        <td class="text-center">
                            <?php 
                                $badge_class = 'badge-gray';
                                if ($c['status'] == 'aguardando_aceite_cliente') $badge_class = 'badge-yellow';
                                if ($c['status'] == 'aguardando_pagamento') $badge_class = 'badge-blue';
                                if ($c['status'] == 'em_andamento') $badge_class = 'badge-green';
                                if ($c['status'] == 'finalizado') $badge_class = 'badge-purple';
                            ?>
                            <span class="badge <?= $badge_class ?>">
                                <?= str_replace('_', ' ', $c['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                <a href="detalhes.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn--sm" style="padding: 6px 10px;" title="Ver Detalhes e Ações">
                                    <i class="ph ph-eye" style="font-size: 18px;"></i>
                                </a>
                                
                                <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn--sm" style="padding: 6px 10px;" title="Editar Contrato">
                                    <i class="ph ph-pencil-simple" style="font-size: 18px;"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="msgSemResultados" class="empty-state empty-state-padded" style="display: none;">
                <i class="ph ph-magnifying-glass empty-state-icon"></i>
                Nenhum contrato encontrado para estes filtros.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-scroll empty-state-icon"></i>
            Nenhum contrato gerado ainda.
        </div>
    <?php endif; ?>
</div>

<script>
// --- MOTOR DE BUSCA AO VIVO ---
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
    document.getElementById('tabelaContratos').style.display = visiveis === 0 ? 'none' : 'table';
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroData').value = '';
    document.getElementById('filtroStatus').value = '';
    filtrarTabelaAoVivo();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>