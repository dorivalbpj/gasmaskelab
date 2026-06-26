# 🔍 REFERÊNCIA RÁPIDA - Snippets Importantes

## 📌 1. Chamada do Endpoint Gemini (Frontend)

```javascript
async function enviarParaGemini(arquivo) {
    const formData = new FormData();
    formData.append('pdf', arquivo);
    formData.append('fatura_id', FATURA_ID);
    
    const resposta = await fetch('ler_fatura_ia.php', {
        method: 'POST',
        body: formData
    });
    
    const dados = await resposta.json();
    // dados.lancamentos = [{descricao, valor, categoria_id, data_compra}, ...]
}
```

---

## 📌 2. Resposta Esperada do Gemini

```json
{
  "sucesso": true,
  "fatura_id": 123,
  "cartao_nome": "MasterCard",
  "mes": 10,
  "ano": 2024,
  "total_itens": 3,
  "lancamentos": [
    {
      "descricao": "Uber",
      "valor": 25.50,
      "categoria_id": 2,
      "data_compra": "2024-10-15"
    },
    {
      "descricao": "Netflix (1/12)",
      "valor": 19.90,
      "categoria_id": null,
      "data_compra": "2024-10-01"
    },
    {
      "descricao": "Restaurante XYZ",
      "valor": 89.90,
      "categoria_id": 1,
      "data_compra": "2024-10-12"
    }
  ]
}
```

---

## 📌 3. Detecção de Duplicidade (JavaScript)

```javascript
// Busca lançamentos já existentes
const lancamentosExistentes = await fetch(
  `obter_lancamentos_fatura.php?fatura_id=${FATURA_ID}`
).then(r => r.json()).then(d => d.lancamentos);

// Valida duplicidade
lancamentos = lancamentos.map(item => {
  const duplicado = lancamentosExistentes.some(exist => 
    exist.descricao.trim() === item.descricao.trim() &&
    Math.abs(parseFloat(exist.valor) - parseFloat(item.valor)) < 0.01
  );
  return {
    ...item,
    duplicado: duplicado,
    selecionado: !duplicado  // Checkbox desmarcado se dup
  };
});
```

---

## 📌 4. Renderizar Tabela de Revisão

```javascript
function renderizarTabelaRevisao() {
  lancamentosRevisao.forEach((item, idx) => {
    const tr = document.createElement('tr');
    
    if (item.duplicado) {
      tr.className = 'linha-duplicidade';  // Amarela
    }
    
    tr.innerHTML = `
      <td>
        <input type="checkbox" class="checkbox-linha" 
               ${item.selecionado ? 'checked' : ''} 
               data-idx="${idx}" onchange="atualizarContadores()">
      </td>
      <td>
        <input type="date" class="input-revisao" 
               value="${item.data_compra}"
               data-idx="${idx}" 
               onchange="atualizarItem(${idx}, 'data_compra', this.value)">
      </td>
      <td>
        <input type="text" class="input-revisao" 
               value="${escapeHtml(item.descricao)}"
               data-idx="${idx}" 
               onchange="atualizarItem(${idx}, 'descricao', this.value)">
        ${item.duplicado ? 
          '<div class="aviso-duplicidade">⚠️ Possível Duplicidade</div>' : 
          ''}
      </td>
      <td>
        <select class="select-revisao" 
                data-idx="${idx}" 
                onchange="atualizarItem(${idx}, 'categoria_id', this.value)">
          <option value="">Sem categoria</option>
          ${CATEGORIAS_DISPONIVEIS.map(cat => 
            `<option value="${cat.id}" 
                    ${cat.id == item.categoria_id ? 'selected' : ''}>
              ${escapeHtml(cat.nome)}
            </option>`
          ).join('')}
        </select>
      </td>
      <td style="text-align: right;">
        <input type="number" class="input-revisao" step="0.01" 
               value="${item.valor}"
               data-idx="${idx}" 
               onchange="atualizarItem(${idx}, 'valor', parseFloat(this.value))">
      </td>
      <td style="text-align: center;">
        <button type="button" onclick="removerLinha(${idx})">
          <i class="ph ph-trash"></i>
        </button>
      </td>
    `;
    
    document.getElementById('corpoTabelaRevisao').appendChild(tr);
  });
}
```

