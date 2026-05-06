<?php
// modules/propostas/gerar_proposta_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php';

requireLogin();
if (!isAdmin()) { echo json_encode(['erro' => 'Acesso negado']); exit; }

$cliente_id = $_POST['cliente_id'] ?? 0;
if (!$cliente_id) { echo json_encode(['erro' => 'Cliente não informado']); exit; }

$stmt = $pdo->prepare("SELECT c.nome, b.respostas, b.servicos_interesse FROM clientes c LEFT JOIN briefings b ON c.id = b.cliente_id WHERE c.id = ? ORDER BY b.id DESC LIMIT 1");
$stmt->execute([$cliente_id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

$nome_cliente = $dados['nome'] ?? 'Cliente';
$info_briefing = $dados['respostas'] ?? 'Nenhum briefing formal preenchido.';

if (!defined('GEMINI_API_KEY')) {
    echo json_encode(['erro' => 'A chave GEMINI_API_KEY não foi encontrada dentro do cofre!']);
    exit;
}
$api_key = trim(GEMINI_API_KEY);

$prompt = "Aja ESTRITAMENTE como o sistema gerador de propostas da agência premium Gasmaske Lab. 
REGRAS ABSOLUTAS:
1. NÃO converse comigo. NÃO diga 'Compreendido', 'Aqui está' ou 'Certo'.
2. Retorne APENAS E EXCLUSIVAMENTE o código HTML pronto.
3. NÃO use blocos de código markdown (como \`\`\`html).
4. Gere uma proposta longa, detalhada e completa. NÃO interrompa o texto no meio.

DADOS DO CLIENTE:
- Nome: $nome_cliente
- Briefing/Detalhes: $info_briefing

ESTRUTURA OBRIGATÓRIA DA PROPOSTA (Use tags <h2>, <h3>, <p>, <ul>, <li>, <strong>):
1. O Desafio Estratégico (Foque na dor do cliente)
2. Benchmarking e Direção Criativa (Padrão Gasmaske e ecossistema Adobe CC)
3. Escopo de Serviços Detalhado
4. Condições e Logística (Avisar que Tráfego Pago é pago à parte pelas plataformas)
5. Próximos Passos para fechamento";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => [
        "maxOutputTokens" => 8192,
        "temperature" => 0.7
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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

if (!$texto) {
    echo json_encode(['erro' => 'Google disse: ' . $response]);
    exit;
}

// Remove backticks de markdown caso o Gemini desobedeça
$texto = preg_replace('/^```html\s*/i', '', $texto);
$texto = preg_replace('/^```\s*/m', '', $texto);
$texto = preg_replace('/```\s*$/m', '', $texto);
$texto = trim($texto);

// Usa JSON_UNESCAPED_UNICODE para não quebrar caracteres especiais em português
// O json_encode já cuida de escapar as quebras de linha corretamente
echo json_encode(['sucesso' => true, 'texto' => $texto], JSON_UNESCAPED_UNICODE);
?>