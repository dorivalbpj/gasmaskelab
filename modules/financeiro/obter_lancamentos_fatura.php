<?php
/**
 * ENDPOINT: obter_lancamentos_fatura.php
 * Retorna os lançamentos já existentes de uma fatura para validação de duplicidades
 * 
 * Recebe: $_GET['fatura_id']
 * Retorna: JSON com array de lançamentos
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Validações
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }
    
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['erro' => 'Sem permissão']);
        exit;
    }
    
    $fatura_id = (int)($_GET['fatura_id'] ?? 0);
    if (!$fatura_id) {
        http_response_code(400);
        echo json_encode(['erro' => 'fatura_id é obrigatório']);
        exit;
    }
    
    // Busca lançamentos existentes
    $stmt = $pdo->prepare("
        SELECT id, descricao, valor 
        FROM fin_lancamentos 
        WHERE fatura_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$fatura_id]);
    $lancamentos = $stmt->fetchAll();
    
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'fatura_id' => $fatura_id,
        'total' => count($lancamentos),
        'lancamentos' => $lancamentos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em obter_lancamentos_fatura.php: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
