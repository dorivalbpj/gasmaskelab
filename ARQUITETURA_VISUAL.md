# 🏗️ ARQUITETURA DA SOLUÇÃO - Diagramas Visuais

## 1️⃣ Fluxo Geral (Alto Nível)

```
┌────────────────────────────────────────────────────────────────────────┐
│                          SISTEMA GASMASKE                              │
│                    (módulos/financeiro/fatura.php)                     │
└────────────────────────────┬─────────────────────────────────────────┘
                             │
                    [Usuário clica em]
                  "Importar PDF (Gemini)"
                             │
                  ┌──────────┴──────────┐
                  ▼                     ▼
        ┌─────────────────┐   ┌──────────────────┐
        │  MODAL UPLOAD   │   │ TELA DE REVISÃO  │
        │  (Hidden)       │   │ (Hidden)         │
        └────────┬────────┘   └──────────────────┘
                 │
         [Input PDF File]
                 │
                 ▼
    ┌──────────────────────────┐
    │ VALIDAR ARQUIVO          │
    │ - Tipo: application/pdf  │
    │ - Tamanho < 10MB         │
    └──────────┬───────────────┘
               │
         ✓ Válido
               │
               ▼
    ┌──────────────────────────┐
    │ ENVIAR VIA FETCH (AJAX)  │
    │ POST ler_fatura_ia.php   │
    │ Body: FormData(PDF)      │
    └──────────┬───────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │ BACKEND: ler_fatura_ia.php   │
    │ 1. Validar sessão            │
    │ 2. Buscar categorias         │
    │ 3. Converter PDF → Base64    │
    │ 4. Montar prompt Gemini      │
    │ 5. cURL → API Gemini         │
    │ 6. Parsar JSON               │
    │ 7. Retornar lançamentos      │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────┐
    │ RESPOSTA JSON            │
    │ {                        │
    │   sucesso: true,         │
    │   lancamentos: [...]     │
    │ }                        │
    └──────────┬───────────────┘
               │
               ▼
    ┌──────────────────────────────────┐
    │ FRONTEND JAVASCRIPT:             │
    │ 1. Receber JSON                  │
    │ 2. Buscar lançamentos existentes  │
    │    GET obter_lancamentos_...     │
    │ 3. Detectar duplicidades         │
    │ 4. Renderizar tabela de revisão  │
    │ 5. Exibir form para edição       │
    └──────────┬────────────────────────┘
               │
    [Usuário revisa e edita]
               │
               ▼
    ┌──────────────────────────────┐
    │ SALVAR LANÇAMENTOS SELECIONADOS
    │ POST salvar_lancamentos_...  │
    │ Body: JSON {                 │
    │   fatura_id: 123,            │
    │   lancamentos: [...]         │
    │ }                            │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │ BACKEND: salvar_...php       │
    │ 1. Iniciar transação         │
    │ 2. Loop INSERT               │
    │ 3. COMMIT ou ROLLBACK        │
    │ 4. Retornar total_salvo      │
    └──────────┬───────────────────┘
               │
               ▼
    ┌──────────────────────────────┐
    │ RESPOSTA: total_salvo = N    │
    └──────────┬───────────────────┘
               │
               ▼
         RELOAD PÁGINA
         (mostra novos lançamentos)
```

---

## 2️⃣ Componentes de Backend

