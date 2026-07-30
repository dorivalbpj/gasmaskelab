<?php
// modules/ia/ajax_criar_fila.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../config/gemini.php';
require_once 'processar_slide.php';

header('Content-Type: application/json');

requireLogin();
if (!isAdmin()) {
    echo json_encode(['sucesso' => false, 'erro' => 'Acesso negado.']);
    exit;
}

// Gerar N imagens em sequência pode passar de 30s facilmente — aumenta o limite
// só desta requisição (não mexe na config global do servidor).
set_time_limit(300);

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
$assunto    = trim($_POST['assunto'] ?? '');
$formato    = $_POST['formato'] ?? '1080x1350';
$quantidade = (int)($_POST['quantidade'] ?? 5);
$modelo     = $_POST['modelo'] ?? 'nano_banana_2';

if ($cliente_id <= 0 || empty($assunto)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Cliente e assunto são obrigatórios.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO carrosseis (cliente_id, assunto, formato, proporcao, quantidade_imagens, modelo_padrao, status) VALUES (?, ?, ?, ?, ?, ?, 'pendente')");
    $stmt->execute([$cliente_id, $assunto, $formato, $formato, $quantidade, $modelo]);

    $carrossel_id = $pdo->lastInsertId();

    $stmtSlide = $pdo->prepare("INSERT INTO carrossel_slides (carrossel_id, numero_slide, status, modelo_usado, versao_atual) VALUES (?, ?, 'pendente', ?, 1)");
    for ($i = 1; $i <= $quantidade; $i++) {
        $stmtSlide->execute([$carrossel_id, $i, $modelo]);
    }

    $pdo->commit();

    // Processa a fila direto, no mesmo processo PHP — nada de chamada de rede
    // pro próprio servidor (era isso que estava travando tudo em "pendente").
    for ($i = 0; $i < $quantidade; $i++) {
        processarProximoSlideDaFila($pdo);
    }

    echo json_encode(['sucesso' => true, 'carrossel_id' => $carrossel_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno ao salvar fila: ' . $e->getMessage()]);
}
?>