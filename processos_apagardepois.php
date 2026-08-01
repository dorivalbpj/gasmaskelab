<?php
// Configuração do banco de dados
$host = 'db';
$dbname = 'gasmaske_db';
$user = 'root';
$pass = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conectado ao banco!\n";
    
    // Buscar clientes para mapeamento por nome
    $clientes = [];
    $stmt = $pdo->query("SELECT id, nome FROM clientes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $clientes[strtolower(trim($row['nome']))] = $row['id'];
    }
    echo "Clientes carregados: " . count($clientes) . "\n";
    
    // Buscar usuários para mapeamento por nome
    $usuarios = [];
    $stmt = $pdo->query("SELECT id, nome FROM usuarios");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $usuarios[strtolower(trim($row['nome']))] = $row['id'];
    }
    echo "Usuários carregados: " . count($usuarios) . "\n";
    
    // Função para converter data DD/MM/YYYY para YYYY-MM-DD
    function converterData($data) {
        if (empty($data)) {
            return null;
        }
        
        $data = trim($data);
        
        // Tentar diferentes formatos
        // Formato DD/MM/YYYY
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $matches)) {
            $dia = $matches[1];
            $mes = $matches[2];
            $ano = $matches[3];
            
            // Validar se é uma data válida
            if (checkdate($mes, $dia, $ano)) {
                return "$ano-$mes-$dia";
            }
        }
        
        // Tentar formato DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $data, $matches)) {
            $dia = $matches[1];
            $mes = $matches[2];
            $ano = $matches[3];
            
            if (checkdate($mes, $dia, $ano)) {
                return "$ano-$mes-$dia";
            }
        }
        
        // Se não conseguir converter, tentar com strtotime como fallback
        $timestamp = strtotime($data);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        echo "Data não reconhecida: '$data'\n";
        return null;
    }
    
    // Abrir o arquivo CSV
    $csvFile = fopen('Tarefas - Tarefas (1).csv', 'r');
    if (!$csvFile) {
        die("Erro ao abrir o arquivo CSV\n");
    }
    
    // Ler cabeçalho
    $header = fgetcsv($csvFile, 0, ',');
    echo "Cabeçalho: " . implode(', ', $header) . "\n\n";
    
    $count = 0;
    $errors = 0;
    $inserted = 0;
    $skipped = 0;
    
    // Preparar a query de inserção
    $sql = "INSERT INTO planejamento (
        cliente_id,
        escopo,
        prioridade,
        responsavel_id,
        tipo,
        tema,
        descricao,
        data_publicacao,
        legenda,
        inspiracao,
        link_arte_final,
        status_geral,
        status_roteiro,
        status_peca,
        roteiro_revisoes,
        peca_revisoes
    ) VALUES (
        :cliente_id,
        :escopo,
        :prioridade,
        :responsavel_id,
        :tipo,
        :tema,
        :descricao,
        :data_publicacao,
        :legenda,
        :inspiracao,
        :link_arte_final,
        :status_geral,
        :status_roteiro,
        :status_peca,
        :roteiro_revisoes,
        :peca_revisoes
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Processar cada linha do CSV
    while (($row = fgetcsv($csvFile, 0, ',')) !== false) {
        $count++;
        
        // Mapear as colunas
        $data = array_combine($header, $row);
        
        // Verificar se é uma linha vazia
        if (empty($data['Tarefa']) && empty($data['cliente'])) {
            continue;
        }
        
        // Ignorar tarefas finalizadas
        $status = trim($data['status'] ?? '');
        if (strtolower($status) === 'finalizado' || strtolower($status) === 'arquivado') {
            $skipped++;
            echo "Pulando tarefa finalizada/arquivada: {$data['Tarefa']}\n";
            continue;
        }
        
        // Buscar cliente_id pelo nome
        $clienteNome = trim($data['cliente'] ?? '');
        $clienteId = null;
        if (!empty($clienteNome)) {
            $clienteKey = strtolower($clienteNome);
            if (isset($clientes[$clienteKey])) {
                $clienteId = $clientes[$clienteKey];
            } else {
                echo "⚠ Cliente não encontrado: '$clienteNome'\n";
            }
        }
        
        // Buscar responsavel_id pelo nome
        $responsavelNome = trim($data['responsável'] ?? '');
        $responsavelId = null;
        if (!empty($responsavelNome)) {
            $responsavelKey = strtolower($responsavelNome);
            if (isset($usuarios[$responsavelKey])) {
                $responsavelId = $usuarios[$responsavelKey];
            } else {
                echo "⚠ Usuário não encontrado: '$responsavelNome'\n";
            }
        }
        
        // Mapear prioridade
        $prioridade = strtolower(trim($data['prioridade'] ?? 'media'));
        if (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'])) {
            $prioridade = 'media';
        }
        
        // Converter data
        $dataPublicacao = converterData($data['prazo'] ?? '');
        if ($dataPublicacao) {
            echo "Data convertida: {$data['prazo']} → $dataPublicacao\n";
        }
        
        // Preparar dados para inserção
        $params = [
            ':cliente_id' => $clienteId,
            ':escopo' => 'cliente',
            ':prioridade' => $prioridade,
            ':responsavel_id' => $responsavelId,
            ':tipo' => trim($data['categoria'] ?? ''),
            ':tema' => trim($data['Tarefa'] ?? ''),
            ':descricao' => trim($data['descrição'] ?? ''),
            ':data_publicacao' => $dataPublicacao,
            ':legenda' => trim($data['Legenda'] ?? ''),
            ':inspiracao' => trim($data['Inspiração'] ?? ''),
            ':link_arte_final' => trim($data['Link entrega'] ?? ''),
            ':status_geral' => $status ?: 'pendente',
            ':status_roteiro' => 'pendente',
            ':status_peca' => 'pendente',
            ':roteiro_revisoes' => 0,
            ':peca_revisoes' => 0
        ];
        
        try {
            $stmt->execute($params);
            $inserted++;
            echo "✓ Inserido: {$data['Tarefa']}\n";
        } catch (PDOException $e) {
            $errors++;
            echo "✗ Erro ao inserir '{$data['Tarefa']}': " . $e->getMessage() . "\n";
            print_r($params);
        }
    }
    
    fclose($csvFile);
    
    echo "\n--- RESUMO ---\n";
    echo "Total de linhas processadas: $count\n";
    echo "Tarefas puladas (finalizadas/arquivadas): $skipped\n";
    echo "Inserções realizadas: $inserted\n";
    echo "Erros: $errors\n";
    
} catch (PDOException $e) {
    echo "Erro de banco de dados: " . $e->getMessage() . "\n";
}