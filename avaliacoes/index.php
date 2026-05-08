<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_empresa = isEmpresa();
$is_cliente = isCliente();

// Empresa vê suas avaliações; cliente vê as avaliações que deu
if ($is_empresa) {
    $eid = $_SESSION['empresa_id'];

    // Resposta da empresa a uma avaliação
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta'], $_POST['avaliacao_id'])) {
        $aid = (int)$_POST['avaliacao_id'];
        $resp = trim($_POST['resposta']);
        $pdo->prepare("UPDATE avaliacoes SET resposta=? WHERE id=? AND empresa_id=?")
            ->execute([$resp ?: null, $aid, $eid]);
        header('Location: index.php?msg='.urlencode('Resposta salva!').'&type=success'); exit;
    }

    $dados = mediaAvaliacoes($pdo, $eid);
    $stmt  = $pdo->prepare("
        SELECT a.*, c.nome AS cliente_nome, o.id AS orc_id, s.nome AS servico_nome
        FROM avaliacoes a
        JOIN clientes c ON c.id = a.cliente_id
        JOIN orcamentos o ON o.id = a.orcamento_id
        LEFT JOIN servicos s ON s.id = o.servico_id
        WHERE a.empresa_id = ?
        ORDER BY a.created_at DESC");
    $stmt->execute([$eid]);
    $avaliacoes = $stmt->fetchAll();

} elseif ($is_cliente) {
    $cid  = $_SESSION['cliente_id'];
    $stmt = $pdo->prepare("
        SELECT a.*, e.nome_empresa, o.id AS orc_id, s.nome AS servico_nome
        FROM avaliacoes a
        JOIN empresas e ON e.id = a.empresa_id
        JOIN orcamentos o ON o.id = a.orcamento_id
        LEFT JOIN servicos s ON s.id = o.servico_id
        WHERE a.cliente_id = ?
        ORDER BY a.created_at DESC");
    $stmt->execute([$cid]);
    $avaliacoes = $stmt->fetchAll();
    $dados = null;
} else {
    header('Location: ../index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Avaliações — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3);}
    .dash-nav .inner{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px;}
    .nav-items{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .nav-items a{color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none;}
    .nav-items a:hover{color:#fff;background:rgba(201,168,76,.18);}

    .rating-hero{background:linear-gradient(135deg,var(--navy),var(--navy-soft));border-radius:var(--radius);padding:28px 32px;margin-bottom:28px;display:flex;gap:32px;align-items:center;flex-wrap:wrap;border:1px solid rgba(201,168,76,.15);}
    .rating-big{font-size:56px;font-weight:700;color:var(--gold);line-height:1;font-family:'Playfair Display',serif;}
    .rating-info{color:#fff;}
    .rating-info .stars-lg{font-size:26px;display:block;margin-bottom:4px;}
    .rating-info small{font-size:13px;color:var(--slate);}

    .bar-row{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
    .bar-row span{font-size:12px;color:var(--slate-lt);width:20px;text-align:right;}
    .bar-wrap{flex:1;background:rgba(255,255,255,.1);border-radius:100px;height:8px;overflow:hidden;}
    .bar-fill{height:100%;background:var(--gold);border-radius:100px;transition:width .4s;}
    .bar-count{font-size:11px;color:var(--slate);width:24px;}

    .rev-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:16px;}
    .rev-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px;}
    .rev-meta{font-size:12px;color:var(--text-muted);margin-top:4px;}
    .rev-titulo{font-weight:600;font-size:15px;margin-bottom:4px;}
    .rev-comentario{font-size:14px;color:var(--text-muted);line-height:1.7;}
    .resposta-box{background:var(--bg);border-left:3px solid var(--gold);border-radius:0 var(--radius-sm) var(--radius-sm) 0;padding:12px 16px;margin-top:14px;}
    .resposta-box strong{font-size:12px;color:var(--gold);display:block;margin-bottom:4px;}
    .resposta-box p{font-size:13px;color:var(--text-muted);}
    .resposta-form{margin-top:12px;}
    .resposta-form textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical;min-height:70px;}
    .resposta-form textarea:focus{outline:none;border-color:var(--gold);}
    .empty-state{text-align:center;padding:60px 20px;color:var(--text-muted);}
    .empty-state .icon{font-size:48px;display:block;margin-bottom:12px;}
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <?php if ($is_empresa): ?>
        <a href="../dashboard_empresa.php">Início</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
        <a href="index.php" style="color:#fff;background:rgba(201,168,76,.18);">Avaliações</a>
      <?php else: ?>
        <a href="../dashboard_cliente.php">Início</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
        <a href="index.php" style="color:#fff;background:rgba(201,168,76,.18);">Minhas Avaliações</a>
      <?php endif; ?>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-title-row">
    <h1><?= $is_empresa ? 'Avaliações Recebidas' : 'Minhas Avaliações' ?></h1>
    <span style="color:var(--text-muted);font-size:14px;"><?= count($avaliacoes) ?> avaliação(ões)</span>
  </div>

  <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

  <?php if ($is_empresa && $dados && $dados['total'] > 0): ?>
  <div class="rating-hero">
    <div>
      <div class="rating-big"><?= number_format($dados['media'], 1, ',', '') ?></div>
    </div>
    <div class="rating-info">
      <span class="stars-lg"><?= starRating($dados['media']) ?></span>
      <div style="font-size:16px;font-weight:600;margin-bottom:2px;">Média geral</div>
      <small><?= $dados['total'] ?> avaliação(ões) recebida(s)</small>
    </div>
    <?php
      // Distribuição por nota
      $dist = [];
      for ($i = 1; $i <= 5; $i++) {
          $s = $pdo->prepare("SELECT COUNT(*) FROM avaliacoes WHERE empresa_id=? AND nota=?");
          $s->execute([$eid, $i]);
          $dist[$i] = (int)$s->fetchColumn();
      }
    ?>
    <div style="flex:1;min-width:200px;">
      <?php for ($i = 5; $i >= 1; $i--): ?>
      <div class="bar-row">
        <span><?=$i?>★</span>
        <div class="bar-wrap">
          <div class="bar-fill" style="width:<?= $dados['total'] ? round($dist[$i]/$dados['total']*100) : 0 ?>%"></div>
        </div>
        <span class="bar-count"><?=$dist[$i]?></span>
      </div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($avaliacoes)): ?>
  <div class="empty-state">
    <span class="icon">⭐</span>
    <p><?= $is_empresa ? 'Nenhuma avaliação recebida ainda.' : 'Você ainda não avaliou nenhum serviço.' ?></p>
  </div>
  <?php else: ?>
    <?php foreach ($avaliacoes as $av): ?>
    <div class="rev-card">
      <div class="rev-head">
        <div>
          <?= starRating($av['nota']) ?>
          <?php if ($av['titulo']): ?>
            <div class="rev-titulo"><?= htmlspecialchars($av['titulo']) ?></div>
          <?php endif; ?>
          <div class="rev-meta">
            <?php if ($is_empresa): ?>
              Por <strong><?= htmlspecialchars($av['cliente_nome']) ?></strong>
            <?php else: ?>
              Para <strong><?= htmlspecialchars($av['nome_empresa']) ?></strong>
            <?php endif; ?>
            · Serviço: <?= htmlspecialchars($av['servico_nome'] ?? '—') ?>
            · <a href="../orcamentos/view.php?id=<?=$av['orc_id']?>">Orçamento #<?=$av['orc_id']?></a>
          </div>
        </div>
        <span style="font-size:12px;color:var(--text-muted);"><?= formatDate($av['created_at'],'d/m/Y') ?></span>
      </div>

      <?php if ($av['comentario']): ?>
        <div class="rev-comentario">"<?= nl2br(htmlspecialchars($av['comentario'])) ?>"</div>
      <?php endif; ?>

      <?php if ($av['resposta']): ?>
        <div class="resposta-box">
          <strong>🏢 Resposta da empresa</strong>
          <p><?= nl2br(htmlspecialchars($av['resposta'])) ?></p>
        </div>
      <?php elseif ($is_empresa): ?>
        <div class="resposta-form">
          <form method="post">
            <input type="hidden" name="avaliacao_id" value="<?=$av['id']?>">
            <textarea name="resposta" placeholder="Responder esta avaliação…" maxlength="500"></textarea>
            <div style="text-align:right;margin-top:6px;">
              <button type="submit" class="btn btn-sm btn-primary">Responder</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
