<?php
// includes/layout/footer.php - Versão Detalhada

if (!defined('APP_ENV')) {
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    $appEnv = $isLocal ? 'development' : 'production';
    $ambienteCor = $isLocal ? '#10b981' : '#3b82f6';
    $ambienteNome = $isLocal ? '🧪 DEV' : '🚀 PROD';
    $ip = $_SERVER['SERVER_ADDR'] ?? 'unknown';
} else {
    $appEnv = APP_ENV;
    $ambienteCor = AMBIENTE_COR;
    $ambienteNome = AMBIENTE_NOME;
    $ip = $_SERVER['SERVER_ADDR'] ?? 'unknown';
}

// Verifica se está em modo debug
$showDetails = defined('APP_DEBUG') && APP_DEBUG === true;
?>
    </div> 
    
    <div style="padding: 12px 24px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); background: var(--bg-surface); letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        
        <!-- Lado esquerdo -->
        <span>Gasmaske Lab &copy; <?= date('Y') ?> &bull; Premium Workspace</span>
        
        <!-- Lado direito -->
        <span style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            
            <!-- Badge do Ambiente -->
            <span style="
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 10px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                background: <?= $ambienteCor ?>;
                color: white;
                opacity: 0.8;
            ">
                <?= $ambienteNome ?>
            </span>
            
            <!-- Detalhes adicionais (apenas em desenvolvimento) -->
            <?php if ($showDetails): ?>
                <span style="opacity: 0.4; font-size: 9px;">
                    IP: <?= $ip ?> | PHP: <?= PHP_VERSION ?>
                </span>
            <?php endif; ?>
            
        </span>
    </div>

</div> </body>
</html>