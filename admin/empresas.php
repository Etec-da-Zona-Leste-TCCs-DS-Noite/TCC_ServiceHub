<?php
// admin/empresas.php — Gerenciamento de Empresas
session_start();
require_once '../includes/config.php';
require_once 'auth_admin.php';

// Toggle status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE empresas SET status = 1 - status WHERE id = ?")->execute([$id]);
    header('Location: empresas.php?msg=Status+atualizado'); exit;
}
// Excluir empresa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM empresas WHERE id = ?")->execute([$id]);
    header('Location: empresas.php?msg=Empresa+removida'); exit;
}

$busca = trim($_GET['busca'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 15; $offset = ($page-1)*$limit;

$where  = $busca ? "WHERE nome_empresa LIKE ? OR email LIKE ? OR cnpj LIKE ?" : '';
$params = $busca ? ["%$busca%","%$busca%","%$busca%"] : [];

$total = $pdo->prepare("SELECT COUNT(*) FROM empresas $where");
$total->execute($params); $total = $total->fetchColumn();
$totalPages = ceil($total/$limit);

$stmt = $pdo->prepare("SELECT e.*, (SELECT COUNT(*) FROM servicos s WHERE s.empresa_id=e.id AND s.status=1) AS total_servicos, (SELECT COUNT(*) FROM orcamentos o WHERE o.empresa_id=e.id) AS total_orc FROM empresas e $where ORDER BY e.created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i=>$v) $stmt->bindValue($i+1,$v);
$stmt->bindValue(count($params)+1,$limit,PDO::PARAM_INT);
$stmt->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stmt->execute(); $empresas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Empresas — Admin ServiceHub</title>
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
<aside class="admin-sidebar">
  <div class="sidebar-logo"><h1>Service<span>Hub</span></h1><div class="badge">ADMIN PANEL</div></div>
  <nav class="sidebar-nav">
    <div class="section-label">Principal</div>
    <a href="index.php">📊 Dashboard</a>
    <a href="empresas.php" class="active">🏢 Empresas</a>
    <a href="clientes.php">👤 Clientes</a>
    <a href="orcamentos.php">📋 Orçamentos</a>
    <div class="section-label">Sistema</div>
    <a href="avaliacoes.php">⭐ Avaliações</a>
    <a href="servicos.php">⚙️ Serviços</a>
    <div class="section-label">Conta</div>
    <a href="../index.php" target="_blank">🌐 Ver Site</a>
    <a href="logout.php">🚪 Sair</a>
  </nav>
  <div class="sidebar-footer" style="font-size:12px;color:var(--slate);">Logado como <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['admin_nome']) ?></strong></div>
</aside>

<div class="main-wrap">
  <div class="top-bar">
    <h2 style="font-size:16px;font-weight:600;">🏢 Empresas</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= number_format($total) ?> empresa(s)</span>
  </div>
  <div class="content">
    <?php if (isset($_GET['msg'])): ?>
    <div class="success-msg"><?= htmlspecialchars(urldecode($_GET['msg'])) ?></div>
    <?php endif; ?>

    <form method="get" style="display:flex;gap:10px;margin-bottom:20px;">
      <input type="text" name="busca" class="form-control" placeholder="Buscar por nome, e-mail ou CNPJ…" value="<?= htmlspecialchars($busca) ?>" style="max-width:380px;">
      <button type="submit" class="btn btn-primary">Buscar</button>
      <?php if ($busca): ?><a href="empresas.php" class="btn btn-ghost">Limpar</a><?php endif; ?>
    </form>

    <table class="admin-table">
      <thead>
        <tr><th>#</th><th>Empresa</th><th>E-mail / CNPJ</th><th>Serviços</th><th>Orçamentos</th><th>Cadastro</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody>
        <?php foreach ($empresas as $e): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:11px;"><?= $e['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($e['nome_empresa']) ?></strong>
            <?php if ($e['telefone']): ?><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($e['telefone']) ?></div><?php endif; ?>
          </td>
          <td>
            <div style="font-size:12px;"><?= htmlspecialchars($e['email']) ?></div>
            <?php if ($e['cnpj']): ?><div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($e['cnpj']) ?></div><?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:600;"><?= $e['total_servicos'] ?></td>
          <td style="text-align:center;"><?= $e['total_orc'] ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= date('d/m/Y', strtotime($e['created_at'])) ?></td>
          <td>
            <?php if ($e['status']): ?>
              <span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:600;">Ativa</span>
            <?php else: ?>
              <span style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:600;">Inativa</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;">
              <a href="empresas.php?toggle=<?= $e['id'] ?>" class="btn btn-sm" style="background:<?= $e['status']?'#fef2f2':'#ecfdf5'?>;color:<?= $e['status']?'#b91c1c':'#065f46'?>;border:1px solid <?= $e['status']?'#fecaca':'#a7f3d0'?>;"
                 onclick="return confirm('Confirmar alteração de status?')"><?= $e['status'] ? 'Desativar' : 'Ativar' ?></a>
              <a href="empresas.php?delete=<?= $e['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('ATENÇÃO: Excluir esta empresa e todos os seus dados?')">Excluir</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($empresas)): ?>
        <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhuma empresa encontrada.</td></tr>
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
