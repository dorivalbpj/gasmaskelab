<?php
// modules/financeiro/cartoes.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$mensagem = '';

// --- 1. CRON JOB INVISÍVEL (Fechamento automático de faturas) ---
// Atualiza para 'fechada' toda fatura aberta onde o dia de hoje já ultrapassou o fechamento
$pdo->query("
    UPDATE fin_faturas f
    JOIN fin_cartoes c ON f.cartao_id = c.id
    SET f.status = 'fechada'
    WHERE f.status = 'aberta' 
    AND (
        (f.ano < YEAR(CURRENT_DATE)) OR 
        (f.ano = YEAR(CURRENT_DATE) AND f.mes < MONTH(CURRENT_DATE)) OR
        (f.ano = YEAR(CURRENT_DATE) AND f.mes = MONTH(CURRENT_DATE) AND DAY(CURRENT_DATE) >= c.dia_fechamento)
    )
");

// --- 2. LÓGICA DE CRUD (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        if ($acao == 'salvar_cartao') {
            $id = $_POST['cartao_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $bandeira = $_POST['bandeira'] ?? '';
            $limite = str_replace(',', '.', $_POST['limite'] ?? 0);
            $fechamento = $_POST['dia_fechamento'] ?? 1;
            $vencimento = $_POST['dia_vencimento'] ?? 10;
            
            if ($id) {
                $pdo->prepare("UPDATE fin_cartoes SET nome=?, bandeira=?, limite=?, dia_fechamento=?, dia_vencimento=? WHERE id=?")
                    ->execute([$nome, $bandeira, $limite, $fechamento, $vencimento, $id]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cartão atualizado com sucesso!</div>";
            } else {
                $pdo->prepare("INSERT INTO fin_cartoes (nome, bandeira, limite, dia_fechamento, dia_vencimento) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$nome, $bandeira, $limite, $fechamento, $vencimento]);
                $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Novo cartão adicionado!</div>";
            }
        } elseif ($acao == 'excluir_cartao') {
            $id = $_POST['cartao_id'] ?? '';
            // Soft Delete para proteger as faturas e lançamentos passados
            $pdo->prepare("UPDATE fin_cartoes SET ativo = 0 WHERE id=?")->execute([$id]);
            $mensagem = "<div class='alert alert-success'><i class='ph-fill ph-check-circle'></i> Cartão excluído (histórico mantido)!</div>";
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><i class='ph-fill ph-warning-circle'></i> Erro: " . $e->getMessage() . "</div>";
    }
}

// --- 3. CONSULTA DOS CARTÕES ATIVOS ---
// Calcula dinamicamente o limite usado (soma dos lançamentos de faturas abertas/fechadas que ainda não foram pagos)
$stmt = $pdo->query("
    SELECT c.*, 
           COALESCE((
               SELECT SUM(l.valor) 
               FROM fin_lancamentos l 
               JOIN fin_faturas f ON l.fatura_id = f.id 
               WHERE f.cartao_id = c.id AND f.status != 'paga' AND l.status != 'pago'
           ), 0) as limite_usado 
    FROM fin_cartoes c 
    WHERE c.ativo = 1 
    ORDER BY c.nome ASC
");
$cartoes = $stmt->fetchAll();

// --- 4. RESUMO GERAL ---
// Totais de limite/uso somando todos os cartões ativos
$total_limite = 0;
$total_usado = 0;
foreach ($cartoes as $c) {
    $total_limite += (float)$c['limite'];
    $total_usado += (float)$c['limite_usado'];
}
$total_disponivel = $total_limite - $total_usado;

// Quanto cai de fatura esse mês: soma dos lançamentos de faturas cujo vencimento
// (data_vencimento) cai no mês/ano atual, considerando todos os cartões ativos
$stmt_resumo = $pdo->query("
    SELECT 
        COALESCE(SUM(l.valor), 0) as total_mes,
        COUNT(DISTINCT f.id) as qtd_faturas,
        COALESCE(SUM(CASE WHEN f.status = 'paga' THEN l.valor ELSE 0 END), 0) as total_pago,
        COALESCE(SUM(CASE WHEN f.status != 'paga' THEN l.valor ELSE 0 END), 0) as total_pendente
    FROM fin_faturas f
    JOIN fin_cartoes c ON f.cartao_id = c.id
    LEFT JOIN fin_lancamentos l ON l.fatura_id = f.id
    WHERE c.ativo = 1
      AND MONTH(f.data_vencimento) = MONTH(CURRENT_DATE)
      AND YEAR(f.data_vencimento) = YEAR(CURRENT_DATE)
");
$resumo_mes = $stmt_resumo->fetch();

// Mapeia a bandeira para uma classe visual (ícone/cor)
function bandeiraClasse($bandeira) {
    $b = strtolower(trim($bandeira));
    if (strpos($b, 'master') !== false) return 'mastercard';
    if (strpos($b, 'visa') !== false) return 'visa';
    if (strpos($b, 'elo') !== false) return 'elo';
    if (strpos($b, 'amex') !== false || strpos($b, 'american') !== false) return 'amex';
    return 'outra';
}
function bandeiraIcone($classe) {
    $icones = [
        'mastercard' => 'ph-fill ph-circles-three',
        'visa'       => 'ph-fill ph-credit-card',
        'elo'        => 'ph-fill ph-circle',
        'amex'       => 'ph-fill ph-credit-card',
        'outra'      => 'ph ph-credit-card',
    ];
    return $icones[$classe] ?? 'ph ph-credit-card';
}

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<link rel="stylesheet" href="../../assets/css/cartoes.css">

<div class="cabecalho">
    <div>
        <h2 class="page-title">Cartões de Crédito</h2>
        <p class="page-subtitle">Gerencie seus limites, vencimentos e faturas de cartão.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalCartao()"><i class="ph ph-plus"></i> Novo Cartão</button>
</div>

<?= $mensagem ?>

<!-- ============ RESUMO GERAL ============ -->
<div class="resumo-geral-grid">
    <div class="resumo-card accent-blue">
        <div class="resumo-card-top">
            <span class="resumo-label">Limite Total</span>
            <span class="resumo-icon accent-blue"><i class="ph-fill ph-wallet"></i></span>
        </div>
        <div class="resumo-value"><?= money($total_limite) ?></div>
        <div class="resumo-sub"><?= count($cartoes) ?> cartão<?= count($cartoes) != 1 ? 'ões' : '' ?> ativo<?= count($cartoes) != 1 ? 's' : '' ?></div>
    </div>

    <div class="resumo-card accent-yellow">
        <div class="resumo-card-top">
            <span class="resumo-label">Limite Usado</span>
            <span class="resumo-icon accent-yellow"><i class="ph-fill ph-chart-line-up"></i></span>
        </div>
        <div class="resumo-value"><?= money($total_usado) ?></div>
        <div class="resumo-sub"><?= $total_limite > 0 ? number_format(($total_usado / $total_limite) * 100, 0) : 0 ?>% do limite total</div>
    </div>

    <div class="resumo-card accent-green">
        <div class="resumo-card-top">
            <span class="resumo-label">Disponível</span>
            <span class="resumo-icon accent-green"><i class="ph-fill ph-check-circle"></i></span>
        </div>
        <div class="resumo-value"><?= money($total_disponivel) ?></div>
        <div class="resumo-sub">Somando todos os cartões</div>
    </div>

    <div class="resumo-card accent-red">
        <div class="resumo-card-top">
            <span class="resumo-label">A Pagar Este Mês</span>
            <span class="resumo-icon accent-red"><i class="ph-fill ph-calendar-check"></i></span>
        </div>
        <div class="resumo-value"><?= money($resumo_mes['total_mes']) ?></div>
        <div class="resumo-sub">
            <span><?= (int)$resumo_mes['qtd_faturas'] ?> fatura<?= $resumo_mes['qtd_faturas'] != 1 ? 's' : '' ?></span>
            <span>Pago: <strong><?= money($resumo_mes['total_pago']) ?></strong></span>
            <span>Pendente: <strong><?= money($resumo_mes['total_pendente']) ?></strong></span>
        </div>
    </div>
</div>

<?php if (count($cartoes) > 0): ?>
    <div class="cartoes-grid">
        <?php foreach ($cartoes as $c): ?>
            <?php
                $limite = (float)$c['limite'];
                $usado = (float)$c['limite_usado'];
                $disponivel = $limite - $usado;
                
                // Impede barra quebrar se o limite for zero (cartão sem limite definido)
                $pct = ($limite > 0) ? ($usado / $limite) * 100 : 0;
                $pct = min(100, max(0, $pct));
                
                // Cores de atenção na barra de progresso
                $bar_color = ($pct > 85) ? 'var(--red)' : (($pct > 65) ? 'var(--yellow)' : 'var(--blue)');

                $classe_bandeira = bandeiraClasse($c['bandeira']);
                $icone_bandeira = bandeiraIcone($classe_bandeira);
            ?>
            <div class="cartao-card">
                <div class="cartao-card-accent" style="background: <?= $bar_color ?>;"></div>
                <div class="cartao-card-body">
                    <div class="cartao-card-header">
                        <div class="cartao-card-header-left">
                            <div class="bandeira-badge bandeira-<?= $classe_bandeira ?>"><i class="<?= $icone_bandeira ?>"></i></div>
                            <div class="cartao-card-titulos">
                                <div class="cartao-card-nome"><?= htmlspecialchars($c['nome']) ?></div>
                                <div class="cartao-card-bandeira"><?= htmlspecialchars($c['bandeira']) ?></div>
                            </div>
                        </div>
                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este cartão? O histórico financeiro será mantido intacto.');">
                            <input type="hidden" name="acao" value="excluir_cartao">
                            <input type="hidden" name="cartao_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn-icon-ghost" title="Excluir Cartão"><i class="ph ph-trash"></i></button>
                        </form>
                    </div>

                    <div class="cartao-disponivel-label">Limite Disponível</div>
                    <div class="cartao-disponivel-valor"><?= money($disponivel) ?></div>

                    <div class="cartao-progress-track">
                        <div class="cartao-progress-fill" style="width: <?= $pct ?>%; background: <?= $bar_color ?>;"></div>
                    </div>

                    <div class="cartao-usado-total">
                        <span>Usado: <strong><?= money($usado) ?></strong></span>
                        <span>Total: <?= money($limite) ?></span>
                    </div>

                    <div class="cartao-datas">
                        <div class="cartao-data-item">
                            <span class="cartao-data-label"><i class="ph ph-lock-simple"></i> Fechamento</span>
                            <span class="cartao-data-valor">Dia <?= htmlspecialchars($c['dia_fechamento']) ?></span>
                        </div>
                        <div class="cartao-data-item">
                            <span class="cartao-data-label"><i class="ph ph-calendar"></i> Vencimento</span>
                            <span class="cartao-data-valor">Dia <?= htmlspecialchars($c['dia_vencimento']) ?></span>
                        </div>
                    </div>

                    <div class="cartao-acoes">
                        <button type="button" class="btn btn-secondary" onclick="abrirModalCartao(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['nome'])) ?>', '<?= addslashes(htmlspecialchars($c['bandeira'])) ?>', <?= $c['limite'] ?>, <?= $c['dia_fechamento'] ?>, <?= $c['dia_vencimento'] ?>)">
                            <i class="ph ph-pencil-simple"></i> Editar
                        </button>
                        <a href="fatura.php?cartao_id=<?= $c['id'] ?>" class="btn btn-primary">
                            <i class="ph ph-file-text"></i> Ver Faturas
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <i class="ph ph-credit-card empty-state-icon"></i>
        Nenhum cartão de crédito cadastrado.<br>Adicione o primeiro para começar a centralizar os gastos físicos e virtuais.
    </div>
<?php endif; ?>

<!-- Modal de Cartão -->
<div class="modal-overlay" id="modalCartaoOverlay">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="fecharModalCartao()"><i class="ph ph-x"></i></button>
        <h3 id="modalTitle" style="margin-top: 0; margin-bottom: 20px;">Novo Cartão</h3>
        <form method="POST">
            <input type="hidden" name="acao" value="salvar_cartao">
            <input type="hidden" name="cartao_id" id="modalCartaoId">
            <div class="form-group"><label>Nome do Cartão (Ex: Nubank Agência)</label><input type="text" name="nome" id="modalCartaoNome" class="form-control" required></div>
            <div class="form-grid">
                <div class="form-group"><label>Bandeira</label><select name="bandeira" id="modalCartaoBandeira" class="form-control"><option value="Mastercard">Mastercard</option><option value="Visa">Visa</option><option value="Elo">Elo</option><option value="Amex">Amex</option><option value="Outra">Outra</option></select></div>
                <div class="form-group"><label>Limite Total (R$)</label><input type="number" step="0.01" name="limite" id="modalCartaoLimite" class="form-control" required></div>
            </div>
            <div class="form-grid">
                <div class="form-group"><label>Dia Fechamento</label><input type="number" name="dia_fechamento" id="modalCartaoFechamento" class="form-control" min="1" max="31" required></div>
                <div class="form-group"><label>Dia Vencimento</label><input type="number" name="dia_vencimento" id="modalCartaoVencimento" class="form-control" min="1" max="31" required></div>
            </div>
            <div style="text-align: right; margin-top: 20px;"><button type="submit" class="btn btn-primary">Salvar Cartão</button></div>
        </form>
    </div>
</div>

<script>
function abrirModalCartao(id = '', nome = '', bandeira = 'Mastercard', limite = '', fechamento = '', vencimento = '') {
    document.getElementById('modalCartaoId').value = id; document.getElementById('modalCartaoNome').value = nome; document.getElementById('modalCartaoBandeira').value = bandeira; document.getElementById('modalCartaoLimite').value = limite; document.getElementById('modalCartaoFechamento').value = fechamento; document.getElementById('modalCartaoVencimento').value = vencimento;
    document.getElementById('modalTitle').innerText = id ? 'Editar Cartão' : 'Novo Cartão'; document.getElementById('modalCartaoOverlay').classList.add('active');
}
function fecharModalCartao() { document.getElementById('modalCartaoOverlay').classList.remove('active'); }
</script>

<?php require_once '../../includes/layout/footer.php'; ?>