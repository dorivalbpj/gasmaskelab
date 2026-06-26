# GUIA DE TESTE - Leitura de Faturas com Gemini

## 🎯 Fluxo Completo Visual

```
┌─────────────────────────────────────────────────────────────────┐
│                        USUÁRIO                                   │
│                  (Tela de Fatura)                                │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                      Clica em:
                "Importar PDF (Gemini)"
                           │
                           ▼
        ┌──────────────────────────────────┐
        │  MODAL DE UPLOAD                 │
        │  - Input file (.pdf)             │
        │  - Botão "Analisar com IA"      │
        │  - Indicador de carregamento    │
        └──────────┬───────────────────────┘
                   │
             Seleciona PDF
             e clica em
          "Analisar com IA"
                   │
                   ▼
    ┌─────────────────────────────────────┐
    │  ler_fatura_ia.php (Backend)       │
    │  ────────────────────────────────  │
    │  1. Valida arquivo (tipo, tamanho) │
    │  2. Busca categorias do banco      │
    │  3. Converte PDF → Base64         │
    │  4. Monta prompt para Gemini      │
    │  5. cURL → API Gemini 1.5 Flash  │
    │  6. Parsa resposta JSON            │
    │  7. Retorna lançamentos           │
    └──────────┬────────────────────────┘
               │
               ▼ (JSON com lançamentos)
    ┌─────────────────────────────────────┐
    │  fatura.php (Frontend JS)          │
    │  ────────────────────────────────  │
    │  1. Recebe JSON do backend        │
    │  2. Chama buscarLancamentosExist.│
    │  3. Detecta duplicidades         │
    │  4. Renderiza tabela de revisão  │
    │  5. Exibe: Data|Desc|Cat|Valor  │
    │     - Linhas duplicadas em AMARELO │
    │     - Checkbox desmarcado se dup. │
    └──────────┬────────────────────────┘
               │
      Usuário revisa e edita
        (descrição, categoria,
           valor, data)
         Marca checkboxes
               │
               ▼
    ┌─────────────────────────────────────┐
    │  salvar_lancamentos_fatura.php    │
    │  ────────────────────────────────  │
    │  1. Valida fatura existe          │
    │  2. Inicia transação              │
    │  3. Loop INSERT por lançamento   │
    │  4. status='pendente'             │
    │  5. forma_pagamento='cartao'     │
    │  6. COMMIT ou ROLLBACK            │
    │  7. Retorna total salvo           │
    └──────────┬────────────────────────┘
               │
        Reload da página
    Mostra novos lançamentos
```

---

## 📋 Exemplo de Resposta do Gemini

### INPUT ao Gemini:
```
Arquivo PDF: [Fatura MasterCard de Outubro/2024]

O sistema tem essas categorias:
1 - Alimentação, 2 - Transporte, 3 - Saúde, 4 - Educação, 5 - Lazer

Extraia APENAS os gastos reais. Ignore pagamentos, estornos, juros.
```

### OUTPUT esperado do Gemini:
```json
[
  {
    "descricao": "Uber",
    "valor": 25.50,
    "categoria_id": 2,
    "data_compra": "2024-10-15"
  },
  {
    "descricao": "Restaurante Pizzaria (1/3)",
    "valor": 89.90,
    "categoria_id": 1,
    "data_compra": "2024-10-12"
  },
  {
    "descricao": "Farmácia XYZ",
    "valor": 45.30,
    "categoria_id": 3,
    "data_compra": "2024-10-18"
  },
  {
    "descricao": "Netflix",
    "valor": 29.90,
    "categoria_id": null,
    "data_compra": "2024-10-01"
  }
]
```

---

## 🔍 Validações do Sistema

### Backend (ler_fatura_ia.php):
```
✅ Arquivo é PDF? → Valida MIME type
✅ Tamanho <= 10MB? → Rejeita >10MB
✅ Fatura existe? → Busca no banco
✅ JSON válido? → Parsa resposta Gemini
✅ Valor > 0? → Remove valores negativos
✅ Descrição não vazia? → Remove branco
✅ Categoria existe? → null se não existir
```

### Frontend (fatura.php):
```
✅ Duplicidade (descricao + valor iguais)?
   → Linha AMARELA + checkbox DESMARCADO
   → Aviso: "⚠️ Possível Duplicidade"

✅ Usuário editou campo?
   → Atualiza localStorage ou variável em memória
   → Recalcula contador de selecionados

✅ Checkbox desmarcado?
   → Exclui do cálculo de total
   → Desabilita botão "Salvar" se nenhum selecionado
```

