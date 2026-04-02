<?php
session_start();
require_once 'includes/config.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['tipo_usuario'])) {
    if ($_SESSION['tipo_usuario'] === 'cliente') {
        header('Location: dashboard_cliente.php');
    } else {
        header('Location: dashboard_empresa.php');
    }
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/auth.php';
    
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipo'] ?? 'cliente';
    
    if ($tipo === 'cliente') {
        if (loginCliente($email, $senha, $pdo)) {
            header('Location: dashboard_cliente.php');
            exit;
        } else {
            $erro = 'Email ou senha inválidos para cliente.';
        }
    } else {
        if (loginEmpresa($email, $senha, $pdo)) {
            header('Location: dashboard_empresa.php');
            exit;
        } else {
            $erro = 'Email ou senha inválidos para empresa.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Plataforma de Serviços</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a2b3e 0%, #1a4a6f 100%);
            padding: 20px;
        }
        .login-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-area h1 {
            font-size: 36px;
            color: #1a4a6f;
            margin-bottom: 10px;
        }
        .logo-area h1 span {
            color: #d4af37;
        }
        .logo-area p {
            color: #666;
        }
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: #666;
        }
        .tab-btn.active {
            color: #1a4a6f;
            border-bottom: 2px solid #d4af37;
            margin-bottom: -2px;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #d4af37;
            outline: none;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 74, 111, 0.3);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        .register-link a {
            color: #d4af37;
            text-decoration: none;
        }
        .error-msg {
            background: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-area">
                <h1>Service<span>Hub</span></h1>
                <p>Conectando clientes e prestadores de serviço</p>
            </div>
            
            <?php if ($erro): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $erro ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('cliente')">Sou Cliente</button>
                <button class="tab-btn" onclick="switchTab('empresa')">Sou Empresa</button>
            </div>
            
            <form method="post" id="form-cliente" class="tab-pane active">
                <input type="hidden" name="tipo" value="cliente">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" placeholder="********" required>
                </div>
                <button type="submit" class="btn-login">Entrar como Cliente</button>
                <div class="register-link">
                    Não tem uma conta? <a href="clientes/cadastro.php">Cadastre-se</a>
                </div>
            </form>
            
            <form method="post" id="form-empresa" class="tab-pane">
                <input type="hidden" name="tipo" value="empresa">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="empresa@email.com" required>
                </div>
                <div class="form-group">
                    <label>Senha</label>
                    <input type="password" name="senha" class="form-control" placeholder="********" required>
                </div>
                <button type="submit" class="btn-login">Entrar como Empresa</button>
                <div class="register-link">
                    Não tem uma conta? <a href="empresas/cadastro.php">Cadastre sua empresa</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function switchTab(tipo) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            if (tipo === 'cliente') {
                document.querySelector('.tab-btn:first-child').classList.add('active');
                document.getElementById('form-cliente').classList.add('active');
            } else {
                document.querySelector('.tab-btn:last-child').classList.add('active');
                document.getElementById('form-empresa').classList.add('active');
            }
        }
    </script>
</body>
</html>