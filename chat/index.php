<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_cliente = isCliente();
$is_empresa = isEmpresa();

if ($is_cliente) {
    $uid = $_SESSION['cliente_id'];
    $stmt = $pdo->prepare("
        SELECT cv.*,
               e.nome_empresa AS outro_nome,
               e.id           AS outro_id,
               o.id           AS orc_id,
               (SELECT COUNT(*) FROM mensagens m
                WHERE m.conversa_id = cv.id AND m.lida = 0 AND m.remetente = 'empresa') AS nao_lidas,
               (SELECT m2.conteudo FROM mensagens m2
                WHERE m2.conversa_id = cv.id ORDER BY m2.created_at DESC LIMIT 1) AS ultima_msg,
               (SELECT m2.created_at FROM mensagens m2
                WHERE m2.conversa_id = cv.id ORDER BY m2.created_at DESC LIMIT 1) AS ultima_data
        FROM conversas cv
        JOIN empresas e ON e.id = cv.empresa_id
        LEFT JOIN orcamentos o ON o.id = cv.orcamento_id
        WHERE cv.cliente_id = ?
        ORDER BY ultima_data DESC");
    $stmt->execute([$uid]);

} elseif ($is_empresa) {
    $uid = $_SESSION['empresa_id'];
    $stmt = $pdo->prepare("
        SELECT cv.*,
               c.nome          AS outro_nome,
               c.id            AS outro_id,
               o.id            AS orc_id,
               (SELECT COUNT(*) FROM mensagens m
                WHERE m.conversa_id = cv.id AND m.lida = 0 AND m.remetente = 'cliente') AS nao_lidas,
               (SELECT m2.conteudo FROM mensagens m2
                WHERE m2.conversa_id = cv.id ORDER BY m2.created_at DESC LIMIT 1) AS ultima_msg,
               (SELECT m2.created_at FROM mensagens m2
                WHERE m2.conversa_id = cv.id ORDER BY m2.created_at DESC LIMIT 1) AS ultima_data
        FROM conversas cv
        JOIN clientes c ON c.id = cv.cliente_id
        LEFT JOIN orcamentos o ON o.id = cv.orcamento_id
        WHERE cv.empresa_id = ?
        ORDER BY ultima_data DESC");
    $stmt->execute([$uid]);
} else {
    header('Location: ../index.php'); exit;
}

$conversas = $stmt->fetchAll();
$total_nao_lidas = array_sum(array_column($conversas, 'nao_lidas'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Mensagens — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3);}
    .dash-nav .inner{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px;}
    .nav-items{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .nav-items a{color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none;}
    .nav-items a:hover,.nav-items a.active{color:#fff;background:rgba(201,168,76,.18);}

    .chat-list-wrap{max-width:720px;margin:0 auto;}
    .conv-item{display:flex;align-items:center;gap:14px;background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;margin-bottom:10px;text-decoration:none;color:var(--text);transition:all var(--transition);cursor:pointer;}
    .conv-item:hover{border-color:var(--gold);box-shadow:var(--shadow);transform:translateY(-2px);}
    .conv-item.unread{border-left:4px solid var(--gold);}
    .conv-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-soft));display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;color:var(--gold);flex-shrink:0;font-family:'Playfair Display',serif;}
    .conv-body{flex:1;min-width:0;}
    .conv-name{font-weight:600;font-size:15px;margin-bottom:2px;}
    .conv-preview{font-size:13px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .conv-meta{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;}
    .conv-time{font-size:11px;color:var(--text-muted);}
    .badge-unread{background:var(--gold);color:var(--navy);border-radius:100px;font-size:11px;font-weight:700;padding:2px 8px;min-width:20px;text-align:center;}
    .conv-orc{font-size:11px;color:var(--gold);margin-top:2px;}

    .empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
    .empty-state .icon{font-size:52px;display:block;margin-bottom:14px;}
    .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;}
    .page-header h1{font-size:24px;}
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <?php if ($is_cliente): ?>
        <a href="../dashboard_cliente.php">Início</a>
        <a href="../clientes/empresas.php">Empresas</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
        <a href="../avaliacoes/index.php">Avaliações</a>
        <a href="index.php" class="active">💬 Mensagens<?= $total_nao_lidas ? " <span class='badge-unread'>$total_nao_lidas</span>" : '' ?></a>
      <?php else: ?>
        <a href="../dashboard_empresa.php">Início</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
        <a href="../avaliacoes/index.php">Avaliações</a>
        <a href="index.php" class="active">💬 Mensagens<?= $total_nao_lidas ? " <span class='badge-unread'>$total_nao_lidas</span>" : '' ?></a>
      <?php endif; ?>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="chat-list-wrap">
    <div class="page-header">
      <h1>💬 Mensagens</h1>
      <?php if ($is_cliente): ?>
        <a href="../clientes/empresas.php" class="btn btn-primary btn-sm">+ Nova Conversa</a>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

    <?php if (empty($conversas)): ?>
    <div class="empty-state">
      <span class="icon">💬</span>
      <p style="font-size:16px;margin-bottom:8px;">Nenhuma conversa ainda.</p>
      <?php if ($is_cliente): ?>
        <p style="font-size:13px;margin-bottom:18px;">Visite a página de uma empresa para iniciar uma conversa.</p>
        <a href="../clientes/empresas.php" class="btn btn-primary">Explorar Empresas</a>
      <?php else: ?>
        <p style="font-size:13px;">Os clientes poderão iniciar conversas através da página da sua empresa.</p>
      <?php endif; ?>
    </div>
    <?php else: ?>
      <?php foreach ($conversas as $cv): ?>
      <a href="conversa.php?id=<?=$cv['id']?>" class="conv-item <?= $cv['nao_lidas'] > 0 ? 'unread' : '' ?>">
        <div class="conv-avatar"><?= strtoupper(substr($cv['outro_nome'], 0, 1)) ?></div>
        <div class="conv-body">
          <div class="conv-name"><?= htmlspecialchars($cv['outro_nome']) ?></div>
          <div class="conv-preview"><?= $cv['ultima_msg'] ? htmlspecialchars(mb_substr($cv['ultima_msg'], 0, 80)) : 'Nenhuma mensagem ainda.' ?></div>
          <?php if ($cv['orc_id']): ?>
            <div class="conv-orc">📄 Orçamento #<?=$cv['orc_id']?></div>
          <?php endif; ?>
        </div>
        <div class="conv-meta">
          <span class="conv-time"><?= $cv['ultima_data'] ? formatDate($cv['ultima_data'], 'd/m H:i') : '' ?></span>
          <?php if ($cv['nao_lidas'] > 0): ?>
            <span class="badge-unread"><?=$cv['nao_lidas']?></span>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
