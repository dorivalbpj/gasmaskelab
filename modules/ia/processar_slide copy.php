<?php
// modules/ia/processar_slide.php
// Função compartilhada: processa UM slide pendente da fila.
// Usada tanto pelo worker_imagem.php (se você quiser manter um cron de apoio)
// quanto diretamente pelo ajax_criar_fila.php (execução imediata, sem depender de rede).

function processarProximoSlideDaFila(PDO $pdo): bool {

    $stmt = $pdo->query("
        SELECT cs.id as slide_id, cs.numero_slide, cs.modelo_usado, cs.carrossel_id,
               c.assunto, c.formato, c.quantidade_imagens, c.cliente_id,
               cli.briefing_ia
        FROM carrossel_slides cs
        JOIN carrosseis c ON cs.carrossel_id = c.id
        JOIN clientes cli ON c.cliente_id = cli.id
        WHERE cs.status = 'pendente'
        ORDER BY cs.id ASC
        LIMIT 1
    ");

    $tarefa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarefa) {
        return false; // fila vazia
    }

    $slide_id     = $tarefa['slide_id'];
    $carrossel_id = $tarefa['carrossel_id'];

    $pdo->prepare("UPDATE carrossel_slides SET status = 'gerando' WHERE id = ?")->execute([$slide_id]);

    try {
        $briefing = $tarefa['briefing_ia'] ?: 'Estilo moderno, limpo e corporativo.';
        $assunto  = $tarefa['assunto'];
        $numero   = $tarefa['numero_slide'];
        $total    = $tarefa['quantidade_imagens'];

        $prompt  = "Você é um designer gráfico. Crie o slide {$numero} de {$total} para um carrossel de redes sociais. ";
        $prompt .= "Assunto deste post: {$assunto}. ";
        $prompt .= "Instruções de estilo e marca: {$briefing}. ";
        $prompt .= "IMPORTANTE: Não coloque textos longos na imagem, crie apenas a composição visual, elementos gráficos, fotos ou ilustrações que representem o tema.";

        $aspectRatio = "1:1";
        if ($tarefa['formato'] === '1080x1350') {
            $aspectRatio = "3:4";
        } elseif ($tarefa['formato'] === '1080x1920') {
            $aspectRatio = "9:16";
        }

        $modeloApi = ($tarefa['modelo_usado'] === 'nano_banana_pro')
            ? 'gemini-3-pro-image'
            : 'gemini-3.1-flash-image';

        $api_key = trim(GEMINI_API_KEY);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modeloApi}:generateContent?key={$api_key}";

        $data = [
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ],
            "generationConfig" => [
                "responseModalities" => ["TEXT", "IMAGE"],
                "imageConfig" => ["aspectRatio" => $aspectRatio]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err       = curl_error($ch);
        curl_close($ch);

        if ($response === false || $http_code >= 400) {
            throw new Exception("Erro API Gemini (HTTP $http_code): " . ($err ?: $response));
        }

        $resArr = json_decode($response, true);

        $base64_image = null;
        $mimeType = 'image/png';
        $parts = $resArr['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (!empty($part['inlineData']['data'])) {
                $base64_image = $part['inlineData']['data'];
                $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                break;
            }
        }

        if (!$base64_image) {
            $motivo = $resArr['promptFeedback']['blockReason'] ?? 'resposta sem imagem: ' . substr($response, 0, 300);
            throw new Exception("Falha ao gerar imagem: {$motivo}");
        }

        $extensao = (strpos($mimeType, 'png') !== false) ? 'png' : 'jpg';

        $base_path  = dirname(__DIR__, 2);
        $upload_dir = $base_path . '/uploads/carrosseis/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = 'slide_' . $slide_id . '_' . time() . '.' . $extensao;
        $filepath = $upload_dir . $filename;
        file_put_contents($filepath, base64_decode($base64_image));

        $url_imagem_publica = BASE_URL . 'uploads/carrosseis/' . $filename;

        $pdo->beginTransaction();

        $stmtVersao = $pdo->prepare("SELECT IFNULL(MAX(versao), 0) + 1 FROM carrossel_slide_versoes WHERE slide_id = ?");
        $stmtVersao->execute([$slide_id]);
        $nova_versao = $stmtVersao->fetchColumn();

        $custo = ($tarefa['modelo_usado'] === 'nano_banana_pro') ? 0.1500 : 0.0500;

        $stmtInsertVersao = $pdo->prepare("INSERT INTO carrossel_slide_versoes (slide_id, versao, modelo_usado, url_imagem, custo_estimado) VALUES (?, ?, ?, ?, ?)");
        $stmtInsertVersao->execute([$slide_id, $nova_versao, $tarefa['modelo_usado'], $url_imagem_publica, $custo]);

        $stmtUpdateSlide = $pdo->prepare("UPDATE carrossel_slides SET status = 'pronto', versao_atual = ? WHERE id = ?");
        $stmtUpdateSlide->execute([$nova_versao, $slide_id]);

        $pdo->commit();

        $stmtChecarFinalizado = $pdo->prepare("SELECT COUNT(*) FROM carrossel_slides WHERE carrossel_id = ? AND status != 'pronto'");
        $stmtChecarFinalizado->execute([$carrossel_id]);
        if ($stmtChecarFinalizado->fetchColumn() == 0) {
            $pdo->prepare("UPDATE carrosseis SET status = 'concluido' WHERE id = ?")->execute([$carrossel_id]);
        }

        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e->getMessage();
        $pdo->prepare("UPDATE carrossel_slides SET status = 'erro' WHERE id = ?")->execute([$slide_id]);

        $stmtErro = $pdo->prepare("INSERT INTO carrossel_slide_versoes (slide_id, versao, modelo_usado, erro_mensagem) VALUES (?, 1, ?, ?)");
        $stmtErro->execute([$slide_id, 'erro_sistema', substr($msg, 0, 500)]);

        return true; // processou (com erro), mas consumiu um item da fila
    }
}