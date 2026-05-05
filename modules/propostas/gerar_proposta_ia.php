<?php
// modules/propostas/gerar_proposta_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';

requireLogin();
if (!isAdmin()) {
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

$cliente_id = $_POST['cliente_id'] ?? 0;
if (!$cliente_id) {
    echo json_encode(['erro' => 'Cliente não informado']);
    exit;
}

$stmt = $pdo->prepare("SELECT c.nome, b.respostas, b.servicos_interesse FROM clientes c LEFT JOIN briefings b ON c.id = b.cliente_id WHERE c.id = ? ORDER BY b.id DESC LIMIT 1");
$stmt->execute([$cliente_id]);
$dados = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dados) {
    echo json_encode(['erro' => 'Cliente não encontrado']);
    exit;
}

$nome_cliente = $dados['nome'];
$info_briefing = $dados['respostas'] ? $dados['respostas'] : 'Nenhum briefing formal preenchido.';
$servicos = $dados['servicos_interesse'] ? $dados['servicos_interesse'] : 'Não especificado.';

// Chave da API com limpeza agressiva para matar o erro "Bad IPv6"
$api_key = 'AIzaSyDsVtnQ1gl_BJkVxwi1A7lYwlTrF4xc2Do';
$api_key = preg_replace('/\s+/', '', $api_key); // Arranca qualquer espaço ou enter invisível

$prompt = "Você é o estrategista de marketing da Gasmaske Lab, uma agência premium. 
Sua missão é redigir o corpo de uma proposta comercial de alto nível em formato HTML (usando apenas tags de formatação como <h2>, <h3>, <p>, <ul>, <li> e <strong>). NÃO inclua a tag ```html no texto, retorne o HTML puro.

Cliente: $nome_cliente
Serviços solicitados: $servicos
Informações do Briefing: $info_briefing

A proposta DEVE seguir esta estrutura executiva:
1. O Desafio Estratégico: Foque na dor do nicho do cliente e como a Gasmaske Lab atua não como fornecedora, mas como boutique de negócios.
2. Benchmarking e Direção Criativa: Mencione que o projeto terá padrão estético inspirado em grandes referências do mercado dele.
3. Escopo de Serviços: Detalhe a entrega (ex: Gestão Omnichannel, Design no ecossistema Adobe CC, Tráfego Pago ou Captação Audiovisual Cinematográfica).
4. Limitações e Logística: Se houver anúncios, deixe explícito que a verba do tráfego pago (Meta/Google Ads) não está inclusa no valor da agência e é paga à parte.
5. Condições Gerais: Mencione que o trabalho só se inicia após a compensação do primeiro pagamento.

Use um tom de voz direto, luxuoso, confiante e focado em resultados.";

// ATUALIZADO PARA O GEMINI 2.5 FLASH!
$url = "[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=)" . $api_key;
$url = preg_replace('/\s+/', '', $url); // Blindagem final na URL

$data = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Mantém funcionando no XAMPP local
$response = curl_exec($ch);
$erro_curl = curl_error($ch);
curl_close($ch);

if ($erro_curl) {
    echo json_encode(['erro' => 'Erro de servidor (cURL): ' . $erro_curl]);
    exit;
}

$resultado = json_decode($response, true);
$texto_gerado = $resultado['candidates'][0]['content']['parts'][0]['text'] ?? '';

if(empty($texto_gerado)) {
    echo json_encode(['erro' => 'A IA não retornou o texto. Resposta da API: ' . $response]);
} else {
    echo json_encode(['sucesso' => true, 'texto' => $texto_gerado]);
}
?>