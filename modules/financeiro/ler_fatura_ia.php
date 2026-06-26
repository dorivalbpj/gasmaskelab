<?php
/**
 * ENDPOINT: ler_fatura_ia.php
 * Lê um PDF de fatura de cartão de crédito usando a API do Gemini Vision
 * Retorna um JSON com os lançamentos extraídos para revisão
 * 
 * REQUISITOS:
 * - Recebe um arquivo PDF via multipart/form-data
 * - Recebe o ID da fatura atual via $_POST['fatura_id']
 * - Retorna JSON com: [{"descricao", "valor", "categoria_id", "data_compra"}, ...]
 */

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php';
require_once '../../includes/functions.php';

// ==================== VERIFICAÇÕES INICIAIS ====================
header('Content-Type: application/json; charset=utf-8');

// Valida sessão e permissões
if (!isset($_SESSION['user_id']) && !isset($_SESSION['usuario_id']) && !isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autenticado']);
    exit;
}

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['erro' => 'Sem permissão para acessar']);
    exit;
}

// Valida entrada
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido. Use POST']);
    exit;
}

$fatura_id = (int)($_POST['fatura_id'] ?? 0);
if (!$fatura_id) {
    http_response_code(400);
    echo json_encode(['erro' => 'fatura_id é obrigatório']);
    exit;
}

// Valida se arquivo foi enviado
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['erro' => 'Arquivo PDF não foi enviado corretamente']);
    exit;
}

$file = $_FILES['pdf'];

// Valida extensão e tipo MIME
$extensao = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mime = mime_content_type($file['tmp_name']);

if ($extensao !== 'pdf' || $mime !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['erro' => 'O arquivo deve ser um PDF válido']);
    exit;
}

// Valida tamanho (máximo 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['erro' => 'Arquivo muito grande (máximo 10MB)']);
    exit;
}

// ==================== BUSCA DADOS DA FATURA ====================
try {
    $stmt = $pdo->prepare("
        SELECT f.id, f.mes, f.ano, f.cartao_id, c.nome as cartao_nome 
        FROM fin_faturas f
        LEFT JOIN fin_cartoes c ON f.cartao_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$fatura_id]);
    $fatura = $stmt->fetch();
    
    if (!$fatura) {
        http_response_code(404);
        echo json_encode(['erro' => 'Fatura não encontrada']);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao consultar fatura: ' . $e->getMessage()]);
    exit;
}

// ==================== BUSCA CATEGORIAS DO BANCO ====================
try {
    $stmt_cat = $pdo->prepare("SELECT id, nome FROM fin_categorias ORDER BY nome ASC");
    $stmt_cat->execute();
    $categorias = $stmt_cat->fetchAll();
    
    // Formata para enviar ao Gemini
    $categorias_lista = [];
    foreach ($categorias as $cat) {
        $categorias_lista[] = $cat['id'] . ' - ' . $cat['nome'];
    }
    $categorias_texto = implode(', ', $categorias_lista);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao consultar categorias: ' . $e->getMessage()]);
    exit;
}

// ==================== LEITURA E CODIFICAÇÃO DO PDF ====================
try {
    $conteudo_pdf = file_get_contents($file['tmp_name']);
    if ($conteudo_pdf === false) {
        throw new Exception('Não foi possível ler o arquivo PDF');
    }
    
    $pdf_base64 = base64_encode($conteudo_pdf);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao processar PDF: ' . $e->getMessage()]);
    exit;
}

// ==================== CONSTRUÇÃO DO PROMPT PARA GEMINI ====================
/**
 * ENGINEERING DE PROMPT CRÍTICO:
 * O prompt deve ser EXTREMAMENTE RESTRITIVO para garantir que:
 * 1. Ignora linhas de ruído (pagamentos, estornos, juros, etc)
 * 2. Retorna APENAS um JSON válido (sem explicações)
 * 3. Lê apenas a parcela do mês atual
 * 4. Extrai apenas: descricao, valor, categoria_id, data_compra
 * 
 * NOTA: O Gemini 1.5 Flash tem visão de imagens/PDFs, não precisa de conversão prévia
 */

