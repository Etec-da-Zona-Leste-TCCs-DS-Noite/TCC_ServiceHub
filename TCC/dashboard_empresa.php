<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: index.php'); exit; }

$eid = $_SESSION['empresa_id'];
$stmt=$pdo->prepare("SELECT * FROM empresas WHERE id=?"); $stmt->execute([$eid]); $empresa=$stmt->fetch();

$r=$pdo->prepare("SELECT COUNT(*) FROM servicos WHERE empresa_id=? AND status=1"); $r->execute([$eid]); $totalServ=$r->fetchColumn();
$r=$pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE empresa_id=?"); $r->execute([$eid]); $totalOrc=$r->fetchColumn();
$r=$pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE empresa_id=? AND status='aprovado'"); $r->execute([$eid]); $totalAprov=$r->fetchColumn();
$r=$pdo->prepare("SELECT SUM(valor_total) FROM orcamentos WHERE empresa_id=? AND status='aprovado'"); $r->execute([$eid]); $fat=$r->fetchColumn();
$avalDados = mediaAvaliacoes($pdo, $eid);

$orcStmt=$pdo->prepare("SELECT o.*,c.nome AS cli,s.nome AS svc FROM orcamentos o JOIN clientes c ON c.id=o.cliente_id JOIN servicos s ON s.id=o.servico_id WHERE o.empresa_id=? ORDER BY o.created_at DESC LIMIT 10");
$orcStmt->execute([$eid]); $orcList=$orcStmt->fetchAll();

$popStmt=$pdo->prepare("SELECT s.nome,COUNT(o.id) AS total FROM servicos s LEFT JOIN orcamentos o ON o.servico_id=s.id WHERE s.empresa_id=? GROUP BY s.id,s.nome ORDER BY total DESC LIMIT 5");
$popStmt->execute([$eid]); $populares=$popStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Painel da Empresa — ServiceHub</title>
  <link rel="stylesheet" href="css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:4px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 13px;border-radius:var(--radius-sm);transition:all var(--transition); }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    .welcome-banner { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);color:#fff;border-radius:var(--radius);padding:28px;margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;border:1px solid rgba(201,168,76,.15); }
    .welcome-banner h1 { font-size:22px;margin-bottom:4px; }
    .welcome-banner p  { color:var(--slate);font-size:14px; }
    .quick-actions { display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:32px; }
    .qa-card { background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;text-align:center;text-decoration:none;color:var(--text);transition:all var(--transition); }
    .qa-card:hover { transform:translateY(-4px);box-shadow:var(--shadow);border-color:var(--gold); }
    .qa-icon { font-size:28px;display:block;margin-bottom:8px; }
    .qa-card strong { display:block;font-size:13px;margin-bottom:3px; }
    .qa-card small { font-size:11px;color:var(--text-muted); }
    .section-heading { font-size:18px;margin-bottom:16px;border-left:3px solid var(--gold);padding-left:12px; }
    .two-col { display:grid;grid-template-columns:1fr 1fr;gap:24px; }
    .pop-item { display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid var(--border); }
    .pop-item:last-child { border-bottom:none; }
    .pop-badge { background:var(--gold-dim);color:#78530a;padding:2px 10px;border-radius:100px;font-size:11px;font-weight:700; }
    .user-chip { display:flex;align-items:center;gap:10px; }
    .avatar { width:36px;height:36px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--navy);font-size:14px;flex-shrink:0; }
    @media(max-width:768px){.two-col{grid-template-columns:1fr;}.welcome-banner{flex-direction:column;}}
  </style>
</head>
<body>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><small style="font-size:11px;color:var(--slate);display:block;">Área da Empresa</small></div>
    <button class="hamburger" onclick="document.querySelector('.nav-items').classList.toggle('open')">☰</button>
    <div class="nav-items">
      <a href="dashboard_empresa.php">Início</a>
      <a href="empresas/meus_servicos.php">Meus Serviços</a>
      <a href="empresas/perfil.php">Perfil</a>
      <a href="clientes/index.php">Clientes</a>
      <a href="orcamentos/index.php?empresa=<?=$eid?>">Orçamentos</a>
      <a href="avaliacoes/index.php">⭐ Avaliações</a>
      <a href="chat/index.php" id="navChat">💬 Mensagens</a>
      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($_SESSION['empresa_nome'],0,1)) ?></div>
        <span style="color:#fff;font-size:13px;"><?= htmlspecialchars($_SESSION['empresa_nome']) ?></span>
        <a href="logout.php" class="btn btn-sm btn-ghost" style="color:var(--slate-lt);">Sair</a>
      </div>
    </div>
  </div>