---

## 📌 5. Salvar Lançamentos (Frontend para Backend)

```javascript
async function salvarLancamentosSelecionados() {
  const selecionados = [];
  
  document.querySelectorAll('.checkbox-linha:checked').forEach(cb => {
    const idx = parseInt(cb.dataset.idx);
    selecionados.push(lancamentosRevisao[idx]);
  });
  
  const resposta = await fetch('salvar_lancamentos_fatura.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      fatura_id: FATURA_ID,
      lancamentos: selecionados
    })
  });
  
  const dados = await resposta.json();
  // dados.total_salvo = número de linhas inseridas
  // dados.erros = array com erros parciais
}
```

---

## 📌 6. Payload para Salvar

```json
{
  "fatura_id": 123,
  "lancamentos": [
    {
      "descricao": "Uber",
      "valor": 25.50,
      "categoria_id": 2,
      "data_compra": "2024-10-15"
    },
    {
      "descricao": "Restaurante",
      "valor": 89.90,
      "categoria_id": 1,
      "data_compra": "2024-10-12"
    }
  ]
}
```

---

## 📌 7. Inserção SQL (Backend)

```php
// Prepare statement
$stmt = $pdo->prepare("
    INSERT INTO fin_lancamentos 
    (descricao, valor, data_vencimento, categoria_id, tipo, forma_pagamento, status, fatura_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

// Loop por lançamento
foreach ($lancamentos as $item) {
    $stmt->execute([
        $item['descricao'],           // string
        round($item['valor'], 2),     // float
        $item['data_compra'],         // YYYY-MM-DD
        $item['categoria_id'],        // int or null
        'empresa',                    // tipo fixo
        'cartao',                     // forma_pagamento fixo
        'pendente',                   // status fixo
        $fatura_id                    // int
    ]);
}
```

---

## 📌 8. Prompt Gemini (Essencial)

```php
$prompt = <<<PROMPT
VOCÊ É UM LEITOR DE FATURAS COM MÁXIMA PRECISÃO.

IGNORE COMPLETAMENTE:
❌ Pagamento de fatura
❌ Saldo anterior
❌ IOF
❌ Estorno
❌ Cancelamento

REGRA PARCELAMENTO:
Se "Apple 2/4 R$ 100", retorne APENAS:
{"descricao": "Apple (2/4)", "valor": 100.00, ...}

CATEGORIAS: {$categorias_texto}

RETORNE APENAS JSON VÁLIDO:
[
  {"descricao": "...", "valor": 0.0, "categoria_id": null, "data_compra": "YYYY-MM-DD"}
]
PROMPT;
```

---

## 📌 9. Requisição cURL para Gemini

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . GEMINI_API_KEY);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'contents' => [['parts' => [
        ['text' => $prompt],
        ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode($pdf)]]
    ]]],
    'generationConfig' => ['temperature' => 0.1]
]));

$resposta = curl_exec($ch);
curl_close($ch);
```

---

## 📌 10. Parsing Defensivo da Resposta

```php
// Remove markdown
$texto = preg_replace('/^```json\s*/i', '', $texto_resposta);
$texto = preg_replace('/\s*```$/i', '', $texto);
$texto = trim($texto);

// Decodifica JSON
$lancamentos = json_decode($texto, true);

if ($lancamentos === null) {
    throw new Exception('JSON inválido: ' . $texto);
}

if (!is_array($lancamentos)) {
    throw new Exception('Não é um array');
}
```

---

## 📌 11. Validações Críticas

```php
// Backend validações
if ($extensao !== 'pdf' || $mime !== 'application/pdf') {
    http_response_code(400);
    throw new Exception('Deve ser PDF válido');
}

if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    throw new Exception('Máximo 10MB');
}

