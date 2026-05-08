<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_cliente = isCliente();
$is_empresa = isEmpresa();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$status_filtro = $_GET['status']      ?? '';
$data_inicio   = $_GET['data_inicio'] ?? '';
$data_fim      = $_GET['data_fim']    ?? '';

// ── Monta WHERE dinâmico ─────────────────────────────────
$where  = 'WHERE 1=1';
$params = [];

if ($is_cliente) {
    $where   .= ' AND o.cliente_id = ?';
    $params[] = (int)$_SESSION['cliente_id'];
} elseif ($is_empresa) {
    $where   .= ' AND o.empresa_id = ?';
    $params[] = (int)$_SESSION['empresa_id'];
}
if ($status_filtro !== '') { $where .= ' AND o.status = ?';          $params[] = $status_filtro; }
if ($data_inicio   !== '') { $where .= ' AND o.data_orcamento >= ?'; $params[] = $data_inicio;   }
if ($data_fim      !== '') { $where .= ' AND o.data_orcamento <= ?'; $params[] = $data_fim;      }

// ── COUNT ────────────────────────────────────────────────
$sqlCount = "SELECT COUNT(*)
             FROM orcamentos o
             LEFT JOIN clientes c ON c.id = o.cliente_id
             LEFT JOIN servicos  s ON s.id = o.servico_id
             LEFT JOIN empresas  e ON e.id = o.empresa_id
             $where";
$stCount = $pdo->prepare($sqlCount);
$stCount->execute($params);
$total      = (int)$stCount->fetchColumn();
$totalPages = (int)ceil($total / $limit);

// ── LISTAGEM — LIMIT/OFFSET com bindValue + PARAM_INT ───
$sqlList = "SELECT o.*, c.nome AS cliente_nome, e.nome_empresa, s.nome AS servico_nome
            FROM orcamentos o
            LEFT JOIN clientes c ON c.id = o.cliente_id
            LEFT JOIN servicos  s ON s.id = o.servico_id
            LEFT JOIN empresas  e ON e.id = o.empresa_id
            $where
            ORDER BY o.id DESC
            LIMIT ? OFFSET ?";

$stList = $pdo->prepare($sqlList);
foreach ($params as $i => $val) {
    $stList->bindValue($i + 1, $val);
}
$stList->bindValue(count($params) + 1, $limit,  PDO::PARAM_INT);
$stList->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stList->execute();
$orcamentos = $stList->fetchAll();

// ── Totais rápidos ───────────────────────────────────────
$totalValor = $totalPendente = $totalAprovado = 0;
foreach ($orcamentos as $o) {
    $totalValor += $o['valor_total'];
    if ($o['status'] === 'pendente') $totalPendente += $o['valor_total'];
    if ($o['status'] === 'aprovado') $totalAprovado += $o['valor_total'];
}