```
┌─────────────────────────────────────────────────────────────┐
│                         BACKEND (PHP)                       │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─ ler_fatura_ia.php (POST) ────────────────────────────┐  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 1. VALIDAÇÕES INICIAIS                          │  │  │
│  │  ├─ Sessão autenticada?                           │  │  │
│  │  ├─ isAdmin()?                                    │  │  │
│  │  ├─ Arquivo enviado via $_FILES['pdf']?          │  │  │
│  │  ├─ Extensão = .pdf?                             │  │  │
│  │  ├─ MIME type = application/pdf?                 │  │  │
│  │  ├─ Tamanho < 10MB?                              │  │  │
│  │  └─ fatura_id é válido no banco?                 │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 2. BUSCAR CONTEXTO DO BANCO                     │  │  │
│  │  ├─ SELECT fin_faturas WHERE id = ?              │  │  │
│  │  └─ SELECT fin_categorias ORDER BY nome ASC      │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 3. PREPARAR PDF                                 │  │  │
│  │  ├─ file_get_contents($_FILES['pdf']['tmp_name'])│  │  │
│  │  └─ base64_encode($conteudo_pdf)                 │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 4. MONTAR PAYLOAD PARA GEMINI                   │  │  │
│  │  ├─ [PROMPT CRÍTICO]                             │  │  │
│  │  ├─ inlineData: base64 PDF                       │  │  │
│  │  ├─ generationConfig.temperature = 0.1           │  │  │
│  │  └─ maxOutputTokens = 4096                       │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 5. CHAMAR GEMINI API VIA cURL                   │  │  │
│  │  ├─ POST https://generativelanguage.googleapis... │  │  │
│  │  ├─ Content-Type: application/json               │  │  │
│  │  ├─ TIMEOUT: 60 segundos                         │  │  │
│  │  └─ HTTP_CODE = 200?                             │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 6. PARSAR RESPOSTA GEMINI                       │  │  │
│  │  ├─ Remove ```json ... ``` se houver            │  │  │
│  │  ├─ Trim() whitespace                            │  │  │
│  │  ├─ json_decode($texto, true)                    │  │  │
│  │  ├─ Valida is_array($lancamentos)?              │  │  │
│  │  └─ Throw Exception se JSON inválido            │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 7. VALIDAR CADA LANÇAMENTO                      │  │  │
│  │  ├─ descricao não vazia?                         │  │  │
│  │  ├─ valor > 0?                                   │  │  │
│  │  ├─ data_compra formato YYYY-MM-DD?             │  │  │
│  │  ├─ categoria_id existe no banco?               │  │  │
│  │  └─ Se inválido → skip com error_log()          │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │ 8. RETORNAR JSON VÁLIDO                         │  │  │
│  │  ├─ HTTP 200                                     │  │  │
│  │  ├─ Content-Type: application/json               │  │  │
│  │  └─ echo json_encode([...])                      │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─ obter_lancamentos_fatura.php (GET) ──────────────────┐  │
│  │                                                        │  │
│  │  SELECT descricao, valor                             │  │
│  │  FROM fin_lancamentos                                │  │
│  │  WHERE fatura_id = ? AND status = 'pendente'         │  │
│  │                                                        │  │
│  │  Retorna: [{id, descricao, valor}, ...]             │  │
│  │                                                        │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
│  ┌─ salvar_lancamentos_fatura.php (POST) ────────────────┐  │
│  │                                                        │  │
│  │  1. Validar sessionm, admin, POST                     │  │
│  │  2. Parsear JSON body                                │  │
│  │  3. Validar fatura_id existe                        │  │
│  │  4. BEGIN TRANSACTION                                │  │
│  │  5. Para cada lançamento:                            │  │
│  │     ├─ Sanitizar campos                              │  │
│  │     ├─ Validar valor > 0, desc não vazio            │  │
│  │     ├─ Validar categoria_id se provided            │  │
│  │     └─ INSERT fin_lancamentos (...)                 │  │
│  │  6. Se total_salvo > 0 → COMMIT, senão → ROLLBACK   │  │
│  │  7. Retornar JSON: {sucesso, total_salvo, erros}   │  │
│  │                                                        │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 3️⃣ Componentes de Frontend (JavaScript)

```
┌──────────────────────────────────────────────────────────────┐
│                   FRONTEND (fatura.php + JS)                 │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─ Modal de Upload (Oculto) ───────────────────────────────┐ │
│  │                                                           │ │
│  │  <div id="modalUploadPDF" style="display: none; ...">   │ │
│  │    <input type="file" id="inputPDF" accept=".pdf">      │ │
│  │    <button onclick="abrirModalUploadPDF()">Analisar</button>
│  │    <div id="indicadorCarregamento">Spinner...</div>    │ │
│  │  </div>                                                  │ │
│  │                                                           │ │
│  │  Evento: btnAnalisarIA.click                             │ │
│  │  └─ Validar arquivo                                     │ │
│  │  └─ FormData(pdf, fatura_id)                            │ │
│  │  └─ fetch('ler_fatura_ia.php', {POST, FormData})        │ │
│  │  └─ Exibir telaRevisao                                  │ │
│  │                                                           │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌─ Tela de Revisão (Oculta por padrão) ────────────────────┐ │
│  │                                                           │ │
│  │  <div id="telaRevisao" style="display: none; ...">      │ │
│  │    <table id="tabelaRevisao">                            │ │
│  │      <thead>                                             │ │
│  │        <tr>                                              │ │
│  │          <th>☑ Checkbox</th>                             │ │
│  │          <th>Data</th>                                   │ │
│  │          <th>Descrição</th>                              │ │
│  │          <th>Categoria</th>                              │ │
│  │          <th>Valor</th>                                  │ │
│  │          <th>Ação</th>                                   │ │
│  │        </tr>                                             │ │
│  │      </thead>                                            │ │
│  │      <tbody id="corpoTabelaRevisao">                    │ │
│  │        <!-- Renderizado dinamicamente -->                │ │
│  │      </tbody>                                            │ │
│  │    </table>                                              │ │
│  │                                                           │ │
│  │    <button onclick="salvarLancamentosSelecionados()">   │ │
│  │      Salvar Lançamentos                                  │ │
│  │    </button>                                             │ │
│  │  </div>                                                  │ │
│  │                                                           │ │
│  │  Lógica:                                                 │ │
│  │  ├─ buscarLancamentosExistentes() → API GET            │ │
│  │  ├─ Detectar duplicidades (desc + valor iguais)         │ │
│  │  ├─ Renderizar com classe linha-duplicidade se dup      │ │
│  │  ├─ Checkbox desmarcado se duplicado                    │ │
│  │  └─ Inputs editáveis para cada campo                   │ │
│  │                                                           │
│  └───────────────────────────────────────────────────────────┘ │
│                                                               │
│  ┌─ Funções JavaScript ──────────────────────────────────────┐ │
│  │                                                           │ │
│  │  abrirModalUploadPDF()                                   │ │
│  │  └─ document.getElementById('modalUploadPDF').display   │ │
│  │                                                           │ │
│  │  exibirTelaRevisao(dados)                               │ │
│  │  ├─ lancamentosRevisao = dados.lancamentos              │ │
│  │  ├─ buscarLancamentosExistentes()                       │ │
│  │  ├─ Marcar .duplicado = true/false para cada item      │ │
│  │  ├─ renderizarTabelaRevisao()                           │ │
│  │  └─ telaRevisao.style.display = 'block'                │ │
│  │                                                           │ │
│  │  detectarDuplicidade(novo, existentes)                  │ │
│  │  └─ Comparar descricao + valor com tolerância 0.01     │ │
│  │                                                           │ │
│  │  renderizarTabelaRevisao()                              │ │
│  │  ├─ Para cada item:                                     │ │
│  │  │  ├─ Criar <tr>                                       │ │
│  │  │  ├─ Se duplicado → classe linha-duplicidade (amarelo)
│  │  │  ├─ Input editável para cada coluna                 │ │
│  │  │  └─ onchange="atualizarItem(idx, campo, valor)"     │ │
│  │  └─ appendChild na tbody                                │ │
│  │                                                           │ │
│  │  atualizarItem(idx, campo, valor)                       │ │
│  │  ├─ lancamentosRevisao[idx][campo] = valor             │ │
│  │  └─ atualizarContadores()                               │ │
│  │                                                           │ │
│  │  removerLinha(idx)                                      │ │
│  │  ├─ lancamentosRevisao.splice(idx, 1)                  │ │
│  │  └─ renderizarTabelaRevisao()                           │ │
│  │                                                           │ │
│  │  atualizarContadores()                                  │ │
│  │  ├─ Contar checkboxes marcados                         │ │
│  │  ├─ Somar valores dos selecionados                     │ │
│  │  └─ Renderizar total em #valorTotalSelecionados        │ │
│  │                                                           │ │
│  │  salvarLancamentosSelecionados()                        │ │
│  │  ├─ Coletar items com checkbox marcado                 │ │
│  │  ├─ fetch('salvar_lancamentos_fatura.php', {POST})     │ │
│  │  ├─ Enviar JSON: {fatura_id, lancamentos}              │ │
│  │  └─ location.reload() após sucesso                     │ │
│  │                                                           │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## 4️⃣ Banco de Dados

