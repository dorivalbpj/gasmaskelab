# ⚡ FOLHA RÁPIDA DE COLA - Quick Reference

> Imprima esta página para manter na mesa durante o desenvolvimento

---

## 🚀 Comece Aqui

```
1. Acesse: http://localhost/modules/financeiro/fatura.php?id=1
2. Clique: "Importar PDF (Gemini)"
3. Selecione: Um arquivo PDF (<10MB)
4. Aguarde: 15-30 segundos (Gemini processando)
5. Revise: Edite dados na tabela se necessário
6. Salve: Clique "Salvar Lançamentos Selecionados"
7. Pronto: Página recarrega com novos gastos
```

---

## 📞 URLs Principais

```
Fatura (UI):     http://localhost/modules/financeiro/fatura.php?id=1
Ler PDF:         POST /modules/financeiro/ler_fatura_ia.php
Duplicidades:    GET  /modules/financeiro/obter_lancamentos_fatura.php?fatura_id=1
Salvar:          POST /modules/financeiro/salvar_lancamentos_fatura.php
```

---

## 🔑 Variáveis de Ambiente

```
Arquivo:               config/gemini.php
Variável:              GEMINI_API_KEY
Valor:                 AIzaSyDf-... (já configurado)
Status:                ✅ Pronto para uso
```

---

## 📡 Endpoints - Resumo

### 1️⃣ ler_fatura_ia.php (POST)
```
Input:  FormData {pdf: File, fatura_id: 123}
Output: JSON {sucesso, fatura_id, lancamentos[]}
Tempo:  15-30s (Gemini API)
Erro:   HTTP 400/401/403/500 + JSON {erro}
```

### 2️⃣ obter_lancamentos_fatura.php (GET)
```
Input:  ?fatura_id=123
Output: JSON {sucesso, total, lancamentos[]}
Tempo:  <100ms
Erro:   HTTP 401/403/500 + JSON {erro}
```

### 3️⃣ salvar_lancamentos_fatura.php (POST)
```
Input:  JSON {fatura_id, lancamentos[]}
Output: JSON {sucesso, total_salvo, erros[]}
Tempo:  <2s
Erro:   HTTP 400/401/403/500 + JSON {erro}
```

---

## 📊 Estrutura de Dados

### Lançamento (do Gemini)
```json
{
  "descricao": "Uber",           // string
  "valor": 25.50,               // float
  "categoria_id": 2,            // int or null
  "data_compra": "2024-10-15"   // YYYY-MM-DD
}
```

### Para Salvar
```json
{
  "fatura_id": 123,
  "lancamentos": [
    {"descricao": "...", "valor": 0.0, ...},
    ...
  ]
}
```

### Lançamento Existente
```json
{
  "id": 1,
  "descricao": "Uber",
  "valor": "25.50"
}
```

---

## 🎨 Classes CSS Importantes

```css
.linha-duplicidade      /* Fundo amarelo para duplicatas */
.aviso-duplicidade      /* Badge com aviso */
.input-revisao          /* Input editável na tabela */
.select-revisao         /* Select editável na tabela */
```

---

## 🔍 JavaScript - Funções Principais

```javascript
abrirModalUploadPDF()               // Abre modal
fecharModalUploadPDF()              // Fecha modal
exibirTelaRevisao(dados)            // Renderiza tabela
renderizarTabelaRevisao()           // Atualiza tabela
atualizarItem(idx, campo, valor)    // Edita campo
removerLinha(idx)                   // Remove linha
atualizarContadores()               // Recalcula totais
salvarLancamentosSelecionados()     // POST ao backend
buscarLancamentosExistentes()       // GET duplicidades
```

---

## 🐛 Debug Rápido

```javascript
// No console (F12 → Console):
console.log(lancamentosRevisao);      // Ver dados em memória
console.log(FATURA_ID);               // ID atual
console.log(CATEGORIAS_DISPONIVEIS);  // Categorias disponíveis

// Ver requisições:
// F12 → Network → Filtrar por XHR → Ver requests
```

---

## 🔒 Segurança - O Que Verificar

```
✅ Autenticado?           sessão existe
✅ Admin?                 isAdmin() = true
✅ PDF válido?            mime_content_type() = application/pdf
✅ Tamanho ok?            file['size'] < 10MB
✅ Fatura existe?         SELECT fin_faturas WHERE id = ?
✅ Categoria existe?      SELECT fin_categorias WHERE id = ?
✅ JSON válido?           json_decode() não retorna null
```

---

## 📝 SQL Úteis para Debug

```sql
-- Ver lançamentos de uma fatura
SELECT * FROM fin_lancamentos WHERE fatura_id = 1;

-- Ver últimos 5 lançamentos importados
SELECT * FROM fin_lancamentos 
WHERE tipo = 'empresa' AND forma_pagamento = 'cartao'
ORDER BY id DESC LIMIT 5;

-- Ver duplicidades possíveis
SELECT descricao, valor, COUNT(*) 
FROM fin_lancamentos 
WHERE fatura_id = 1
GROUP BY descricao, valor 
HAVING COUNT(*) > 1;

-- Verificar status de fatura
SELECT * FROM fin_faturas WHERE id = 1;
```

---

## ⏱️ Performance - Timeouts

```
Gemini API:    60 segundos (CURLOPT_TIMEOUT)
Frontend:      Nenhum (fetch nativa)
Database:      Padrão MySQL (<2s típico)
```

