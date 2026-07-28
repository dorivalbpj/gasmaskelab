<?php
// includes/layout/footer.php 

if (!defined('APP_ENV')) {
    $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    $appEnv = $isLocal ? 'development' : 'production';
    $ambienteCor = $isLocal ? '#059669' : '#475569'; // verde escuro / slate
    $ambienteNome = $isLocal ? 'DEV' : 'PROD';
    $ip = $_SERVER['SERVER_ADDR'] ?? 'unknown';
} else {
    $appEnv = APP_ENV;
    $ambienteCor = AMBIENTE_COR;
    $ambienteNome = AMBIENTE_NOME;
    $ip = $_SERVER['SERVER_ADDR'] ?? 'unknown';
}

$showDetails = defined('APP_DEBUG') && APP_DEBUG === true;
?>
    </div> 
    
    <div style="padding: 12px 24px; border-top: 1px solid var(--border); font-size: 11px; color: var(--text-muted); background: var(--bg-surface); letter-spacing: 0.3px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
        
        <!-- Lado esquerdo -->
        <span>Gasmaske Lab &copy; <?= date('Y') ?> <span style="opacity: 0.4; margin: 0 6px;">&middot;</span> Premium Workspace</span>
        
        <!-- Lado direito -->
        <span style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            
            <!-- Badge do Ambiente (discreto, sem fundo colorido chapado) -->
            <span style="
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.8px;
                text-transform: uppercase;
                color: var(--text-muted);
                opacity: 0.85;
            ">
                <span style="
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    background: <?= $ambienteCor ?>;
                    display: inline-block;
                "></span>
                <?= $ambienteNome ?>
            </span>
            
            <!-- Detalhes adicionais (apenas em desenvolvimento) -->
            <?php if ($showDetails): ?>
                <span style="opacity: 0.35; font-size: 9px; border-left: 1px solid var(--border); padding-left: 12px;">
                    IP: <?= $ip ?> &nbsp;|&nbsp; PHP: <?= PHP_VERSION ?>
                </span>
            <?php endif; ?>
            
        </span>
    </div>

</div> </body>
</html>