<?php
// config/session.php

// Inicia a sessão do PHP
session_start();

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