---

## 🚨 Erros Comuns

| Erro | Causa | Solução |
|------|-------|---------|
| "JSON inválido" | Gemini retornou texto extra | Aumentar timeout cURL |
| "Fatura não encontrada" | fatura_id inválido | Verificar URL ?id=X |
| "Sem permissão" | Não é admin | Login com admin |
| "Arquivo muito grande" | PDF >10MB | Comprimir com GhostScript |
| "Duplicidade não detecta" | Valores diferentes | Verificar precisão (0.01) |

---

## 📦 Dependências Externas

```
✅ Google Gemini API 1.5 Flash
   └─ Endpoint: generativelanguage.googleapis.com
   └─ Método: POST /v1beta/models/gemini-1.5-flash:generateContent
   └─ Autenticação: API Key em query param
   └─ Timeout: 60 segundos

✅ PHP cURL
   └─ Extensão padrão do PHP
   └─ Status: Habilitada (teste com: php -m | grep curl)

✅ MySQL PDO
   └─ Extensão padrão do PHP
   └─ Status: Habilitada (teste com: php -m | grep pdo)
```

---

## 🎯 Checklist Antes de Enviar para Produção

```
Código:
[ ] Todos os endpoints retornam JSON válido
[ ] Error handling está em place
[ ] Prepared statements em 100% das queries
[ ] Sem variáveis superglobais desprotegidas

Frontend:
[ ] Modal abre e fecha corretamente
[ ] Tabela renderiza sem erros
[ ] Duplicidades detectadas (amarelo)
[ ] Edição de campos funciona
[ ] Total se recalcula ao editar

Backend:
[ ] ler_fatura_ia.php processa PDF
[ ] obter_lancamentos_fatura.php retorna dados
[ ] salvar_lancamentos_fatura.php insere no BD
[ ] Transação ACID funciona

Segurança:
[ ] isAdmin() verificado em todos endpoints
[ ] GEMINI_API_KEY não exposta
[ ] Validação de arquivo PDF
[ ] Escape de HTML/SQL em tudo

Testes:
[ ] Teste 1-10 executados e passados
[ ] Logs monitorados sem erros críticos
[ ] Performance dentro do esperado (<2s para salvar)
```

---

## 📚 Documentação por Caso de Uso

| Preciso... | Arquivo | Seção |
|---|---|---|
| ...começar rápido | README_IMPLEMENTACAO.md | Quick Start |
| ...testar | TESTE_FATURA_GEMINI.md | Testes de Funcionalidade |
| ...customizar prompt | PROMPT_ENGINEERING_GEMINI.md | Estrutura Do Prompt |
| ...snippets de código | REFERENCIA_RAPIDA.md | 📌 Pontos Principais |
| ...arquitetura completa | ARQUITETURA_VISUAL.md | Diagramas |
| ...antes de deploy | CHECKLIST_PRONTO_PRODUCAO.md | Testes |

---

## 🎁 Dicas de Ouro

```
💡 Aumentar timeout do Gemini?
   curl_setopt($ch, CURLOPT_TIMEOUT, 120);

💡 Testar prompt do Gemini?
   Acesse: https://ai.google.dev/aistudio
   Cole o prompt inteiro (sem partes PHP)
   Upload um PDF real

💡 Debugar JavaScript?
   F12 → Console → Digite: lancamentosRevisao
   Ver dados em memória sem reload

💡 Monitorar erros?
   tail -f /var/log/apache2/error.log
   ou em XAMPP: C:\xampp\apache\logs\error.log

💡 Performance lenta?
   1. Aumentar CURLOPT_TIMEOUT
   2. Verificar conexão Gemini API
   3. Comprimir PDF antes de enviar

💡 Não consegue reproduzir erro?
   1. Ativar error_reporting(E_ALL) no topo do arquivo
   2. Verificar error_log do PHP
   3. DevTools F12 → Network → Ver requisição completa
```

---

## 🌐 Links Úteis

```
Google Gemini Studio (Teste prompt):
https://ai.google.dev/aistudio

Documentação Gemini API:
https://ai.google.dev/docs

PHP cURL:
https://www.php.net/manual/en/book.curl.php

MySQL Queries:
https://dev.mysql.com/doc/

MDN Fetch API:
https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API

DevTools Chrome:
https://developer.chrome.com/docs/devtools/
```

---

## 📱 Responsivo - Testar Em:

```
✅ Desktop (Chrome, Firefox, Safari)
✅ Tablet (iPad em modo landscape)
✅ Mobile (iPhone 12/13 em modo portrait)

Breakpoints principais:
- Desktop: >1024px
- Tablet: 768px - 1024px
- Mobile: <768px

Tabela em mobile pode precisar de scroll horizontal
```

---

## 🎬 Resumo do Fluxo (Em 30 Segundos)

```
1. Upload PDF              → ler_fatura_ia.php
2. Gemini processa         → API externa
3. Tabela renderiza        → obter_lancamentos_fatura.php
4. Duplicidades detectadas → JavaScript comparação
5. Usuário edita/seleciona → Inputs editáveis
6. Clica "Salvar"          → salvar_lancamentos_fatura.php
7. INSERT em BD            → Transação ACID
8. Sucesso!                → Reload página
```

---

**Status: ✅ PRONTO PARA USO**
**Última atualização: 14/06/2026**
