<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$erros = [];
$nome = $email = $telefone = $endereco = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = cleanInput($_POST['nome']     ?? '');
    $email    = cleanInput($_POST['email']    ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');

    if (empty($nome))  $erros['nome']  = 'Nome é obrigatório.';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $erros['email'] = 'E-mail inválido.';

    if (empty($erros)) {
        // Gera uma senha aleatória temporária (o cliente pode redefinir depois)
        $senha_temp = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, senha, telefone, endereco) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$nome, $email, $senha_temp, $telefone, $endereco])) {
            header('Location: index.php?msg=' . urlencode('Cliente cadastrado com sucesso!') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao cadastrar cliente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Novo Cliente</title>
    <link rel="stylesheet" href="../css/estilo.css">
</head>
<body>

<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h1>ServiceHub</h1>
            <p>Gestão de Serviços e Orçamentos</p>
        </div>
        <nav class="main-nav">
            <ul>
                <li><a href="../index.php">Início</a></li>
                <li><a href="../servicos/index.php">Serviços</a></li>
                <li><a href="index.php">Clientes</a></li>
                <li><a href="../orcamentos/index.php">Orçamentos</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="container">
    <h1>Novo Cliente</h1>
    <a href="index.php" class="btn">← Voltar</a>

    <?php if (!empty($erros['geral'])): ?>
        <?= showMessage($erros['geral'], 'error') ?>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" class="form-control"
                   value="<?= htmlspecialchars($nome) ?>" required>
            <?php if (isset($erros['nome'])): ?>
                <small style="color:red;"><?= $erros['nome'] ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($email) ?>">
            <?php if (isset($erros['email'])): ?>
                <small style="color:red;"><?= $erros['email'] ?></small>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone</label>
            <input type="text" id="telefone" name="telefone" class="form-control"
                   value="<?= htmlspecialchars($telefone) ?>">
        </div>

        <div class="form-group">
            <label for="endereco">Endereço</label>
            <textarea id="endereco" name="endereco" class="form-control"
                      rows="3"><?= htmlspecialchars($endereco) ?></textarea>
        </div>

        <div class="form-group">
            <button type="submit" class="btn">Salvar</button>
        </div>
    </form>
</div>
</body>
</html>
