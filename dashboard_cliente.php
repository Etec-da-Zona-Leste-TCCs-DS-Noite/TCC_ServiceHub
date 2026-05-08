<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
verificarLogin();
if (!isCliente()) { header('Location: index.php'); exit; }

$cid = $_SESSION['cliente_id'];
$empresas     = $pdo->query("SELECT * FROM empresas WHERE status=1 ORDER BY nome_empresa")->fetchAll();
$orcamentosStmt=$pdo->prepare("SELECT o.*,s.nome AS svc,e.nome_empresa AS emp FROM orcamentos o LEFT JOIN servicos s ON s.id=o.servico_id LEFT JOIN empresas e ON e.id=o.empresa_id WHERE o.cliente_id=? ORDER BY o.created_at DESC LIMIT 10");
$orcamentosStmt->execute([$cid]); $orcList=$orcamentosStmt->fetchAll();

$r=$pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id=?"); $r->execute([$cid]); $totalOrc=$r->fetchColumn();
$r=$pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id=? AND status='aprovado'"); $r->execute([$cid]); $totalAprov=$r->fetchColumn();
$r=$pdo->prepare("SELECT SUM(valor_total) FROM orcamentos WHERE cliente_id=? AND status='aprovado'"); $r->execute([$cid]); $gasto=$r->fetchColumn();

// Orçamentos concluídos sem avaliação
$pendAvStmt = $pdo->prepare("
    SELECT o.id, e.nome_empresa, s.nome AS servico_nome
    FROM orcamentos o
    LEFT JOIN empresas e ON e.id = o.empresa_id
    LEFT JOIN servicos s ON s.id = o.servico_id
    WHERE o.cliente_id = ? AND o.status = 'concluido'
      AND NOT EXISTS (SELECT 1 FROM avaliacoes a WHERE a.orcamento_id = o.id)
    ORDER BY o.updated_at DESC LIMIT 5");
$pendAvStmt->execute([$cid]);
$pendentesAvaliar = $pendAvStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Painel do Cliente — ServiceHub</title>
  <link rel="stylesheet" href="css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%); border-bottom:1px solid rgba(201,168,76,.2); position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition); }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    .welcome-banner { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);color:#fff;border-radius:var(--radius);padding:30px;margin-bottom:28px;border:1px solid rgba(201,168,76,.15); }
    .welcome-banner h1 { font-size:24px;margin-bottom:6px; }
    .welcome-banner p  { color:var(--slate);font-size:14px; }
    .section-heading { font-size:18px;margin-bottom:16px;border-left:3px solid var(--gold);padding-left:12px; }
    .empresa-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-bottom:36px; }
    .emp-card { background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all var(--transition); }
    .emp-card:hover { transform:translateY(-4px);box-shadow:var(--shadow); }
    .emp-card-top { background:linear-gradient(135deg,var(--navy),var(--navy-soft));padding:18px;color:#fff; }
    .emp-card-top h3 { font-size:15px;margin-bottom:4px; }
    .emp-card-top small { color:var(--slate);font-size:12px; }
    .emp-card-body { padding:16px; }
    .emp-card-body p { font-size:13px;color:var(--text-muted);margin-bottom:8px; }
    .user-chip { display:flex;align-items:center;gap:10px; }
    .avatar { width:36px;height:36px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--navy);font-size:14px;flex-shrink:0; }
  </style>
</head>
<body>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <button class="hamburger" onclick="document.querySelector('.nav-items').classList.toggle('open')">☰</button>
    <div class="nav-items">
      <a href="dashboard_cliente.php">Início</a>
      <a href="clientes/empresas.php">Empresas</a>
      <a href="clientes/mapa.php"><i class="fas fa-map-marked-alt"></i> Mapa</a>
      <a href="orcamentos/index.php?cliente=<?=$cid?>">Meus Orçamentos</a>
      <a href="avaliacoes/index.php">⭐ Avaliações</a>
      <a href="chat/index.php" id="navChat">💬 Mensagens</a>
      <a href="clientes/perfil.php">Meu Perfil</a>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($_SESSION['cliente_nome'],0,1)) ?></div>
        <span style="color:#fff;font-size:13px;"><?= htmlspecialchars($_SESSION['cliente_nome']) ?></span>
        <a href="logout.php" class="btn btn-sm btn-ghost" style="color:var(--slate-lt);">Sair</a>
      </div>
    </div>
  </div>
</nav>

