<?php
// config/session.php

// Inicia a sessão do PHP
session_start();

// Define a URL base do projeto dinamicamente
if (!defined('BASE_URL')) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $script_dir = dirname($script_name);
    
    // Remove /modules/*, /publico/*, /config/*, /includes/*, /assets/* do final para achar a raiz real
    $script_dir = preg_replace('#/(modules|publico|config|includes|assets)(/.*)?$#', '', $script_dir);
    
    // Remove barra final e adiciona de novo para garantir formato correto
    $base_path = rtrim($script_dir, '/') . '/';
    define('BASE_URL', $protocolo . '://' . $host . $base_path);
}

// Função para verificar se existe alguém logado
function isLogado() {
    return isset($_SESSION['usuario_id']);
}

// Função para barrar quem não está logado
function requireLogin() {
    if (!isLogado()) {
        // Se não estiver logado, manda de volta pra tela de login
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Função para verificar se o usuário é Admin
function isAdmin() {
    return isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] === 'admin';
}
?>