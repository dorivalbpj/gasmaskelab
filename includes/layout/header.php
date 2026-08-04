<?php
// includes/layout/header.php
$temaAtivo = (isset($_SESSION['tema_ui']) && $_SESSION['tema_ui'] === 'light') ? 'theme-light' : '';

// =======================================================
// LÓGICA DE NOTIFICAÇÕES GLOBAIS
// =======================================================
global $pdo;

$notif_briefings = 0; 
$notif_propostas = 0; 
$notif_contratos = 0; 
$notif_crm = 0;

if (isset($pdo)) {
    $stmt_briefings = $pdo->query("SELECT COUNT(*) as qtd FROM briefings WHERE status = 'novo'");
    $notif_briefings = $stmt_briefings->fetch()['qtd'] ?? 0;
    
    $stmt_propostas = $pdo->query("SELECT COUNT(*) as qtd FROM propostas WHERE status IN ('aguardando_aprovacao', 'alterada', 'revisada', 'rascunho', 'enviada')");
    $notif_propostas = $stmt_propostas->fetch()['qtd'] ?? 0;
    
    $stmt_contratos = $pdo->query("SELECT COUNT(*) as qtd FROM contratos WHERE status IN ('aguardando_aceite_cliente', 'alterado')");
    $notif_contratos = $stmt_contratos->fetch()['qtd'] ?? 0;
    
    $stmt_crm = $pdo->query("SELECT COUNT(*) as qtd FROM leads WHERE data_proximo_contato IS NOT NULL AND data_proximo_contato <= CURDATE() AND status NOT IN ('ganho', 'perdido')");
    $notif_crm = $stmt_crm->fetch()['qtd'] ?? 0;
}

$total_notificacoes = $notif_briefings + $notif_propostas + $notif_contratos + $notif_crm;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Título principal -->
    <title>Gasmaske ERP</title>
    
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo-v.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Folha de estilo externa -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <!-- Script para o efeito Marquee na aba -->
    <script>
        let pageTitle = "Gasmaske ERP  ";
        
        function animateTitle() {
            pageTitle = pageTitle.substring(1) + pageTitle.substring(0, 1);
            document.title = pageTitle;
        }
        
        setInterval(animateTitle, 300);
    </script>
</head>
<body class="<?= $temaAtivo ?>">