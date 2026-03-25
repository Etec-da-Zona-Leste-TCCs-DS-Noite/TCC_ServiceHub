<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtros
$status_filtro = $_GET['status'] ?? '';
$cliente_filtro = $_GET['cliente'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Montar query com filtros
$sql = "SELECT o.*, c.nome as cliente_nome 
        FROM orcamentos o 
        LEFT JOIN clientes c ON c.id = o.cliente_id 
        WHERE 1=1";
$params = [];

if ($status_filtro) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filtro;
}

if ($cliente_filtro) {
    $sql .= " AND o.cliente_id = ?";
    $params[] = $cliente_filtro;
}

if ($data_inicio) {
    $sql .= " AND o.data_orcamento >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $sql .= " AND o.data_orcamento <= ?";
    $params[] = $data_fim;
}

// Contar total para paginação
$countSql = str_replace("SELECT o.*, c.nome as cliente_nome", "SELECT COUNT(*)", $sql);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Adicionar ordenação e paginação
$sql .= " ORDER BY o.id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

// Bind dos parâmetros
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value);
}
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orcamentos = $stmt->fetchAll();

// Buscar clientes para o filtro
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

$statusColors = [
    'pendente' => 'warning',
    'aprovado' => 'success',
    'rejeitado' => 'danger',
    'expirado' => 'secondary'
];

$statusLabels = [
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'rejeitado' => 'Rejeitado',
    'expirado' => 'Expirado'
];
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ServiceHub - Orçamentos</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <style>
        .badge-warning { background: #ffc107; color: #000; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; }
        .badge-success { background: #28a745; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; }
        .badge-danger { background: #dc3545; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; }
        .badge-secondary { background: #6c757d; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-block; }
        
        .filtros {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filtros .form-group {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 10px;
        }
        
        .filtros label {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: #666;
        }
        
        .btn-limpar {
            background: #6c757d;
            vertical-align: bottom;
            margin-top: 24px;
        }
        
        .btn-limpar:hover {
            background: #5a6268;
        }
        
        .total-resumo {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-resumo .valor {
            font-size: 24px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .filtros .form-group {
                display: block;
                margin-right: 0;
            }
            
            .btn-limpar {
                margin-top: 0;
            }
        }
    </style>
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
        <h1>💰 Orçamentos</h1>
        <div class="text-right mb-3">
            <a href="create.php" class="btn">➕ Novo Orçamento</a>
            <a href="../servicos/index.php" class="btn">📋 Serviços</a>
            <a href="../clientes/index.php" class="btn">👥 Clientes</a>
            <a href="../relatorios/index.php" class="btn">📊 Relatórios</a>
            <a href="../index.php" class="btn">🏠 Início</a>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php echo showMessage(urldecode($_GET['msg']), $_GET['type'] ?? 'success'); ?>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filtros">
            <form method="get" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Todos</option>
                        <option value="pendente" <?= $status_filtro == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="aprovado" <?= $status_filtro == 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                        <option value="rejeitado" <?= $status_filtro == 'rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                        <option value="expirado" <?= $status_filtro == 'expirado' ? 'selected' : '' ?>>Expirado</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Cliente</label>
                    <select name="cliente" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?= $cli['id'] ?>" <?= $cliente_filtro == $cli['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cli['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">🔍 Filtrar</button>
                    <a href="index.php" class="btn btn-limpar">🗑️ Limpar</a>
                </div>
            </form>
        </div>
        
        <!-- Resumo dos totais -->
        <?php
        // Calcular totais dos orçamentos filtrados
        $totalValor = 0;
        $totalPendente = 0;
        $totalAprovado = 0;
        
        foreach ($orcamentos as $orc) {
            $totalValor += $orc['valor_total'];
            if ($orc['status'] == 'pendente') $totalPendente += $orc['valor_total'];
            if ($orc['status'] == 'aprovado') $totalAprovado += $orc['valor_total'];
        }
        ?>
        
        <div class="total-resumo">
            <div>
                <strong>Total dos Orçamentos:</strong> <?= count($orcamentos) ?> orçamento(s)
                <br>
                <small>Pendente: R$ <?= number_format($totalPendente, 2, ',', '.') ?> | Aprovado: R$ <?= number_format($totalAprovado, 2, ',', '.') ?></small>
            </div>
            <div class="valor">
                R$ <?= number_format($totalValor, 2, ',', '.') ?>
            </div>
        </div>

        <!-- Tabela de orçamentos -->
         <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Validade</th>
                    <th>Valor Total</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orcamentos as $orc): ?>
                    <tr>
                        <td><?= $orc['id'] ?></td>
                        <td><?= htmlspecialchars($orc['cliente_nome'] ?? 'Cliente não informado') ?></td>
                        <td><?= formatDate($orc['data_orcamento']) ?></td>
                        <td><?= $orc['data_validade'] ? formatDate($orc['data_validade']) : '-' ?></td>
                        <td><strong>R$ <?= number_format($orc['valor_total'], 2, ',', '.') ?></strong></td>
                        <td>
                            <span class="badge-<?= $statusColors[$orc['status']] ?>">
                                <?= $statusLabels[$orc['status']] ?>
                            </span>
                        </td>
                        <td>
                            <a href="view.php?id=<?= $orc['id'] ?>" class="btn" style="padding: 4px 8px; font-size: 12px;">👁️ Ver</a>
                            <a href="edit.php?id=<?= $orc['id'] ?>" class="btn btn-warning" style="padding: 4px 8px; font-size: 12px;">✏️ Editar</a>
                            <a href="delete.php?id=<?= $orc['id'] ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;" onclick="return confirm('Tem certeza que deseja excluir este orçamento?')">🗑️ Excluir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orcamentos)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <p>Nenhum orçamento encontrado.</p>
                            <a href="create.php" class="btn">➕ Criar primeiro orçamento</a>
                        </td>
                    </tr>
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
                            <a href="?page=<?= $i ?>&status=<?= urlencode($status_filtro) ?>&cliente=<?= urlencode($cliente_filtro) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>
            </ul>
        <?php endif; ?>
        
        <!-- Botões de ação rápida -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
            <a href="../relatorios/index.php" class="btn">📊 Ver Relatórios Completos</a>
            <a href="create.php" class="btn" style="background: #28a745;">➕ Novo Orçamento</a>
        </div>
    </div>
</body>
</html>