$prompt = <<<PROMPT
VOCÊ É UM LEITOR DE FATURAS DE CARTÃO DE CRÉDITO COM MÁXIMA PRECISÃO.

CONTEXTO:
- Analise a fatura de cartão de crédito do arquivo PDF em anexo
- A fatura é referente ao mês {$fatura_id} (Fatura ID: {$fatura_id})
- Identifique TODOS os gastos reais desta fatura

INSTRUÇÕES CRÍTICAS - IGNORE COMPLETAMENTE:
❌ Linhas com "Pagamento de fatura"
❌ Linhas com "Pagamento recebido"
❌ Linhas com "Saldo anterior"
❌ Linhas com "Saldo total"
❌ Linhas com "Estorno"
❌ Linhas com "Cancelamento"
❌ Linhas com "IOF"
❌ Linhas com "Juros"
❌ Linhas com "Multa"
❌ Qualquer linha que não seja um gasto/débito real
❌ Mensagens de rodapé ou informações administrativas

REGRA DE PARCELAMENTO:
- Se o gasto aparece como "Item 2/4" ou "Parcela 2/4", significa que é uma COMPRA PARCELADA
- Identifique a 'parcela_atual' e o 'total_parcelas'. Ex: 2/4 -> parcela_atual = 2, total_parcelas = 4
- O valor extraído deve ser EXATAMENTE o valor de apenas uma parcela (a que aparece na fatura)
- Retorne a descrição SEM a numeração da parcela. Ex: Se está "Apple 2/4", retorne descricao "Apple".

CATEGORIAS DISPONÍVEIS NO SISTEMA:
{$categorias_texto}

TAREFA:
Para cada gasto VÁLIDO encontrado, extraia:
1. descricao (string): Nome do estabelecimento/produto (limpo, sem info de parcela)
2. valor (float): Valor de apenas UMA parcela em reais.
3. categoria_id (int ou null)
4. data_compra (string): Data YYYY-MM-DD
5. parcela_atual (int): Se não for parcelado, retorne 1
6. total_parcelas (int): Se não for parcelado, retorne 1

FORMATO DE RETORNO (OBRIGATÓRIO):
Retorne APENAS um JSON válido, sem qualquer texto antes ou depois. Exemplo:
[
  {"descricao": "Uber", "valor": 25.50, "categoria_id": 5, "data_compra": "2024-10-15", "parcela_atual": 1, "total_parcelas": 1},
  {"descricao": "Apple", "valor": 100.00, "categoria_id": null, "data_compra": "2024-10-10", "parcela_atual": 2, "total_parcelas": 4}
]

⚠️ ERRO CRÍTICO: Se o JSON for inválido ou contiver texto extra, o sistema não funcionará. Retorne APENAS JSON.
PROMPT;

