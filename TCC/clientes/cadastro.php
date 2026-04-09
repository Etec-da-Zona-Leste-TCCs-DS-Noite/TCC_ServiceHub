<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome           = cleanInput($_POST['nome']           ?? '');
    $email          = cleanInput($_POST['email']          ?? '');
    $senha          = $_POST['senha']           ?? '';
    $confirmar_senha= $_POST['confirmar_senha'] ?? '';
    $telefone       = cleanInput($_POST['telefone']       ?? '');
    $endereco       = cleanInput($_POST['endereco']       ?? '');
    $tipo           = $_POST['tipo']            ?? 'fisica';
    $cpf_cnpj       = cleanInput($_POST['cpf_cnpj']       ?? '');

    $erros = [];

    if (empty($nome))  $erros['nome']  = 'Nome é obrigatório.';
    if (empty($email)) $erros['email'] = 'E-mail é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'E-mail inválido.';
    if (empty($senha)) $erros['senha'] = 'Senha é obrigatória.';
    elseif (strlen($senha) < 6) $erros['senha'] = 'Senha deve ter no mínimo 6 caracteres.';
    if ($senha !== $confirmar_senha) $erros['confirmar_senha'] = 'As senhas não conferem.';

    // Verificar e-mail duplicado
    $check = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) $erros['email'] = 'E-mail já cadastrado.';

    if (empty($erros)) {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO clientes (nome, email, senha, telefone, endereco, tipo, cpf_cnpj) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if ($stmt->execute([$nome, $email, $senha_hash, $telefone, $endereco, $tipo, $cpf_cnpj])) {
            header('Location: ../index.php?msg=' . urlencode('Cadastro realizado com sucesso! Faça login.') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao cadastrar. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Cadastro de Cliente</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #0a2b3e 0%, #1a4a6f 100%);
            padding: 20px;
        }
        .auth-box {
            background: white; border-radius: 20px; padding: 40px;
            width: 100%; max-width: 620px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo-area { text-align: center; margin-bottom: 30px; }
        .logo-area h1 { font-size: 32px; color: #1a4a6f; }
        .logo-area h1 span { color: #d4af37; }
        .logo-area p { color: #666; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; color: #333; }
        .form-control {
            width: 100%; padding: 10px 12px;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; box-sizing: border-box; transition: all 0.3s;
        }
        .form-control:focus { border-color: #d4af37; outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,0.1); }
        .btn-submit {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(26,74,111,0.3); }
        .register-link { text-align: center; margin-top: 20px; font-size: 14px; }
        .register-link a { color: #d4af37; text-decoration: none; }
        .error-text { color: #c00; font-size: 12px; display: block; margin-top: 4px; }
        .error-msg  { background: #fee; color: #c00; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .auth-box { padding: 25px 15px; }
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-box">
        <div class="logo-area">
            <h1>Service<span>Hub</span></h1>
            <p>Crie sua conta de cliente</p>
        </div>

        <?php if (!empty($erros['geral'])): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?= $erros['geral'] ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>Nome Completo *</label>
                <input type="text" name="nome" class="form-control"
                       value="<?= htmlspecialchars($nome ?? '') ?>" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="error-text"><?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>E-mail *</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($email ?? '') ?>" required>
                    <?php if (isset($erros['email'])): ?>
                        <span class="error-text"><?= $erros['email'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" class="form-control"
                           value="<?= htmlspecialchars($telefone ?? '') ?>" placeholder="(11) 99999-9999">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de Pessoa</label>
                    <select name="tipo" class="form-control">
                        <option value="fisica"   <?= (($tipo ?? '') === 'fisica')   ? 'selected' : '' ?>>Pessoa Física</option>
                        <option value="juridica" <?= (($tipo ?? '') === 'juridica') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CPF / CNPJ</label>
                    <input type="text" name="cpf_cnpj" class="form-control"
                           value="<?= htmlspecialchars($cpf_cnpj ?? '') ?>" placeholder="000.000.000-00">
                </div>
            </div>

            <div class="form-group">
                <label>Endereço</label>
                <textarea name="endereco" class="form-control" rows="2"><?= htmlspecialchars($endereco ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Senha *</label>
                    <input type="password" name="senha" class="form-control" required>
                    <?php if (isset($erros['senha'])): ?>
                        <span class="error-text"><?= $erros['senha'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha *</label>
                    <input type="password" name="confirmar_senha" class="form-control" required>
                    <?php if (isset($erros['confirmar_senha'])): ?>
                        <span class="error-text"><?= $erros['confirmar_senha'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus"></i> Cadastrar
            </button>

            <div class="register-link">
                Já tem uma conta? <a href="../index.php">Faça login</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
