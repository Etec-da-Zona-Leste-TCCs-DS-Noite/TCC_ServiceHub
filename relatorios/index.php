<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

// Relatórios são exclusivos para empresa logada
if (!isEmpresa()) {
    header('Location: ../index.php');
    exit;
}

$eid = $_SESSION['empresa_id'];

// KPIs da empresa
$totalServicos       = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id=?");
$totalServicos->execute([$eid]); $totalServicos = $totalServicos->fetchColumn();

$totalServicosAtivos = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id=? AND status=1");
$totalServicosAtivos->execute([$eid]); $totalServicosAtivos = $totalServicosAtivos->fetchColumn();

$totalClientes       = $pdo->prepare("SELECT COUNT(DISTINCT cliente_id) FROM orcamentos WHERE empresa_id=?");
$totalClientes->execute([$eid]); $totalClientes = $totalClientes->fetchColumn();

$totalOrcamentos     = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE empresa_id=?");
$totalOrcamentos->execute([$eid]); $totalOrcamentos = $totalOrcamentos->fetchColumn();

// Por status
$statusList = ['pendente','aprovado','rejeitado','concluido','expirado'];
$orcPorStatus = $valPorStatus = [];
foreach ($statusList as $s) {
    $st = $pdo->prepare("SELECT COUNT(*), SUM(valor_total) FROM orcamentos WHERE empresa_id=? AND status=?");
    $st->execute([$eid, $s]);
    [$orcPorStatus[$s], $valPorStatus[$s]] = $st->fetch(PDO::FETCH_NUM);
    $valPorStatus[$s] = (float)($valPorStatus[$s] ?? 0);
}

// Faturamento por mês (últimos 12 meses)
$porMes = [];
for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $st  = $pdo->prepare("SELECT COUNT(*), SUM(valor_total) FROM orcamentos WHERE empresa_id=? AND DATE_FORMAT(data_orcamento,'%Y-%m')=?");
    $st->execute([$eid, $mes]);
    [$cnt, $val] = $st->fetch(PDO::FETCH_NUM);
    $porMes[] = ['mes' => $mes, 'total' => (int)$cnt, 'valor' => (float)($val ?? 0)];
}