<div class="container">
  <div class="welcome-banner">
    <h1>Olá, <?= htmlspecialchars($_SESSION['cliente_nome']) ?>!</h1>
    <p>Encontre as melhores empresas e serviços para suas necessidades.</p>
  </div>

  <div class="stats-grid" style="margin-bottom:36px;">
    <div class="stat-card">
      <div class="stat-icon">💼</div>
      <div class="stat-number"><?=$totalOrc?></div>
      <div class="stat-label">Orçamentos realizados</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-number"><?=$totalAprov?></div>
      <div class="stat-label">Orçamentos aprovados</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-number"><?= formatMoney($gasto) ?></div>
      <div class="stat-label">Total investido</div>
    </div>
  </div>

  <?php if (!empty($pendentesAvaliar)): ?>
  <div style="background:linear-gradient(135deg,#1a2d42,#0d1b2a);border:1px solid rgba(201,168,76,.3);border-radius:var(--radius);padding:20px 24px;margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
      <span style="font-size:22px;">⭐</span>
      <strong style="color:#fff;font-size:15px;">Serviços aguardando sua avaliação</strong>
      <span style="background:var(--gold);color:var(--navy);border-radius:100px;padding:1px 8px;font-size:11px;font-weight:700;"><?= count($pendentesAvaliar) ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php foreach ($pendentesAvaliar as $pa): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;background:rgba(255,255,255,.05);border-radius:var(--radius-sm);padding:10px 14px;">
        <div>
          <strong style="color:#fff;font-size:13px;"><?= htmlspecialchars($pa['nome_empresa'] ?? '—') ?></strong>
          <span style="color:var(--slate);font-size:12px;"> · <?= htmlspecialchars($pa['servico_nome'] ?? '—') ?> · Orçamento #<?=$pa['id']?></span>
        </div>
        <a href="avaliacoes/create.php?orcamento_id=<?=$pa['id']?>" class="btn btn-sm btn-primary" style="white-space:nowrap;">Avaliar</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <h2 class="section-heading">Empresas em Destaque</h2>
  <div class="empresa-grid">
    <?php foreach (array_slice($empresas,0,6) as $e): ?>
    <div class="emp-card">
      <div class="emp-card-top">
        <h3><?= htmlspecialchars($e['nome_empresa']) ?></h3>
        <?php if ($e['site']): ?><small><?= htmlspecialchars($e['site']) ?></small><?php endif; ?>
      </div>
      <div class="emp-card-body">
        <p><?= htmlspecialchars(mb_substr($e['descricao']??'',0,100)) ?>…</p>
        <p>📍 <?= htmlspecialchars($e['endereco']??'Local não informado') ?></p>
        <a href="clientes/empresa.php?id=<?=$e['id']?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center;margin-top:4px;">Ver Serviços →</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <h2 class="section-heading">Meus Últimos Orçamentos</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Serviço</th><th>Empresa</th><th>Valor</th><th>Data</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orcList as $o): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:12px;">#<?=$o['id']?></td>
          <td><?= htmlspecialchars($o['svc'] ?? 'Serviço removido') ?></td>
          <td><?= htmlspecialchars($o['emp'] ?? 'Empresa removida') ?></td>
          <td><strong style="color:var(--teal);"><?= formatMoney($o['valor_total']) ?></strong></td>
          <td><?= formatDate($o['data_orcamento']) ?></td>
          <td><?= statusBadge($o['status']) ?></td>
          <td><a href="orcamentos/view.php?id=<?=$o['id']?>" class="btn btn-sm">Ver</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orcList)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhum orçamento encontrado.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>

<script>
// Hamburger menu
document.querySelector('.hamburger')?.addEventListener('click', function(){
  document.querySelector('.nav-items').classList.toggle('open');
});
// Auto-loading em forms
document.querySelectorAll('form').forEach(f => {
  f.addEventListener('submit', function(){
    const btn = this.querySelector('[type=submit]');
    if(btn) btn.setAttribute('data-loading','1');
  });
});
</script>
<script>
// Badge de mensagens não lidas
(function pollUnread() {
  fetch('chat/unread.php')
    .then(r => r.json())
    .then(d => {
      const el = document.getElementById('navChat');
      if (el) el.innerHTML = '💬 Mensagens' + (d.count > 0 ? ` <span style="background:#c9a84c;color:#0d1b2a;border-radius:100px;font-size:11px;font-weight:700;padding:1px 7px;">${d.count}</span>` : '');
    })
    .catch(() => {});
  setTimeout(pollUnread, 10000);
})();
</script>
</body>
</html>
