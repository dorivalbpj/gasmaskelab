<?php
// api/telegram/telegram_funcoes.php
require_once __DIR__ . '/../../config/database.php'; 

define('TELEGRAM_TOKEN', '8492747375:AAGw2OU6L9nFOEwK8T5ztVf3GTI99vg5pXg');

function enviarMensagemTelegram(string $texto, $destino_id) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $destino_id,
        'text' => $texto,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function montarResumoTarefas(PDO $pdo) {
    $texto = "Salve! Aqui estão os BOs:\n\n";
    
    // 1. Buscando Tarefas Atrasadas com Responsável e Categoria
    $sqlAtrasadas = "SELECT p.tema, p.tipo, u.nome as responsavel_nome, c.nome as cliente_nome, DATE_FORMAT(p.data_publicacao, '%d/%m') as data_br 
                     FROM planejamento p 
                     LEFT JOIN clientes c ON p.cliente_id = c.id 
                     LEFT JOIN usuarios u ON p.responsavel_id = u.id
                     WHERE p.data_publicacao < CURDATE() 
                     AND p.status_geral NOT IN ('finalizado', 'arquivado') 
                     ORDER BY p.data_publicacao ASC";
    $stmtAtrasadas = $pdo->query($sqlAtrasadas);
    $atrasadas = $stmtAtrasadas->fetchAll(PDO::FETCH_ASSOC);

    if (count($atrasadas) > 0) {
        $texto .= "🚨 <b>TAREFAS ATRASADAS</b> 🚨\n";
        foreach ($atrasadas as $t) {
            $cliente = $t['cliente_nome'] ? $t['cliente_nome'] : 'Interno';
            $categoria = $t['tipo'] ? $t['tipo'] : 'Sem cat.';
            $resp = $t['responsavel_nome'] ? $t['responsavel_nome'] : 'Sem resp.';
            
            $texto .= "• [{$cliente}] {$t['tema']}\n  └ <i>{$categoria} | 👤 {$resp} (Venceu: {$t['data_br']})</i>\n\n";
        }
    }

    // 2. Buscando Tarefas de Hoje com Responsável e Categoria
    $sqlHoje = "SELECT p.tema, p.tipo, u.nome as responsavel_nome, c.nome as cliente_nome 
                FROM planejamento p 
                LEFT JOIN clientes c ON p.cliente_id = c.id 
                LEFT JOIN usuarios u ON p.responsavel_id = u.id
                WHERE p.data_publicacao = CURDATE() 
                AND p.status_geral NOT IN ('finalizado', 'arquivado')";
    $stmtHoje = $pdo->query($sqlHoje);
    $hoje = $stmtHoje->fetchAll(PDO::FETCH_ASSOC);

    $texto .= "📅 <b>TAREFAS DE HOJE</b> 📅\n";
    if (count($hoje) > 0) {
        foreach ($hoje as $t) {
            $cliente = $t['cliente_nome'] ? $t['cliente_nome'] : 'Interno';
            $categoria = $t['tipo'] ? $t['tipo'] : 'Sem cat.';
            $resp = $t['responsavel_nome'] ? $t['responsavel_nome'] : 'Sem resp.';

            $texto .= "• [{$cliente}] {$t['tema']}\n  └ <i>{$categoria} | 👤 {$resp}</i>\n\n";
        }
    } else {
        $texto .= "<i>Tudo limpo pra hoje!</i> 🎉\n";
    }

    return $texto;
}

