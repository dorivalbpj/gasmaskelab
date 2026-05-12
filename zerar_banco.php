<?php
// zerar_banco.php

require_once 'config/database.php';

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // TIREI O 'financeiro' DAQUI
    $tabelas = [
        'planejamento',
        'contratos',
        'proposta_servicos',
        'propostas',
        'briefings',
        'clientes',
        'usuarios'
    ];

    foreach ($tabelas as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`;");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    $senha_hash = password_hash('FioteFioteVi13@', PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, 'admin')";
    $stmt = $pdo->prepare($sql);

    $stmt->execute(['Junior', 'junior@gasmaskelab.com.br', $senha_hash]);
    $stmt->execute(['Viviane', 'viviane@gasmaskelab.com.br', $senha_hash]);

    echo "<div style='font-family: sans-serif; text-align: center; margin-top: 50px;'>";
    echo "<h1 style='color: #28a745;'>Tudo certo, patrão! 🚀</h1>";
    echo "<p>Banco de dados zerado e os usuários Junior e Viviane foram criados com sucesso.</p>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ MUITO IMPORTANTE: Apague este arquivo (zerar_banco.php) do servidor AGORA!</p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h1>Deu ruim:</h1> " . $e->getMessage();
}