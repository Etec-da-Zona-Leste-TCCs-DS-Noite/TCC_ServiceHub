<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$erros = [];
$nome = $descricao = $categoria = '';
$valor = '';
$duracao_estimada = '';
$status = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome'] ?? '');
    $descricao = cleanInput($_POST['descricao'] ?? '');
    $categoria = cleanInput($_POST['categoria'] ?? '');
    $valor = str_replace(',', '.', $_POST['valor'] ?? '');
    $duracao_estimada = $_POST['duracao_estimada'] ?? '';
    $status = $_POST['status'] ?? 1;

    if (empty($nome)) $erros['nome'] = 'Nome é obrigatório.';
    if (empty($valor)) $erros['valor'] = 'Valor é obrigatório.';
    elseif (!is_numeric($valor)) $erros['valor'] = 'Valor inválido.';

    if (empty($erros)) {
        $stmt = $pdo->prepare("INSERT INTO servicos (nome, descricao, valor, duracao_estimada, categoria, status) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nome, $descricao, $valor, $duracao_estimada, $categoria, $status])) {
            header('Location: index.php?msg=' . urlencode('Serviço cadastrado com sucesso!') . '&type=success');
            exit;
        } else {
            $erros['geral'] = 'Erro ao cadastrar serviço.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Novo Serviço</title>
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
        <h1>Novo Serviço</h1>
        <a href="index.php" class="btn">Voltar</a>

        <?php if (!empty($erros['geral'])) echo showMessage($erros['geral'], 'error'); ?>

        <form method="post">
            <div class="form-group">
                <label for="nome">Nome *</label>
                <input type="text" id="nome" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>">
                <?php if (isset($erros['nome'])): ?><small style="color:red;"><?= $erros['nome'] ?></small><?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="descricao">Descrição</label>
                <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= htmlspecialchars($descricao) ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="categoria">Categoria</label>
                <input type="text" id="categoria" name="categoria" class="form-control" value="<?= htmlspecialchars($categoria) ?>">
            </div>
            
            <div class="form-group">
                <label for="valor">Valor (R$) *</label>
                <input type="text" id="valor" name="valor" class="form-control" value="<?= $valor ?>" placeholder="0,00">
                <?php if (isset($erros['valor'])): ?><small style="color:red;"><?= $erros['valor'] ?></small><?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="duracao_estimada">Duração Estimada (horas)</label>
                <input type="number" id="duracao_estimada" name="duracao_estimada" class="form-control" value="<?= $duracao_estimada ?>">
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="1" <?= $status == 1 ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= $status == 0 ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">Salvar</button>
            </div>
        </form>
    </div>
</body>
</html>