<?php
// modules/propostas/index.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

// Busca propostas
$stmt = $pdo->query("SELECT p.*, c.nome as cliente_nome FROM propostas p JOIN clientes c ON p.cliente_id = c.id ORDER BY p.criado_em DESC");
$propostas = $stmt->fetchAll();

$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$dominio = $_SERVER['HTTP_HOST'];
$url_base_publica = $protocolo . "://" . $dominio . "/gasmaske/publico/proposta.php?token=";

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Propostas Comerciais</h2>
        <p class="page-subtitle">Acompanhe seus orçamentos e o status de fechamento.</p>
    </div>
    <a href="form.php" class="btn btn-primary"><i class="ph ph-plus"></i> Nova Proposta</a>
</div>

<div class="card">
    
    <div class="filter-bar-container">
        <div class="filter-col-lg">
            <label class="filter-label">Buscar Cliente ou Título</label>
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
            <label class="filter-label">Status</label>
            <select id="filtroStatus" class="form-control" onchange="filtrarTabelaAoVivo()">
                <option value="">Todos</option>
                <option value="rascunho">Rascunho</option>
                <option value="enviada">Enviada</option>
                <option value="aceita">Validada</option>
                <option value="recusada">Recusada</option>
                <option value="expirada">Expirada</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-ghost btn-h44" onclick="limparFiltros()" title="Limpar Filtros">
                <i class="ph ph-x-circle"></i> Limpar
            </button>
        </div>
    </div>

    <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
        <h3 class="card-title">Histórico de Propostas</h3>
        <span class="badge badge-gray" id="contadorRegistros"><?= count($propostas) ?> Registros</span>
    </div>

    <?php if (count($propostas) > 0): ?>
        <div class="table-wrapper">
            <table id="tabelaPropostas">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cliente / Título</th>
                        <th>Valor Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($propostas as $p): ?>
                    <?php 
                        $total = ($p['tipo_cobranca'] == 'mensal') ? ($p['valor'] * $p['duracao_meses']) : $p['valor'];
                        $link_completo = $url_base_publica . $p['token'];
                        $data_pura = date('Y-m-d', strtotime($p['criado_em']));
                        $texto_busca = strtolower($p['cliente_nome'] . " " . $p['titulo'] . " " . $p['codigo_proposta']);
                    ?>
                    <tr class="linha-dado" data-busca="<?= htmlspecialchars($texto_busca) ?>" data-data="<?= $data_pura ?>" data-status="<?= $p['status'] ?>">
                        <td><span class="txt-date-sm"><?= dataBR($p['criado_em']) ?></span></td>
                        <td>
                            <span class="txt-name-main"><?= htmlspecialchars($p['cliente_nome']) ?></span>
                            <span class="txt-meta-sm"><?= htmlspecialchars($p['titulo']) ?></span>
                        </td>
                        <td>
                            <span class="txt-name-main"><?= money($total) ?></span>
                            <span class="txt-meta-sm"><?= $p['tipo_cobranca'] == 'mensal' ? $p['duracao_meses'] . 'x de ' . money($p['valor']) : 'Valor Único' ?></span>
                        </td>
                        <td class="text-center">
                            <?php 
                                $badge = 'badge-gray';
                                if ($p['status'] == 'enviada') $badge = 'badge-blue';
                                if ($p['status'] == 'aceita') $badge = 'badge-green';
                                if ($p['status'] == 'recusada') $badge = 'badge-red';
                                if ($p['status'] == 'expirada') $badge = 'badge-yellow';
                            ?>
                            <span class="badge <?= $badge ?>"><?= $p['status'] ?></span>
                        </td>
                        <td class="text-center">
                            <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                
                                <?php if ($p['status'] == 'aceita' && !empty($p['contrato_id'])): ?>
                                    <a href="../contratos/detalhes.php?id=<?= $p['contrato_id'] ?>" 
                                       class="btn btn-ghost btn--sm" 
                                       style="color: var(--purple); border-color: rgba(168, 85, 247, 0.2); background: rgba(168, 85, 247, 0.05); padding: 6px 10px;" 
                                       title="Ver Contrato Gerado">
                                        <i class="ph ph-scroll" style="font-size: 18px;"></i>
                                    </a>
                                <?php endif; ?>

                                <a href="form.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn--sm" style="padding: 6px 10px;" title="Editar Proposta">
                                    <i class="ph ph-pencil-simple" style="font-size: 18px;"></i>
                                </a>
                                
                                <?php if($p['status'] == 'enviada' || $p['status'] == 'rascunho'): ?>
                                    <button type="button" class="btn btn-ghost btn--sm" onclick="copiarMensagemWpp('<?= addslashes($p['cliente_nome']) ?>', '<?= addslashes($p['titulo']) ?>', '<?= $link_completo ?>', this)" style="color: #25D366; border-color: rgba(37, 211, 102, 0.2); background: rgba(37, 211, 102, 0.05); padding: 6px 10px;" title="Copiar mensagem para WhatsApp">
                                        <i class="ph ph-whatsapp-logo" style="font-size: 18px;"></i>
                                    </button>
                                <?php endif; ?>

                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div id="msgSemResultados" class="empty-state empty-state-padded" style="display: none;">
                <i class="ph ph-magnifying-glass empty-state-icon"></i>
                Nenhuma proposta encontrada para estes filtros.
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state empty-state-padded">
            <i class="ph ph-file-text empty-state-icon"></i>
            Nenhuma proposta criada ainda.
        </div>
    <?php endif; ?>
</div>

<script>
// --- MOTOR DE CÓPIA PARA WHATSAPP ---
function copiarMensagemWpp(nome, titulo, link, btn) {
    const msg = `Olá, ${nome.split(' ')[0]}! Tudo bem?\n\nA proposta para *${titulo}* está pronta: \n\n🔗 ${link}`;
    navigator.clipboard.writeText(msg).then(() => {
        const originalHTML = btn.innerHTML;
        // Muda para o ícone de sucesso mantendo o formato do botão quadrado
        btn.innerHTML = '<i class="ph-fill ph-check-circle" style="font-size: 18px;"></i>';
        btn.style.background = 'rgba(37, 211, 102, 0.2)';
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.background = 'rgba(37, 211, 102, 0.05)';
        }, 2000);
    });
}

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
    document.getElementById('tabelaPropostas').style.display = visiveis === 0 ? 'none' : 'table';
}

function limparFiltros() {
    document.getElementById('filtroTexto').value = '';
    document.getElementById('filtroData').value = '';
    document.getElementById('filtroStatus').value = '';
    filtrarTabelaAoVivo();
}
</script>

<?php require_once '../../includes/layout/footer.php'; ?>