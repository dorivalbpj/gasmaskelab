<?php
// test_gemini.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/gemini.php';

$api_key = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . $api_key;

// Teste 1: Verificar se a chave existe
if (empty($api_key)) {
    die("ERRO: Chave da API não configurada!");
}

// Teste 2: Testar conexão cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "contents" => [["parts" => [["text" => "Teste"]]]]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "=== DIAGNÓSTICO ===\n";
echo "Chave API: " . (empty($api_key) ? "❌ NÃO DEFINIDA" : "✅ DEFINIDA") . "\n";
echo "URL: " . $url . "\n";
echo "cURL Error: " . ($err ?: "✅ Sem erros") . "\n";
echo "HTTP Code: " . ($info['http_code'] ?? 'N/A') . "\n";
echo "Response: " . ($response ? substr($response, 0, 200) : "❌ SEM RESPOSTA") . "\n";
?>