```
┌─────────────────────────────────────────────────────────┐
│                    DATABASE (MySQL)                      │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Tabela: fin_faturas                                   │
│  ├─ id (PK)                                            │
│  ├─ cartao_id (FK)                                     │
│  ├─ mes (int: 1-12)                                    │
│  ├─ ano (int: YYYY)                                    │
│  ├─ status (enum: 'aberta', 'fechada', 'paga')        │
│  ├─ data_vencimento (date)                            │
│  └─ data_pagamento (date nullable)                    │
│                                                          │
│  Tabela: fin_lancamentos (Core)                       │
│  ├─ id (PK)                                            │
│  ├─ descricao (varchar 255)           ← Vem do Gemini │
│  ├─ valor (decimal 10,2)              ← Vem do Gemini │
│  ├─ data_vencimento (date)            ← Vem do Gemini │
│  ├─ categoria_id (FK nullable)        ← Vem do Gemini │
│  ├─ tipo (enum: 'empresa', 'pessoal') ← SEMPRE 'empresa'
│  ├─ forma_pagamento                   ← SEMPRE 'cartao'
│  ├─ status (enum: 'pendente',         ← SEMPRE 'pendente'
│  │            'pago', 'atrasado')                     │
│  ├─ fatura_id (FK)                    ← Do input      │
│  ├─ grupo_id (nullable)               ← NULL (parcelamento)
│  ├─ parcela_atual (nullable)          ← NULL         │
│  ├─ total_parcelas (nullable)         ← NULL         │
│  ├─ observacao (nullable)             ← NULL         │
│  ├─ data_pagamento (nullable)         ← NULL         │
│  ├─ codigo_pagamento (nullable)       ← NULL         │
│  ├─ mes_referencia (nullable)         ← NULL         │
│  ├─ ano_referencia (nullable)         ← NULL         │
│  ├─ recorrente_id (nullable)          ← NULL         │
│  └─ created_at (timestamp)            ← NOW()        │
│                                                          │
│  Tabela: fin_categorias                                │
│  ├─ id (PK)                                            │
│  ├─ nome (varchar 100)                                │
│  ├─ cor (hex color)                                   │
│  └─ ativo (boolean)                                   │
│                                                          │
│  Tabela: fin_cartoes                                  │
│  ├─ id (PK)                                            │
│  ├─ nome (varchar 255)                                │
│  ├─ bandeira (enum: 'mastercard', 'visa', ...)        │
│  ├─ dia_fechamento (int)                              │
│  └─ dia_vencimento (int)                              │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 5️⃣ Fluxo de Dados (Request/Response)

```
┌──────────────────────────────────────────────────────────────┐
│                    REQUEST 1: Upload PDF                      │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  POST /modules/financeiro/ler_fatura_ia.php                  │
│  Content-Type: multipart/form-data                           │
│  ─────────────────────────────────────────────────────────── │
│                                                               │
│  Body:                                                        │
│  {                                                            │
│    "pdf": <Binary PDF data>,                                 │
│    "fatura_id": "123"                                        │
│  }                                                            │
│                                                               │
│  Response (200):                                              │
│  {                                                            │
│    "sucesso": true,                                          │
│    "fatura_id": 123,                                         │
│    "cartao_nome": "MasterCard",                              │
│    "mes": 10,                                                │
│    "ano": 2024,                                              │
│    "total_itens": 3,                                         │
│    "lancamentos": [                                          │
│      {                                                        │
│        "descricao": "Uber",                                  │
│        "valor": 25.50,                                       │
│        "categoria_id": 2,                                    │
│        "data_compra": "2024-10-15"                           │
│      },                                                       │
│      ...                                                      │
│    ]                                                          │
│  }                                                            │
│                                                               │
│  Response (400 ~ 500): {"sucesso": false, "erro": "..."}    │
│                                                               │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│              REQUEST 2: Obter Lançamentos Existentes          │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  GET /modules/financeiro/obter_lancamentos_fatura.php        │
│      ?fatura_id=123                                          │
│                                                               │
│  Response (200):                                              │
│  {                                                            │
│    "sucesso": true,                                          │
│    "fatura_id": 123,                                         │
│    "total": 2,                                               │
│    "lancamentos": [                                          │
│      {                                                        │
│        "id": 1,                                              │
│        "descricao": "Uber",                                  │
│        "valor": "25.50"                                      │
│      },                                                       │
│      {                                                        │
│        "id": 2,                                              │
│        "descricao": "Netflix",                               │
│        "valor": "19.90"                                      │
│      }                                                        │
│    ]                                                          │
│  }                                                            │
│                                                               │
└──────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────┐
│             REQUEST 3: Salvar Lançamentos Selecionados        │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  POST /modules/financeiro/salvar_lancamentos_fatura.php      │
│  Content-Type: application/json                              │
│  ─────────────────────────────────────────────────────────── │
│                                                               │
│  Body:                                                        │
│  {                                                            │
│    "fatura_id": 123,                                         │
│    "lancamentos": [                                          │
│      {                                                        │
│        "descricao": "Uber",                                  │
│        "valor": 25.50,                                       │
│        "categoria_id": 2,                                    │
│        "data_compra": "2024-10-15"                           │
│      },                                                       │
│      {                                                        │
│        "descricao": "Restaurante",                           │
│        "valor": 89.90,                                       │
│        "categoria_id": 1,                                    │
│        "data_compra": "2024-10-12"                           │
│      }                                                        │
│    ]                                                          │
│  }                                                            │
│                                                               │
│  Response (200):                                              │
│  {                                                            │
│    "sucesso": true,                                          │
│    "total_salvo": 2,                                         │
│    "erros": [],                                              │
│    "mensagem": "2 lançamento(s) importado(s) com sucesso!"   │
│  }                                                            │
│                                                               │
│  Response (400): {"sucesso": false, "erro": "...", "erros"...} │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## 6️⃣ Máquina de Estados (Lançamento)

