<?php
// api/telegram/webhook.php
require_once __DIR__ . '/telegram_funcoes.php';

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update["message"])) {
    $chat_id_remetente = $update["message"]["chat"]["id"];
    $texto_recebido = strtolower($update["message"]["text"]);

    // Array com os IDs autorizados a usar o bot
    $ids_permitidos = [
        '21496092', // ID Fiiote
        '27706718' // ID da Vi
    ];

    if (in_array($chat_id_remetente, $ids_permitidos)) {
        
        // Verifica qual comando foi enviado
        if (strpos($texto_recebido, 'tarefa') !== false) {
            
            $mensagem = montarResumoTarefas($pdo);
            enviarMensagemTelegram($mensagem, $chat_id_remetente);
            
        } elseif (strpos($texto_recebido, 'financeiro') !== false || strpos($texto_recebido, 'relatorio') !== false) {
            
            $mensagem = montarRelatorioFinanceiro($pdo);
            enviarMensagemTelegram($mensagem, $chat_id_remetente);
            
        } else {
            // Menu inicial
            $menu = "Fala! O que você quer ver?\n\n";
            $menu .= "👉 <b>/tarefas</b> (Resumo do Planejamento)\n";
            $menu .= "👉 <b>/financeiro</b> (Balanço da Semana)";
            enviarMensagemTelegram($menu, $chat_id_remetente);
        }
    }
}
?>