</nav>

<div class="container">
  <div class="welcome-banner">
    <div>
      <h1>Bem-vindo, <?= htmlspecialchars($empresa['nome_empresa']) ?>!</h1>
      <p>Gerencie seus serviços e acompanhe os orçamentos.</p>
    </div>
    <a href="empresas/meus_servicos.php?action=create" class="btn btn-primary">+ Novo Serviço</a>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-number"><?=$totalServ?></div>
      <div class="stat-label">Serviços ativos</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📨</div>
      <div class="stat-number"><?=$totalOrc?></div>
      <div class="stat-label">Orçamentos recebidos</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-number"><?=$totalAprov?></div>
      <div class="stat-label">Orçamentos aprovados</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💰</div>
      <div class="stat-number"><?= formatMoney($fat) ?></div>
      <div class="stat-label">Faturamento total</div>
    </div>
    <div class="stat-card" style="border-top:3px solid var(--gold);">
      <div class="stat-icon">⭐</div>
      <div class="stat-number" style="color:var(--gold);">
        <?= $avalDados['total'] > 0 ? number_format($avalDados['media'],1,',','') : '—' ?>
      </div>
      <div class="stat-label">
        Avaliação média
        <?php if ($avalDados['total'] > 0): ?>
          <br><small style="font-size:11px;"><?=$avalDados['total']?> avaliação(ões)</small>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <h2 class="section-heading">Ações Rápidas</h2>
  <div class="quick-actions">
    <a href="empresas/meus_servicos.php?action=create" class="qa-card">
      <span class="qa-icon">➕</span><strong>Novo Serviço</strong><small>Adicionar serviço</small>
    </a>
    <a href="empresas/meus_servicos.php" class="qa-card">
      <span class="qa-icon">🗂</span><strong>Gerenciar Serviços</strong><small>Editar ou remover</small>
    </a>
    <a href="empresas/perfil.php" class="qa-card">
      <span class="qa-icon">🏢</span><strong>Editar Perfil</strong><small>Atualizar dados</small>
    </a>
    <a href="orcamentos/index.php?empresa=<?=$eid?>" class="qa-card">
      <span class="qa-icon">📊</span><strong>Ver Orçamentos</strong><small>Acompanhar solicitações</small>
    </a>
    <a href="relatorios/index.php" class="qa-card">
      <span class="qa-icon">📈</span><strong>Relatórios</strong><small>Análises e métricas</small>
    </a>
  </div>

  <div class="two-col">
    <div>
      <h2 class="section-heading">Últimos Orçamentos</h2>
      <div class="table-wrap">
        <table>
          <thead><tr><th>#</th><th>Cliente</th><th>Serviço</th><th>Valor</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orcList as $o): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:11px;">#<?=$o['id']?></td>
              <td><?= htmlspecialchars($o['cli']) ?></td>
              <td><?= htmlspecialchars($o['svc']) ?></td>
              <td style="color:var(--teal);font-weight:600;"><?= formatMoney($o['valor_total']) ?></td>
              <td><?= statusBadge($o['status']) ?></td>
              <td><a href="orcamentos/view.php?id=<?=$o['id']?>" class="btn btn-sm">Ver</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orcList)): ?>
            <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--text-muted);">Nenhum orçamento ainda.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div>
      <h2 class="section-heading">Serviços Mais Solicitados</h2>
      <div class="card">
        <div class="card-body">
          <?php foreach ($populares as $p): ?>
          <div class="pop-item">
            <span style="font-size:14px;font-weight:500;">⚙ <?= htmlspecialchars($p['nome']) ?></span>
            <span class="pop-badge"><?=$p['total']?> sol.</span>
          </div>
          <?php endforeach; ?>
          <?php if (empty($populares)): ?>
          <p style="text-align:center;color:var(--text-muted);font-size:14px;padding:20px 0;">Nenhum dado ainda.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
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
