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

require_once '../../includes/layout/header.php';
require_once '../../includes/layout/sidebar.php';
?>

<div class="cabecalho">
    <div>
        <h2 class="page-title">Cartões de Crédito</h2>
        <p class="page-subtitle">Gerencie seus limites, vencimentos e faturas de cartão.</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="abrirModalCartao()"><i class="ph ph-plus"></i> Novo Cartão</button>
</div>

<?= $mensagem ?>

<?php if (count($cartoes) > 0): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
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
            ?>
            <div class="card" style="margin-bottom: 0;">
                <div class="card-header" style="border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 15px;">
                    <h3 class="card-title" style="color: var(--text);"><i class="ph ph-credit-card" style="font-size: 16px; margin-right: 5px;"></i> <?= htmlspecialchars($c['nome']) ?> <span style="color: var(--text-3); font-weight: 500;">(<?= htmlspecialchars($c['bandeira']) ?>)</span></h3>
                    <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Excluir este cartão? O histórico financeiro será mantido intacto.');">
                        <input type="hidden" name="acao" value="excluir_cartao">
                        <input type="hidden" name="cartao_id" value="<?= $c['id'] ?>">
                        <button type="submit" style="background: none; border: none; color: var(--text-3); cursor: pointer;" title="Excluir Cartão"><i class="ph ph-trash" style="font-size: 18px;"></i></button>
                    </form>
                </div>
                
                <div style="font-size: 12px; color: var(--text-2); margin-bottom: 2px;">Limite Disponível</div>
                <div style="font-size: 26px; font-weight: 700; color: var(--green);"><?= money($disponivel) ?></div>
                
                <div style="width: 100%; height: 6px; background: var(--bg-hover); border-radius: 3px; margin: 12px 0;">
                    <div style="width: <?= $pct ?>%; height: 100%; background: <?= $bar_color ?>; border-radius: 3px;"></div>
                </div>
                
                <div style="display: flex; justify-content: space-between; font-size: 11px; color: var(--text-3); margin-bottom: 16px;">
                    <span>Usado: <strong style="color: var(--text-primary);"><?= money($usado) ?></strong></span>
                    <span>Total: <?= money($limite) ?></span>
                </div>
                
                <div style="background: var(--bg-hover); padding: 12px; border-radius: var(--r-md); display: flex; justify-content: space-between; font-size: 12px; color: var(--text-2); margin-bottom: 16px;">
                    <div style="text-align: center;"><span style="display: block; font-size: 10px; text-transform: uppercase;">Fechamento</span> <strong>Dia <?= htmlspecialchars($c['dia_fechamento']) ?></strong></div>
                    <div style="width: 1px; background: var(--border-mid);"></div>
                    <div style="text-align: center;"><span style="display: block; font-size: 10px; text-transform: uppercase;">Vencimento</span> <strong>Dia <?= htmlspecialchars($c['dia_vencimento']) ?></strong></div>
                </div>
                
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1; justify-content: center;" onclick="abrirModalCartao(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['nome'])) ?>', '<?= addslashes(htmlspecialchars($c['bandeira'])) ?>', <?= $c['limite'] ?>, <?= $c['dia_fechamento'] ?>, <?= $c['dia_vencimento'] ?>)">
                        <i class="ph ph-pencil-simple"></i> Editar
                    </button>
                    <!-- Botão bloqueado por enquanto, pois fatura.php será implementado futuramente -->
                    <button type="button" class="btn btn-primary" style="flex: 1; justify-content: center;" onclick="alert('Funcionalidade de faturas será liberada nas próximas etapas!')">
                        <i class="ph ph-file-text"></i> Ver Faturas
                    </button>
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