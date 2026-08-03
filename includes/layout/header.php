<?php
// includes/layout/header.php
$temaAtivo = (isset($_SESSION['tema_ui']) && $_SESSION['tema_ui'] === 'light') ? 'theme-light' : '';
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