<?php
// config/config.php - Configuração central com detecção automática de ambiente

// ============================================
// 1. DETECÇÃO DE AMBIENTE
// ============================================
function detectarAmbiente() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Ambientes de desenvolvimento local
    $locais = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    if (in_array($host, $locais)) {
        return 'local';
    }
    
    // Se conter 'docker' no host ou estiver em rede interna
    if (strpos($host, 'docker') !== false || strpos($host, '.local') !== false) {
        return 'local';
    }
    
    // Verifica se está rodando via CLI (linha de comando)
    if (php_sapi_name() === 'cli') {
        return 'local';
    }
    
    
    if (file_exists(__DIR__ . '/../.env.local')) {
    return 'local';
}
    
    // Por padrão, assume produção
    return 'producao';
}

$ambiente = detectarAmbiente();

// ============================================
// 2. CONFIGURAÇÕES POR AMBIENTE
// ============================================

if ($ambiente === 'local') {
    // ===== CONFIGURAÇÕES LOCAIS (DOCKER) =====
    
    // URL Base - SEM /erp (local é na raiz)
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocolo . '://' . $host . '/');
    define('BASE_PATH', '');
    
    // Banco de dados LOCAL
    $db_host = 'db';  // Nome do serviço no docker-compose
    $db_name = 'gasmaske_db';
    $db_user = 'root';
    $db_pass = 'root';
    
    // Debug (opcional)
    define('APP_DEBUG', true);
    define('APP_ENV', 'development');
    
} else {
    // ===== CONFIGURAÇÕES DE PRODUÇÃO (HOSTINGER) =====
    
    // URL Base - COM /erp (produção está na subpasta)
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'gasmaskelab.com.br';
    define('BASE_URL', $protocolo . '://' . $host . '/erp/');
    define('BASE_PATH', '/erp');
    
    // Banco de dados PRODUÇÃO
    $db_host = 'localhost';
    $db_name = 'u288703276_gasmaske';
    $db_user = 'u288703276_gasmaskelab';
    $db_pass = 'FioteFioteVi13@';
    
    // Debug
    define('APP_DEBUG', false);
    define('APP_ENV', 'production');
}

// ============================================
// 3. CONSTANTES ADICIONAIS
// ============================================

// Para debug visual (mostra no rodapé se estiver em desenvolvimento)
define('AMBIENTE_ATUAL', $ambiente);

// ============================================
// 4. CONEXÃO COM O BANCO DE DADOS
// ============================================

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Em produção, mostra erro genérico para segurança
    if ($ambiente === 'producao') {
        die("Erro na conexão com o banco de dados. Contate o administrador.");
    } else {
        die("Erro de conexão com o banco de dados: " . $e->getMessage());
    }
}

// ============================================
// 5. FUNÇÃO DE UTILIDADE (opcional)
// ============================================

/**
 * Retorna a URL completa para um caminho interno
 * Exemplo: url('publico/briefing.php') -> http://localhost/publico/briefing.php
 */
function url($caminho = '') {
    return BASE_URL . ltrim($caminho, '/');
}