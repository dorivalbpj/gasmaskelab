<?php
// includes/functions.php

// Formata número para dinheiro: 1500.00 vira R$ 1.500,00
function money($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Formata data do banco (YYYY-MM-DD) para padrão brasileiro (DD/MM/YYYY)
function dataBR($data) {
    if (!$data) return '-';
    return date('d/m/Y', strtotime($data));
}


// --- GERADOR DE PIX OFICIAL (BR CODE) ---
function calcularCrc16($payload) {
    $payload .= '6304';
    $polinomio = 0x1021;
    $resultado = 0xFFFF;
    if (($length = strlen($payload)) > 0) {
        for ($offset = 0; $offset < $length; $offset++) {
            $resultado ^= (ord($payload[$offset]) << 8);
            for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                if (($resultado <<= 1) & 0x10000) $resultado ^= $polinomio;
                $resultado &= 0xFFFF;
            }
        }
    }
    return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
}

function gerarPayloadPix($chave, $valor, $nome, $cidade) {
    // Limpa a chave PIX (tira traços e pontos)
    $chave = preg_replace('/[^0-9a-zA-Z@.]/', '', $chave);
    // Formata o valor corretamente
    $valor = number_format($valor, 2, '.', '');
    
    // Monta a estrutura oficial exigida pelo Banco Central
    $px = [
        '00' => '01', // Payload Format Indicator
        '26' => '0014BR.GOV.BCB.PIX01' . str_pad(strlen($chave), 2, '0', STR_PAD_LEFT) . $chave, // Merchant Account Info
        '52' => '0000', // Merchant Category Code
        '53' => '986', // Transaction Currency (BRL - Real)
        '54' => $valor, // Transaction Amount
        '58' => 'BR', // Country Code
        '59' => substr($nome, 0, 25), // Merchant Name
        '60' => substr($cidade, 0, 15), // Merchant City
        '62' => '0503***' // Additional Data Field (TXID)
    ];
    
    $payload = '';
    foreach ($px as $k => $v) {
        $payload .= $k . str_pad(strlen($v), 2, '0', STR_PAD_LEFT) . $v;
    }
    
    // Adiciona a trava matemática no final
    return $payload . '6304' . calcularCrc16($payload);
}


?>

