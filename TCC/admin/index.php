<?php
// admin/index.php — Painel Administrativo ServiceHub
session_start();
require_once '../includes/config.php';
require_once 'auth_admin.php';

// KPIs globais
$totalClientes  = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$totalEmpresas  = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
$totalServicos  = $pdo->query("SELECT COUNT(*) FROM servicos WHERE status=1")->fetchColumn();
$totalOrc       = $pdo->query("SELECT COUNT(*) FROM orcamentos")->fetchColumn();
$orcPendente    = $pdo->query("SELECT COUNT(*) FROM orcamentos WHERE status='pendente'")->fetchColumn();
$faturamento    = $pdo->query("SELECT SUM(valor_total) FROM orcamentos WHERE status='concluido'")->fetchColumn();
$mediaAval      = $pdo->query("SELECT ROUND(AVG(nota),1) FROM avaliacoes")->fetchColumn();
$totalAval      = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();
$totalMensagens = $pdo->query("SELECT COUNT(*) FROM mensagens")->fetchColumn();

// Últimos orçamentos
$ultOrc = $pdo->query("
    SELECT o.id, o.status, o.valor_total, o.created_at,
           c.nome AS cliente, e.nome_empresa AS empresa, s.nome AS servico
    FROM orcamentos o
    LEFT JOIN clientes c  ON c.id = o.cliente_id
    LEFT JOIN empresas e  ON e.id = o.empresa_id
    LEFT JOIN servicos s  ON s.id = o.servico_id
    ORDER BY o.created_at DESC LIMIT 8
")->fetchAll();

// Últimas empresas cadastradas
$ultEmpresas = $pdo->query("
    SELECT id, nome_empresa, email, status, created_at FROM empresas ORDER BY created_at DESC LIMIT 6
")->fetchAll();

// Faturamento mensal últimos 6 meses
$porMes = [];
for ($i = 5; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $st  = $pdo->prepare("SELECT COUNT(*), SUM(valor_total) FROM orcamentos WHERE DATE_FORMAT(created_at,'%Y-%m')=? AND status='concluido'");
    $st->execute([$mes]);
    [$cnt, $val] = $st->fetch(PDO::FETCH_NUM);
    $porMes[] = ['mes' => date('M/y', strtotime($mes.'-01')), 'total' => (int)$cnt, 'valor' => (float)($val ?? 0)];
}

function adminBadge($status) {
    $map = [
        'pendente'  => 'background:#fef9e7;color:#b45309;border:1px solid #fde68a',
        'aprovado'  => 'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0',
        'rejeitado' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca',
        'concluido' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe',
        'expirado'  => 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0',
    ];
    $style = $map[$status] ?? 'background:#f8fafc;color:#475569;border:1px solid #e2e8f0';
    return "<span style='$style;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:600;'>".ucfirst($status)."</span>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Painel Admin — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    body { background:#f1f5f9; }
    .admin-sidebar {
      position:fixed; top:0; left:0; bottom:0; width:220px;
      background:var(--navy); z-index:300;
      display:flex; flex-direction:column; padding:0;
      box-shadow: 4px 0 20px rgba(0,0,0,.2);
    }
    .sidebar-logo { padding:22px 20px 16px; border-bottom:1px solid rgba(255,255,255,.08); }
    .sidebar-logo h1 { font-size:18px; color:#fff; }
    .sidebar-logo h1 span { color:var(--gold); }
    .sidebar-logo .badge { font-size:10px; color:var(--slate); letter-spacing:.5px; font-weight:600; }
    .sidebar-nav { flex:1; padding:12px 0; overflow-y:auto; }
    .sidebar-nav a {
      display:flex; align-items:center; gap:10px;
      color:var(--slate-lt); font-size:13px; font-weight:500;
      padding:10px 20px; text-decoration:none;
      transition:all .15s ease;
    }
    .sidebar-nav a:hover, .sidebar-nav a.active {
      color:#fff; background:rgba(201,168,76,.15);
      border-left:3px solid var(--gold); padding-left:17px;
    }
    .sidebar-nav .section-label {
      font-size:10px; font-weight:700; letter-spacing:1px;
      color:var(--slate); text-transform:uppercase;
      padding:16px 20px 6px;
    }
    .sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,.08); }
    .main-wrap { margin-left:220px; min-height:100vh; }
    .top-bar {
      background:#fff; border-bottom:1px solid var(--border);
      padding:0 28px; height:56px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:100;
    }
    .top-bar h2 { font-size:16px; color:var(--text); font-weight:600; }
    .admin-badge { background:var(--navy); color:var(--gold); font-size:11px; padding:3px 10px; border-radius:100px; font-weight:700; }
    .content { padding:28px; }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:16px; margin-bottom:32px; }
    .kpi-card { background:#fff; border:1px solid var(--border); border-radius:var(--r); padding:18px 20px; }
    .kpi-card .kpi-icon { font-size:22px; margin-bottom:8px; }
    .kpi-card .kpi-val { font-size:26px; font-weight:700; color:var(--text); line-height:1; margin-bottom:4px; }
    .kpi-card .kpi-label { font-size:12px; color:var(--text-muted); font-weight:500; }
    .kpi-card.gold { border-top:3px solid var(--gold); }
    .kpi-card.green { border-top:3px solid var(--green-lt); }
    .section-title { font-size:15px; font-weight:600; color:var(--text); margin-bottom:14px; border-left:3px solid var(--gold); padding-left:10px; }
    .two-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .panel { background:#fff; border:1px solid var(--border); border-radius:var(--r); overflow:hidden; }
    .panel-head { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .panel-head h3 { font-size:14px; font-weight:600; }
    .panel-body { padding:0; }
    table.admin-table { width:100%; border-collapse:collapse; }
    table.admin-table th { padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); border-bottom:1px solid var(--border); background:#fafafa; text-align:left; }
    table.admin-table td { padding:11px 14px; font-size:13px; border-bottom:1px solid var(--border); }
    table.admin-table tr:last-child td { border-bottom:none; }
    .bar-container { height:6px; background:#f1f5f9; border-radius:100px; overflow:hidden; margin-top:4px; }
    .bar-fill { height:100%; background:var(--gold); border-radius:100px; }
    @media(max-width:900px) { .admin-sidebar{display:none;} .main-wrap{margin-left:0;} .two-grid{grid-template-columns:1fr;} }
  </style>
</head>
<body>

<aside class="admin-sidebar">
  <div class="sidebar-logo">
    <h1>Service<span>Hub</span></h1>
    <div class="badge">ADMIN PANEL</div>
  </div>
  <nav class="sidebar-nav">
    <div class="section-label">Principal</div>
    <a href="index.php" class="active">📊 Dashboard</a>
    <a href="empresas.php">🏢 Empresas</a>
    <a href="clientes.php">👤 Clientes</a>
    <a href="orcamentos.php">📋 Orçamentos</a>
    <div class="section-label">Sistema</div>
    <a href="avaliacoes.php">⭐ Avaliações</a>
    <a href="servicos.php">⚙️ Serviços</a>
    <div class="section-label">Conta</div>
    <a href="../index.php" target="_blank">🌐 Ver Site</a>
    <a href="logout.php">🚪 Sair</a>
  </nav>
  <div class="sidebar-footer" style="font-size:12px;color:var(--slate);">
    Logado como <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['admin_nome']) ?></strong>
  </div>
</aside>

<div class="main-wrap">
  <div class="top-bar">
    <h2>Dashboard Geral</h2>
    <span class="admin-badge">ADMINISTRADOR</span>
  </div>
  <div class="content">

    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi-card gold">
        <div class="kpi-icon">👤</div>
        <div class="kpi-val"><?= number_format($totalClientes) ?></div>
        <div class="kpi-label">Clientes cadastrados</div>
      </div>
      <div class="kpi-card gold">
        <div class="kpi-icon">🏢</div>
        <div class="kpi-val"><?= number_format($totalEmpresas) ?></div>
        <div class="kpi-label">Empresas cadastradas</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">⚙️</div>
        <div class="kpi-val"><?= number_format($totalServicos) ?></div>
        <div class="kpi-label">Serviços ativos</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">📋</div>
        <div class="kpi-val"><?= number_format($totalOrc) ?></div>
        <div class="kpi-label">Total de orçamentos</div>
      </div>
      <div class="kpi-card" style="border-top:3px solid #f39c12;">
        <div class="kpi-icon">⏳</div>
        <div class="kpi-val" style="color:#b45309;"><?= number_format($orcPendente) ?></div>
        <div class="kpi-label">Orçamentos pendentes</div>
      </div>
      <div class="kpi-card green">
        <div class="kpi-icon">💰</div>
        <div class="kpi-val" style="font-size:18px;color:var(--green);">R$ <?= number_format($faturamento ?? 0, 2, ',', '.') ?></div>
        <div class="kpi-label">Volume concluído</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">⭐</div>
        <div class="kpi-val" style="color:var(--gold);"><?= $mediaAval ?: '—' ?></div>
        <div class="kpi-label"><?= $totalAval ?> avaliações</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon">💬</div>
        <div class="kpi-val"><?= number_format($totalMensagens) ?></div>
        <div class="kpi-label">Mensagens enviadas</div>
      </div>
    </div>

    <!-- Faturamento mensal simplificado -->
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:20px 24px;margin-bottom:28px;">
      <div class="section-title">Faturamento Concluído — Últimos 6 Meses</div>
      <?php
      $maxVal = max(1, max(array_column($porMes, 'valor')));
      foreach ($porMes as $m):
      ?>
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:10px;">
        <span style="width:50px;font-size:12px;color:var(--text-muted);text-align:right;"><?= $m['mes'] ?></span>
        <div style="flex:1;height:22px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
          <div style="height:100%;width:<?= ($m['valor']/$maxVal)*100 ?>%;background:linear-gradient(90deg,var(--navy-soft),var(--gold));border-radius:4px;"></div>
        </div>
        <span style="width:100px;font-size:12px;font-weight:600;color:var(--text);">R$ <?= number_format($m['valor'],2,',','.') ?></span>
        <span style="font-size:11px;color:var(--text-muted);"><?= $m['total'] ?> orç.</span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Tabelas -->
    <div class="two-grid">
      <!-- Últimos orçamentos -->
      <div class="panel">
        <div class="panel-head">
          <h3>📋 Últimos Orçamentos</h3>
          <a href="orcamentos.php" style="font-size:12px;color:var(--gold);">Ver todos →</a>
        </div>
        <div class="panel-body">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Cliente</th><th>Empresa</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($ultOrc as $o): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:11px;">#<?= $o['id'] ?></td>
              <td style="font-size:13px;"><?= htmlspecialchars($o['cliente'] ?? '—') ?></td>
              <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($o['empresa'] ?? '—') ?></td>
              <td style="font-weight:600;color:var(--green);">R$ <?= number_format($o['valor_total'],2,',','.') ?></td>
              <td><?= adminBadge($o['status']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Últimas empresas -->
      <div class="panel">
        <div class="panel-head">
          <h3>🏢 Últimas Empresas Cadastradas</h3>
          <a href="empresas.php" style="font-size:12px;color:var(--gold);">Gerenciar →</a>
        </div>
        <div class="panel-body">
          <table class="admin-table">
            <thead><tr><th>Empresa</th><th>E-mail</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ultEmpresas as $e): ?>
            <tr>
              <td style="font-weight:500;"><?= htmlspecialchars($e['nome_empresa']) ?></td>
              <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($e['email']) ?></td>
              <td>
                <?php if ($e['status']): ?>
                  <span style="background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;padding:2px 8px;border-radius:100px;font-size:11px;">Ativa</span>
                <?php else: ?>
                  <span style="background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:2px 8px;border-radius:100px;font-size:11px;">Inativa</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="empresas.php?toggle=<?= $e['id'] ?>" title="<?= $e['status'] ? 'Desativar' : 'Ativar' ?>"
                   style="font-size:18px;text-decoration:none;" onclick="return confirm('Confirmar ação?')">
                  <?= $e['status'] ? '🔴' : '🟢' ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main-wrap -->
</body>
</html>