### Salvar (salvar_lancamentos_fatura.php):
```
✅ Transação iniciada
✅ Para cada lançamento:
   - Valida descricao não vazia
   - Valida valor > 0
   - Valida categoria_id se fornecido
   - INSERT em fin_lancamentos
✅ Se erro em uma linha → Continua (parcial)
✅ Se nenhuma linha salva → ROLLBACK
✅ Se alguma salva → COMMIT
```

---

## 🧪 Casos de Teste

### Caso 1: PDF Válido com 3 Gastos
**Input**: `fatura.pdf` com conteúdo:
```
MasterCard OUTUBRO/2024

Uber - R$ 25,50
Mercado X - R$ 152,30
Spotify - R$ 19,90

SALDO TOTAL: R$ 197,70
```

**Output Esperado**:
- 3 linhas na tabela
- Nenhuma duplicidade
- Todos checkboxes marcados
- Total: R$ 197,70

---

### Caso 2: PDF com Duplicidade
**Input**: PDF com dois Ubers iguais (R$ 25,50 em datas diferentes)

**Output Esperado**:
- 2 linhas do Uber
- 1ª linha: Normal
- 2ª linha: AMARELA + checkbox desmarcado + aviso "Possível Duplicidade"
- Total se incluir ambas: R$ 51,00

---

### Caso 3: PDF com Ruído (Pagamento, Juros, etc)
**Input**: PDF com:
```
Pagamento de fatura anterior - R$ 1.000
Uber - R$ 25,50
IOF - R$ 2,30
Restaurante - R$ 89,90
Cancelamento - R$ -10,00
```

**Output Esperado**:
- APENAS 2 linhas: Uber + Restaurante
- Gerado remove: "Pagamento", "IOF", "Cancelamento"
- Total: R$ 115,40

---

### Caso 4: Edição antes de Salvar
**Input**: Usuário importa 3 lançamentos, mas quer editar:
1. Aumentar Uber de R$ 25,50 → R$ 30,00
2. Mudar categoria "Mercado" → "Alimentação"
3. Remover uma linha (clica X)

**Output Esperado**:
- 2 lançamentos salvos (não 3)
- Uber com R$ 30,00 (valor editado)
- Mercado com categoria_id correto
- Database: fin_lancamentos com 2 novos rows

---

## 📲 Como Testar na Prática

### 1️⃣ Gerar PDF de teste:
```bash
# Crie um PDF manualmente com conteúdo de fatura simulada
# OU use uma fatura real de seu cartão de crédito
```

### 2️⃣ Acesse a página:
```
http://localhost/modules/financeiro/fatura.php?id=1
```

### 3️⃣ Clique em "Importar PDF (Gemini)"

### 4️⃣ Selecione arquivo PDF

### 5️⃣ Clique "Analisar com IA"

### 6️⃣ Aguarde processamento (15-30 segundos)

### 7️⃣ Revise e edite dados na tabela

### 8️⃣ Clique "Salvar Lançamentos"

### 9️⃣ Verifique em `fin_lancamentos` que os dados foram salvos

---

## 🐛 Troubleshooting

### Erro: "Erro cURL: Timeout"
- Gemini API demora muito
- Aumentar `curl_setopt(...CURLOPT_TIMEOUT, 120)` em ler_fatura_ia.php

### Erro: "JSON inválido"
- Gemini retornou texto extra antes/depois do JSON
- Função `preg_replace` já trata tags markdown, mas pode haver casos raros

### Erro: "Fatura não encontrada"
- ID da fatura não existe no banco
- Verifique se a fatura_id está correta na URL

### Checkbox não fica desmarcado na duplicidade
- Verifique se buscarLancamentosExistentes() está retornando dados
- Abra DevTools > Network para ver a requisição a obter_lancamentos_fatura.php

### "Salvar Lançamentos" não funciona
- Verifique se há pelo menos 1 checkbox marcado
- Abra DevTools > Console para ver erro JavaScript
- Verifique se salvar_lancamentos_fatura.php está acessível

---

## 🔐 Segurança

✅ Todas as queries usam Prepared Statements (PDO)
✅ Validação de sessão em TODOS os endpoints
✅ Permissão isAdmin() em TODOS os endpoints
✅ Validação de MIME type (.pdf + application/pdf)
✅ Limite de tamanho (10MB)
✅ Escape de HTML em descricoes (escapeHtml() em JS)
✅ Transação ACID no salvar (COMMIT/ROLLBACK)
✅ Error log em caso de falha (não expõe ao cliente)
