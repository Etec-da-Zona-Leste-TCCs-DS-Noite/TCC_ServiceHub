<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

verificarLogin();

if (!isCliente()) {
    header('Location: ../index.php');
    exit;
}

// Buscar todas as empresas
$empresas = $pdo->query("SELECT * FROM empresas WHERE status = 1 ORDER BY nome_empresa")->fetchAll();

// Buscar categorias
$categorias = $pdo->query("SELECT DISTINCT categoria FROM servicos WHERE categoria IS NOT NULL AND categoria != ''")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Empresas</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 15px 0;
        }
        .main-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .search-box {
            margin-bottom: 30px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }
        .empresa-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .empresa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .card-header h3 {
            margin-bottom: 5px;
        }
        .card-body {
            padding: 20px;
        }
        .empresa-desc {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .empresa-info {
            margin-bottom: 15px;
        }
        .empresa-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .empresa-info i {
            width: 20px;
            color: var(--primary-gold);
        }
        .btn-view {
            background: var(--primary-gold);
            color: var(--primary-dark);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            width: 100%;
            text-align: center;
            font-weight: 500;
        }
        .btn-view:hover {
            background: #c4a02e;
        }
        .categorias-filtro {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 25px;
        }
        .categoria-btn {
            padding: 6px 15px;
            background: #e9ecef;
            border-radius: 20px;
            text-decoration: none;
            color: #495057;
            font-size: 13px;
        }
        .categoria-btn:hover, .categoria-btn.active {
            background: var(--primary-gold);
            color: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="main-content" style="padding: 0 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="logo">
                    <h2 style="color: white;">Service<span style="color: #d4af37;">Hub</span></h2>
                </div>
                <div>
                    <a href="../dashboard_cliente.php" style="color: white; margin-right: 20px;">Voltar</a>
                    <a href="../logout.php" style="color: white;">Sair</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <h1><i class="fas fa-building"></i> Empresas Parceiras</h1>
        <p>Encontre a empresa ideal para o seu serviço</p>
        
        <div class="search-box">
            <input type="text" id="searchEmpresa" placeholder="🔍 Buscar empresa por nome, descrição ou localização..." onkeyup="filterEmpresas()">
        </div>
        
        <div class="categorias-filtro">
            <a href="#" class="categoria-btn active" data-cat="todos">Todos</a>
            <?php foreach ($categorias as $cat): ?>
                <a href="#" class="categoria-btn" data-cat="<?= strtolower($cat) ?>"><?= htmlspecialchars($cat) ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="empresas-grid" id="empresasGrid">
            <?php foreach ($empresas as $emp): 
                // Buscar serviços da empresa
                $servicosStmt = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND status = 1");
                $servicosStmt->execute([$emp['id']]);
                $totalServicos = $servicosStmt->fetchColumn();
            ?>
            <div class="empresa-card" data-nome="<?= strtolower($emp['nome_empresa']) ?>" data-desc="<?= strtolower($emp['descricao'] ?? '') ?>" data-local="<?= strtolower($emp['endereco'] ?? '') ?>">
                <div class="card-header">
                    <h3><?= htmlspecialchars($emp['nome_empresa']) ?></h3>
                    <?php if ($emp['site']): ?>
                        <small><i class="fas fa-globe"></i> <?= htmlspecialchars($emp['site']) ?></small>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="empresa-desc">
                        <?= htmlspecialchars(substr($emp['descricao'] ?? 'Empresa especializada em serviços de qualidade.', 0, 120)) ?>...
                    </div>
                    <div class="empresa-info">
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($emp['endereco'] ?? 'Local não informado') ?></p>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($emp['telefone'] ?? 'Telefone não informado') ?></p>
                        <p><i class="fas fa-briefcase"></i> <?= $totalServicos ?> serviço(s) disponível(is)</p>
                    </div>
                    <a href="empresa.php?id=<?= $emp['id'] ?>" class="btn-view">Ver Serviços</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function filterEmpresas() {
            let searchTerm = document.getElementById('searchEmpresa').value.toLowerCase();
            let empresas = document.querySelectorAll('.empresa-card');
            
            empresas.forEach(empresa => {
                let nome = empresa.getAttribute('data-nome') || '';
                let desc = empresa.getAttribute('data-desc') || '';
                let local = empresa.getAttribute('data-local') || '';
                
                if (nome.includes(searchTerm) || desc.includes(searchTerm) || local.includes(searchTerm)) {
                    empresa.style.display = '';
                } else {
                    empresa.style.display = 'none';
                }
            });
        }
        
        document.querySelectorAll('.categoria-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.categoria-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                let categoria = this.getAttribute('data-cat');
                // Filtrar por categoria (implementar se necessário)
            });
        });
    </script>
</body>
</html>