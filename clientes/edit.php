<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    header('Location: index.php?msg=' . urlencode('Cliente não encontrado') . '&type=error');
    exit;
}

$erros = [];
$nome = $cliente['nome'];
$email = $cliente['email'];
$telefone = $cliente['telefone'];
$endereco = $cliente['endereco'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');

    if (empty($nome)) $erros['nome'] = 'Nome é obrigatório.';
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'Email inválido.';

    if (empty($erros)) {
        $update = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ?, endereco = ? WHERE id = ?");
        if ($update->execute([$nome, $email, $telefone, $endereco, $id])) {
            header('Location: index.php?msg=' . urlencode('Cliente atualizado com sucesso!') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao atualizar cliente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
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
                <li><a href="../clientes/index.php">Clientes</a></li>
                <li><a href="../orcamentos/index.php">Orçamentos</a></li>
                <li><a href="../relatorios/index.php">Relatórios</a></li>
            </ul>
        </nav>
    </div>
</header>
    <div class="container">
        <h1>Editar Cliente</h1>
        <a href="index.php" class="btn">Voltar</a>

        <?php if (!empty($erros['geral'])) echo showMessage($erros['geral'], 'error'); ?>

        <form method="post">
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>">
                <?php if (isset($erros['nome'])): ?><small style="color:red;"><?= $erros['nome'] ?></small><?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
                <?php if (isset($erros['email'])): ?><small style="color:red;"><?= $erros['email'] ?></small><?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="text" id="telefone" name="telefone" class="form-control" value="<?= htmlspecialchars($telefone) ?>">
            </div>
            
            <div class="form-group">
                <label for="endereco">Endereço</label>
                <textarea id="endereco" name="endereco" class="form-control" rows="3"><?= htmlspecialchars($endereco) ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Atualizar</button>
            </div>
        </form>
    </div>
</body>
</html>