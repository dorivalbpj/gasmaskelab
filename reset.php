<?php
// reset.php - Apague depois de usar!
require_once 'config/database.php';

// Gera o código criptografado da senha "123456"
$nova_senha = password_hash('123456', PASSWORD_DEFAULT);

// Atualiza TODOS os usuários que têm perfil de "cliente"
$pdo->query("UPDATE usuarios SET senha = '$nova_senha' WHERE perfil = 'cliente'");

echo "<h1>Sucesso!</h1>";
echo "<p>A senha de TODOS os clientes do sistema agora é: <strong>123456</strong></p>";
echo "<a href='index.php'>Clique aqui para ir para o Login</a>";
?>