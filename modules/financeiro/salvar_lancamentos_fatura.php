<?php
/**
 * ENDPOINT: salvar_lancamentos_fatura.php
 * Salva os lançamentos importados do PDF no banco de dados
 * 
 * Recebe JSON POST com:
 * {
 *   "fatura_id": 123,
 *   "lancamentos": [
 *     {"descricao": "Uber", "valor": 25.50, "categoria_id": 3, "data_compra": "2024-10-15"},
 *     ...
 *   ]
 * }
 * 
 * Insere em fin_lancamentos com status = 'pendente', tipo = 'empresa', forma_pagamento = 'cartao'
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // ==================== VALIDAÇÕES INICIAIS ====================
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id']) && !isset($_SESSION['id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }
    
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['erro' => 'Sem permissão']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['erro' => 'Método não permitido']);
        exit;
    }
    
    // Lê o JSON do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['erro' => 'Nenhum dado foi enviado']);
        exit;
    }
    
    $fatura_id = (int)($input['fatura_id'] ?? 0);
    $lancamentos = $input['lancamentos'] ?? [];
    
    if (!$fatura_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'fatura_id é obrigatório']);
        exit;
    }
    
    if (!is_array($lancamentos) || count($lancamentos) === 0) {
        http_response_code(400);
        echo json_encode(['erro' => 'Nenhum lançamento para salvar']);
        exit;
    }
    
    // ==================== VALIDAÇÃO DA FATURA ====================
    $stmt_fatura = $pdo->prepare("
        SELECT id, mes, ano, cartao_id 
        FROM fin_faturas 
        WHERE id = ?
    ");
    $stmt_fatura->execute([$fatura_id]);
    $fatura = $stmt_fatura->fetch();
    
    if (!$fatura) {
        http_response_code(404);
        echo json_encode(['erro' => 'Fatura não encontrada']);
        exit;
    }
    
    // ==================== PREPARA STATEMENT PARA INSERT ====================
    $stmt_insert = $pdo->prepare("
        INSERT INTO fin_lancamentos 
        (descricao, valor, data_vencimento, categoria_id, tipo, forma_pagamento, status, fatura_id, parcela_atual, total_parcelas, grupo_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // ==================== LOOP DE INSERÇÃO ====================
    $pdo->beginTransaction();
    $total_salvo = 0;
    $erros = [];
    
    foreach ($lancamentos as $idx => $item) {
        try {
            // Sanitização e validação
            $descricao = trim((string)($item['descricao'] ?? ''));
            $valor = (float)($item['valor'] ?? 0);
            $categoria_id = isset($item['categoria_id']) && $item['categoria_id'] !== null && $item['categoria_id'] !== '' 
                ? (int)$item['categoria_id'] 
                : null;
            $data_compra = trim((string)($item['data_compra'] ?? date('Y-m-d')));
            $tipo = $item['tipo'] ?? 'pessoal';
            $parcela_atual = (int)($item['parcela_atual'] ?? 1);
            $total_parcelas = (int)($item['total_parcelas'] ?? 1);
            
            // Validações de negócio
            if (strlen($descricao) === 0) {
                $erros[] = "Linha " . ($idx + 1) . ": Descrição vazia";
                continue;
            }
            
            if ($valor <= 0) {
                $erros[] = "Linha " . ($idx + 1) . ": Valor deve ser positivo";
                continue;
            }
            
            // Valida data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_compra)) {
                $data_compra = date('Y-m-d');
            }
            
            // Valida categoria_id se fornecido
            if ($categoria_id !== null) {
                $stmt_cat = $pdo->prepare("SELECT id FROM fin_categorias WHERE id = ?");
                $stmt_cat->execute([$categoria_id]);
                if (!$stmt_cat->fetch()) {
                    $categoria_id = null; // Se não existir, remove
                }
            }
            
            if ($total_parcelas > 1) {
                $grupo_id = uniqid('parc_');
                for ($p = $parcela_atual; $p <= $total_parcelas; $p++) {
                    $offset_meses = $p - $parcela_atual;
                    
                    if ($offset_meses == 0) {
                        $fatura_alvo_id = $fatura_id;
                    } else {
                        $target_mes = $fatura['mes'] + $offset_meses;
                        $target_ano = $fatura['ano'];
                        while ($target_mes > 12) {
                            $target_mes -= 12;
                            $target_ano++;
                        }
                        
                        $stmt_f = $pdo->prepare("SELECT id FROM fin_faturas WHERE cartao_id = ? AND mes = ? AND ano = ?");
                        $stmt_f->execute([$fatura['cartao_id'], $target_mes, $target_ano]);
                        $fat_futura = $stmt_f->fetchColumn();
                        
                        if ($fat_futura) {
                            $fatura_alvo_id = $fat_futura;
                        } else {
                            $stmt_c = $pdo->prepare("SELECT dia_vencimento FROM fin_cartoes WHERE id = ?");
                            $stmt_c->execute([$fatura['cartao_id']]);
                            $dia_venc = $stmt_c->fetchColumn();
                            
                            $data_base = sprintf('%04d-%02d-01', $target_ano, $target_mes);
                            $max_dias = date('t', strtotime($data_base));
                            $dia_venc_real = min((int)$dia_venc, $max_dias);
                            $data_venc_futura = sprintf('%04d-%02d-%02d', $target_ano, $target_mes, $dia_venc_real);
                            
                            $pdo->prepare("INSERT INTO fin_faturas (cartao_id, mes, ano, data_vencimento, status) VALUES (?, ?, ?, ?, 'aberta')")
                                ->execute([$fatura['cartao_id'], $target_mes, $target_ano, $data_venc_futura]);
                            $fatura_alvo_id = $pdo->lastInsertId();
                        }
                    }
                    
                    $stmt_insert->execute([
                        $descricao, round($valor, 2), $data_compra, $categoria_id, $tipo, 'cartao', 'pendente', $fatura_alvo_id, $p, $total_parcelas, $grupo_id
                    ]);
                    if ($offset_meses == 0) $total_salvo++;
                }
            } else {
                $stmt_insert->execute([
                    $descricao, round($valor, 2), $data_compra, $categoria_id, $tipo, 'cartao', 'pendente', $fatura_id, 1, 1, null
                ]);
                $total_salvo++;
            }
            
        } catch (Exception $e) {
            $erros[] = "Linha " . ($idx + 1) . ": " . $e->getMessage();
            continue;
        }
    }
    
    // ==================== COMMIT OU ROLLBACK ====================
    if ($total_salvo > 0) {
        $pdo->commit();
        
        http_response_code(200);
        echo json_encode([
            'sucesso' => true,
            'total_salvo' => $total_salvo,
            'erros' => $erros,
            'mensagem' => $total_salvo . ' lançamento(s) importado(s) com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        $pdo->rollBack();
        
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'erro' => 'Nenhum lançamento foi salvo. Verifique os erros.',
            'erros' => $erros
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    error_log('Erro em salvar_lancamentos_fatura.php: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => 'Erro ao salvar lançamentos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
