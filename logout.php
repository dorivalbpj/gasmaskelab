<?php
// logout.php

session_start();
session_destroy(); // Destrói todas as informações da sessão
header("Location: login.php"); // Manda de volta pra tela de login
exit;
?>