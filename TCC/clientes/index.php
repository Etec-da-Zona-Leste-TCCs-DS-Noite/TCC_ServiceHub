<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM clientes");
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

$stmt = $pdo->prepare("SELECT * FROM clientes ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ServiceHub - Clientes</title>
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
        <h1>👥 Clientes</h1>
        <div class="text-right mb-3">
            <a href="create.php" class="btn">➕ Novo Cliente</a>
            <a href="../servicos/index.php" class="btn">📋 Serviços</a>
            <a href="../orcamentos/index.php" class="btn">💰 Orçamentos</a>
            <a href="../index.php" class="btn">🏠 Início</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php echo showMessage(urldecode($_GET['msg']), $_GET['type'] ?? 'success'); ?>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Endereço</th>
                    <th>Orçamentos</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cli): 
                    // Contar orçamentos do cliente
                    $orcCount = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = ?");
                    $orcCount->execute([$cli['id']]);
                    $totalOrc = $orcCount->fetchColumn();
                    ?>
                    <tr>
                        <td><?= $cli['id'] ?></td>
                        <td><?= htmlspecialchars($cli['nome']) ?></td>
                        <td><?= htmlspecialchars($cli['email']) ?></td>
                        <td><?= htmlspecialchars($cli['telefone']) ?></td>
                        <td><?= htmlspecialchars(substr($cli['endereco'], 0, 50)) ?>...</td>
                        <td><?= $totalOrc ?></td>
                        <td>
                            <a href="edit.php?id=<?= $cli['id'] ?>" class="btn btn-warning">✏️ Editar</a>
                            <a href="delete.php?id=<?= $cli['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este cliente?')">🗑️ Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="7" style="text-align: center;">Nenhum cliente cadastrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginação -->
        <?php if ($totalPages > 1): ?>
            <ul class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="<?= ($i == $page) ? 'active' : '' ?>">
                        <?php if ($i == $page): ?>
                            <span><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>