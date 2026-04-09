<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

verificarLogin();

if (!isEmpresa()) {
    header('Location: ../index.php');
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Buscar dados da empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: ../dashboard_empresa.php');
    exit;
}

$erros = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = cleanInput($_POST['nome_empresa'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $cnpj = cleanInput($_POST['cnpj'] ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');
    $descricao = cleanInput($_POST['descricao'] ?? '');
    $site = cleanInput($_POST['site'] ?? '');
    
    if (empty($nome_empresa)) $erros['nome_empresa'] = 'Nome da empresa é obrigatório.';
    if (empty($email)) $erros['email'] = 'Email é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Email inválido.';
    
    // Verificar email duplicado
    if ($email !== $empresa['email']) {
        $check = $pdo->prepare("SELECT id FROM empresas WHERE email = ? AND id != ?");
        $check->execute([$email, $empresa_id]);
        if ($check->fetch()) $erros['email'] = 'Email já cadastrado para outra empresa.';
    }
    
    // Verificar CNPJ duplicado
    if (!empty($cnpj) && $cnpj !== $empresa['cnpj']) {
        $check = $pdo->prepare("SELECT id FROM empresas WHERE cnpj = ? AND id != ?");
        $check->execute([$cnpj, $empresa_id]);
        if ($check->fetch()) $erros['cnpj'] = 'CNPJ já cadastrado para outra empresa.';
    }
    
    // Atualizar senha se fornecida
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    if (!empty($senha)) {
        if (strlen($senha) < 6) {
            $erros['senha'] = 'Senha deve ter no mínimo 6 caracteres.';
        } elseif ($senha !== $confirmar_senha) {
            $erros['confirmar_senha'] = 'As senhas não conferem.';
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        }
    }
    
    if (empty($erros)) {
        if (isset($senha_hash)) {
            $update = $pdo->prepare("UPDATE empresas SET nome_empresa = ?, email = ?, cnpj = ?, telefone = ?, endereco = ?, descricao = ?, site = ?, senha = ? WHERE id = ?");
            $result = $update->execute([$nome_empresa, $email, $cnpj, $telefone, $endereco, $descricao, $site, $senha_hash, $empresa_id]);
        } else {
            $update = $pdo->prepare("UPDATE empresas SET nome_empresa = ?, email = ?, cnpj = ?, telefone = ?, endereco = ?, descricao = ?, site = ? WHERE id = ?");
            $result = $update->execute([$nome_empresa, $email, $cnpj, $telefone, $endereco, $descricao, $site, $empresa_id]);
        }
        
        if ($result) {
            $_SESSION['empresa_nome'] = $nome_empresa;
            $_SESSION['empresa_email'] = $email;
            $success = 'Perfil atualizado com sucesso!';
            // Recarregar dados
            $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $empresa = $stmt->fetch();
        } else {
            $erros['geral'] = 'Erro ao atualizar perfil.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Perfil da Empresa</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 30px 0;
        }
        .profile-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .profile-avatar {
            text-align: center;
            margin-bottom: 30px;
        }
        .profile-avatar i {
            font-size: 80px;
            color: #d4af37;
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
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #d4af37;
            outline: none;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn-save {
            background: #d4af37;
            color: #0a2b3e;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-save:hover {
            background: #c4a02e;
        }
        .error-text {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: block;
        }
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section-divider {
            border-top: 1px solid #eee;
            margin: 25px 0;
            padding-top: 20px;
        }
        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .profile-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div style="max-width: 1280px; margin: 0 auto; padding: 0 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1><i class="fas fa-building"></i> Perfil da Empresa</h1>
                    <p>Gerencie as informações da sua empresa</p>
                </div>
                <a href="../dashboard_empresa.php" class="btn-outline" style="color: white; border-color: white;">← Voltar</a>
            </div>
        </div>
    </div>
    
    <div class="profile-container">
        <div class="profile-avatar">
            <i class="fas fa-building"></i>
            <h2><?= htmlspecialchars($empresa['nome_empresa']) ?></h2>
            <p>Membro desde <?= date('d/m/Y', strtotime($empresa['created_at'])) ?></p>
        </div>
        
        <?php if ($success): ?>
            <div class="success-msg"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>
        
        <?php if (!empty($erros['geral'])): ?>
            <div class="alert alert-error"><?= $erros['geral'] ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>Nome da Empresa *</label>
                <input type="text" name="nome_empresa" class="form-control" value="<?= htmlspecialchars($empresa['nome_empresa']) ?>" required>
                <?php if (isset($erros['nome_empresa'])): ?>
                    <span class="error-text"><?= $erros['nome_empresa'] ?></span>
                <?php endif; ?>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($empresa['email']) ?>" required>
                    <?php if (isset($erros['email'])): ?>
                        <span class="error-text"><?= $erros['email'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>CNPJ</label>
                    <input type="text" name="cnpj" class="form-control" value="<?= htmlspecialchars($empresa['cnpj']) ?>" placeholder="00.000.000/0001-00">
                    <?php if (isset($erros['cnpj'])): ?>
                        <span class="error-text"><?= $erros['cnpj'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($empresa['telefone']) ?>" placeholder="(11) 99999-9999">
                </div>
                <div class="form-group">
                    <label>Site</label>
                    <input type="url" name="site" class="form-control" value="<?= htmlspecialchars($empresa['site']) ?>" placeholder="https://www.exemplo.com">
                </div>
            </div>
            
            <div class="form-group">
                <label>Endereço</label>
                <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($empresa['endereco']) ?>" placeholder="Cidade, Estado">
            </div>
            
            <div class="form-group">
                <label>Descrição da Empresa</label>
                <textarea name="descricao" class="form-control" rows="4" placeholder="Descreva os serviços que sua empresa oferece..."><?= htmlspecialchars($empresa['descricao']) ?></textarea>
            </div>
            
            <div class="section-divider">
                <h3><i class="fas fa-lock"></i> Alterar Senha</h3>
                <p style="font-size: 12px; color: #666;">Deixe em branco para manter a senha atual</p>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" name="senha" class="form-control">
                    <?php if (isset($erros['senha'])): ?>
                        <span class="error-text"><?= $erros['senha'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha</label>
                    <input type="password" name="confirmar_senha" class="form-control">
                    <?php if (isset($erros['confirmar_senha'])): ?>
                        <span class="error-text"><?= $erros['confirmar_senha'] ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
        </form>
    </div>
</body>
</html>