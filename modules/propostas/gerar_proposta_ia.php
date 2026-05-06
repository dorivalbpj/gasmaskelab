<?php
// modules/propostas/gerar_proposta_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php'; // <--- AQUI ELE PUXA O SEU COFRE SECRETO

requireLogin();
if (!isAdmin()) { echo json_encode(['erro' => 'Acesso negado']); exit; }

$cliente_id = $_POST['cliente_id'] ?? 0;
if (!$cliente_id) { echo json_encode(['erro' => 'Cliente não informado']); exit; }

$stmt = $pdo->prepare("SELECT c.nome, b.respostas, b.servicos_interesse FROM clientes c LEFT JOIN briefings b ON c.id = b.cliente_id WHERE c.id = ? ORDER BY b.id DESC LIMIT 1");
$stmt->execute([$cliente_id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

$nome_cliente = $dados['nome'] ?? 'Cliente';
$info_briefing = $dados['respostas'] ?? 'Sem briefing.';

// Puxa a chave do cofre com segurança
$api_key = trim(GEMINI_API_KEY);

$prompt = "Você é o estrategista da Gasmaske Lab. Redija o corpo de uma proposta premium em HTML (tags h2, h3, p, ul, li) para o cliente $nome_cliente com base no briefing: $info_briefing. Foco em autoridade e design Adobe CC.";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

$data = ["contents" => [["parts" => [["text" => $prompt]]]]];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) { echo json_encode(['erro' => 'Erro cURL: ' . $err]); exit; }

$resArr = json_decode($response, true);
$texto = $resArr['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (!$texto) { echo json_encode(['erro' => 'IA não respondeu. Verifique sua conexão.']); exit; }

echo json_encode(['sucesso' => true, 'texto' => $texto]);
?>