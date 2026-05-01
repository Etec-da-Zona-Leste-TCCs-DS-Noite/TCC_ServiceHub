<?php
// admin/avaliacoes.php — Moderação de Avaliações
session_start();
require_once '../includes/config.php';
require_once 'auth_admin.php';

// Excluir avaliação
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM avaliacoes WHERE id = ?")->execute([$id]);
    header('Location: avaliacoes.php?msg=Avaliação+removida'); exit;
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;

$total = $pdo->query("SELECT COUNT(*) FROM avaliacoes")->fetchColumn();
$totalPages = (int)ceil($total/$limit);

$stmt = $pdo->prepare("
    SELECT a.*, c.nome AS cliente_nome, e.nome_empresa, s.nome AS servico_nome
    FROM avaliacoes a
    JOIN clientes c ON c.id = a.cliente_id
    JOIN empresas e ON e.id = a.empresa_id
    JOIN orcamentos o ON o.id = a.orcamento_id
    LEFT JOIN servicos s ON s.id = o.servico_id
    ORDER BY a.created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1,$limit,PDO::PARAM_INT);
$stmt->bindValue(2,$offset,PDO::PARAM_INT);
$stmt->execute(); $avaliacoes = $stmt->fetchAll();

$media = $pdo->query("SELECT ROUND(AVG(nota),1), COUNT(*) FROM avaliacoes")->fetch(PDO::FETCH_NUM);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Avaliações — Admin ServiceHub</title>
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
    .admin-table{width:100%;border-collapse:collapse;background:#fff;border-radius:var(--r);overflow:hidden;}
    .admin-table th{padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:1px solid var(--border);background:#fafafa;text-align:left;}
    .admin-table td{padding:11px 14px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:top;}
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
    <a href="orcamentos.php">📋 Orçamentos</a>
    <div class="section-label">Sistema</div>
    <a href="avaliacoes.php" class="active">⭐ Avaliações</a>
    <a href="servicos.php">⚙️ Serviços</a>
    <div class="section-label">Conta</div>
    <a href="../index.php" target="_blank">🌐 Ver Site</a>
    <a href="logout.php">🚪 Sair</a>
  </nav>
  <div class="sidebar-footer" style="font-size:12px;color:var(--slate);">Logado como <strong style="color:#fff;"><?= htmlspecialchars($_SESSION['admin_nome']) ?></strong></div>
</aside>

<div class="main-wrap">
  <div class="top-bar">
    <h2 style="font-size:16px;font-weight:600;">⭐ Moderação de Avaliações</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= $media[1] ?> avaliação(ões) · Média geral: <strong style="color:var(--gold);"><?= $media[0] ?? '—' ?> ★</strong></span>
  </div>
  <div class="content">
    <?php if (isset($_GET['msg'])): ?>
    <div class="success-msg"><?= htmlspecialchars(urldecode($_GET['msg'])) ?></div>
    <?php endif; ?>

    <table class="admin-table">
      <thead><tr><th>#</th><th>Cliente</th><th>Empresa</th><th>Nota</th><th>Título / Comentário</th><th>Data</th><th>Ação</th></tr></thead>
      <tbody>
        <?php foreach ($avaliacoes as $a): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:11px;"><?= $a['id'] ?></td>
          <td><?= htmlspecialchars($a['cliente_nome']) ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($a['nome_empresa']) ?></td>
          <td>
            <span style="font-size:16px;font-weight:700;color:var(--gold);"><?= $a['nota'] ?>★</span>
          </td>
          <td>
            <?php if ($a['titulo']): ?><strong style="font-size:13px;"><?= htmlspecialchars($a['titulo']) ?></strong><br><?php endif; ?>
            <?php if ($a['comentario']): ?><span style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(mb_substr($a['comentario'],0,100)) ?><?= strlen($a['comentario'])>100?'…':'' ?></span><?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted);"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
          <td>
            <a href="avaliacoes.php?delete=<?= $a['id'] ?>" class="btn btn-sm btn-danger"
               onclick="return confirm('Excluir esta avaliação?')">Remover</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($avaliacoes)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Nenhuma avaliação.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div style="display:flex;gap:6px;margin-top:20px;flex-wrap:wrap;">
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
      <a href="?page=<?=$i?>" class="btn btn-sm <?=$i==$page?'btn-primary':'btn-ghost'?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