// ==================== REQUISIÇÃO PARA A API GEMINI ====================
try {
    // URL da API Gemini com chave
    $gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode(GEMINI_API_KEY);
    
    // Monta payload para o Gemini
    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inlineData' => [
                            'mimeType' => 'application/pdf',
                            'data' => $pdf_base64
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,  // Baixa temperatura para respostas mais determinísticas
            'topP' => 0.8,
            'topK' => 40,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json'
        ]
    ];
    
    $payload_json = json_encode($payload);
    
    // Inicializa cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $gemini_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
    
    // Executa requisição
    $resposta_gemini = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Valida resposta HTTP
    if ($curl_error) {
        throw new Exception('Erro cURL: ' . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("Gemini retornou HTTP {$http_code}. Resposta: {$resposta_gemini}");
    }
    
    // Decodifica resposta
    $resposta_decoded = json_decode($resposta_gemini, true);
    
    if (!$resposta_decoded || !isset($resposta_decoded['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception('Resposta inválida do Gemini: ' . $resposta_gemini);
    }
    
    $texto_resposta = $resposta_decoded['candidates'][0]['content']['parts'][0]['text'];
    
    // ==================== PARSING DA RESPOSTA JSON ====================
    /**
     * PARSING DEFENSIVO:
     * O Gemini pode retornar o JSON envolvido em tags de markdown (```json...```)
     * ou com espaços extras. Vamos limpar isso.
     */
    
    // 1. Isola apenas o conteúdo entre colchetes (ignora textos antes ou depois)
    if (preg_match('/\[.*\]/s', $texto_resposta, $matches)) {
        $texto_resposta = $matches[0];
    } else {
        $texto_resposta = str_replace(['```json', '```'], '', $texto_resposta);
        $texto_resposta = trim($texto_resposta);
    }
    
    // 2. Remove vírgulas "sobrando" no final de objetos/arrays (Trailing commas)
    $texto_resposta = preg_replace('/,\s*([\]}])/m', '$1', $texto_resposta);
    
    // Tenta decodificar JSON
    $lancamentos = json_decode($texto_resposta, true);
    
    if ($lancamentos === null) {
        $json_erro = json_last_error_msg();
        throw new Exception("Erro de Leitura ({$json_erro}). A IA pode ter sido interrompida (fatura muito extensa). Final da resposta: " . substr(trim($texto_resposta), -150));
    }
    
    if (!is_array($lancamentos)) {
        throw new Exception('Resposta do Gemini não é um array JSON');
    }
    
    // ==================== VALIDAÇÃO E SANITIZAÇÃO DOS DADOS ====================
    $lancamentos_validados = [];
    
    foreach ($lancamentos as $idx => $item) {
        // Validações
        if (!isset($item['descricao']) || !isset($item['valor'])) {
            error_log("Item {$idx} inválido: descricao ou valor faltando");
            continue;
        }
        
        $descricao = trim((string)$item['descricao']);
        $valor = (float)$item['valor'];
        $categoria_id = isset($item['categoria_id']) && $item['categoria_id'] !== null ? (int)$item['categoria_id'] : null;
        $data_compra = isset($item['data_compra']) ? trim((string)$item['data_compra']) : date('Y-m-d');
        
        $parcela_atual = isset($item['parcela_atual']) ? (int)$item['parcela_atual'] : 1;
        $total_parcelas = isset($item['total_parcelas']) ? (int)$item['total_parcelas'] : 1;
        if ($parcela_atual > $total_parcelas) $total_parcelas = $parcela_atual;
        
        // Validações de negócio
        if (strlen($descricao) === 0 || $descricao === '-' || $descricao === 'null') {
            error_log("Item {$idx}: Descrição vazia ou inválida");
            continue;
        }
        
        if ($valor <= 0) {
            error_log("Item {$idx}: Valor não positivo");
            continue;
        }
        
        // Valida formato de data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_compra)) {
            $data_compra = date('Y-m-d');
        }
        
        // Valida se categoria_id existe
        if ($categoria_id !== null) {
            $categoria_existe = false;
            foreach ($categorias as $cat) {
                if ($cat['id'] == $categoria_id) {
                    $categoria_existe = true;
                    break;
                }
            }
            if (!$categoria_existe) {
                $categoria_id = null;
            }
        }
        
        $lancamentos_validados[] = [
            'descricao' => $descricao,
            'valor' => round($valor, 2),
            'categoria_id' => $categoria_id,
            'data_compra' => $data_compra,
            'parcela_atual' => $parcela_atual,
            'total_parcelas' => $total_parcelas
        ];
    }
    
    // ==================== RETORNO ====================
    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'fatura_id' => $fatura_id,
        'cartao_nome' => $fatura['cartao_nome'] ?? 'Cartão',
        'mes' => (int)$fatura['mes'],
        'ano' => (int)$fatura['ano'],
        'total_itens' => count($lancamentos_validados),
        'lancamentos' => $lancamentos_validados
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Erro em ler_fatura_ia.php: ' . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
