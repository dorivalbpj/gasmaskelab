<?php
// config/session.php

// Inicia a sessão do PHP
session_start();

// Define a URL base do projeto dinamicamente e com segurança
if (!defined('BASE_URL')) {
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Verifica se está rodando localmente (seu PC)
    if ($host === 'localhost' || $host === '127.0.0.1') {
        // Ambiente Local: adiciona a pasta do projeto
        define('BASE_URL', $protocolo . '://' . $host . '/gasmaske/');
    } else {
        // Ambiente de Produção (ex: erp.gasmaskelab.com.br): roda direto na raiz
        define('BASE_URL', $protocolo . '://' . $host . '/');
    }
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