<?php
// includes/functions_avatar.php

/**
 * Gera uma URL de avatar por iniciais usando ui-avatars.com
 * Usado como fallback quando não há avatar manual.
 * @param string $nome - Nome do cliente
 * @return string - URL do avatar com as iniciais
 */
function gerarAvatarIniciais($nome) {
    $nome_encoded = urlencode(trim($nome));
    return "https://ui-avatars.com/api/?name={$nome_encoded}&background=6366f1&color=fff&size=128&bold=true&format=png";
}

/**
 * Salva o avatar_url no banco.
 * Se o cliente não tiver avatar manual, gera um por iniciais.
 * @param int $cliente_id
 * @param string $nome - Nome do cliente (usado para iniciais)
 * @param PDO $pdo
 * @return bool
 */
function salvarAvatarCliente($cliente_id, $nome, $pdo) {
    // Busca se já existe um avatar manual salvo (upload físico)
    $stmt = $pdo->prepare("SELECT avatar_url FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $atual = $stmt->fetchColumn();

    // Se já tem um avatar manual (caminho local), não sobrescreve
    if (!empty($atual) && str_starts_with($atual, '/')) {
        return true;
    }

    // Gera URL por iniciais e salva
    $url = gerarAvatarIniciais($nome);
    $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = ? WHERE id = ?");
    $stmt->execute([$url, $cliente_id]);
    return true;
}
?>