function montarRelatorioFinanceiro(PDO $pdo) {
    $inicioSemana = date('Y-m-d', strtotime('monday this week'));
    $fimSemana = date('Y-m-d', strtotime('sunday this week'));
    $dataBrInicio = date('d/m', strtotime($inicioSemana));
    $dataBrFim = date('d/m', strtotime($fimSemana));

    $texto = "📊 <b>BALANÇO DA SEMANA</b> ($dataBrInicio a $dataBrFim)\n\n";

    // --- CÁLCULO DOS TOTAIS ---
    $sqlEntradas = "SELECT 
        SUM(CASE WHEN status = 'pago' AND data_pagamento BETWEEN ? AND ? THEN valor ELSE 0 END) as recebido,
        SUM(CASE WHEN status != 'pago' AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) as a_receber
        FROM parcelas";
    $stmtE = $pdo->prepare($sqlEntradas);
    $stmtE->execute([$inicioSemana, $fimSemana, $inicioSemana, $fimSemana]);
    $entradas = $stmtE->fetch(PDO::FETCH_ASSOC);

    $sqlSaidas = "SELECT 
        SUM(CASE WHEN status = 'pago' AND data_pagamento BETWEEN ? AND ? THEN valor ELSE 0 END) as pago,
        SUM(CASE WHEN status != 'pago' AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) as a_pagar
        FROM fin_lancamentos";
    $stmtS = $pdo->prepare($sqlSaidas);
    $stmtS->execute([$inicioSemana, $fimSemana, $inicioSemana, $fimSemana]);
    $saidas = $stmtS->fetch(PDO::FETCH_ASSOC);

    $recebido = number_format((float)$entradas['recebido'], 2, ',', '.');
    $a_receber = number_format((float)$entradas['a_receber'], 2, ',', '.');
    $pago = number_format((float)$saidas['pago'], 2, ',', '.');
    $a_pagar = number_format((float)$saidas['a_pagar'], 2, ',', '.');
    
    $saldo = (float)$entradas['recebido'] - (float)$saidas['pago'];
    $saldoFmt = number_format($saldo, 2, ',', '.');
    $emojiSaldo = $saldo >= 0 ? "🟢" : "🔴";

    // Resumo
    $texto .= "⚖️ <b>SALDO DA SEMANA:</b> $emojiSaldo R$ $saldoFmt\n\n";

    // --- DETALHAMENTO DE ENTRADAS ---
    $texto .= "🟩 <b>LISTA DE ENTRADAS</b>\n";
    
    $sqlListaE = "SELECT p.descricao, p.valor, p.status, cli.nome as cliente_nome 
                  FROM parcelas p 
                  LEFT JOIN contratos c ON p.contrato_id = c.id 
                  LEFT JOIN clientes cli ON c.cliente_id = cli.id 
                  WHERE (p.status = 'pago' AND p.data_pagamento BETWEEN ? AND ?) 
                     OR (p.status != 'pago' AND p.data_vencimento BETWEEN ? AND ?)
                  ORDER BY p.data_vencimento ASC";
    $stmtListaE = $pdo->prepare($sqlListaE);
    $stmtListaE->execute([$inicioSemana, $fimSemana, $inicioSemana, $fimSemana]);
    $listaE = $stmtListaE->fetchAll(PDO::FETCH_ASSOC);

    if (count($listaE) > 0) {
        foreach ($listaE as $e) {
            $statusEmoji = ($e['status'] == 'pago') ? '🟢' : (($e['status'] == 'atrasado') ? '🔴' : '🟡');
            $cliente = $e['cliente_nome'] ? $e['cliente_nome'] : 'Avulso';
            $val = number_format((float)$e['valor'], 2, ',', '.');
            $texto .= "{$statusEmoji} [{$cliente}] {$e['descricao']} - <b>R$ {$val}</b>\n";
        }
    } else {
        $texto .= "<i>Nenhuma entrada registrada na semana.</i>\n";
    }
    $texto .= "\n";

    // --- DETALHAMENTO DE SAÍDAS ---
    $texto .= "🟥 <b>LISTA DE SAÍDAS</b>\n";
    
    $sqlListaS = "SELECT l.descricao, l.valor, l.status, cat.nome as categoria_nome 
                  FROM fin_lancamentos l 
                  LEFT JOIN fin_categorias cat ON l.categoria_id = cat.id 
                  WHERE (l.status = 'pago' AND l.data_pagamento BETWEEN ? AND ?) 
                     OR (l.status != 'pago' AND l.data_vencimento BETWEEN ? AND ?)
                  ORDER BY l.data_vencimento ASC";
    $stmtListaS = $pdo->prepare($sqlListaS);
    $stmtListaS->execute([$inicioSemana, $fimSemana, $inicioSemana, $fimSemana]);
    $listaS = $stmtListaS->fetchAll(PDO::FETCH_ASSOC);

    if (count($listaS) > 0) {
        foreach ($listaS as $s) {
            $statusEmoji = ($s['status'] == 'pago') ? '🟢' : (($s['status'] == 'atrasado') ? '🔴' : '🟡');
            $cat = $s['categoria_nome'] ? $s['categoria_nome'] : 'Diversos';
            $val = number_format((float)$s['valor'], 2, ',', '.');
            $texto .= "{$statusEmoji} [{$cat}] {$s['descricao']} - <b>R$ {$val}</b>\n";
        }
    } else {
        $texto .= "<i>Nenhuma saída registrada na semana.</i>\n";
    }

    // Legenda dos Status
    $texto .= "\n<i>Legenda: 🟢 Pago | 🟡 Pendente | 🔴 Atrasado</i>";

    return $texto;
}
?>