// Serviços mais solicitados
$topServicos = $pdo->prepare("
    SELECT s.nome, COUNT(oi.id) AS total_sol, SUM(oi.quantidade) AS total_qty, SUM(oi.subtotal) AS total_val
    FROM servicos s LEFT JOIN orcamento_itens oi ON oi.servico_id = s.id
    LEFT JOIN orcamentos o ON o.id = oi.orcamento_id
    WHERE s.empresa_id = ?
    GROUP BY s.id, s.nome ORDER BY total_sol DESC LIMIT 10
");
$topServicos->execute([$eid]); $topServicos = $topServicos->fetchAll();

// Top clientes
$topClientes = $pdo->prepare("
    SELECT c.nome, COUNT(o.id) AS total_orc, SUM(o.valor_total) AS total_val
    FROM orcamentos o JOIN clientes c ON c.id = o.cliente_id
    WHERE o.empresa_id = ? AND o.status IN ('aprovado','concluido')
    GROUP BY c.id, c.nome ORDER BY total_val DESC LIMIT 5
");
$topClientes->execute([$eid]); $topClientes = $topClientes->fetchAll();

// Filtro de listagem
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');
$sf          = $_GET['status']      ?? '';

$sql = "SELECT o.*, c.nome AS cliente_nome FROM orcamentos o
        LEFT JOIN clientes c ON c.id = o.cliente_id
        WHERE o.empresa_id = ? AND o.data_orcamento BETWEEN ? AND ?";
$p = [$eid, $data_inicio, $data_fim];
if ($sf) { $sql .= " AND o.status=?"; $p[] = $sf; }
$sql .= " ORDER BY o.data_orcamento DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($p);
$lista = $stmt->fetchAll();

// Total do período filtrado
$totalPeriodo = array_sum(array_column($lista, 'valor_total'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Relatórios — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none; }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    .status-block { text-align:center; }
    .status-val   { font-size:26px;font-weight:700;font-family:'DM Sans',sans-serif;line-height:1.1; }
    .status-money { font-size:13px;color:var(--text-muted);margin-top:4px; }
    .chart-bar-wrap { display:flex;flex-direction:column;gap:8px; }
    .chart-bar-row  { display:flex;align-items:center;gap:10px; }
    .chart-bar-label{ font-size:12px;color:var(--text-muted);width:110px;text-align:right;flex-shrink:0; }
    .chart-bar-bg   { flex:1;background:var(--bg);border-radius:4px;height:20px;overflow:hidden; }
    .chart-bar-fill { height:100%;background:linear-gradient(90deg,var(--gold),var(--gold-lt));border-radius:4px;transition:width .6s ease; }
    .chart-bar-val  { font-size:12px;font-weight:600;color:var(--text);width:40px;text-align:right;flex-shrink:0; }
    .top-item { display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border); }
    .top-item:last-child { border-bottom:none; }
    .top-badge { background:var(--gold-dim);color:#78530a;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:700; }
    @media(max-width:768px){.two-col-report{grid-template-columns:1fr!important;}}
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><small style="font-size:11px;color:var(--slate);display:block;">Área da Empresa</small></div>
    <div class="nav-items">
      <a href="../dashboard_empresa.php">Início</a>
      <a href="../empresas/meus_servicos.php">Meus Serviços</a>
      <a href="../empresas/perfil.php">Perfil</a>
      <a href="../orcamentos/index.php">Orçamentos</a>
      <a href="index.php" style="color:#fff;background:rgba(201,168,76,.18);">Relatórios</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-title-row">
    <h1>Relatórios — <?= htmlspecialchars($_SESSION['empresa_nome']) ?></h1>
  </div>

  <!-- KPIs -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-number"><?=$totalServicosAtivos?></div>
      <div class="stat-label"><?=$totalServicosAtivos?> ativos de <?=$totalServicos?> serviços</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-number"><?=$totalClientes?></div>
      <div class="stat-label">Clientes atendidos</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-number"><?=$totalOrcamentos?></div>
      <div class="stat-label">Orçamentos totais</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-number"><?=$orcPorStatus['aprovado'] + $orcPorStatus['concluido']?></div>
      <div class="stat-label">Aprovados/Concluídos · <?= formatMoney($valPorStatus['aprovado'] + $valPorStatus['concluido']) ?></div>
    </div>
  </div>

  <!-- Status -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Orçamentos por Status</h3></div>
    <div class="card-body">
      <div style="display:flex;flex-wrap:wrap;gap:24px;justify-content:space-around;">
        <?php
        $colors = ['pendente'=>'#f59e0b','aprovado'=>'#10b981','rejeitado'=>'#ef4444','concluido'=>'#6366f1','expirado'=>'#94a3b8'];
        $icons  = ['pendente'=>'⏳','aprovado'=>'✅','rejeitado'=>'✕','concluido'=>'🏁','expirado'=>'⌛'];
        foreach ($statusList as $s): ?>
        <div class="status-block">
          <div style="width:48px;height:48px;border-radius:50%;background:<?=$colors[$s]?>;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;">
            <?= $icons[$s] ?>
          </div>
          <div class="status-val" style="color:<?=$colors[$s]?>"><?=$orcPorStatus[$s]?></div>
          <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-top:4px;"><?=ucfirst($s)?></div>
          <div class="status-money"><?= formatMoney($valPorStatus[$s]) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;" class="two-col-report">

    <!-- Serviços mais solicitados -->
    <div class="card">
      <div class="card-header"><h3>Serviços Mais Solicitados</h3></div>
      <div class="card-body">
        <?php if (empty($topServicos)): ?>
          <p style="color:var(--text-muted);font-size:14px;">Nenhum dado disponível.</p>
        <?php else:
          $maxSol = max(array_column($topServicos,'total_sol')) ?: 1; ?>
          <div class="chart-bar-wrap">
            <?php foreach ($topServicos as $t): ?>
            <div class="chart-bar-row">
              <div class="chart-bar-label" title="<?= htmlspecialchars($t['nome']) ?>"><?= htmlspecialchars(mb_substr($t['nome'],0,16)) ?></div>
              <div class="chart-bar-bg">
                <div class="chart-bar-fill" style="width:<?= round($t['total_sol']/$maxSol*100) ?>%"></div>
              </div>
              <div class="chart-bar-val"><?=$t['total_sol']?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Top Clientes -->
    <div class="card">
      <div class="card-header"><h3>Melhores Clientes (aprovados/concluídos)</h3></div>
      <div class="card-body">
        <?php if (empty($topClientes)): ?>
          <p style="color:var(--text-muted);font-size:14px;">Nenhum dado disponível.</p>
        <?php else: ?>
          <?php foreach ($topClientes as $tc): ?>
          <div class="top-item">
            <div>
              <span style="font-size:14px;font-weight:500;">👤 <?= htmlspecialchars($tc['nome']) ?></span>
              <br><small style="color:var(--text-muted);"><?= $tc['total_orc'] ?> orçamento(s)</small>
            </div>
            <span class="top-badge"><?= formatMoney($tc['total_val']) ?></span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Evolução mensal -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h3>Evolução Mensal — Últimos 12 Meses</h3></div>
    <div style="overflow-x:auto;">
      <table>
        <thead><tr><th>Mês</th><th>Qtd. Orçamentos</th><th>Valor Total</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($porMes) as $m): ?>
          <tr>
            <td><?= date('m/Y', strtotime($m['mes'])) ?></td>
            <td><?=$m['total']?></td>
            <td><strong style="color:var(--teal);"><?= formatMoney($m['valor']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Lista filtrada -->
  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <h3>Lista de Orçamentos no Período</h3>
      <?php if (!empty($lista)): ?>
        <span style="font-size:13px;color:var(--text-muted);">
          <?= count($lista) ?> registros · Total: <strong style="color:var(--teal);"><?= formatMoney($totalPeriodo) ?></strong>
        </span>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding-bottom:0;">
      <form class="filter-bar" method="get" style="background:var(--bg);margin-bottom:16px;">
        <div class="form-group"><label>Data início</label><input type="date" name="data_inicio" class="form-control" value="<?=$data_inicio?>"></div>
        <div class="form-group"><label>Data fim</label><input type="date" name="data_fim" class="form-control" value="<?=$data_fim?>"></div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" class="form-control">
            <option value="">Todos</option>
            <?php foreach ($statusList as $s): ?>
            <option value="<?=$s?>" <?=$sf===$s?'selected':''?>><?=ucfirst($s)?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="padding-bottom:1px;">
          <button type="submit" class="btn btn-primary">Filtrar</button>
          <a href="index.php" class="btn btn-ghost" style="margin-left:6px;">Limpar</a>
        </div>
      </form>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table>
        <thead><tr><th>#</th><th>Cliente</th><th>Data</th><th>Valor</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($lista as $o): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px;">#<?=$o['id']?></td>
            <td><?= htmlspecialchars($o['cliente_nome']??'—') ?></td>
            <td><?= formatDate($o['data_orcamento']) ?></td>
            <td><strong style="color:var(--teal);"><?= formatMoney($o['valor_total']) ?></strong></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td><a href="../orcamentos/view.php?id=<?=$o['id']?>" class="btn btn-sm">Ver</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($lista)): ?>
          <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhum orçamento no período.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
