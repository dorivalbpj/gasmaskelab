<?php
// includes/functions_avatar.php

/**
 * Busca o avatar do Instagram usando unavatar.io
 * @param string $username - Nome de usuário do Instagram
 * @return string|null - URL do avatar ou null se não encontrar
 */
function buscarAvatarInstagram($username) {
    if (empty($username)) {
        return null;
    }
    
    // Limpa o username (remove @ se tiver)
    $username = ltrim($username, '@');
    
    // URL do unavatar.io
    $url = "https://unavatar.io/instagram/{$username}";
    
    // Verifica se a imagem existe (apenas HEAD request)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Só cabeçalho
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Se retornou 200, a imagem existe
    if ($httpCode === 200) {
        return $url;
    }
    
    return null;
}

/**
 * Busca e salva o avatar no cadastro/edição
 * @param int $cliente_id - ID do cliente
 * @param string $username - Nome de usuário do Instagram
 * @param PDO $pdo - Conexão com o banco
 * @return bool - Se conseguiu salvar ou não
 */
function salvarAvatarCliente($cliente_id, $username, $pdo) {
    if (empty($username)) {
        // Se não tem username, remove o avatar
        $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = NULL WHERE id = ?");
        $stmt->execute([$cliente_id]);
        return false;
    }
    
    $avatar_url = buscarAvatarInstagram($username);
    
    if ($avatar_url) {
        $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = ? WHERE id = ?");
        $stmt->execute([$avatar_url, $cliente_id]);
        return true;
    } else {
        // Se não encontrou, remove o avatar
        $stmt = $pdo->prepare("UPDATE clientes SET avatar_url = NULL WHERE id = ?");
        $stmt->execute([$cliente_id]);
        return false;
    }
}
?>