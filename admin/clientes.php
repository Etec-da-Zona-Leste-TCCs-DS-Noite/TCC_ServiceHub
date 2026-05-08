<?php
// admin/clientes.php — Gerenciamento de Clientes
session_start();
require_once '../includes/config.php';
require_once 'auth_admin.php';

// Excluir cliente
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
    header('Location: clientes.php?msg=Cliente+removido'); exit;
}

$busca = trim($_GET['busca'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; $offset = ($page-1)*$limit;

$where  = $busca ? "WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ?" : '';
$params = $busca ? ["%$busca%","%$busca%","%$busca%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM clientes $where");
$total->execute($params); $total = $total->fetchColumn();
$totalPages = ceil($total/$limit);

$stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM orcamentos o WHERE o.cliente_id=c.id) AS total_orc FROM clientes c $where ORDER BY c.created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$limit,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $clientes = $stmt->fetchAll();

function sidebarHtml($active) {
    return '<aside class="admin-sidebar">
  <div class="sidebar-logo"><h1>Service<span>Hub</span></h1><div class="badge">ADMIN PANEL</div></div>
  <nav class="sidebar-nav">
    <div class="section-label">Principal</div>
    <a href="index.php"'.($active==='dashboard'?' class="active"':'').'>📊 Dashboard</a>
    <a href="empresas.php"'.($active==='empresas'?' class="active"':'').'>🏢 Empresas</a>
    <a href="clientes.php"'.($active==='clientes'?' class="active"':'').'>👤 Clientes</a>
    <a href="orcamentos.php"'.($active==='orcamentos'?' class="active"':'').'>📋 Orçamentos</a>
    <div class="section-label">Sistema</div>
    <a href="avaliacoes.php"'.($active==='avaliacoes'?' class="active"':'').'>⭐ Avaliações</a>
    <a href="servicos.php"'.($active==='servicos'?' class="active"':'').'>⚙️ Serviços</a>
    <div class="section-label">Conta</div>
    <a href="../index.php" target="_blank">🌐 Ver Site</a>
    <a href="logout.php">🚪 Sair</a>
  </nav>
  <div class="sidebar-footer" style="font-size:12px;color:var(--slate);">Logado como <strong style="color:#fff;">'.$_SESSION['admin_nome'].'</strong></div>
</aside>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Clientes — Admin ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    body{background:#f1f5f9;}
    .admin-sidebar{position:fixed;top:0;left:0;bottom:0;width:220px;background:var(--navy);z-index:300;display:flex;flex-direction:column;}
    .sidebar-logo{padding:22px 20px 16px;border-bottom:1px solid rgba(255,255,255,.08);}
    .sidebar-logo h1{font-size:18px;color:#fff;} .sidebar-logo h1 span{color:var(--gold);}
    .sidebar-logo .badge{font-size:10px;color:var(--slate);letter-spacing:.5px;font-weight:600;}
    .sidebar-nav{flex:1;padding:12px 0;overflow-y:auto;}
    .sidebar-nav a{display:flex;align-items:center;gap:10px;color:var(--slate-lt);font-size:13px;font-weight:500;padding:10px 20px;text-decoration:none;transition:all .15s;}
    .sidebar-nav a:hover,.sidebar-nav a.active{color:#fff;background:rgba(201,168,76,.15);border-left:3px solid var(--gold);padding-left:17px;}
    .sidebar-nav .section-label{font-size:10px;font-weight:700;letter-spacing:1px;color:var(--slate);text-transform:uppercase;padding:16px 20px 6px;}
    .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,.08);}
    .main-wrap{margin-left:220px;min-height:100vh;}
    .top-bar{background:#fff;border-bottom:1px solid var(--border);padding:0 28px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
    .content{padding:28px;}
    .admin-table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--r);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);}
    .admin-table th{padding:11px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:1px solid var(--border);background:#fafafa;text-align:left;font-weight:600;}
    .admin-table td{padding:12px 14px;font-size:13px;border-bottom:1px solid var(--border);}
    .admin-table tr:last-child td{border-bottom:none;}
    .admin-table tr:hover td{background:#fafafa;}
    @media(max-width:900px){.admin-sidebar{display:none;}.main-wrap{margin-left:0;}}
  </style>
</head>
<body>
<?= sidebarHtml('clientes') ?>

<div class="main-wrap">
  <div class="top-bar">
    <h2 style="font-size:16px;font-weight:600;">👤 Clientes</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= number_format($total) ?> cliente(s)</span>
  </div>
  <div class="content">
    <?php if (isset($_GET['msg'])): ?>
    <div class="success-msg"><?= htmlspecialchars(urldecode($_GET['msg'])) ?></div>
    <?php endif; ?>

    <form method="get" style="display:flex;gap:10px;margin-bottom:20px;">
      <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, e-mail ou telefone…" value="<?= htmlspecialchars($busca) ?>" style="max-width:380px;">
      <button type="submit" class="btn btn-primary">Buscar</button>
      <?php if ($busca): ?><a href="clientes.php" class="btn btn-ghost">Limpar</a><?php endif; ?>
    </form>

    <table class="admin-table">
      <thead>
        <tr><th>#</th><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Orçamentos</th><th>Cadastro</th><th>Ações</th></tr>
      </thead>
      <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:11px;"><?= $c['id'] ?></td>
          <td><strong><?= htmlspecialchars($c['nome']) ?></strong></td>
          <td style="font-size:12px;"><?= htmlspecialchars($c['email']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($c['telefone'] ?? '—') ?></td>
          <td style="text-align:center;font-weight:600;"><?= $c['total_orc'] ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
          <td>
            <a href="clientes.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-danger"
               onclick="return confirm('Excluir este cliente e todos os seus dados?')">Excluir</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhum cliente encontrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:20px;flex-wrap:wrap;">
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a href="?page=<?=$i?><?=$busca?'&busca='.urlencode($busca):''?>" class="btn btn-sm <?=$i==$page?'btn-primary':'btn-ghost'?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
