<?php
// modules/contratos/gerar_contrato_ia.php
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/gemini.php';

requireLogin();

$p_id = $_POST['proposta_id'] ?? 0;
$cli_id = $_POST['cliente_id'] ?? 0;
$valor = $_POST['valor'] ?? '0.00';
$duracao = $_POST['duracao_meses'] ?? '1';

$dados_cliente = null;
$escopo = "Serviços de Marketing, Gestão de Conteúdo e Design Estratégico.";
$tipo_cobranca = 'mensal';

// 1. Tenta achar os dados e o escopo pela Proposta (se vier de uma)
if ($p_id > 0) {
    $stmt = $pdo->prepare("SELECT p.descricao, p.valor as prop_valor, p.duracao_meses as prop_duracao, p.tipo_cobranca as prop_tipo, c.* FROM propostas p JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$p_id]);
    $dados_cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if($dados_cliente) {
        $escopo = strip_tags($dados_cliente['descricao']);
        if (empty($_POST['valor']) || $_POST['valor'] == '0' || $_POST['valor'] == '0.00') $valor = $dados_cliente['prop_valor'];
        if (empty($_POST['duracao_meses'])) $duracao = $dados_cliente['prop_duracao'];
        $tipo_cobranca = $dados_cliente['prop_tipo'] ?? 'mensal';
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

$nome_cliente = $dados_cliente['nome'] ?? '';
$doc_cliente = $dados_cliente['cpf_cnpj'] ?? 'Será preenchido na assinatura';
$end_cliente = $dados_cliente['endereco'] ?? 'Será preenchido na assinatura';
$telefone_cliente = $dados_cliente['telefone'] ?? 'Não informado';
$email_cliente = $dados_cliente['email'] ?? 'Não informado';

$desc_pagamento = ($tipo_cobranca === 'unico') 
    ? "Pela prestação dos serviços descritos na proposta contratada, o CONTRATANTE pagará à CONTRATADA o valor total de R$ $valor. Sendo 50% de sinal para reserva de pauta e início do projeto, e 50% na aprovação e entrega final."
    : "Pela prestação dos serviços descritos na proposta contratada, o CONTRATANTE pagará à CONTRATADA o valor mensal recorrente de R$ $valor. O vencimento de cada mensalidade ocorrerá todo dia 10 de cada mês.";

$gatilho = ($tipo_cobranca === 'unico')
    ? "Gatilho de Início: O início efetivo de qualquer atividade, planejamento ou desenvolvimento por parte da CONTRATADA está estritamente condicionado à confirmação e compensação do pagamento de sinal (50%)."
    : "Gatilho de Início: O setup operacional e o início efetivo de qualquer atividade por parte da CONTRATADA estão estritamente condicionados à confirmação e compensação do pagamento da primeira mensalidade.";

$prompt = "Aja como o departamento jurídico da Gasmaske Lab. Eu tenho um modelo exato de contrato e quero que você retorne EXATAMENTE este modelo, substituindo as tags correspondentes com os dados abaixo, e redigindo de forma clara o escopo com base nestas informações:
ESCOPO PARA A CLÁUSULA 1: $escopo

Aqui estão os dados para preencher:
RAZAO_SOCIAL: $nome_cliente
DOCUMENTO: $doc_cliente
ENDERECO: $end_cliente
WHATSAPP: $telefone_cliente
EMAIL: $email_cliente
PRAZO_MESES: $duracao

REGRAS:
1. Retorne APENAS o contrato preenchido, sem adicionar nenhum texto antes ou depois.
2. Na cláusula 1.2, insira os módulos e entregáveis descritos no ESCOPO de forma organizada e profissional, em formato de lista simples.
3. Não utilize NENHUMA formatação markdown (sem asteriscos, sem negrito, sem itálico) pois o sistema removerá. Use MAIÚSCULAS para dar ênfase a títulos.
4. Mantenha os placeholders {{SISTEMA_DATA_HORA}} e {{SISTEMA_IP}} EXATAMENTE COMO ESTÃO no texto abaixo, pois o sistema PHP irá preenchê-los dinamicamente.

MODELO DE CONTRATO A SER SEGUIDO ESTRITAMENTE:

▸ DADOS DO CONTRATANTE

Nome / Razão Social: $nome_cliente
CPF / CNPJ: $doc_cliente
Endereço: $end_cliente
WhatsApp: $telefone_cliente
E-mail: $email_cliente

▸ REGISTRO DE ASSINATURA DIGITAL

O contratante leu e aceitou os termos clicando em \"Assinar e Iniciar Projeto\".
Data e Hora: {{SISTEMA_DATA_HORA}}
IP de Origem: {{SISTEMA_IP}}
Este registro possui validade jurídica conforme a Lei nº 14.063/2020 (Assinatura Digital).

---

CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE MARKETING E DESENVOLVIMENTO

Pelo presente instrumento particular, as partes abaixo qualificadas celebram este contrato sob as seguintes cláusulas e condições:

CONTRATADA:
Gasmaske Lab – Marketing e Desenvolvimento, inscrita no CNPJ sob o nº 58.714.373/0001-04, com sede na Cidade de Vila Velha/ES, neste ato representada por Viviane de Souza Araujo.
Dados Bancários para Pagamento: Banco Inter (077) | Agência: 0001 | Conta: 47564779-3 | Chave PIX (CNPJ): 58.714.373/0001-04.

CONTRATANTE:
A pessoa física ou jurídica qualificada automaticamente no bloco de metadados de assinatura digital deste instrumento ($nome_cliente).

---

CLÁUSULA 1ª – DO OBJETO E ESCOPO DOS SERVIÇOS (MODULAR)

1.1. O presente contrato tem por objeto a prestação de serviços profissionais pela CONTRATADA ao CONTRATANTE.
1.2. O escopo específico dos serviços contratados será preenchido dinamicamente de acordo com a proposta comercial ativa, sendo inseridos aqui os módulos correspondentes:

[INSIRA AQUI O ESCOPO DETALHADO BASEADO NO TEXTO FORNECIDO E FORMATE COMO UMA LISTA CLARA E OBJETIVA]

1.3. Trava de Escopo: Qualquer atividade, plataforma, rede social ou formato que não esteja expressamente listado no módulo acima é considerado fora de escopo. A inclusão de novas demandas exigirá a formulação de um aditivo contratual ou a emissão de um novo orçamento comercial.

CLÁUSULA 2ª – DO FLUXO DE TRABALHO, CRONOGRAMA E INSUMOS

2.1. O CONTRATANTE compromete-se a fornecer toda a matéria-prima (informações, logotipos, senhas, diretrizes, fotos e vídeos originais) necessária para a execução das atividades nos prazos solicitados pela CONTRATADA.
2.2. Resiliência de Cronograma por Falta de Material: Caso o CONTRATANTE atrase ou deixe de enviar os insumos obrigatórios para a produção das peças ou desenvolvimento, a CONTRATADA fica isenta do cumprimento do calendário e das metas de entrega daquele período. Fica pactuado que tal atraso por parte do cliente não gerará qualquer direito a descontos, compensações ou reembolsos nas mensalidades devidas, que continuarão vencendo normalmente.
2.3. O CONTRATANTE terá o prazo de até 48 (quarenta e oito) horas úteis para analisar e aprovar ou solicitar alterações nos materiais e cronogramas enviados. A ausência de resposta dentro deste prazo configurará aprovação tácita, autorizando a CONTRATADA a dar seguimento ao fluxo operacional para não travar o cronograma do projeto.

CLÁUSULA 3ª – DA COMUNICAÇÃO OFICIAL E HORÁRIO DE ATENDIMENTO

3.1. O suporte, os alinhamentos estratégicos, o envio de relatórios e a entrega de materiais ocorrerão exclusivamente através dos canais oficiais definidos pela CONTRATADA (ex: grupo exclusivo de WhatsApp e/ou e-mail).
3.2. Todas as interações e atendimentos serão realizados estritamente em horário comercial: de segunda a sexta-feira, das 09h às 18h. Solicitações recebidas fora deste período ou em dias não úteis serão formalmente computadas e respondidas no próximo dia útil subsequente.

CLÁUSULA 4ª – DAS RODADAS DE AJUSTES E REFACÇÕES

4.1. Conforme detalhado no escopo do serviço selecionado, o CONTRATANTE terá direito ao limite padrão de rodadas de ajustes finos por lote ou peça enviada estipulado na proposta.
4.2. Caso o CONTRATANTE exija alterações estruturais após a aprovação prévia, mude a diretriz original ou demande rodadas de refações que superem o limite estipulado, a execução destas atividades gerará a necessidade de aprovação de um novo orçamento complementar, de acordo com a tabela vigente da CONTRATADA.

CLÁUSULA 5ª – FERRAMENTAS DE TERCEIROS, LICENÇAS E ANÚNCIOS

5.1. Os honorários mensais pagos à CONTRATADA cobrem exclusivamente as horas técnicas e a prestação do serviço contratado.
5.2. Quaisquer custos adicionais referentes à aquisição de licenças de softwares específicos, plugins premium, hospedagens extras, domínios, APIs pagas ou plataformas de automação solicitadas pelo projeto serão de inteira responsabilidade do CONTRATANTE.
5.3. Verba de Tráfego (Quando aplicável): O orçamento destinado à veiculação de anúncios (Meta Ads, Google Ads, etc.) é definido pelo CONTRATANTE e pago diretamente às respectivas plataformas, não compondo o valor deste contrato. A CONTRATADA não se responsabiliza pela gestão financeira destas contas de anúncio.

CLÁUSULA 6ª – DOS ACESSOS, PLATAFORMAS E LIMITAÇÃO DE RESPONSABILIDADE

6.1. O CONTRATANTE obriga-se a fornecer todos os acessos administrativos (logins, senhas e tokens) indispensáveis às ferramentas digitais e redes sociais. Eventuais atrasos na liberação de acessos não suspendem o vencimento das parcelas contratuais.
6.2. Isenção por Ações de Terceiros: A CONTRATADA utiliza as melhores práticas de mercado, contudo, não possui controle sobre as políticas internas e algoritmos de empresas terceiras (Google, Meta, TikTok, WordPress). Dessa forma, a CONTRATADA fica totalmente isenta de qualquer responsabilidade legal ou financeira por eventuais bloqueios, quedas de alcance, suspensões de contas de anúncios ou banimentos que ocorram nas plataformas do CONTRATANTE.
6.3. Natureza das Obrigações e Ausência de Garantia de Resultados: Fica expressamente pactuado entre as partes que as obrigações assumidas pela CONTRATADA por meio deste instrumento são de meio (aplicação de técnica, esforço, expertise e zelo profissional) e não de resultado. Por depender diretamente de fatores externos imponderáveis — tais como oscilações de mercado, ações da concorrência, atualizações de algoritmos e decisões exclusivas de compra dos consumidores —, a CONTRATADA não garante metas numéricas ou resultados específicos de Retorno sobre Investimento (ROI), volume de leads, ganho quantitativo de seguidores, faturamento ou conversões de vendas, restando totalmente isenta de responsabilidade civil, indenizatória ou rescisória em caso de não atingimento de expectativas puramente comerciais do CONTRATANTE.

CLÁUSULA 7ª – VALORES E FORMA DE PAGAMENTO

7.1. $desc_pagamento
7.2. Os pagamentos deverão ser realizados via PIX ou transferência bancária para as contas indicadas no preâmbulo deste contrato.
7.3. $gatilho
7.4. Em caso de atraso no pagamento, incidirá multa moratória de 2% (dois por cento), juros de mora de 1% (um por cento) ao mês cobrados pro rata die e correção monetária.
7.5. Atrasos superiores a 15 (quinze) dias no pagamento autorizam a CONTRATADA a paralisar e suspender imediatamente todas as atividades operacionais, campanhas de tráfego pago e postagens, até a integral regularização do débito, sem prejuízo ao tempo de vigência do contrato.

CLÁUSULA 8ª – DA PROPRIEDADE INTELECTUAL E USO DE PORTFÓLIO

8.1. Todo e qualquer material desenvolvido pela CONTRATADA em virtude deste contrato (incluindo artes, criativos, designs, roteiros, códigos e estruturas de sites) permanecerá sob a propriedade intelectual exclusiva da CONTRATADA até a quitação integral das respectivas parcelas e competências contratuais.
8.2. Após o pagamento integral das obrigações financeiras, os direitos de uso e publicação dos materiais finais aprovados serão transferidos automaticamente ao CONTRATANTE. Os arquivos abertos ou projetos de edição permanecem sob posse da agência.
8.3. Direito de Portfólio: A CONTRATADA reserva-se o direito de utilizar as peças criadas, layouts, identidades visuais e métricas de desempenho (preservando informações sigilosas) em suas próprias redes sociais, site e apresentações comerciais, para fins exclusivos de portfólio e demonstração de expertise.

CLÁUSULA 9ª – DA CONFIDENCIALIDADE E PROTEÇÃO DE DADOS (LGPD)

9.1. Ambas as partes comprometem-se a manter sigilo absoluto sobre quaisquer informações comerciais, dados de faturamento, estratégias internas, listas de clientes e credenciais trocadas ao longo da vigência deste contrato.
9.2. É expressamente vedado ao CONTRATANTE compartilhar com terceiros (incluindo outras agências ou profissionais) as metodologias exclusivas, estruturas de funil e estratégias aplicadas pela CONTRATADA.
9.3. No tratamento de dados pessoais eventualmente coletados em campanhas, as partes obrigam-se a cumprir rigorosamente as determinações da Lei Geral de Proteção de Dados (Lei nº 13.709/2018 - LGPD).

CLÁUSULA 10ª – DA VIGÊNCIA E RESCISÃO CONTRATUAL

10.1. O presente contrato terá vigência pelo prazo estipulado de $duracao meses, contados a partir da confirmação do primeiro pagamento.
10.2. Após o período inicial, o contrato poderá ser renovado automaticamente por iguais períodos ou mediante novo acordo comercial entre as partes.
10.3. O contrato poderá ser rescindido por qualquer uma das partes a qualquer momento, mediante aviso prévio formal e por escrito de, no mínimo, 30 (trinta) dias.
10.4. Taxa Compensatória por Rescisão Antecipada: Caso o CONTRATANTE solicite a rescisão imotivada do contrato antes do término da vigência mínima acordada, será cobrada uma multa compensatória equivalente ao valor de 01 (uma) parcela integral vigente, além da quitação de eventuais saldos em aberto por serviços já prestados.

CLÁUSULA 11ª – DA AUSÊNCIA DE VÍNCULO TRABALHISTA

11.1. Fica expressamente declarado e reconhecido pelas partes que este contrato regula uma relação de natureza estritamente civil e comercial entre pessoas jurídicas ou autônomos prestadores de serviço.
11.2. Não há, em hipótese alguma, estabelecimento de vínculo empregatício, subordinação jurídica, exclusividade ou dependência econômica entre a CONTRATADA (ou seus colaboradores/sócios) e o CONTRATANTE, mantendo cada parte total autonomia em suas estruturas de trabalho.

CLÁUSULA 12ª – DA REVOGAÇÃO DE ACESSOS AO TÉRMINO DO CONTRATO

12.1. Ocorrendo a extinção, rescisão ou término regular deste contrato, a CONTRATADA providenciará a imediata remoção de todos os seus acessos administrativos das páginas, gerenciadores de anúncios e servidores do CONTRATANTE.
12.2. O CONTRATANTE assume a obrigação e responsabilidade de alterar todas as senhas que haviam sido compartilhadas para a execução do escopo em até 48 (quarenta e oito) horas após o encerramento, ficando a CONTRATADA totalmente isenta de qualquer responsabilidade sobre acessos ou eventos posteriores nas contas.

CLÁUSULA 13ª – CASO FORTUITO, FORÇA MAIOR E DISPOSIÇÕES GERAIS

13.1. Nenhuma das partes será responsabilizada pelo atraso ou falha no cumprimento de suas obrigações decorrentes de Caso Fortuito ou Força Maior (art. 393 do Código Civil), tais como instabilidades ou quedas globais nos servidores das redes sociais, interrupções prolongadas no fornecimento de energia elétrica/internet por concessionárias, ou outras panes de infraestrutura técnica externa.
13.2. As tolerâncias contratuais praticadas por qualquer das partes não implicam renúncia de direitos ou novação de cláusulas.
13.3. Este instrumento e seu registro eletrônico substituem integralmente quaisquer acordos anteriores, sejam eles verbais ou escritos, firmados entre as partes sobre o mesmo objeto.

CLÁUSULA 14ª – DO FORO

14.1. Fica eleito o foro da Comarca de Vila Velha, Estado do Espírito Santo, para dirimir quaisquer dúvidas, controvérsias ou litígios oriundos deste instrumento, com expressa renúncia a qualquer outro, por mais privilegiado que se apresente.";

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    "generationConfig" => ["maxOutputTokens" => 8192, "temperature" => 0.2] // Temperatura reduzida para 0.2
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($response === false || $err) {
    echo json_encode(['erro' => 'Erro cURL: ' . ($err ?: 'Falha na requisiçã')]); 
    exit;
}

$resArr = json_decode($response, true);
$texto = $resArr['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (!$texto) { echo json_encode(['erro' => 'Falha de comunicação com o Google.']); exit; }

echo json_encode(['sucesso' => true, 'texto' => trim($texto)]);
?>