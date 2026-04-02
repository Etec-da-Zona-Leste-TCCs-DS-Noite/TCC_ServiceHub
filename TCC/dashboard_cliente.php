<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

verificarLogin();

if (!isCliente()) {
    header('Location: index.php');
    exit;
}

$cliente_id = $_SESSION['cliente_id'];

// Buscar empresas
$empresas = $pdo->query("SELECT * FROM empresas WHERE status = 1 ORDER BY nome_empresa")->fetchAll();

// Buscar serviços por categoria
$categorias = $pdo->query("SELECT DISTINCT categoria FROM servicos WHERE categoria IS NOT NULL AND categoria != ''")->fetchAll(PDO::FETCH_COLUMN);

// Buscar orçamentos do cliente
$orcamentos = $pdo->prepare("
    SELECT o.*, s.nome as servico_nome, e.nome_empresa as empresa_nome
    FROM orcamentos o
    JOIN servicos s ON s.id = o.servico_id
    JOIN empresas e ON e.id = o.empresa_id
    WHERE o.cliente_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$orcamentos->execute([$cliente_id]);
$orcamentosList = $orcamentos->fetchAll();

// Estatísticas
$totalOrcamentos = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = ?");
$totalOrcamentos->execute([$cliente_id]);
$totalOrc = $totalOrcamentos->fetchColumn();

$totalAprovados = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = ? AND status = 'aprovado'");
$totalAprovados->execute([$cliente_id]);
$totalAprov = $totalAprovados->fetchColumn();

$totalGasto = $pdo->prepare("SELECT SUM(valor_total) FROM orcamentos WHERE cliente_id = ? AND status = 'aprovado'");
$totalGasto->execute([$cliente_id]);
$totalGastoValor = $totalGasto->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Painel do Cliente</title>
    <link rel="stylesheet" href="css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-container {
            min-height: 100vh;
            background: #f5f7fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .logo h2 {
            color: white;
            font-size: 24px;
        }
        .logo span {
            color: #d4af37;
        }
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: color 0.3s;
        }
        .nav-links a:hover {
            color: #d4af37;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #d4af37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .btn-logout {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
        }
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .welcome-card {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            border-bottom: 3px solid #d4af37;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1a4a6f;
        }
        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            border-left: 4px solid #d4af37;
            padding-left: 15px;
        }
        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .empresa-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .empresa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .empresa-card .card-body {
            padding: 20px;
        }
        .empresa-card h3 {
            color: #1a4a6f;
            margin-bottom: 10px;
        }
        .empresa-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #d4af37;
            color: #0a2b3e;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #c4a02e;
            transform: translateY(-2px);
        }
        .btn-outline {
            border: 1px solid #d4af37;
            color: #d4af37;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        th {
            background: #1a4a6f;
            color: white;
            padding: 12px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-pendente { background: #ffc107; color: #000; }
        .status-aprovado { background: #28a745; color: #fff; }
        .status-rejeitado { background: #dc3545; color: #fff; }
        .status-concluido { background: #17a2b8; color: #fff; }
        @media (max-width: 768px) {
            .navbar .container {
                flex-direction: column;
                gap: 15px;
            }
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="navbar">
            <div class="main-content" style="padding: 0 20px; max-width: 1280px; margin: 0 auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div class="logo">
                        <h2>Service<span>Hub</span></h2>
                    </div>
                    <div class="nav-links">
                        <a href="dashboard_cliente.php">Início</a>
                        <a href="clientes/empresas.php">Empresas</a>
                        <a href="orcamentos/index.php?cliente=<?= $cliente_id ?>">Meus Orçamentos</a>
                        <div class="user-info">
                            <div class="user-avatar">
                                <?= strtoupper(substr($_SESSION['cliente_nome'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($_SESSION['cliente_nome']) ?></span>
                            <a href="logout.php" class="btn-logout">Sair</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="welcome-card">
                <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['cliente_nome']) ?>!</h1>
                <p>Encontre as melhores empresas e serviços para suas necessidades.</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="number"><?= $totalOrc ?></div>
                    <div>Orçamentos Realizados</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?= $totalAprov ?></div>
                    <div>Orçamentos Aprovados</div>
                </div>
                <div class="stat-card">
                    <div class="number">R$ <?= number_format($totalGastoValor ?? 0, 2, ',', '.') ?></div>
                    <div>Total Gasto</div>
                </div>
            </div>
            
            <h2 class="section-title">Empresas em Destaque</h2>
            <div class="empresas-grid">
                <?php foreach (array_slice($empresas, 0, 6) as $emp): ?>
                <div class="empresa-card">
                    <div class="card-body">
                        <h3><i class="fas fa-building"></i> <?= htmlspecialchars($emp['nome_empresa']) ?></h3>
                        <p><?= htmlspecialchars(substr($emp['descricao'] ?? '', 0, 100)) ?>...</p>
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($emp['endereco'] ?? 'Local não informado') ?></p>
                        <a href="clientes/empresa.php?id=<?= $emp['id'] ?>" class="btn-primary">Ver Serviços</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <h2 class="section-title">Meus Últimos Orçamentos</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Serviço</th>
                            <th>Empresa</th>
                            <th>Valor</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </thead>
                    <tbody>
                        <?php foreach ($orcamentosList as $orc): ?>
                        <tr>
                            <td>#<?= $orc['id'] ?></td>
                            <td><?= htmlspecialchars($orc['servico_nome']) ?></td>
                            <td><?= htmlspecialchars($orc['empresa_nome']) ?></td>
                            <td>R$ <?= number_format($orc['valor_total'], 2, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($orc['data_orcamento'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $orc['status'] ?>">
                                    <?= ucfirst($orc['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="orcamentos/view.php?id=<?= $orc['id'] ?>" class="btn-outline" style="padding: 4px 12px;">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($orcamentosList)): ?>
                         <tr>
                            <td colspan="7" style="text-align: center;">Nenhum orçamento encontrado.</td>
                         </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <footer style="background: #0a2b3e; color: #999; text-align: center; padding: 20px; margin-top: 40px;">
            <p>&copy; <?= date('Y') ?> ServiceHub - Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>