<?php
// config/database.php

// Define a BASE_URL dinamicamente (Regra de Arquitetura V4)
if (!defined('BASE_URL')) {
    define('BASE_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/');
}

$host = 'localhost'; // 
$dbname = 'u288703276_gasmaske'; 
$usuario = 'u288703276_gasmaskelab';      
$senha = 'FioteFioteVi13@';          

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $usuario, $senha);
    // Configura o PDO para mostrar os erros caso algo dê errado
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Define o formato de retorno dos dados
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erro de conexão com o banco de dados: " . $e->getMessage());
}
?>