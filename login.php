<?php
// login.php
require_once 'config/session.php';
require_once 'config/database.php';

if (isLogado()) {
    header("Location: index.php");
    exit;
}

$erro = '';
$versao_sistema = "2.1.0"; // Versão para o rodapé

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    if (!empty($email) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT id, nome, senha, perfil, cliente_id FROM usuarios WHERE email = :email AND ativo = 1");
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];
            $_SESSION['cliente_id'] = $usuario['cliente_id'];
            header("Location: index.php");
            exit;
        } else {
            $erro = "E-mail ou senha incorretos.";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gasmaske Workspace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        :root {
            --login-bg: #0a0a0a;
            --login-card: #141414;
            --login-red: #FF3F34;
            --login-text: #ffffff;
            --login-muted: #71717a;
            --login-border: #27272a;
        }

        body { 
            margin: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--login-bg);
            color: var(--login-text);
            display: flex;
            flex-direction: column; /* Para alinhar o card e o footer */
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .glow {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(255, 63, 52, 0.08) 0%, rgba(0,0,0,0) 70%);
            z-index: 0;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .login-card {
            background: var(--login-card);
            border: 1px solid var(--login-border);
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 400px;
            z-index: 1;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .brand-wrapper {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-h {
            width: 220px; /* Aumentei para destacar a logo horizontal */
            height: auto;
        }

        .form-group { margin-bottom: 20px; }

        .form-group label {
            display: block;
            font-size: 11px;
            color: var(--login-muted);
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            color: var(--login-muted);
            font-size: 20px;
        }

        .form-input {
            width: 100%;
            background: #000;
            border: 1px solid var(--login-border);
            padding: 14px 14px 14px 45px;
            color: #fff;
            border-radius: 10px;
            font-size: 15px;
            transition: 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--login-red);
            box-shadow: 0 0 0 2px rgba(255, 63, 52, 0.2);
        }

        .btn-login {
            width: 100%;
            background: var(--login-red);
            color: #fff;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            filter: brightness(1.2);
            transform: translateY(-2px);
        }

        /* --- FOOTER ESTILIZADO --- */
        .login-footer {
            margin-top: 40px;
            z-index: 1;
            text-align: center;
            width: 100%;
            max-width: 600px;
        }

        .footer-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            font-size: 12px;
            color: var(--login-muted);
        }

        .footer-info span { display: flex; align-items: center; gap: 5px; }
        .footer-info i { color: var(--login-red); }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .social-links a {
            color: var(--login-muted);
            font-size: 22px;
            transition: 0.3s;
        }

        .social-links a:hover { color: var(--login-red); }

        .system-meta {
            font-size: 10px;
            color: #3f3f46;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .alert-error {
            background: rgba(255, 63, 52, 0.1);
            color: var(--login-red);
            border: 1px solid var(--login-red);
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <div class="glow"></div>

    <div class="login-card">
        <div class="brand-wrapper">
            <img src="assets/img/logo-h.png" class="logo-h" alt="Gasmaske Lab">
        </div>

        <?php if ($erro): ?>
            <div class="alert-error"><?= $erro ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>ACESSO AO WORKSPACE</label>
                <div class="input-wrapper">
                    <i class="ph ph-user"></i>
                    <input type="email" name="email" class="form-input" placeholder="seu@email.com" required>
                </div>
            </div>

            <div class="form-group">
                <label>SENHA DE SEGURANÇA</label>
                <div class="input-wrapper">
                    <i class="ph ph-key"></i>
                    <input type="password" name="senha" class="form-input" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>

    <footer class="login-footer">
        <div class="footer-info">
            <span><i class="ph ph-envelope"></i> contato@gasmaskelab.com.br</span>
            <span><i class="ph ph-whatsapp-logo"></i> 27 99934-7112</span>
            <span><i class="ph ph-buildings"></i> CNPJ: 58.714.373/0001-04</span>
        </div>

        <div class="social-links">
            <a href="https://instagram.com/gasmaskelab" target="_blank"><i class="ph ph-instagram-logo"></i></a>
            <a href="https://www.tiktok.com/@gasmaskelab" target="_blank"><i class="ph ph-tiktok-logo"></i></a>
            <a href="https://linkedin.com/company/gasmaskelab" target="_blank"><i class="ph ph-linkedin-logo"></i></a>
        </div>

        <div class="system-meta">
            Gasmaske Workspace &copy; <?= date('Y') ?> | Version <?= $versao_sistema ?>
        </div>
    </footer>

</body>
</html>