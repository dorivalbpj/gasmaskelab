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
    
    // Verifica se existe arquivo de ambiente (opcional)
    if (file_exists(__DIR__ . '/../.env.local')) {
        return 'local';
    }
    
    // Por padrão, assume produção
    return 'producao';
}

// Só executa a detecção se não estiver definido
if (!defined('AMBIENTE_ATUAL')) {
    $ambiente = detectarAmbiente();
    define('AMBIENTE_ATUAL', $ambiente);
} else {
    $ambiente = AMBIENTE_ATUAL;
}

// ============================================
// 2. CONFIGURAÇÕES POR AMBIENTE
// ============================================

// Só define BASE_URL se não existir
if (!defined('BASE_URL')) {
    if ($ambiente === 'local') {
        // ===== CONFIGURAÇÕES LOCAIS (DOCKER) =====
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('BASE_URL', $protocolo . '://' . $host . '/');
        define('BASE_PATH', '');
        
        // Banco de dados LOCAL
        $db_host = 'db';
        $db_name = 'gasmaske_db';
        $db_user = 'root';
        $db_pass = 'root';
        
    } else {
        // ===== CONFIGURAÇÕES DE PRODUÇÃO (HOSTINGER) =====
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'gasmaskelab.com.br';
        define('BASE_URL', $protocolo . '://' . $host . '/erp/');
        define('BASE_PATH', '/erp');
        
        // Banco de dados PRODUÇÃO
        $db_host = 'localhost';
        $db_name = 'u288703276_gasmaske';
        $db_user = 'u288703276_gasmaskelab';
        $db_pass = 'FioteFioteVi13@';
    }
}

// ============================================
// 3. CONSTANTES ADICIONAIS (com proteção)
// ============================================

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', $ambiente === 'local');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', $ambiente === 'local' ? 'development' : 'production');
}

if (!defined('AMBIENTE_NOME')) {
    define('AMBIENTE_NOME', $ambiente === 'local' ? '🧪 Desenvolvimento' : '🚀 Produção');
}

if (!defined('AMBIENTE_COR')) {
    define('AMBIENTE_COR', $ambiente === 'local' ? '#10b981' : '#3b82f6');
}

if (!defined('AMBIENTE_ICONE')) {
    define('AMBIENTE_ICONE', $ambiente === 'local' ? '🛠️' : '🌐');
}

// ============================================
// 4. CONEXÃO COM O BANCO DE DADOS
// ============================================

// Só conecta se o PDO não existir
if (!isset($pdo)) {
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
}

// ============================================
// 5. FUNÇÃO DE UTILIDADE
// ============================================

if (!function_exists('url')) {
    /**
     * Retorna a URL completa para um caminho interno
     * Exemplo: url('publico/briefing.php') -> http://localhost/publico/briefing.php
     */
    function url($caminho = '') {
        return BASE_URL . ltrim($caminho, '/');
    }
}
?>