<?php
// modules/ia/ajax_checar_status.php

require_once '../../config/session.php';
require_once '../../config/database.php';

header('Content-Type: application/json');
requireLogin();

$carrossel_id = (int)($_GET['id'] ?? 0);

if ($carrossel_id <= 0) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido']);
    exit;
}

try {
    // Busca o status geral e o custo total do carrossel
    $stmtGeral = $pdo->prepare("
        SELECT c.status, c.quantidade_imagens, c.modelo_padrao,
               IFNULL((SELECT SUM(custo_estimado) FROM carrossel_slide_versoes v JOIN carrossel_slides cs ON v.slide_id = cs.id WHERE cs.carrossel_id = c.id), 0) as custo_total
        FROM carrosseis c 
        WHERE c.id = ?
    ");
    $stmtGeral->execute([$carrossel_id]);
    $geral = $stmtGeral->fetch(PDO::FETCH_ASSOC);

    // Busca os slides e a URL da versão atual de cada um
    $stmtSlides = $pdo->prepare("
        SELECT s.id, s.numero_slide, s.status, s.modelo_usado, s.versao_atual,
               v.url_imagem, v.erro_mensagem
        FROM carrossel_slides s
        LEFT JOIN carrossel_slide_versoes v ON v.slide_id = s.id AND v.versao = s.versao_atual
        WHERE s.carrossel_id = ?
        ORDER BY s.numero_slide ASC
    ");
    $stmtSlides->execute([$carrossel_id]);
    $slides = $stmtSlides->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'sucesso' => true,
        'geral' => $geral,
        'slides' => $slides
    ]);

} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>