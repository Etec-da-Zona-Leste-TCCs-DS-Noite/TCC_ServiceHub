<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;
$catFiltro = trim($_GET['categoria'] ?? '');

$where  = $catFiltro ? "WHERE categoria = ?" : "WHERE 1=1";
$params = $catFiltro ? [$catFiltro] : [];

$total      = $pdo->prepare("SELECT COUNT(*) FROM servicos $where");
$total->execute($params);
$total      = $total->fetchColumn();
$totalPages = (int)ceil($total / $limit);

$sql = "SELECT * FROM servicos $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k + 1, $v);
$stmt->bindValue(count($params) + 1, (int)$limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$servicos = $stmt->fetchAll();

$categorias = $pdo->query("SELECT DISTINCT categoria FROM servicos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Serviços — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
</head>
<body>
<header class="main-header">
  <div class="header-content">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><p>Gestão de Serviços &amp; Orçamentos</p></div>
    <nav class="main-nav"><ul>
      <li><a href="../index.php">Início</a></li>
      <li><a href="index.php" class="active">Serviços</a></li>
      <li><a href="../clientes/index.php">Clientes</a></li>
      <li><a href="../orcamentos/index.php">Orçamentos</a></li>
      <li><a href="../relatorios/index.php">Relatórios</a></li>
    </ul></nav>
  </div>
</header>

<div class="container">
  <div class="page-title-row">
    <h1>Serviços</h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="create.php" class="btn btn-primary">+ Novo Serviço</a>
      <a href="../orcamentos/index.php" class="btn btn-ghost">Orçamentos</a>
    </div>
  </div>

  <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

  <?php if (!empty($categorias)): ?>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;align-items:center;">
    <span style="font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Filtrar:</span>
    <a href="index.php" class="btn btn-sm <?= $catFiltro==='' ? 'btn-primary' : 'btn-ghost' ?>">Todos</a>
    <?php foreach ($categorias as $cat): ?>
    <a href="?categoria=<?= urlencode($cat) ?>" class="btn btn-sm <?= $catFiltro===$cat ? 'btn-primary' : 'btn-ghost' ?>"><?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>#</th><th>Nome</th><th>Categoria</th><th>Valor</th><th>Duração</th><th>Status</th><th>Ações</th>
      </tr></thead>
      <tbody>
        <?php foreach ($servicos as $s): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:12px;">#<?= $s['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($s['nome']) ?></strong>
            <?php if ($s['descricao']): ?>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars(mb_substr($s['descricao'],0,60)) ?>…</div>
            <?php endif; ?>
          </td>
          <td><?= $s['categoria'] ? "<span class='badge badge-primary'>".htmlspecialchars($s['categoria'])."</span>" : '<span style="color:var(--text-muted)">—</span>' ?></td>
          <td><strong style="color:var(--teal);"><?= formatMoney($s['valor']) ?></strong></td>
          <td><?= $s['duracao_estimada'] ? $s['duracao_estimada'].'h' : '—' ?></td>
          <td><?= $s['status'] ? "<span class='badge badge-aprovado'>Ativo</span>" : "<span class='badge badge-rejeitado'>Inativo</span>" ?></td>
          <td>
            <div style="display:flex;gap:6px;">
              <a href="edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
              <a href="delete.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este serviço?')">Excluir</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($servicos)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <span class="empty-icon">📋</span>
            <h3>Nenhum serviço cadastrado</h3>
            <p>Adicione o primeiro serviço para começar.</p>
            <a href="create.php" class="btn btn-primary">+ Novo Serviço</a>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <ul class="pagination">
    <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <li class="<?= $i==$page?'active':'' ?>">
      <?= $i==$page ? "<span>$i</span>" : "<a href='?page=$i".($catFiltro?"&categoria=".urlencode($catFiltro):"")."'>$i</a>" ?>
    </li>
    <?php endfor; ?>
  </ul>
  <?php endif; ?>
</div>
</body>
</html>