```
                    ┌─────────────────────────┐
                    │   LANÇAMENTO CRIADO     │
                    │   (por ler_fatura_ia)   │
                    └────────────┬────────────┘
                                 │
                    ┌────────────▼───────────┐
                    │     status = 'pendente' │
                    │    forma_pagamento =    │
                    │       'cartao'          │
                    │      tipo = 'empresa'   │
                    └────────────┬────────────┘
                                 │
                    [Usuário abre fatura]
                                 │
        ┌────────────────────────┴────────────────────────┐
        │                                                 │
        ▼ [Paga fatura inteira]          [Edita individual]
    ┌──────────────────────┐                 │
    │  status = 'pago'     │                 │
    │  data_pagamento = d  │                 │
    │  (Automático)        │                 │
    │                      │                 │
    │  [Fatura fechada]    │                 │
    └──────────────────────┘                 │
                                             │
                                    [Lançamento fica pendente
                                     até pagamento manual]
```

---

## 7️⃣ Segurança - Validações em Camadas

```
┌─────────────────────────────────────────────────────────────┐
│                  LAYER 1: FRONTEND (JS)                     │
├─────────────────────────────────────────────────────────────┤
│ ✓ Tipo de arquivo = .pdf?                                  │
│ ✓ MIME type = application/pdf?                             │
│ ✓ Tamanho < 10MB?                                          │
│ ✓ Pelo menos 1 item selecionado antes de salvar?           │
│ ✓ Escape HTML em descricoes (XSS prevention)               │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                 LAYER 2: BACKEND (PHP)                      │
├─────────────────────────────────────────────────────────────┤
│ ✓ Sessão autenticada? (isset($_SESSION['user_id']))       │
│ ✓ Permissão admin? (isAdmin())                             │
│ ✓ Método HTTP correto? (POST/GET)                          │
│ ✓ Arquivo realmente é PDF? (mime_content_type())          │
│ ✓ Fatura existe no banco? (SELECT WHERE id = ?)            │
│ ✓ JSON do Gemini é válido? (json_decode())                │
│ ✓ Campos obrigatórios presentes? (isset())                 │
│ ✓ Valores válidos? (valor > 0, desc não vazio)             │
│ ✓ Categoria existe se fornecida? (SELECT WHERE id = ?)     │
│ ✓ Todas as queries com Prepared Statements (PDO)           │
│ ✓ Transação ACID para INSERT múltiplo                      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                 LAYER 3: DATABASE (MySQL)                   │
├─────────────────────────────────────────────────────────────┤
│ ✓ Foreign Keys ativadas? (fin_lancamentos.fatura_id)       │
│ ✓ Constraints? (valor NOT NULL, status ENUM)               │
│ ✓ Índices para performance? (fatura_id, categoria_id)      │
│ ✓ Backups regulares?                                       │
└─────────────────────────────────────────────────────────────┘
```

---

**Diagrama criado: 14/06/2026**
