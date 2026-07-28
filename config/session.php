<?php
// config/session.php

// Inicia a sessão do PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclui as configurações centrais
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/config.php';
}

// Função para verificar se existe alguém logado
function isLogado() {
    return isset($_SESSION['usuario_id']);
}

// Função para barrar quem não está logado
function requireLogin() {
    if (!isLogado()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

// Função para verificar se o usuário é Admin
function isAdmin() {
    return isset($_SESSION['usuario_perfil']) && $_SESSION['usuario_perfil'] === 'admin';
}