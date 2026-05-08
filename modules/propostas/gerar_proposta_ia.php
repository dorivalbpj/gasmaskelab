<?php
// modules/propostas/gerar_proposta_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php';

requireLogin();
if (!isAdmin()) { echo json_encode(['erro' => 'Acesso negado']); exit; }

$cliente_id = $_POST['cliente_id'] ?? 0;
// Recebe o texto que estava no editor da tela!
$contexto_briefing = $_POST['contexto_briefing'] ?? '';

if (!$cliente_id) { echo json_encode(['erro' => 'Cliente não informado']); exit; }
if (empty($contexto_briefing)) { echo json_encode(['erro' => 'O briefing não foi recebido.']); exit; }

// Puxa apenas o nome do cliente pra IA saber de quem tá falando
$stmt = $pdo->prepare("SELECT nome FROM clientes WHERE id = ?");
$stmt->execute([$cliente_id]);
$nome_cliente = $stmt->fetchColumn() ?: 'Cliente';

if (!defined('GEMINI_API_KEY')) {
    echo json_encode(['erro' => 'Chave GEMINI_API_KEY não encontrada no cofre!']);
    exit;
}

// Blindagem da API Key
$api_key = trim(GEMINI_API_KEY);
$host = "https://generativelanguage.googleapis.com";
$endpoint = "/v1beta/models/gemini-2.5-flash:generateContent";
$url_suja = $host . $endpoint . "?key=" . $api_key;
$url_limpa = preg_replace('/[\s\x00-\x1F\x7F]/', '', $url_suja);

// PROMPT SNIPER DA GASMASKE LAB
$prompt = "Aja ESTRITAMENTE como o estrategista chefe da agência premium Gasmaske Lab. 
Sua missão é SUBSTITUIR o texto de briefing bruto abaixo por uma PROPOSTA COMERCIAL ULTRA-PERSONALIZADA.

REGRAS ABSOLUTAS:
1. Retorne APENAS o código HTML pronto (não use markdown ```html).
2. Tom de voz: Premium, implacável, focado em autoridade e fechamento de alto nível (zero 'design fofo').
3. VOCÊ DEVE OBRIGATORIAMENTE ler os problemas, dores e serviços contidos no texto de briefing e USÁ-LOS como base para a sua proposta.

DADOS DO CLIENTE E TEXTO DE BRIEFING CAPTURADO:
- Nome do Cliente: $nome_cliente
- CONTEÚDO DO BRIEFING A SER ANALISADO:
$contexto_briefing

ESTRUTURA OBRIGATÓRIA DA PROPOSTA (Use as tags <h2>, <p>, <ul>, <li>, <strong>):

<h2>O Cenário Atual</h2>
(Escreva 2 parágrafos imponentes mostrando que a Gasmaske entendeu perfeitamente as dores, as redes sociais e os objetivos relatados no briefing.)

<h2>A Arquitetura da Solução (O Escopo)</h2>
(Com base no briefing, crie uma lista <ul> detalhando os serviços que serão executados. 
CADA item <li> DEVE obrigatoriamente começar com <strong>Nome do Serviço:</strong> seguido da explicação prática de como isso resolve a dor do cliente.)

<h2>A Execução</h2>
(Escreva sobre a dinâmica de trabalho, o nível técnico da Gasmaske Lab e os próximos passos para iniciar o projeto.)";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["maxOutputTokens" => 8192, "temperature" => 0.4] 
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_limpa);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) { 
    echo json_encode(['erro' => 'Erro cURL: ' . $err]); 
    exit; 
}

$resArr = json_decode($response, true);
$texto = $resArr['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (!$texto) { echo json_encode(['erro' => 'Google falhou.']); exit; }

$texto = str_replace(["\r\n", "\r", "\n"], ' ', $texto);
echo json_encode(['sucesso' => true, 'texto' => $texto]);
?>