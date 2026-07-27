<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitização
    function s($v) { return strip_tags(trim($v ?? '')); }

    $q1          = s($_POST['q1']);
    $q2          = s($_POST['q2']);
    $q3          = s($_POST['q3']);
    $q4          = s($_POST['q4']);
    $q5          = s($_POST['q5']);
    $q6          = s($_POST['q6']);
    $q7          = s($_POST['q7']);
    $q8          = s($_POST['q8']);
    $q9          = s($_POST['q9']);
    $q10_detalhe = s($_POST['q10_detalhe']);
    $q11         = s($_POST['q11']);
    $q12         = s($_POST['q12']);
    $q13         = s($_POST['q13']);
    $q14_detalhe = s($_POST['q14_detalhe']);
    $q15         = s($_POST['q15']);
    $q16         = s($_POST['q16']);
    $q17         = s($_POST['q17']);
    $q18         = s($_POST['q18']);

    // Checkboxes
    $consumo = (isset($_POST['consumo']) && is_array($_POST['consumo']))
        ? implode(", ", $_POST['consumo'])
        : "Nenhum selecionado";

    $processo = (isset($_POST['processo']) && is_array($_POST['processo']))
        ? implode(", ", $_POST['processo'])
        : "Nenhum selecionado";

    $para    = "contato@gasmaskelab.com.br";
    $assunto = "Briefing Artístico – GV";

    $corpo  = "BRIEFING ARTÍSTICO — GV × GASMASKE LAB\n";
    $corpo .= str_repeat("=", 50) . "\n\n";

    $corpo .= "BLOCO 1 — RAÍZES & HISTÓRIA\n";
    $corpo .= str_repeat("-", 40) . "\n";
    $corpo .= "01. Onde nasceu / infância:\n$q1\n\n";
    $corpo .= "02. Como a música entrou na vida:\n$q2\n\n";
    $corpo .= "03. Primeiro momento de certeza:\n$q3\n\n";
    $corpo .= "04. Maior virada:\n$q4\n\n";

    $corpo .= "BLOCO 2 — DISCOGRAFIA & OBRAS\n";
    $corpo .= str_repeat("-", 40) . "\n";
    $corpo .= "05. Discografia:\n$q5\n\n";
    $corpo .= "06. Música favorita e por quê:\n$q6\n\n";
    $corpo .= "07. Música com relação diferente:\n$q7\n\n";
    $corpo .= "08. O que ainda quer fazer:\n$q8\n\n";

    $corpo .= "BLOCO 3 — REFERÊNCIAS & UNIVERSO\n";
    $corpo .= str_repeat("-", 40) . "\n";
    $corpo .= "09. Artistas que formaram:\n$q9\n\n";
    $corpo .= "10. Consumo além da música: $consumo\n";
    $corpo .= "Detalhes: $q10_detalhe\n\n";
    $corpo .= "11. Se o som fosse uma cena de filme:\n$q11\n\n";
    $corpo .= "12. Frase / filosofia que guia:\n$q12\n\n";

    $corpo .= "BLOCO 4 — PALCO & PROCESSO CRIATIVO\n";
    $corpo .= str_repeat("-", 40) . "\n";
    $corpo .= "13. Palco vs. estúdio:\n$q13\n\n";
    $corpo .= "14. Como a música nasce: $processo\n";
    $corpo .= "Detalhes: $q14_detalhe\n\n";
    $corpo .= "15. Rituais antes de se apresentar/gravar:\n$q15\n\n";

    $corpo .= "BLOCO 5 — IMAGEM & REDES\n";
    $corpo .= str_repeat("-", 40) . "\n";
    $corpo .= "16. Como quer ser descrito:\n$q16\n\n";
    $corpo .= "17. Conteúdo que bombou (entendeu / não entendeu):\n$q17\n\n";
    $corpo .= "18. O que nunca quer mostrar:\n$q18\n\n";

    $headers  = "From: contato@gasmaskelab.com.br\r\n";
    $headers .= "Reply-To: contato@gasmaskelab.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $envio = mail($para, $assunto, $corpo, $headers, "-fcontato@gasmaskelab.com.br");

    if ($envio) {
        http_response_code(200);
        echo "Sucesso";
    } else {
        http_response_code(500);
        echo "Erro";
    }

} else {
    http_response_code(403);
    echo "Acesso negado";
}
?>
