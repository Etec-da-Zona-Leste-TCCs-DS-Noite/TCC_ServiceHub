<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Estatísticas gerais
$totalServicos = $pdo->query("SELECT COUNT(*) FROM servicos")->fetchColumn();
$totalServicosAtivos = $pdo->query("SELECT COUNT(*) FROM servicos WHERE status = 1")->fetchColumn();
$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$totalOrcamentos = $pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn();

// Orçamentos por status
$orcamentosPorStatus = [];
$statusList = ['pendente', 'aprovado', 'rejeitado', 'expirado'];
foreach ($statusList as $status) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE status = ?");
    $stmt->execute([$status]);
    $orcamentosPorStatus[$status] = $stmt->fetchColumn();
}

// Valores por status
$valoresPorStatus = [];
foreach ($statusList as $status) {
    $stmt = $pdo->prepare("SELECT SUM(valor_total) FROM orcamentos WHERE status = ?");
    $stmt->execute([$status]);
    $valoresPorStatus[$status] = $stmt->fetchColumn() ?? 0;
}

// Orçamentos por mês (últimos 12 meses)
$orcamentosPorMes = [];
for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*), SUM(valor_total) FROM orcamentos WHERE DATE_FORMAT(data_orcamento, '%Y-%m') = ?");
    $stmt->execute([$mes]);
    $result = $stmt->fetch();
    $orcamentosPorMes[] = [
        'mes' => $mes,
        'total' => $result[0] ?? 0,
        'valor' => $result[1] ?? 0
    ];
}

// Serviços mais solicitados
$servicosMaisSolicitados = $pdo->query("
    SELECT s.nome, COUNT(oi.id) as total_solicitacoes, SUM(oi.quantidade) as total_quantidade
    FROM servicos s
    LEFT JOIN orcamento_itens oi ON oi.servico_id = s.id
    GROUP BY s.id
    ORDER BY total_solicitacoes DESC
    LIMIT 10
")->fetchAll();

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$status_filtro = $_GET['status'] ?? '';

// Orçamentos filtrados
$sql = "SELECT o.*, c.nome as cliente_nome 
        FROM orcamentos o 
        LEFT JOIN clientes c ON c.id = o.cliente_id 
        WHERE o.data_orcamento BETWEEN ? AND ?";
$params = [$data_inicio, $data_fim];

if ($status_filtro) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filtro;
}
$sql .= " ORDER BY o.data_orcamento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orcamentosFiltrados = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>ServiceHub - Relatórios</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <style>
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
            color: white;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card .small {
            font-size: 12px;
            margin-top: 5px;
            opacity: 0.8;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
        }
        .filtros-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .filtros-form .form-group {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 0;
        }
        table {
            margin-top: 0;
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
        <h1>📊 Relatórios</h1>
        <div class="text-right mb-3">
            <a href="../servicos/index.php" class="btn">📋 Serviços</a>
            <a href="../clientes/index.php" class="btn">👥 Clientes</a>
            <a href="../orcamentos/index.php" class="btn">💰 Orçamentos</a>
            <a href="../index.php" class="btn">🏠 Início</a>
        </div>

        <!-- Cards de estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total de Serviços</h3>
                <div class="number"><?= $totalServicos ?></div>
                <div class="small"><?= $totalServicosAtivos ?> ativos</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>Total de Clientes</h3>
                <div class="number"><?= $totalClientes ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>Total de Orçamentos</h3>
                <div class="number"><?= $totalOrcamentos ?></div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3>Orçamentos Aprovados</h3>
                <div class="number"><?= $orcamentosPorStatus['aprovado'] ?></div>
                <div class="small">R$ <?= number_format($valoresPorStatus['aprovado'], 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- Estatísticas por status -->
        <div class="card">
            <h3>Orçamentos por Status</h3>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <?php foreach ($statusList as $status): ?>
                    <div style="text-align: center;">
                        <div class="status-badge" style="background: <?= $status == 'pendente' ? '#ffc107' : ($status == 'aprovado' ? '#28a745' : ($status == 'rejeitado' ? '#dc3545' : '#6c757d')) ?>; color: white;">
                            <?= ucfirst($status) ?>
                        </div>
                        <div style="font-size: 24px; font-weight: bold; margin-top: 5px;"><?= $orcamentosPorStatus[$status] ?></div>
                        <div>R$ <?= number_format($valoresPorStatus[$status], 2, ',', '.') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Serviços mais solicitados -->
        <div class="card">
            <h3>Serviços Mais Solicitados</h3>
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Total de Solicitações</th>
                        <th>Quantidade Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicosMaisSolicitados as $serv): ?>
                        <tr>
                            <td><?= htmlspecialchars($serv['nome']) ?></td>
                            <td><?= $serv['total_solicitacoes'] ?></td>
                            <td><?= $serv['total_quantidade'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Orçamentos por mês -->
        <div class="card">
            <h3>Orçamentos por Mês (Últimos 12 meses)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Quantidade</th>
                        <th>Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orcamentosPorMes as $mes): ?>
                        <tr>
                            <td><?= date('m/Y', strtotime($mes['mes'])) ?></td>
                            <td><?= $mes['total'] ?></td>
                            <td>R$ <?= number_format($mes['valor'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Filtro de orçamentos -->
        <div class="card">
            <h3>Lista de Orçamentos</h3>
            <form method="get" class="filtros-form">
                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $data_inicio ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $data_fim ?>" class="form-control">
                </div>
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
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">Filtrar</button>
                </div>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orcamentosFiltrados as $orc): ?>
                        <tr>
                            <td><?= $orc['id'] ?></td>
                            <td><?= htmlspecialchars($orc['cliente_nome'] ?? 'Cliente não informado') ?></td>
                            <td><?= formatDate($orc['data_orcamento']) ?></td>
                            <td>R$ <?= number_format($orc['valor_total'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge badge-<?= $statusColors[$orc['status']] ?? 'secondary' ?>">
                                    <?= ucfirst($orc['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="../orcamentos/view.php?id=<?= $orc['id'] ?>" class="btn">👁️ Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orcamentosFiltrados)): ?>
                        <tr><td colspan="6" style="text-align: center;">Nenhum orçamento encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>