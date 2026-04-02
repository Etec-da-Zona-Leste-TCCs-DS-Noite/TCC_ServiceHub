<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';

verificarLogin();

if (!isEmpresa()) {
    header('Location: index.php');
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Buscar dados da empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();

// Estatísticas
$totalServicos = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND status = 1");
$totalServicos->execute([$empresa_id]);
$totalServ = $totalServicos->fetchColumn();

$totalOrcamentos = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE empresa_id = ?");
$totalOrcamentos->execute([$empresa_id]);
$totalOrc = $totalOrcamentos->fetchColumn();

$totalAprovados = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE empresa_id = ? AND status = 'aprovado'");
$totalAprovados->execute([$empresa_id]);
$totalAprov = $totalAprovados->fetchColumn();

$totalFaturamento = $pdo->prepare("SELECT SUM(valor_total) FROM orcamentos WHERE empresa_id = ? AND status = 'aprovado'");
$totalFaturamento->execute([$empresa_id]);
$faturamento = $totalFaturamento->fetchColumn();

// Últimos orçamentos recebidos
$ultimosOrcamentos = $pdo->prepare("
    SELECT o.*, c.nome as cliente_nome, s.nome as servico_nome
    FROM orcamentos o
    JOIN clientes c ON c.id = o.cliente_id
    JOIN servicos s ON s.id = o.servico_id
    WHERE o.empresa_id = ?
    ORDER BY o.created_at DESC
    LIMIT 10
");
$ultimosOrcamentos->execute([$empresa_id]);
$orcamentosList = $ultimosOrcamentos->fetchAll();

// Serviços mais solicitados
$servicosPopulares = $pdo->prepare("
    SELECT s.nome, COUNT(o.id) as total_solicitacoes
    FROM servicos s
    LEFT JOIN orcamentos o ON o.servico_id = s.id
    WHERE s.empresa_id = ?
    GROUP BY s.id
    ORDER BY total_solicitacoes DESC
    LIMIT 5
");
$servicosPopulares->execute([$empresa_id]);
$populares = $servicosPopulares->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Painel da Empresa</title>
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
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
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
            flex-wrap: wrap;
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
            color: #0a2b3e;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card .icon {
            font-size: 40px;
            color: #d4af37;
            margin-bottom: 15px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #1a4a6f;
        }
        .stat-card .label {
            color: #666;
            margin-top: 5px;
        }
        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
            border-left: 4px solid #d4af37;
            padding-left: 15px;
        }
        .btn-primary {
            background: #d4af37;
            color: #0a2b3e;
            padding: 10px 25px;
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
            background: transparent;
        }
        .btn-outline:hover {
            background: #d4af37;
            color: #0a2b3e;
        }
        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #1a4a6f;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        tr:hover {
            background: #f9f9f9;
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
        .servicos-populares {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        .popular-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .popular-item:last-child {
            border-bottom: none;
        }
        .popular-nome {
            font-weight: 500;
        }
        .popular-count {
            background: #d4af37;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .quick-action-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #d4af37;
        }
        .quick-action-card i {
            font-size: 32px;
            color: #d4af37;
            margin-bottom: 10px;
            display: block;
        }
        @media (max-width: 768px) {
            .navbar .container {
                flex-direction: column;
                gap: 15px;
            }
            .nav-links {
                justify-content: center;
            }
            .welcome-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="navbar">
            <div class="container">
                <div class="logo">
                    <h2>Service<span>Hub</span></h2>
                    <small style="font-size: 12px;">Área da Empresa</small>
                </div>
                <div class="nav-links">
                    <a href="dashboard_empresa.php">Início</a>
                    <a href="empresas/meus_servicos.php">Meus Serviços</a>
                    <a href="empresas/perfil.php">Perfil</a>
                    <a href="orcamentos/index.php?empresa=<?= $empresa_id ?>">Orçamentos</a>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['empresa_nome'], 0, 1)) ?>
                        </div>
                        <span><?= htmlspecialchars($_SESSION['empresa_nome']) ?></span>
                        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="welcome-card">
                <div>
                    <h1>Bem-vindo, <?= htmlspecialchars($empresa['nome_empresa']) ?>!</h1>
                    <p>Gerencie seus serviços e acompanhe os orçamentos dos clientes.</p>
                </div>
                <div>
                    <a href="empresas/meus_servicos.php" class="btn-primary"><i class="fas fa-plus"></i> Novo Serviço</a>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-briefcase"></i></div>
                    <div class="number"><?= $totalServ ?></div>
                    <div class="label">Serviços Ativos</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="number"><?= $totalOrc ?></div>
                    <div class="label">Orçamentos Recebidos</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <div class="number"><?= $totalAprov ?></div>
                    <div class="label">Orçamentos Aprovados</div>
                </div>
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="number">R$ <?= number_format($faturamento ?? 0, 2, ',', '.') ?></div>
                    <div class="label">Faturamento Total</div>
                </div>
            </div>
            
            <h2 class="section-title">Ações Rápidas</h2>
            <div class="quick-actions">
                <a href="empresas/meus_servicos.php" class="quick-action-card">
                    <i class="fas fa-plus-circle"></i>
                    <strong>Novo Serviço</strong>
                    <small>Adicione um novo serviço</small>
                </a>
                <a href="empresas/meus_servicos.php" class="quick-action-card">
                    <i class="fas fa-edit"></i>
                    <strong>Gerenciar Serviços</strong>
                    <small>Edite ou remova serviços</small>
                </a>
                <a href="empresas/perfil.php" class="quick-action-card">
                    <i class="fas fa-building"></i>
                    <strong>Editar Perfil</strong>
                    <small>Atualize dados da empresa</small>
                </a>
                <a href="orcamentos/index.php?empresa=<?= $empresa_id ?>" class="quick-action-card">
                    <i class="fas fa-chart-line"></i>
                    <strong>Ver Orçamentos</strong>
                    <small>Acompanhe solicitações</small>
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <h2 class="section-title">Últimos Orçamentos</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Serviço</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </thead>
                            <tbody>
                                <?php foreach ($orcamentosList as $orc): ?>
                                <tr>
                                    <td>#<?= $orc['id'] ?></td>
                                    <td><?= htmlspecialchars($orc['cliente_nome']) ?></td>
                                    <td><?= htmlspecialchars($orc['servico_nome']) ?></td>
                                    <td>R$ <?= number_format($orc['valor_total'], 2, ',', '.') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $orc['status'] ?>">
                                            <?= ucfirst($orc['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="orcamentos/view.php?id=<?= $orc['id'] ?>" class="btn-outline" style="padding: 4px 10px; font-size: 12px;">Ver</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($orcamentosList)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">Nenhum orçamento recebido ainda.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div>
                    <h2 class="section-title">Serviços Mais Solicitados</h2>
                    <div class="servicos-populares">
                        <?php foreach ($populares as $serv): ?>
                        <div class="popular-item">
                            <span class="popular-nome"><i class="fas fa-cog"></i> <?= htmlspecialchars($serv['nome']) ?></span>
                            <span class="popular-count"><?= $serv['total_solicitacoes'] ?> solicitações</span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($populares)): ?>
                        <div style="text-align: center; padding: 30px; color: #666;">
                            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                            Nenhum serviço solicitado ainda.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <footer style="background: #0a2b3e; color: #999; text-align: center; padding: 20px; margin-top: 40px;">
            <p>&copy; <?= date('Y') ?> ServiceHub - Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>