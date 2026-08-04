<?php
// modules/contratos/gerar_contrato.php

require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();
if (!isAdmin()) die("Acesso negado.");

$id = $_GET['id'] ?? 0;

if (!$id) {
    die("ID do contrato não informado.");
}

// Busca os dados do contrato COM TODOS OS CAMPOS
$stmt = $pdo->prepare("
    SELECT c.*, 
           cli.nome AS cliente_nome, 
           cli.email AS cliente_email, 
           cli.telefone AS cliente_telefone,
           cli.cpf_cnpj AS cliente_cpf_cnpj,
           cli.endereco AS cliente_endereco,
           cli.endereco_completo AS cliente_endereco_completo,
           cli.dados_faturamento
    FROM contratos c
    JOIN clientes cli ON c.cliente_id = cli.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contrato = $stmt->fetch();

if (!$contrato) {
    die("Contrato não encontrado.");
}

// ========== DADOS DA ASSINATURA ==========
$cpf_cnpj_aceite = $contrato['cpf_cnpj_aceite'] ?? 'Não assinado';
$aceito_em = $contrato['aceito_em'] ? date('d/m/Y H:i:s', strtotime($contrato['aceito_em'])) : 'Não assinado';
$aceito_ip = $contrato['aceito_ip'] ?? 'Não registrado';

// ========== PREPARA O TEXTO DO CONTRATO ==========
$texto_contrato = $contrato['texto_contrato'] ?? '';

// Substitui os placeholders pelos dados REAIS da assinatura
$texto_contrato = str_replace('{{SISTEMA_DATA_HORA}}', $aceito_em, $texto_contrato);
$texto_contrato = str_replace('{{SISTEMA_IP}}', $aceito_ip, $texto_contrato);

// Se ainda tiver algum placeholder de CPF/CNPJ ou endereço, substitui também
$texto_contrato = str_replace('Será preenchido na assinatura', $cpf_cnpj_aceite, $texto_contrato);

// ========== FORMATANDO VALORES ==========
$valor_parcela = number_format($contrato['valor'] ?? 0, 2, ',', '.');
$duracao = (int)($contrato['duracao_meses'] ?? 1);
$valor_total = number_format(($contrato['valor'] ?? 0) * $duracao, 2, ',', '.');
$data_atual = date('d/m/Y H:i:s');
$status_label = str_replace('_', ' ', $contrato['status']);

// Endereço do cliente (prioriza o endereço_completo)
$endereco_cliente = $contrato['cliente_endereco_completo'] ?? $contrato['cliente_endereco'] ?? 'Não informado';

// ========== MONTA O CONTEÚDO DO TXT ==========
$conteudo = "=" . str_repeat("=", 78) . "=\n";
$conteudo .= "                    CONTRATO DE PRESTAÇÃO DE SERVIÇOS\n";
$conteudo .= "                  GASMASKE LAB - Assessoria Musical\n";
$conteudo .= "=" . str_repeat("=", 78) . "=\n\n";

$conteudo .= "📄 CÓDIGO: " . $contrato['codigo_agc'] . "\n";
$conteudo .= "📅 DATA DE GERAÇÃO: " . $data_atual . "\n";
$conteudo .= "📊 STATUS: " . strtoupper($status_label) . "\n\n";

// ========== DADOS DO CONTRATANTE ==========
$conteudo .= str_repeat("-", 78) . "\n";
$conteudo .= "DADOS DO CONTRATANTE\n";
$conteudo .= str_repeat("-", 78) . "\n\n";

$conteudo .= "NOME / RAZÃO SOCIAL: " . $contrato['cliente_nome'] . "\n";
$conteudo .= "CPF / CNPJ: " . $cpf_cnpj_aceite . "\n";
$conteudo .= "E-MAIL: " . ($contrato['cliente_email'] ?? 'Não informado') . "\n";
$conteudo .= "TELEFONE: " . ($contrato['cliente_telefone'] ?? 'Não informado') . "\n";
$conteudo .= "ENDEREÇO: " . $endereco_cliente . "\n\n";

// ========== DADOS DO CONTRATO ==========
$conteudo .= str_repeat("-", 78) . "\n";
$conteudo .= "DADOS DO CONTRATO\n";
$conteudo .= str_repeat("-", 78) . "\n\n";

$conteudo .= "VALOR MENSAL: R$ " . $valor_parcela . "\n";
$conteudo .= "DURAÇÃO: " . $duracao . " meses\n";
$conteudo .= "VALOR TOTAL: R$ " . $valor_total . "\n";
$conteudo .= "DIA DE VENCIMENTO: " . ($contrato['dia_vencimento'] ?? 'Não definido') . "\n\n";

// ========== REGISTRO DE ASSINATURA DIGITAL ==========
$conteudo .= str_repeat("-", 78) . "\n";
$conteudo .= "REGISTRO DE ASSINATURA DIGITAL\n";
$conteudo .= str_repeat("-", 78) . "\n\n";

$conteudo .= "DOCUMENTO: " . $cpf_cnpj_aceite . "\n";
$conteudo .= "DATA E HORA: " . $aceito_em . "\n";
$conteudo .= "IP DE ORIGEM: " . $aceito_ip . "\n";
$conteudo .= "VALIDADE JURÍDICA: Lei nº 14.063/2020 (Assinatura Digital)\n\n";

// ========== CLÁUSULAS DO CONTRATO (COM PLACEHOLDERS SUBSTITUÍDOS) ==========
$conteudo .= str_repeat("-", 78) . "\n";
$conteudo .= "CLÁUSULAS DO CONTRATO\n";
$conteudo .= str_repeat("-", 78) . "\n\n";

// Adiciona o texto do contrato COM OS PLACEHOLDERS JÁ SUBSTITUÍDOS
$conteudo .= $texto_contrato . "\n\n";

// ========== RODAPÉ ==========
$conteudo .= str_repeat("=", 78) . "\n";
$conteudo .= "Documento gerado eletronicamente em " . $data_atual . "\n";
$conteudo .= "Gasmaske Lab - " . BASE_URL . "\n";
$conteudo .= "Este documento tem fé pública e validade jurídica nos termos da Lei nº 14.063/2020.\n";
$conteudo .= "Verifique a autenticidade pelo código do contrato: " . $contrato['codigo_agc'] . "\n";
$conteudo .= str_repeat("=", 78) . "\n";

// Define o nome do arquivo
$nome_arquivo = 'Contrato_' . $contrato['codigo_agc'] . '_' . date('Y-m-d') . '.txt';

// Força o download
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
header('Content-Length: ' . strlen($conteudo));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $conteudo;
exit;