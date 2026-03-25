<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;

// Buscar total de registros
$totalStmt = $pdo->query("SELECT COUNT(*) FROM servicos");
$total = $totalStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Buscar serviços com paginação
$stmt = $pdo->prepare("SELECT * FROM servicos ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$servicos = $stmt->fetchAll();

// Buscar categorias únicas para filtro
$categorias = $pdo->query("SELECT DISTINCT categoria FROM servicos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ServiceHub - Serviços</title>
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
        <h1>📋 Serviços</h1>
        <div class="text-right mb-3">
            <a href="create.php" class="btn">➕ Novo Serviço</a>
            <a href="../orcamentos/index.php" class="btn">💰 Orçamentos</a>
            <a href="../clientes/index.php" class="btn">👥 Clientes</a>
            <a href="../index.php" class="btn">🏠 Início</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php echo showMessage(urldecode($_GET['msg']), $_GET['type'] ?? 'success'); ?>
        <?php endif; ?>

        <!-- Filtro por categoria -->
        <?php if (!empty($categorias)): ?>
        <div class="filtros mb-3">
            <strong>Filtrar por categoria:</strong>
            <a href="index.php" class="btn btn-small">Todos</a>
            <?php foreach ($categorias as $cat): ?>
                <a href="?categoria=<?= urlencode($cat) ?>" class="btn btn-small"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Descrição</th>
                    <th>Valor</th>
                    <th>Duração</th>
                    <th>Categoria</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicos as $serv): ?>
                    <tr>
                        <td><?= $serv['id'] ?></td>
                        <td><?= htmlspecialchars($serv['nome']) ?></td>
                        <td><?= htmlspecialchars(substr($serv['descricao'], 0, 50)) ?>...</td>
                        <td>R$ <?= number_format($serv['valor'], 2, ',', '.') ?></td>
                        <td><?= $serv['duracao_estimada'] ? $serv['duracao_estimada'] . 'h' : '-' ?></td>
                        <td><?= htmlspecialchars($serv['categoria'] ?: '-') ?></td>
                        <td>
                            <span class="badge badge-<?= $serv['status'] ? 'success' : 'danger' ?>">
                                <?= $serv['status'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit.php?id=<?= $serv['id'] ?>" class="btn btn-warning">✏️ Editar</a>
                            <a href="delete.php?id=<?= $serv['id'] ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja excluir este serviço?')">🗑️ Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($servicos)): ?>
                    <tr><td colspan="8" style="text-align: center;">Nenhum serviço cadastrado.</td></tr>
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