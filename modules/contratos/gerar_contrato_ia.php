<?php
// modules/contratos/gerar_contrato_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php';

requireLogin();

$p_id = $_POST['proposta_id'] ?? 0;
$cli_id = $_POST['cliente_id'] ?? 0;

$dados_cliente = null;
$escopo = "Serviços de Marketing, Gestão de Conteúdo e Design Estratégico.";

// 1. Tenta achar os dados e o escopo pela Proposta (se vier de uma)
if ($p_id > 0) {
    $stmt = $pdo->prepare("SELECT p.descricao, c.* FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$p_id]);
    $dados_cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if($dados_cliente) {
        $escopo = strip_tags($dados_cliente['descricao']);
    }
} 

// 2. Se não veio de proposta, puxa direto da tabela do Cliente
if (!$dados_cliente && $cli_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cli_id]);
    $dados_cliente = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$dados_cliente) {
    echo json_encode(['erro' => 'Não foi possível localizar os dados do cliente no banco. Selecione um cliente válido.']);
    exit;
}

$api_key = preg_replace('/[^a-zA-Z0-9_-]/', '', trim(GEMINI_API_KEY));
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

// O NOVO PROMPT BLINDADO
$prompt = "Aja como o departamento jurídico da Gasmaske Lab. Redija um CONTRATO DE PRESTAÇÃO DE SERVIÇOS profissional e imponente.
CONTRATADA: Gasmaske Lab - Marketing e Desenvolvimento (CNPJ: 58.714.373/0001-04), Vila Velha/ES. Representante: Viviane de Souza Araujo. Dados Bancários: Banco Inter (077) | Ag: 0001 | Conta: 47564779-3 | PIX (CNPJ): 58714373000104.
CONTRATANTE: " . $dados_cliente['nome'] . " (Doc: " . ($dados_cliente['cpf_cnpj'] ?? 'Não informado') . "), Endereço: " . ($dados_cliente['endereco'] ?? 'Não informado') . ".

REGRAS DE FORMATAÇÃO:
1. Use LETRAS MAIÚSCULAS nos títulos das cláusulas. 
2. Sem marcações HTML. Sem Markdown (asteriscos). Seja direto.
3. ESTRITAMENTE PROIBIDO: NÃO crie linhas de assinatura (___), espaços para assinar ou datas no final do documento. A assinatura será 100% digital via IP, então termine o texto abruptamente após a cláusula de Foro.

CLÁUSULAS OBRIGATÓRIAS:
1. OBJETO (Baseie-se neste escopo: $escopo)
2. OBRIGAÇÕES (Gasmaske usa pacote Adobe; SAC, relacionamento e Vendas são de inteira responsabilidade do cliente)
3. FLUXO E APROVAÇÃO (Cliente tem 48h para aprovar, senão é aprovação tácita. Limite de 1 rodada de ajustes finos)
4. PROPRIEDADE INTELECTUAL E PORTFÓLIO (Arquivos na nuvem por 30 dias após rescisão. É OBRIGATÓRIO constar que a Gasmaske Lab reserva-se o direito de utilizar as peças criativas, a marca do cliente e os resultados obtidos em seu portfólio, site e redes sociais para demonstração de expertise comercial.)
5. PAGAMENTO E ATRASOS (Multa 2%, Juros 1% ao mês. Suspensão imediata dos serviços com 15 dias de atraso)
6. FORO (Elege-se o foro de Vila Velha/ES para dirimir quaisquer controvérsias).";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["maxOutputTokens" => 8192, "temperature" => 0.3] // Temperatura ainda mais baixa (0.3) para garantir obediência cega ao formato jurídico
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$resArr = json_decode($response, true);
$texto = $resArr['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (!$texto) { echo json_encode(['erro' => 'Falha de comunicação com o Google.']); exit; }

echo json_encode(['sucesso' => true, 'texto' => trim($texto)]);
?>