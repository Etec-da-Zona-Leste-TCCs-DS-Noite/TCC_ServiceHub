<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = cleanInput($_POST['nome_empresa'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    $cnpj = cleanInput($_POST['cnpj'] ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');
    $descricao = cleanInput($_POST['descricao'] ?? '');
    $site = cleanInput($_POST['site'] ?? '');
    
    $erros = [];
    
    if (empty($nome_empresa)) $erros['nome_empresa'] = 'Nome da empresa é obrigatório.';
    if (empty($email)) $erros['email'] = 'Email é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Email inválido.';
    if (empty($senha)) $erros['senha'] = 'Senha é obrigatória.';
    elseif (strlen($senha) < 6) $erros['senha'] = 'Senha deve ter no mínimo 6 caracteres.';
    if ($senha !== $confirmar_senha) $erros['confirmar_senha'] = 'As senhas não conferem.';
    
    // Verificar email duplicado
    $check = $pdo->prepare("SELECT id FROM empresas WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) $erros['email'] = 'Email já cadastrado.';
    
    // Verificar CNPJ duplicado
    if (!empty($cnpj)) {
        $check = $pdo->prepare("SELECT id FROM empresas WHERE cnpj = ?");
        $check->execute([$cnpj]);
        if ($check->fetch()) $erros['cnpj'] = 'CNPJ já cadastrado.';
    }
    
    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO empresas (nome_empresa, email, senha, cnpj, telefone, endereco, descricao, site) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nome_empresa, $email, $senha_hash, $cnpj, $telefone, $endereco, $descricao, $site])) {
            header('Location: ../index.php?msg=' . urlencode('Empresa cadastrada com sucesso! Faça login.') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao cadastrar. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Cadastro de Empresa</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a2b3e 0%, #1a4a6f 100%);
            padding: 20px;
        }
        .auth-box {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 700px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo-area {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-area h1 {
            font-size: 32px;
            color: #1a4a6f;
            margin-bottom: 10px;
        }
        .logo-area h1 span {
            color: #d4af37;
        }
        .logo-area p {
            color: #666;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
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
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-submit:hover {
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
        .error-text {
            color: #c00;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
        .error-msg {
            background: #fee;
            color: #c00;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .auth-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="logo-area">
                <h1>Service<span>Hub</span></h1>
                <p>Cadastre sua empresa e comece a vender serviços</p>
            </div>
            
            <?php if (!empty($erros['geral'])): ?>
                <div class="error-msg"><?= $erros['geral'] ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome da Empresa *</label>
                        <input type="text" name="nome_empresa" class="form-control" value="<?= htmlspecialchars($nome_empresa ?? '') ?>" required>
                        <?php if (isset($erros['nome_empresa'])): ?>
                            <small class="error-text"><?= $erros['nome_empresa'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
                        <?php if (isset($erros['email'])): ?>
                            <small class="error-text"><?= $erros['email'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" value="<?= htmlspecialchars($cnpj ?? '') ?>" placeholder="00.000.000/0001-00">
                        <?php if (isset($erros['cnpj'])): ?>
                            <small class="error-text"><?= $erros['cnpj'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($telefone ?? '') ?>" placeholder="(11) 99999-9999">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Site</label>
                        <input type="url" name="site" class="form-control" value="<?= htmlspecialchars($site ?? '') ?>" placeholder="https://www.exemplo.com">
                    </div>
                    <div class="form-group">
                        <label>Endereço</label>
                        <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($endereco ?? '') ?>" placeholder="Cidade, Estado">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descrição da Empresa</label>
                    <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva os serviços que sua empresa oferece..."><?= htmlspecialchars($descricao ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Senha *</label>
                        <input type="password" name="senha" class="form-control" required>
                        <?php if (isset($erros['senha'])): ?>
                            <small class="error-text"><?= $erros['senha'] ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Confirmar Senha *</label>
                        <input type="password" name="confirmar_senha" class="form-control" required>
                        <?php if (isset($erros['confirmar_senha'])): ?>
                            <small class="error-text"><?= $erros['confirmar_senha'] ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">Cadastrar Empresa</button>
                
                <div class="register-link">
                    Já tem uma conta? <a href="../index.php">Faça login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>