if (strlen($descricao) === 0 || $valor <= 0) {
    error_log("Item inválido: desc='{$descricao}', valor={$valor}");
    continue;  // Pula este item
}

// Frontend validações
if (!arquivo || arquivo.type !== 'application/pdf') {
    alert('Selecione um PDF válido');
    return;
}

if (arquivo.size > 10 * 1024 * 1024) {
    alert('Arquivo muito grande');
    return;
}
```

---

## 📌 12. CSS para Duplicidade

```css
.linha-duplicidade {
    background-color: rgba(251, 191, 36, 0.1) !important;
}

.aviso-duplicidade {
    font-size: 11px;
    color: #d97706;
    background: rgba(217, 119, 6, 0.1);
    padding: 2px 6px;
    border-radius: 3px;
    margin-top: 4px;
    display: inline-block;
}

.input-revisao:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
```

---

## 📌 13. Transação SQL

```php
try {
    $pdo->beginTransaction();
    
    $total_salvo = 0;
    foreach ($lancamentos as $item) {
        $stmt->execute([...]);
        $total_salvo++;
    }
    
    if ($total_salvo > 0) {
        $pdo->commit();  // Salva tudo
        echo json_encode(['sucesso' => true, 'total_salvo' => $total_salvo]);
    } else {
        $pdo->rollBack();  // Desfaz tudo
        http_response_code(400);
        echo json_encode(['sucesso' => false]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
```

---

## 📌 14. Helper: Formatar Moeda em JavaScript

```javascript
function formatarMoeda(valor) {
    return 'R$ ' + valor.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Uso:
console.log(formatarMoeda(1500.00));  // R$ 1.500,00
```

---

## 📌 15. Helper: Escape HTML em JavaScript

```javascript
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}

// Uso:
console.log(escapeHtml('<script>alert("oi")</script>'));
// &lt;script&gt;alert(&quot;oi&quot;)&lt;/script&gt;
```

---

## 🚨 Checklist de Debug

- [ ] `GEMINI_API_KEY` está definida em `config/gemini.php`?
- [ ] Arquivo PDF é válido e <10MB?
- [ ] Resposta do Gemini em `error_log` mostra JSON?
- [ ] JavaScript console mostra erro? (F12)
- [ ] Network tab mostra 200 OK em `ler_fatura_ia.php`?
- [ ] `obter_lancamentos_fatura.php` retorna dados existentes?
- [ ] Checkbox está marcado mas linha não é amarela? Dados não matchearam.
- [ ] Salvar retorna erro 500? Verifique `error_log` do PHP.
- [ ] Lançamentos não aparecem no banco? Verifique se `fin_lancamentos` tem campos corretos.

---

## 🔗 Endpoints Disponíveis

| Endpoint | Método | Input | Output |
|----------|--------|-------|--------|
| `ler_fatura_ia.php` | POST | PDF + fatura_id | JSON: sucesso, lancamentos[] |
| `obter_lancamentos_fatura.php` | GET | fatura_id | JSON: lancamentos[] |
| `salvar_lancamentos_fatura.php` | POST | JSON: fatura_id, lancamentos[] | JSON: sucesso, total_salvo |

---

## 📞 Contatos Rápidos

- **Gemini API Docs**: https://ai.google.dev/docs
- **Gemini Studio (teste)**: https://ai.google.dev/aistudio
- **PHP cURL Docs**: https://www.php.net/manual/en/book.curl.php
- **Fetch API Docs**: https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API

---

## ⚡ Performance Tips

1. **Aumentar timeout cURL para 120s** se Gemini der timeout:
   ```php
   curl_setopt($ch, CURLOPT_TIMEOUT, 120);
   ```

2. **Cache de categorias em JS** para não buscar do DB toda vez:
   ```javascript
   const CATEGORIAS_CACHE = localStorage.getItem('categorias');
   ```

3. **Lazy load de PDFs** para múltiplas faturas

4. **Comprimir PDF antes de enviar** (se >5MB)

---

**Última atualização: 14/06/2026**
