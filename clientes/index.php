<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

// Esta listagem só é acessível por empresas logadas
if (!isEmpresa()) {
    header('Location: ../index.php');
    exit;
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$busca  = trim($_GET['busca'] ?? '');

$where  = $busca ? "WHERE nome LIKE ? OR email LIKE ? OR telefone LIKE ?" : '';
$params = $busca ? ["%$busca%", "%$busca%", "%$busca%"] : [];

$total      = $pdo->prepare("SELECT COUNT(*) FROM clientes $where");
$total->execute($params);
$total      = $total->fetchColumn();
$totalPages = (int)ceil($total / $limit);

$sql = "SELECT * FROM clientes $where ORDER BY nome ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k + 1, $v);
$stmt->bindValue(count($params) + 1, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Clientes — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none; }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
  </style>
</head>
<body>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="../dashboard_empresa.php">Início</a>
      <a href="../empresas/meus_servicos.php">Meus Serviços</a>
      <a href="../orcamentos/index.php">Orçamentos</a>
      <a href="../relatorios/index.php">Relatórios</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-title-row">
    <h1>Clientes</h1>
  </div>

  <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

  <form class="filter-bar" method="get" style="margin-bottom:24px;">
    <div class="form-group">
      <label>Buscar cliente</label>
      <input type="text" name="busca" class="form-control" placeholder="Nome, e-mail ou telefone…" value="<?= htmlspecialchars($busca) ?>">
    </div>
    <div style="display:flex;gap:8px;padding-bottom:1px;">
      <button type="submit" class="btn btn-primary">Buscar</button>
      <?php if ($busca): ?><a href="index.php" class="btn btn-ghost">Limpar</a><?php endif; ?>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Nome</th><th>E-mail</th><th>Telefone</th><th>Tipo</th><th>Orçamentos</th><th>Desde</th></tr>
      </thead>
      <tbody>
        <?php foreach ($clientes as $cli):
            $orcCount = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = ? AND empresa_id = ?");
            $orcCount->execute([$cli['id'], $_SESSION['empresa_id']]);
            $totalOrc = $orcCount->fetchColumn();
        ?>
        <tr>
          <td style="color:var(--text-muted);font-size:12px;">#<?= $cli['id'] ?></td>
          <td><strong><?= htmlspecialchars($cli['nome']) ?></strong></td>
          <td><?= htmlspecialchars($cli['email']) ?></td>
          <td><?= htmlspecialchars($cli['telefone'] ?? '—') ?></td>
          <td><?= $cli['tipo'] === 'juridica' ? 'PJ' : 'PF' ?></td>
          <td>
            <?php if ($totalOrc > 0): ?>
              <a href="../orcamentos/index.php" style="color:var(--teal);font-weight:600;"><?= $totalOrc ?></a>
            <?php else: ?>
              <span style="color:var(--text-muted);">0</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--text-muted);font-size:12px;"><?= formatDate($cli['created_at'], 'd/m/Y') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($clientes)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <span class="empty-icon">👥</span>
            <h3>Nenhum cliente encontrado</h3>
            <p><?= $busca ? 'Tente outros termos de busca.' : 'Os clientes que solicitarem seus serviços aparecerão aqui.' ?></p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <ul class="pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <li class="<?= $i == $page ? 'active' : '' ?>">
      <?= $i == $page ? "<span>$i</span>" : "<a href='?page=$i&busca=".urlencode($busca)."'>$i</a>" ?>
    </li>
    <?php endfor; ?>
  </ul>
  <?php endif; ?>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
