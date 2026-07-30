<?php
// modules/ia/worker_imagem.php
// Mantido pra quem quiser rodar via cron como "rede de segurança" (ex: reprocessar
// itens que ficaram pendentes por qualquer motivo). Não é mais o único disparador —
// o ajax_criar_fila.php agora processa a fila diretamente, sem depender deste arquivo.

set_time_limit(120);
ignore_user_abort(true);

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/gemini.php';
require_once __DIR__ . '/processar_slide.php';

processarProximoSlideDaFila($pdo);
?>