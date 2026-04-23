<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_cliente = isCliente();
$is_empresa = isEmpresa();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("
    SELECT o.*, c.nome AS cliente_nome, c.email AS cliente_email,
           c.telefone AS cliente_telefone, c.endereco AS cliente_endereco,
           e.nome_empresa, e.telefone AS empresa_tel, e.email AS empresa_email
    FROM orcamentos o
    LEFT JOIN clientes c ON c.id = o.cliente_id
    LEFT JOIN empresas e ON e.id = o.empresa_id
    WHERE o.id = ?");
$stmt->execute([$id]);
$orc = $stmt->fetch();
if (!$orc) { header('Location: index.php?msg='.urlencode('Orçamento não encontrado').'&type=error'); exit; }

// Verificar permissão de acesso
if ($is_cliente && $orc['cliente_id'] != $_SESSION['cliente_id']) {
    header('Location: index.php?msg='.urlencode('Acesso negado').'&type=error'); exit;
}
if ($is_empresa && $orc['empresa_id'] != $_SESSION['empresa_id']) {
    header('Location: index.php?msg='.urlencode('Acesso negado').'&type=error'); exit;
}

$itens = $pdo->prepare("
    SELECT oi.*, s.nome AS servico_nome, s.descricao AS servico_desc
    FROM orcamento_itens oi JOIN servicos s ON s.id = oi.servico_id
    WHERE oi.orcamento_id = ?");
$itens->execute([$id]);
$itens = $itens->fetchAll();

// Alterar status — empresa pode aprovar/rejeitar, cliente pode cancelar se pendente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $novoStatus = $_POST['status'];
    $allowed    = ['pendente','aprovado','rejeitado','concluido','expirado'];
    if ($is_cliente) $allowed = ['rejeitado']; // cliente só pode rejeitar (cancelar)
    if (in_array($novoStatus, $allowed)) {
        $pdo->prepare("UPDATE orcamentos SET status=? WHERE id=?")->execute([$novoStatus, $id]);
        header('Location: view.php?id='.$id.'&msg='.urlencode('Status atualizado!').'&type=success'); exit;
    }
}

$back_url = $is_cliente ? 'index.php' : ($is_empresa ? 'index.php' : 'index.php');

// Avaliação: verifica se o cliente já avaliou este orçamento
$jaAvaliou = false;
$avaliacao = null;
if ($is_cliente && $orc['status'] === 'concluido') {
    $stmtAv = $pdo->prepare("SELECT * FROM avaliacoes WHERE orcamento_id = ?");
    $stmtAv->execute([$id]);
    $avaliacao = $stmtAv->fetch();
    $jaAvaliou = (bool)$avaliacao;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Orçamento #<?=$orc['id']?> — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none; }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    @media print { .dash-nav, .main-header, .no-print { display:none!important; } body { background:#fff; } }
  </style>
</head>
<body>

<?php if ($is_cliente || $is_empresa): ?>
<nav class="dash-nav no-print">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <?php if ($is_cliente): ?>
        <a href="../dashboard_cliente.php">Início</a>
        <a href="../clientes/empresas.php">Empresas</a>
      <?php else: ?>
        <a href="../dashboard_empresa.php">Início</a>
      <?php endif; ?>
      <a href="index.php">Orçamentos</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>
<?php else: ?>
<header class="main-header no-print">
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
  <div class="no-print page-title-row">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <a href="<?= $back_url ?>" class="btn btn-ghost">← Voltar</a>
      <h1 style="margin:0;">Orçamento #<?=$orc['id']?></h1>
      <?= statusBadge($orc['status']) ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php if (!$is_cliente): ?>
        <a href="edit.php?id=<?=$orc['id']?>" class="btn btn-warning">Editar</a>
      <?php endif; ?>
      <?php if ($is_cliente && $orc['status'] === 'concluido'): ?>
        <?php if ($jaAvaliou): ?>
          <a href="../avaliacoes/index.php" class="btn btn-ghost">⭐ Ver minha avaliação</a>
        <?php else: ?>
          <a href="../avaliacoes/create.php?orcamento_id=<?=$orc['id']?>" class="btn btn-primary">⭐ Avaliar Serviço</a>
        <?php endif; ?>
      <?php endif; ?>
      <button onclick="window.print()" class="btn btn-ghost">🖨 Imprimir</button>
      <?php if ($is_cliente && $orc['empresa_id']): ?>
        <a href="../chat/iniciar.php?empresa_id=<?=$orc['empresa_id']?>&orcamento_id=<?=$orc['id']?>" class="btn btn-ghost">💬 Falar com a Empresa</a>
      <?php elseif ($is_empresa && $orc['cliente_id']): ?>
        <?php
          $chkConv = $pdo->prepare("SELECT id FROM conversas WHERE cliente_id=? AND empresa_id=?");
          $chkConv->execute([$orc['cliente_id'], $orc['empresa_id']]);
          $chkC = $chkConv->fetch();
        ?>
        <?php if ($chkC): ?>
          <a href="../chat/conversa.php?id=<?=$chkC['id']?>" class="btn btn-ghost">💬 Chat com Cliente</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

  <div class="detail-header">
    <div>
      <h2>Orçamento #<?=$orc['id']?></h2>
      <p>Emitido em <?= formatDate($orc['data_orcamento']) ?><?= $orc['data_validade'] ? ' · Válido até '.formatDate($orc['data_validade']) : '' ?></p>
    </div>
    <?php if ($orc['status'] === 'pendente'): ?>
    <form method="post" class="no-print" style="display:flex;gap:8px;align-items:center;">
      <select name="status" class="form-control" style="width:auto;background:#1a2d42;color:#fff;border-color:rgba(255,255,255,.15);">
        <option value="">Alterar status…</option>
        <?php if ($is_empresa || !$is_cliente): ?>
          <option value="aprovado">✅ Aprovar</option>
          <option value="rejeitado">✕ Rejeitar</option>
          <option value="expirado">⌛ Expirar</option>
        <?php endif; ?>
        <?php if ($is_cliente): ?>
          <option value="rejeitado">Cancelar solicitação</option>
        <?php endif; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
    </form>
    <?php elseif ($orc['status'] === 'aprovado' && ($is_empresa || !$is_cliente)): ?>
    <form method="post" class="no-print" style="display:flex;gap:8px;align-items:center;">
      <select name="status" class="form-control" style="width:auto;background:#1a2d42;color:#fff;border-color:rgba(255,255,255,.15);">
        <option value="">Alterar status…</option>
        <option value="concluido">🏁 Concluir</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Aplicar</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="info-grid">
    <div class="info-item">
      <div class="info-label">Cliente</div>
      <div class="info-value"><?= htmlspecialchars($orc['cliente_nome'] ?? '—') ?></div>
    </div>
    <?php if ($orc['cliente_email']): ?>
    <div class="info-item">
      <div class="info-label">E-mail do Cliente</div>
      <div class="info-value"><?= htmlspecialchars($orc['cliente_email']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($orc['cliente_telefone']): ?>
    <div class="info-item">
      <div class="info-label">Telefone do Cliente</div>
      <div class="info-value"><?= htmlspecialchars($orc['cliente_telefone']) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($orc['nome_empresa']): ?>
    <div class="info-item">
      <div class="info-label">Empresa Prestadora</div>
      <div class="info-value"><?= htmlspecialchars($orc['nome_empresa']) ?></div>
    </div>
    <?php endif; ?>
    <div class="info-item">
      <div class="info-label">Status</div>
      <div class="info-value"><?= statusBadge($orc['status']) ?></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:20px;">
    <div class="card-header"><h3>Serviços Solicitados</h3></div>
    <div class="table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table>
        <thead><tr><th>Serviço</th><th>Descrição</th><th>Qtd</th><th>Valor Unit.</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($itens as $it): ?>
          <tr>
            <td><strong><?= htmlspecialchars($it['servico_nome']) ?></strong></td>
            <td style="color:var(--text-muted);font-size:13px;"><?= $it['servico_desc'] ? htmlspecialchars(mb_substr($it['servico_desc'],0,60)).'…' : '—' ?></td>
            <td><?= $it['quantidade'] ?></td>
            <td><?= formatMoney($it['valor_unitario']) ?></td>
            <td><strong><?= formatMoney($it['subtotal']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($itens)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">Nenhum item registrado.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer">
      <div class="total-bar">
        <span class="total-label">Total do Orçamento</span>
        <span class="total-value"><?= formatMoney($orc['valor_total']) ?></span>
      </div>
    </div>
  </div>

  <?php if ($orc['observacoes']): ?>
  <div class="card">
    <div class="card-header"><h3>Observações</h3></div>
    <div class="card-body">
      <p style="white-space:pre-line;font-size:14px;"><?= htmlspecialchars($orc['observacoes']) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <?php
  // Bloco de avaliação — exibe se concluído
  if ($orc['status'] === 'concluido'):
      $stmtAv2 = $pdo->prepare("
          SELECT a.*, c.nome AS cliente_nome
          FROM avaliacoes a JOIN clientes c ON c.id = a.cliente_id
          WHERE a.orcamento_id = ?");
      $stmtAv2->execute([$id]);
      $avBlock = $stmtAv2->fetch();
  ?>
  <div class="card no-print" style="margin-top:20px;border-top:2px solid var(--gold-dim);">
    <div class="card-header" style="display:flex;align-items:center;gap:10px;">
      <span style="font-size:20px;">⭐</span>
      <h3 style="margin:0;">Avaliação do Serviço</h3>
    </div>
    <div class="card-body" style="padding:20px;">
      <?php if ($avBlock): ?>
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
          <div style="flex:1;">
            <?php
            $stars = '';
            for ($i=1; $i<=5; $i++) $stars .= $i<=$avBlock['nota'] ? '<span style="color:#c9a84c;font-size:22px;">★</span>' : '<span style="color:#d0d8e0;font-size:22px;">☆</span>';
            echo $stars;
            ?>
            <?php if ($avBlock['titulo']): ?>
              <div style="font-weight:600;font-size:15px;margin-top:6px;"><?= htmlspecialchars($avBlock['titulo']) ?></div>
            <?php endif; ?>
            <?php if ($avBlock['comentario']): ?>
              <p style="font-size:14px;color:var(--text-muted);margin-top:6px;line-height:1.7;">"<?= nl2br(htmlspecialchars($avBlock['comentario'])) ?>"</p>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--text-muted);margin-top:8px;">
              Por <strong><?= htmlspecialchars($avBlock['cliente_nome']) ?></strong> em <?= formatDate($avBlock['created_at'],'d/m/Y') ?>
            </div>
            <?php if ($avBlock['resposta']): ?>
              <div style="background:var(--bg);border-left:3px solid var(--gold);border-radius:0 6px 6px 0;padding:10px 14px;margin-top:12px;">
                <strong style="font-size:12px;color:var(--gold);display:block;margin-bottom:4px;">🏢 Resposta da empresa</strong>
                <p style="font-size:13px;color:var(--text-muted);"><?= nl2br(htmlspecialchars($avBlock['resposta'])) ?></p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif ($is_cliente): ?>
        <div style="text-align:center;padding:20px;">
          <p style="color:var(--text-muted);margin-bottom:14px;">Este serviço ainda não foi avaliado. Compartilhe sua experiência!</p>
          <a href="../avaliacoes/create.php?orcamento_id=<?=$id?>" class="btn btn-primary">⭐ Avaliar agora</a>
        </div>
      <?php else: ?>
        <p style="color:var(--text-muted);font-size:14px;">Nenhuma avaliação registrada para este orçamento.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
