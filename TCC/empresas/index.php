<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

verificarLogin();

if (!isEmpresa()) {
    header('Location: ../index.php');
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

// Buscar serviços da empresa
$servicos = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id = ? ORDER BY id DESC");
$servicos->execute([$empresa_id]);
$servicosList = $servicos->fetchAll();

// Estatísticas
$totalAtivos = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND status = 1");
$totalAtivos->execute([$empresa_id]);
$totalAtivosCount = $totalAtivos->fetchColumn();

$totalInativos = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND status = 0");
$totalInativos->execute([$empresa_id]);
$totalInativosCount = $totalInativos->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Meus Serviços</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 30px 0;
        }
        .stats-mini {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-mini-card {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stat-mini-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #1a4a6f;
        }
        .servico-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .servico-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .servico-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .servico-body {
            padding: 20px;
        }
        .servico-title {
            font-size: 18px;
            font-weight: bold;
            color: #1a4a6f;
        }
        .servico-price {
            font-size: 22px;
            font-weight: bold;
            color: #d4af37;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-ativo { background: #d4edda; color: #155724; }
        .status-inativo { background: #f8d7da; color: #721c24; }
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="main-content" style="max-width: 1280px; margin: 0 auto; padding: 0 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h1><i class="fas fa-briefcase"></i> Meus Serviços</h1>
                    <p>Gerencie os serviços oferecidos pela sua empresa</p>
                </div>
                <div>
                    <a href="../dashboard_empresa.php" class="btn-outline" style="color: white; border-color: white; margin-right: 10px;">Voltar</a>
                    <a href="meus_servicos.php?action=create" class="btn-primary" style="background: #d4af37; color: #0a2b3e;">+ Novo Serviço</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content" style="max-width: 1280px; margin: 0 auto; padding: 30px 20px;">
        <div class="stats-mini">
            <div class="stat-mini-card">
                <div class="number"><?= count($servicosList) ?></div>
                <div>Total de Serviços</div>
            </div>
            <div class="stat-mini-card">
                <div class="number"><?= $totalAtivosCount ?></div>
                <div>Serviços Ativos</div>
            </div>
            <div class="stat-mini-card">
                <div class="number"><?= $totalInativosCount ?></div>
                <div>Serviços Inativos</div>
            </div>
        </div>
        
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success"><?= urldecode($_GET['msg']) ?></div>
        <?php endif; ?>
        
        <?php if (empty($servicosList)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open" style="font-size: 64px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                <h3>Nenhum serviço cadastrado</h3>
                <p>Comece a oferecer seus serviços cadastrando o primeiro serviço.</p>
                <a href="meus_servicos.php?action=create" class="btn-primary" style="margin-top: 15px; display: inline-block;">+ Cadastrar Serviço</a>
            </div>
        <?php else: ?>
            <?php foreach ($servicosList as $serv): ?>
            <div class="servico-card">
                <div class="servico-header">
                    <span class="servico-title"><?= htmlspecialchars($serv['nome']) ?></span>
                    <span class="status-badge status-<?= $serv['status'] ? 'ativo' : 'inativo' ?>">
                        <?= $serv['status'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
                <div class="servico-body">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div>
                            <p><strong>Descrição:</strong> <?= htmlspecialchars($serv['descricao'] ?: 'Sem descrição') ?></p>
                            <p><strong>Categoria:</strong> <?= htmlspecialchars($serv['categoria'] ?: 'Não definida') ?></p>
                            <p><strong>Duração:</strong> <?= $serv['duracao_estimada'] ? $serv['duracao_estimada'] . ' horas' : 'Não definida' ?></p>
                        </div>
                        <div style="text-align: right;">
                            <div class="servico-price">R$ <?= number_format($serv['valor'], 2, ',', '.') ?></div>
                            <div style="margin-top: 15px;">
                                <a href="meus_servicos.php?action=edit&id=<?= $serv['id'] ?>" class="btn-outline btn-sm"><i class="fas fa-edit"></i> Editar</a>
                                <a href="meus_servicos.php?action=delete&id=<?= $serv['id'] ?>" class="btn-outline btn-sm" style="border-color: #dc3545; color: #dc3545;" onclick="return confirm('Tem certeza que deseja excluir este serviço?')"><i class="fas fa-trash"></i> Excluir</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>