$back_url = $is_cliente
    ? '../dashboard_cliente.php'
    : ($is_empresa ? '../dashboard_empresa.php' : '../index.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Orçamentos — ServiceHub</title>
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

<?php if ($is_cliente || $is_empresa): ?>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="<?= $back_url ?>">Início</a>
      <?php if ($is_cliente): ?>
        <a href="../clientes/empresas.php">Empresas</a>
        <a href="../clientes/perfil.php">Meu Perfil</a>
      <?php else: ?>
        <a href="../empresas/meus_servicos.php">Meus Serviços</a>
        <a href="../empresas/perfil.php">Perfil</a>
        <a href="../relatorios/index.php">Relatórios</a>
      <?php endif; ?>
      <a href="index.php" style="color:#fff;background:rgba(201,168,76,.18);">Orçamentos</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>
<?php else: ?>
<header class="main-header">
  <div class="header-content">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><p>Gestão de Serviços &amp; Orçamentos</p></div>
    <nav class="main-nav"><ul>
      <li><a href="../index.php">Início</a></li>
      <li><a href="../clientes/index.php">Clientes</a></li>
      <li><a href="index.php" class="active">Orçamentos</a></li>
      <li><a href="../relatorios/index.php">Relatórios</a></li>
    </ul></nav>
  </div>
</header>
<?php endif; ?>

<div class="container">
  <div class="page-title-row">
    <h1><?= $is_cliente ? 'Meus Orçamentos' : 'Orçamentos' ?></h1>
    <?php if ($is_cliente): ?>
      <a href="../clientes/empresas.php" class="btn btn-primary">+ Solicitar Orçamento</a>
    <?php elseif ($is_empresa): ?>
      <a href="create.php" class="btn btn-primary">+ Novo Orçamento</a>
    <?php endif; ?>
  </div>

  <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

  <div class="summary-ribbon">
    <div class="ribbon-item">
      <div class="ribbon-val"><?= $total ?></div>
      <div class="ribbon-lbl">Total de registros</div>
    </div>
    <div class="ribbon-item">
      <div class="ribbon-val"><?= formatMoney($totalValor) ?></div>
      <div class="ribbon-lbl">Valor nesta página</div>
    </div>
    <div class="ribbon-item">
      <div class="ribbon-val" style="color:#fbbf24;"><?= formatMoney($totalPendente) ?></div>
      <div class="ribbon-lbl">Pendente</div>
    </div>
    <div class="ribbon-item">
      <div class="ribbon-val" style="color:#6ee7b7;"><?= formatMoney($totalAprovado) ?></div>
      <div class="ribbon-lbl">Aprovado</div>
    </div>
  </div>

  <form class="filter-bar" method="get">
    <div class="form-group">
      <label>Status</label>
      <select name="status" class="form-control">
        <option value="">Todos</option>
        <?php foreach (['pendente','aprovado','rejeitado','concluido','expirado'] as $s): ?>
        <option value="<?= $s ?>" <?= $status_filtro === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Data início</label>
      <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
    </div>
    <div class="form-group">
      <label>Data fim</label>
      <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
    </div>
    <div style="display:flex;gap:8px;padding-bottom:1px;">
      <button type="submit" class="btn btn-primary">Filtrar</button>
      <a href="index.php" class="btn btn-ghost">Limpar</a>
    </div>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th>
        <?php if (!$is_cliente): ?><th>Cliente</th><?php endif; ?>
        <?php if (!$is_empresa): ?><th>Empresa</th><?php endif; ?>
        <th>Serviço</th><th>Data</th><th>Validade</th><th>Valor</th><th>Status</th><th>Ações</th>
      </tr></thead>
      <tbody>
        <?php foreach ($orcamentos as $o): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:12px;">#<?= $o['id'] ?></td>
          <?php if (!$is_cliente): ?>
          <td><strong><?= htmlspecialchars($o['cliente_nome'] ?? '—') ?></strong></td>
          <?php endif; ?>
          <?php if (!$is_empresa): ?>
          <td><?= htmlspecialchars($o['nome_empresa'] ?? '—') ?></td>
          <?php endif; ?>
          <td><?= htmlspecialchars($o['servico_nome'] ?? '—') ?></td>
          <td><?= formatDate($o['data_orcamento']) ?></td>
          <td><?= formatDate($o['data_validade']) ?></td>
          <td><strong style="color:var(--teal);"><?= formatMoney($o['valor_total']) ?></strong></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <a href="view.php?id=<?= $o['id'] ?>" class="btn btn-sm">Ver</a>
              <?php if ($is_empresa): ?>
              <a href="edit.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
              <a href="delete.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Excluir este orçamento?')">Excluir</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($orcamentos)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <span class="empty-icon">💰</span>
            <h3>Nenhum orçamento encontrado</h3>
            <?php if ($is_cliente): ?>
              <p>Explore as empresas disponíveis e solicite seu primeiro orçamento!</p>
              <a href="../clientes/empresas.php" class="btn btn-primary">Ver Empresas</a>
            <?php elseif ($is_empresa): ?>
              <p>Crie o primeiro orçamento agora mesmo.</p>
              <a href="create.php" class="btn btn-primary">+ Novo Orçamento</a>
            <?php endif; ?>
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
      <?php
        $qs = http_build_query(['page' => $i, 'status' => $status_filtro, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim]);
        echo $i == $page ? "<span>$i</span>" : "<a href='index.php?$qs'>$i</a>";
      ?>
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
