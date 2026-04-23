<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

verificarLogin();

if (!isCliente()) {
    header('Location: ../index.php');
    exit;
}

// Filtro de busca
$busca = trim($_GET['busca'] ?? '');
$catFiltro = trim($_GET['categoria'] ?? '');

// Buscar todas as empresas (com filtro opcional)
$sql = "SELECT * FROM empresas WHERE status = 1";
$params = [];
if ($busca !== '') {
    $sql .= " AND (nome_empresa LIKE ? OR descricao LIKE ? OR endereco LIKE ?)";
    $like = '%' . $busca . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
$sql .= " ORDER BY nome_empresa";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empresas = $stmt->fetchAll();

// Buscar categorias disponíveis
$categorias = $pdo->query(
    "SELECT DISTINCT categoria FROM servicos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria"
)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - Empresas</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            padding: 15px 0;
            position: sticky; top: 0; z-index: 100;
        }
        .navbar .inner {
            max-width: 1280px; margin: 0 auto; padding: 0 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .main-content { max-width: 1280px; margin: 0 auto; padding: 30px 20px; }
        .search-form {
            display: flex; gap: 10px; margin-bottom: 20px;
        }
        .search-form input {
            flex: 1; padding: 12px; border: 1px solid #ddd;
            border-radius: 8px; font-size: 14px;
        }
        .search-form button {
            background: #1a4a6f; color: white;
            border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer;
        }
        .categorias-filtro {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 25px;
        }
        .categoria-btn {
            padding: 6px 15px; background: #e9ecef; border-radius: 20px;
            text-decoration: none; color: #495057; font-size: 13px;
            transition: all 0.2s;
        }
        .categoria-btn:hover, .categoria-btn.active {
            background: #d4af37; color: #0a2b3e;
        }
        .empresas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        .empresa-card {
            background: white; border-radius: 12px; overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: all 0.3s;
        }
        .empresa-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .card-header {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white; padding: 20px; text-align: center;
        }
        .card-header h3 { margin-bottom: 4px; font-size: 17px; }
        .card-body { padding: 20px; }
        .empresa-desc { color: #555; margin-bottom: 15px; font-size: 14px; line-height: 1.5; }
        .empresa-info p { margin: 5px 0; font-size: 13px; color: #666; }
        .empresa-info i { width: 18px; color: #d4af37; }
        .btn-view {
            background: #d4af37; color: #0a2b3e;
            padding: 10px 20px; border-radius: 5px;
            text-decoration: none; display: block;
            text-align: center; font-weight: 600; margin-top: 15px;
            transition: background 0.2s;
        }
        .btn-view:hover { background: #c4a02e; }
        .empty-state {
            text-align: center; padding: 60px; background: white;
            border-radius: 12px; color: #999;
        }
        @media (max-width: 600px) {
            .search-form { flex-direction: column; }
            .navbar .inner { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="inner">
        <h2 style="color:white;">Service<span style="color:#d4af37;">Hub</span></h2>
        <div>
            <a href="../dashboard_cliente.php" style="color:white;margin-right:20px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="../logout.php" style="color:white;">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</nav>

<div class="main-content">
    <h1><i class="fas fa-building" style="color:#d4af37;"></i> Empresas Parceiras</h1>
    <p style="color:#666;margin-bottom:25px;">Encontre a empresa ideal para o seu serviço</p>

    <!-- Busca via GET para funcionar sem JS -->
    <form class="search-form" method="get" action="">
        <input type="text" name="busca" value="<?= htmlspecialchars($busca) ?>"
               placeholder="🔍 Buscar por nome, descrição ou localização...">
        <button type="submit"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($busca): ?>
            <a href="empresas.php" style="padding:12px 16px;background:#6c757d;color:white;border-radius:8px;text-decoration:none;">
                Limpar
            </a>
        <?php endif; ?>
    </form>

    <div class="categorias-filtro">
        <a href="empresas.php" class="categoria-btn <?= $catFiltro === '' ? 'active' : '' ?>">Todos</a>
        <?php foreach ($categorias as $cat): ?>
            <a href="?categoria=<?= urlencode($cat) ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>"
               class="categoria-btn <?= $catFiltro === $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($empresas)): ?>
        <div class="empty-state">
            <i class="fas fa-building" style="font-size:48px;display:block;margin-bottom:15px;"></i>
            <h3>Nenhuma empresa encontrada</h3>
            <p>Tente outros termos de busca.</p>
        </div>
    <?php else: ?>
    <div class="empresas-grid">
        <?php foreach ($empresas as $emp):
            $servicosStmt = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND status = 1");
            $servicosStmt->execute([$emp['id']]);
            $totalServicos = $servicosStmt->fetchColumn();

            // Filtro de categoria (server-side)
            if ($catFiltro !== '') {
                $hascat = $pdo->prepare(
                    "SELECT COUNT(*) FROM servicos WHERE empresa_id = ? AND categoria = ? AND status = 1"
                );
                $hascat->execute([$emp['id'], $catFiltro]);
                if ($hascat->fetchColumn() == 0) continue;
            }
        ?>
        <div class="empresa-card">
            <div class="card-header">
                <h3><?= htmlspecialchars($emp['nome_empresa']) ?></h3>
                <?php if ($emp['site']): ?>
                    <small><i class="fas fa-globe"></i> <?= htmlspecialchars($emp['site']) ?></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="empresa-desc">
                    <?= htmlspecialchars(mb_substr($emp['descricao'] ?? 'Empresa especializada em serviços de qualidade.', 0, 120)) ?>...
                </div>
                <?php $av = mediaAvaliacoes($pdo, $emp['id']); ?>
                <?php if ($av['total'] > 0): ?>
                <div style="margin:8px 0 6px;display:flex;align-items:center;gap:6px;">
                  <?= starRating($av['nota'] ?? $av['media'] ?? 0, true) ?>
                  <span style="font-size:12px;color:var(--text-muted);"><?= number_format($av['media'],1,',','') ?> (<?=$av['total']?>)</span>
                </div>
                <?php endif; ?>
                <div class="empresa-info">
                    <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($emp['endereco'] ?? 'Local não informado') ?></p>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($emp['telefone'] ?? 'Não informado') ?></p>
                    <p><i class="fas fa-briefcase"></i> <?= $totalServicos ?> serviço(s) disponível(is)</p>
                </div>
                <a href="empresa.php?id=<?= $emp['id'] ?>" class="btn-view">Ver Serviços</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
