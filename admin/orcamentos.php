<?php
// admin/orcamentos.php — Gerenciamento de Orçamentos (visão global)
session_start();
require_once '../includes/config.php';
require_once 'auth_admin.php';

$status_filtro = $_GET['status'] ?? '';
$busca = trim($_GET['busca'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;

$statusValidos = ['pendente','aprovado','rejeitado','concluido','expirado'];
$where  = 'WHERE 1=1';
$params = [];
if (in_array($status_filtro, $statusValidos)) { $where .= ' AND o.status = ?'; $params[] = $status_filtro; }
if ($busca !== '') { $where .= ' AND (c.nome LIKE ? OR e.nome_empresa LIKE ?)'; $params[] = "%$busca%"; $params[] = "%$busca%"; }

$total = $pdo->prepare("SELECT COUNT(*) FROM orcamentos o LEFT JOIN clientes c ON c.id=o.cliente_id LEFT JOIN empresas e ON e.id=o.empresa_id $where");
$total->execute($params); $total = $total->fetchColumn();
$totalPages = (int)ceil($total/$limit);

$stList = $pdo->prepare("SELECT o.*, c.nome AS cliente_nome, e.nome_empresa, s.nome AS servico_nome FROM orcamentos o LEFT JOIN clientes c ON c.id=o.cliente_id LEFT JOIN empresas e ON e.id=o.empresa_id LEFT JOIN servicos s ON s.id=o.servico_id $where ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
foreach ($params as $i=>$v) $stList->bindValue($i+1,$v);
$stList->bindValue(count($params)+1,$limit,PDO::PARAM_INT);
$stList->bindValue(count($params)+2,$offset,PDO::PARAM_INT);
$stList->execute(); $orcamentos = $stList->fetchAll();

$kpi = $pdo->query("SELECT COUNT(*) AS total, SUM(status='pendente') AS pend, SUM(status='aprovado') AS aprov, SUM(status='concluido') AS conc, SUM(CASE WHEN status='concluido' THEN valor_total END) AS faturado FROM orcamentos")->fetch();

function adBadge($status) {
    $map = ['pendente'=>'#f39c12','aprovado'=>'#27ae60','rejeitado'=>'#e74c3c','concluido'=>'#2980b9','expirado'=>'#95a5a6'];
    $c = $map[$status] ?? '#95a5a6';
    return "<span style='background:{$c}22;color:{$c};border:1px solid {$c}44;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:600;'>".ucfirst($status)."</span>";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Orçamentos — Admin ServiceHub</title>
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
    .kpi-row{display:flex;gap:14px;margin-bottom:24px;flex-wrap:wrap;}
    .kpi-box{background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:14px 18px;flex:1;min-width:120px;}
    .kpi-box .val{font-size:22px;font-weight:700;} .kpi-box .lbl{font-size:11px;color:var(--text-muted);font-weight:500;}
    .admin-table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--r);overflow:hidden;}
    .admin-table th{padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:1px solid var(--border);background:#fafafa;text-align:left;}
    .admin-table td{padding:11px 14px;font-size:13px;border-bottom:1px solid var(--border);}
    .admin-table tr:last-child td{border-bottom:none;}
    @media(max-width:900px){.admin-sidebar{display:none;}.main-wrap{margin-left:0;}}
  </style>
</head>
<body>
<aside class="admin-sidebar">
  <div class="sidebar-logo"><h1>Service<span>Hub</span></h1><div class="badge">ADMIN PANEL</div></div>
  <nav class="sidebar-nav">
    <div class="section-label">Principal</div>
    <a href="index.php">📊 Dashboard</a>
    <a href="empresas.php">🏢 Empresas</a>
    <a href="clientes.php">👤 Clientes</a>
    <a href="orcamentos.php" class="active">📋 Orçamentos</a>
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
    <h2 style="font-size:16px;font-weight:600;">📋 Todos os Orçamentos</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= number_format($total) ?> resultado(s)</span>
  </div>
  <div class="content">

    <div class="kpi-row">
      <div class="kpi-box"><div class="val"><?= $kpi['total'] ?></div><div class="lbl">Total</div></div>
      <div class="kpi-box" style="border-top:3px solid #f39c12;"><div class="val" style="color:#b45309;"><?= $kpi['pend'] ?></div><div class="lbl">Pendentes</div></div>
      <div class="kpi-box" style="border-top:3px solid #27ae60;"><div class="val" style="color:#065f46;"><?= $kpi['aprov'] ?></div><div class="lbl">Aprovados</div></div>
      <div class="kpi-box" style="border-top:3px solid #2980b9;"><div class="val" style="color:#1d4ed8;"><?= $kpi['conc'] ?></div><div class="lbl">Concluídos</div></div>
      <div class="kpi-box" style="border-top:3px solid var(--gold);"><div class="val" style="color:var(--green);font-size:16px;">R$ <?= number_format($kpi['faturado'] ?? 0,2,',','.') ?></div><div class="lbl">Volume concluído</div></div>
    </div>

    <form method="get" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
      <input type="text" name="busca" class="form-control" placeholder="Buscar cliente ou empresa…" value="<?= htmlspecialchars($busca) ?>" style="max-width:280px;">
      <select name="status" class="form-control" style="max-width:160px;">
        <option value="">Todos os status</option>
        <?php foreach (['pendente','aprovado','rejeitado','concluido','expirado'] as $s): ?>
        <option value="<?=$s?>" <?=$status_filtro===$s?'selected':''?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary">Filtrar</button>
      <a href="orcamentos.php" class="btn btn-ghost">Limpar</a>
    </form>

    <table class="admin-table">
      <thead><tr><th>#</th><th>Cliente</th><th>Empresa</th><th>Serviço</th><th>Valor</th><th>Data</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($orcamentos as $o): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:11px;">#<?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['cliente_nome'] ?? '—') ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($o['nome_empresa'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($o['servico_nome'] ?? '—') ?></td>
          <td style="font-weight:600;color:var(--green);">R$ <?= number_format($o['valor_total'],2,',','.') ?></td>
          <td style="font-size:12px;color:var(--text-muted);"><?= date('d/m/Y', strtotime($o['data_orcamento'])) ?></td>
          <td><?= adBadge($o['status']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orcamentos)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhum orçamento encontrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:20px;flex-wrap:wrap;">
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a href="?page=<?=$i?>&status=<?=urlencode($status_filtro)?>&busca=<?=urlencode($busca)?>" class="btn btn-sm <?=$i==$page?'btn-primary':'btn-ghost'?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
