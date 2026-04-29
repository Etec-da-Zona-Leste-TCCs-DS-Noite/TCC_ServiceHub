<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$eid = (int)$_SESSION['empresa_id'];

// ── Atualizar status de um orçamento ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $orc_id    = (int)$_POST['orc_id'];
    $novoStatus = $_POST['novo_status'] ?? '';
    $allowed    = ['aprovado', 'concluido', 'rejeitado', 'pendente'];
    if (in_array($novoStatus, $allowed)) {
        $pdo->prepare("UPDATE orcamentos SET status=? WHERE id=? AND empresa_id=?")
            ->execute([$novoStatus, $orc_id, $eid]);
    }
    header('Location: meus_servicos.php?status=' . urlencode($_POST['filtro_atual'] ?? '') . '&msg=Status+atualizado');
    exit;
}

// ── Filtro de status ──────────────────────────────────────
$filtro = $_GET['status'] ?? '';
$statusValidos = ['pendente','aprovado','concluido','rejeitado','expirado'];

$where  = 'WHERE o.empresa_id = ?';
$params = [$eid];
if (in_array($filtro, $statusValidos)) {
    $where   .= ' AND o.status = ?';
    $params[] = $filtro;
}

// ── Busca de texto ────────────────────────────────────────
$busca = trim($_GET['busca'] ?? '');
if ($busca !== '') {
    $where   .= ' AND (c.nome LIKE ? OR s.nome LIKE ?)';
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

// ── Paginação ─────────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*)
             FROM orcamentos o
             JOIN clientes c ON c.id = o.cliente_id
             LEFT JOIN servicos s ON s.id = o.servico_id
             $where";
$stCount = $pdo->prepare($sqlCount);
$stCount->execute($params);
$total      = (int)$stCount->fetchColumn();
$totalPages = (int)ceil($total / $limit);

$sqlList = "SELECT o.*,
                   c.nome      AS cliente_nome,
                   c.telefone  AS cliente_tel,
                   s.nome      AS servico_nome,
                   s.categoria AS servico_cat,
                   a.nota      AS avaliacao_nota,
                   a.comentario AS avaliacao_comentario,
                   a.titulo    AS avaliacao_titulo
            FROM orcamentos o
            JOIN clientes c ON c.id = o.cliente_id
            LEFT JOIN servicos s ON s.id = o.servico_id
            LEFT JOIN avaliacoes a ON a.orcamento_id = o.id
            $where
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?";
$stList = $pdo->prepare($sqlList);
foreach ($params as $i => $v) $stList->bindValue($i + 1, $v);
$stList->bindValue(count($params) + 1, $limit,  PDO::PARAM_INT);
$stList->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stList->execute();
$historico = $stList->fetchAll();

// ── KPIs ──────────────────────────────────────────────────
$kpi = $pdo->prepare("SELECT
    COUNT(*)                                                AS total,
    SUM(status='pendente')                                  AS pendente,
    SUM(status='aprovado')                                  AS aprovado,
    SUM(status='concluido')                                 AS concluido,
    SUM(status='rejeitado')                                 AS rejeitado,
    SUM(CASE WHEN status='concluido' THEN valor_total END)  AS receita
    FROM orcamentos WHERE empresa_id = ?");
$kpi->execute([$eid]);
$k = $kpi->fetch();

$avMedia = $pdo->prepare("SELECT ROUND(AVG(a.nota),1), COUNT(a.id)
    FROM avaliacoes a WHERE a.empresa_id = ?");
$avMedia->execute([$eid]);
[$mediaNote, $totalAval] = $avMedia->fetch(PDO::FETCH_NUM);

// ── Helpers ───────────────────────────────────────────────
$statusCfg = [
    'pendente'  => ['label'=>'Pendente',   'color'=>'#f39c12','bg'=>'#fef9e7','icon'=>'fa-clock'],
    'aprovado'  => ['label'=>'Aprovado',   'color'=>'#2980b9','bg'=>'#eaf4fb','icon'=>'fa-thumbs-up'],
    'concluido' => ['label'=>'Concluído',  'color'=>'#27ae60','bg'=>'#eafaf1','icon'=>'fa-check-circle'],
    'rejeitado' => ['label'=>'Rejeitado',  'color'=>'#e74c3c','bg'=>'#fdedec','icon'=>'fa-times-circle'],
    'expirado'  => ['label'=>'Expirado',   'color'=>'#95a5a6','bg'=>'#f2f3f4','icon'=>'fa-ban'],
];
function badge($st, $cfg) {
    $c = $cfg[$st] ?? ['label'=>$st,'color'=>'#999','bg'=>'#eee','icon'=>'fa-circle'];
    return "<span class='badge' style='color:{$c['color']};background:{$c['bg']};'>
                <i class='fas {$c['icon']}'></i> {$c['label']}
            </span>";
}
function estrelas($n) {
    if (!$n) return '<span style="color:#bbb;font-size:12px;">Sem avaliação</span>';
    $s = '';
    for ($i=1;$i<=5;$i++)
        $s .= "<i class='fas fa-star' style='color:".($i<=$n?'#f39c12':'#ddd')."'></i>";
    return $s;
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Meus Serviços — ServiceHub</title>
<link rel="stylesheet" href="../css/estilo.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
/* ── Reset / base ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; color: #2c3e50; margin: 0; }
a { text-decoration: none; }

/* ── Navbar ─────────────────────────────────────────────── */
.dash-nav {
    background: linear-gradient(135deg,#0a2b3e,#1a4a6f);
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 2px 16px rgba(0,0,0,.3);
}
.dash-nav .inner {
    max-width: 1280px; margin: 0 auto; padding: 0 24px;
    display: flex; align-items: center; justify-content: space-between;
    min-height: 62px; flex-wrap: wrap; gap: 10px;
}
.nav-logo h1 { color: #fff; font-size: 22px; margin: 0; }
.nav-logo h1 span { color: #d4af37; }
.nav-logo small { color: #8aa; font-size: 11px; display: block; margin-top: -2px; }
.nav-links { display: flex; gap: 4px; flex-wrap: wrap; align-items: center; }
.nav-links a {
    color: #c8d6df; font-size: 13px; font-weight: 500;
    padding: 6px 12px; border-radius: 6px; transition: all .2s;
}
.nav-links a:hover, .nav-links a.active { color: #fff; background: rgba(212,175,55,.2); }
.nav-user { display: flex; align-items: center; gap: 10px; }
.avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #d4af37; color: #0a2b3e;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px;
}

/* ── Página ─────────────────────────────────────────────── */
.page-wrap { max-width: 1280px; margin: 0 auto; padding: 28px 20px 60px; }

/* ── Header da página ───────────────────────────────────── */
.page-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    flex-wrap: wrap; gap: 14px; margin-bottom: 28px;
}
.page-header h2 { font-size: 22px; margin: 0 0 4px; }
.page-header p  { color: #7f8c8d; margin: 0; font-size: 13px; }
.btn-catalog {
    background: linear-gradient(135deg,#d4af37,#b8962e);
    color: #fff; padding: 10px 20px; border-radius: 8px;
    font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 8px;
    transition: all .2s; white-space: nowrap;
}
.btn-catalog:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212,175,55,.4); }

/* ── KPI cards ──────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px; margin-bottom: 28px;
}
.kpi-card {
    background: #fff; border-radius: 12px; padding: 18px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
    display: flex; align-items: center; gap: 14px;
    border-left: 4px solid transparent;
}
.kpi-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.kpi-val  { font-size: 24px; font-weight: 700; line-height: 1; }
.kpi-lbl  { font-size: 11px; color: #7f8c8d; margin-top: 3px; font-weight: 500; text-transform: uppercase; letter-spacing: .4px; }
.kpi-card.total    { border-color: #3498db; } .kpi-card.total .kpi-icon    { background:#eaf4fb; color:#3498db; }
.kpi-card.pendente { border-color: #f39c12; } .kpi-card.pendente .kpi-icon { background:#fef9e7; color:#f39c12; }
.kpi-card.aprovado { border-color: #2980b9; } .kpi-card.aprovado .kpi-icon { background:#eaf4fb; color:#2980b9; }
.kpi-card.concluido{ border-color: #27ae60; } .kpi-card.concluido .kpi-icon{ background:#eafaf1; color:#27ae60; }
.kpi-card.avaliacao{ border-color: #e67e22; } .kpi-card.avaliacao .kpi-icon{ background:#fef5ec; color:#e67e22; }
.kpi-card.receita  { border-color: #8e44ad; } .kpi-card.receita .kpi-icon  { background:#f5eef8; color:#8e44ad; }

/* ── Barra de filtros ───────────────────────────────────── */
.filter-bar {
    background: #fff; border-radius: 12px; padding: 16px 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06); margin-bottom: 20px;
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
}
.tab-filters { display: flex; gap: 6px; flex-wrap: wrap; flex: 1; }
.tab-btn {
    padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 2px solid transparent; cursor: pointer; transition: all .2s;
    background: #f4f6f9; color: #555;
    text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
}
.tab-btn:hover  { background: #e8ecef; }
.tab-btn.active { background: #0a2b3e; color: #fff; border-color: #0a2b3e; }
.tab-btn .cnt   { background: rgba(255,255,255,.2); padding: 0 6px; border-radius: 10px; font-size: 11px; }
.tab-btn:not(.active) .cnt { background: #dde3e9; color: #555; }

.search-wrap { display: flex; gap: 8px; }
.search-input {
    padding: 8px 14px; border: 1px solid #ddd; border-radius: 8px;
    font-size: 13px; width: 220px; transition: all .2s;
}
.search-input:focus { border-color: #d4af37; outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,.12); }
.btn-search {
    padding: 8px 16px; background: #1a4a6f; color: #fff;
    border: none; border-radius: 8px; font-size: 13px; cursor: pointer; transition: all .2s;
}
.btn-search:hover { background: #0a2b3e; }

/* ── Toast ──────────────────────────────────────────────── */
.toast {
    background: #27ae60; color: #fff; padding: 12px 20px;
    border-radius: 8px; margin-bottom: 16px; font-size: 13px;
    display: flex; align-items: center; gap: 8px;
}

/* ── Tabela/Cards histórico ─────────────────────────────── */
.historico-wrap { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden; }
.historico-header {
    padding: 16px 20px; border-bottom: 1px solid #eee;
    display: flex; justify-content: space-between; align-items: center;
}
.historico-header h3 { margin: 0; font-size: 15px; }
.count-badge {
    background: #f4f6f9; color: #7f8c8d;
    padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;
}

table { width: 100%; border-collapse: collapse; }
thead th {
    padding: 11px 16px; text-align: left; font-size: 11px;
    font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
    color: #7f8c8d; background: #f8f9fa; border-bottom: 1px solid #eee;
}
tbody tr { transition: background .15s; }
tbody tr:hover { background: #fafbfc; }
tbody td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }

.client-name  { font-weight: 600; color: #2c3e50; }
.client-tel   { font-size: 11px; color: #95a5a6; margin-top: 2px; }
.service-name { font-weight: 500; }
.service-cat  { font-size: 11px; color: #7f8c8d; }
.valor-cell   { font-weight: 700; color: #2c3e50; }
.date-cell    { color: #7f8c8d; white-space: nowrap; }

.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
    white-space: nowrap;
}

/* Avaliação inline */
.stars-inline { display: flex; align-items: center; gap: 4px; }
.stars-inline i { font-size: 13px; }
.stars-inline .nota-num { font-size: 12px; color: #7f8c8d; margin-left: 2px; }

/* Ações */
.actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-sm {
    padding: 5px 10px; border-radius: 6px; font-size: 11px;
    font-weight: 600; cursor: pointer; border: none; transition: all .15s;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
    text-decoration: none;
}
.btn-view    { background: #eaf4fb; color: #2980b9; }
.btn-view:hover { background: #2980b9; color: #fff; }
.btn-concluir { background: #eafaf1; color: #27ae60; }
.btn-concluir:hover { background: #27ae60; color: #fff; }
.btn-rejeitar { background: #fdedec; color: #e74c3c; }
.btn-rejeitar:hover { background: #e74c3c; color: #fff; }
.btn-aprovar  { background: #eaf4fb; color: #2980b9; }
.btn-aprovar:hover { background: #2980b9; color: #fff; }

/* ── Empty state ─────────────────────────────────────────── */
.empty-state {
    padding: 60px 20px; text-align: center; color: #95a5a6;
}
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .4; display: block; }
.empty-state h3 { margin: 0 0 8px; color: #bdc3c7; font-size: 18px; }
.empty-state p  { margin: 0; font-size: 13px; }

/* ── Paginação ───────────────────────────────────────────── */
.paginacao {
    display: flex; justify-content: center; align-items: center;
    gap: 6px; padding: 20px;
}
.paginacao a, .paginacao span {
    padding: 7px 13px; border-radius: 6px; font-size: 13px; font-weight: 600;
    border: 1px solid #ddd; color: #555; transition: all .2s;
}
.paginacao a:hover { background: #1a4a6f; color: #fff; border-color: #1a4a6f; }
.paginacao .active-pg { background: #1a4a6f; color: #fff; border-color: #1a4a6f; }

/* ── Modal de detalhes ───────────────────────────────────── */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.5); z-index: 1000;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
    background: #fff; border-radius: 16px; padding: 28px;
    max-width: 520px; width: 95%; max-height: 85vh; overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.modal-header h3 { margin: 0; font-size: 18px; }
.modal-close {
    background: none; border: none; font-size: 20px; cursor: pointer; color: #95a5a6; padding: 0;
}
.modal-close:hover { color: #e74c3c; }
.modal-row { display: flex; gap: 8px; margin-bottom: 12px; align-items: flex-start; }
.modal-lbl { font-weight: 600; font-size: 12px; text-transform: uppercase; color: #95a5a6; min-width: 110px; padding-top: 2px; }
.modal-val { font-size: 14px; color: #2c3e50; flex: 1; }
.modal-divider { border: none; border-top: 1px solid #eee; margin: 16px 0; }
.avaliacao-box {
    background: #fef9e7; border: 1px solid #f39c12; border-radius: 10px; padding: 14px;
}
.avaliacao-box .av-title { font-weight: 700; margin-bottom: 6px; }
.avaliacao-box .av-comment { font-size: 13px; color: #555; font-style: italic; margin-top: 6px; }

@media (max-width: 900px) {
    .col-hide { display: none; }
}
@media (max-width: 600px) {
    .col-hide-sm { display: none; }
    .page-header { flex-direction: column; }
    .search-input { width: 140px; }
    thead th, tbody td { padding: 10px 10px; }
}
</style>
</head>
<body>

<!-- ── Navbar ─────────────────────────────────────────────── -->
<nav class="dash-nav">
  <div class="inner">
    <div class="nav-logo">
      <h1>Service<span>Hub</span></h1>
      <small>Área da Empresa</small>
    </div>
    <div class="nav-links">
      <a href="../dashboard_empresa.php">Início</a>
      <a href="meus_servicos.php" class="active">Meus Serviços</a>
      <a href="perfil.php">Perfil</a>
      <a href="../orcamentos/index.php">Orçamentos</a>
      <a href="../avaliacoes/index.php">⭐ Avaliações</a>
      <a href="../chat/index.php">💬 Mensagens</a>
    </div>
    <div class="nav-user">
      <div class="avatar"><?= strtoupper(substr($_SESSION['empresa_nome'] ?? 'E', 0, 1)) ?></div>
      <span style="color:#fff;font-size:13px;"><?= htmlspecialchars($_SESSION['empresa_nome'] ?? '') ?></span>
      <a href="../logout.php" style="color:#8aa;font-size:12px;margin-left:4px;">Sair</a>
    </div>
  </div>
</nav>

<div class="page-wrap">

  <!-- ── Toast ───────────────────────────────────────────── -->
  <?php if ($msg): ?>
    <div class="toast"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- ── Cabeçalho da página ─────────────────────────────── -->
  <div class="page-header">
    <div>
      <h2><i class="fas fa-history" style="color:#d4af37"></i> Histórico de Serviços</h2>
      <p>Acompanhe todos os pedidos recebidos, seus status e avaliações dos clientes.</p>
    </div>
    <a href="../servicos/index.php" class="btn-catalog">
      <i class="fas fa-cog"></i> Gerenciar Catálogo
    </a>
  </div>

  <!-- ── KPIs ────────────────────────────────────────────── -->
  <div class="kpi-grid">
    <div class="kpi-card total">
      <div class="kpi-icon"><i class="fas fa-list-alt"></i></div>
      <div>
        <div class="kpi-val"><?= (int)$k['total'] ?></div>
        <div class="kpi-lbl">Total de pedidos</div>
      </div>
    </div>
    <div class="kpi-card pendente">
      <div class="kpi-icon"><i class="fas fa-clock"></i></div>
      <div>
        <div class="kpi-val"><?= (int)$k['pendente'] ?></div>
        <div class="kpi-lbl">Pendentes</div>
      </div>
    </div>
    <div class="kpi-card aprovado">
      <div class="kpi-icon"><i class="fas fa-thumbs-up"></i></div>
      <div>
        <div class="kpi-val"><?= (int)$k['aprovado'] ?></div>
        <div class="kpi-lbl">Em andamento</div>
      </div>
    </div>
    <div class="kpi-card concluido">
      <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
      <div>
        <div class="kpi-val"><?= (int)$k['concluido'] ?></div>
        <div class="kpi-lbl">Concluídos</div>
      </div>
    </div>
    <div class="kpi-card avaliacao">
      <div class="kpi-icon"><i class="fas fa-star"></i></div>
      <div>
        <div class="kpi-val"><?= $mediaNote ? number_format($mediaNote,1) : '—' ?></div>
        <div class="kpi-lbl">Média de avaliação</div>
      </div>
    </div>
    <div class="kpi-card receita">
      <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
      <div>
        <div class="kpi-val" style="font-size:17px;">R$ <?= number_format((float)$k['receita'],0,'.','.') ?></div>
        <div class="kpi-lbl">Receita concluída</div>
      </div>
    </div>
  </div>

  <!-- ── Filtros ──────────────────────────────────────────── -->
  <div class="filter-bar">
    <div class="tab-filters">
      <?php
      $kpiPorStatus = [
          ''          => (int)$k['total'],
          'pendente'  => (int)$k['pendente'],
          'aprovado'  => (int)$k['aprovado'],
          'concluido' => (int)$k['concluido'],
          'rejeitado' => (int)$k['rejeitado'],
      ];
      $tabLabels = [
          ''          => ['Todos',       'fa-th-list'],
          'pendente'  => ['Pendentes',   'fa-clock'],
          'aprovado'  => ['Em andamento','fa-thumbs-up'],
          'concluido' => ['Concluídos',  'fa-check-circle'],
          'rejeitado' => ['Rejeitados',  'fa-times-circle'],
      ];
      foreach ($tabLabels as $val => [$label, $icon]):
          $active = ($filtro === $val) ? 'active' : '';
          $href = 'meus_servicos.php?status=' . urlencode($val) . ($busca ? '&busca='.urlencode($busca) : '');
      ?>
      <a href="<?= $href ?>" class="tab-btn <?= $active ?>">
        <i class="fas <?= $icon ?>"></i> <?= $label ?>
        <span class="cnt"><?= $kpiPorStatus[$val] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <form method="get" class="search-wrap">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filtro) ?>">
      <input type="text" name="busca" class="search-input"
             placeholder="Buscar cliente ou serviço..." value="<?= htmlspecialchars($busca) ?>">
      <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
    </form>
  </div>

  <!-- ── Tabela de histórico ──────────────────────────────── -->
  <div class="historico-wrap">
    <div class="historico-header">
      <h3><i class="fas fa-table" style="color:#d4af37;margin-right:8px;"></i>Pedidos</h3>
      <span class="count-badge"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($historico)): ?>
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>Nenhum pedido encontrado</h3>
        <p>Quando clientes solicitarem seus serviços, eles aparecerão aqui.</p>
      </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Cliente</th>
          <th>Serviço</th>
          <th class="col-hide">Valor</th>
          <th class="col-hide">Data</th>
          <th>Status</th>
          <th>Avaliação</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($historico as $h): ?>
        <tr>
          <td style="color:#aaa;font-size:12px;">#<?= $h['id'] ?></td>

          <td>
            <div class="client-name"><?= htmlspecialchars($h['cliente_nome']) ?></div>
            <?php if ($h['cliente_tel']): ?>
              <div class="client-tel"><i class="fas fa-phone" style="font-size:10px;"></i> <?= htmlspecialchars($h['cliente_tel']) ?></div>
            <?php endif; ?>
          </td>

          <td>
            <div class="service-name"><?= htmlspecialchars($h['servico_nome'] ?? '(removido)') ?></div>
            <?php if ($h['servico_cat']): ?>
              <div class="service-cat"><?= htmlspecialchars($h['servico_cat']) ?></div>
            <?php endif; ?>
          </td>

          <td class="valor-cell col-hide">R$ <?= number_format((float)$h['valor_total'], 2, ',', '.') ?></td>

          <td class="date-cell col-hide">
            <?= date('d/m/Y', strtotime($h['data_orcamento'])) ?>
            <?php if ($h['data_validade']): ?>
              <div style="font-size:11px;color:#bbb;">Venc. <?= date('d/m/Y', strtotime($h['data_validade'])) ?></div>
            <?php endif; ?>
          </td>

          <td><?= badge($h['status'], $statusCfg) ?></td>

          <td>
            <?php if ($h['avaliacao_nota']): ?>
              <div class="stars-inline">
                <?= estrelas($h['avaliacao_nota']) ?>
                <span class="nota-num"><?= $h['avaliacao_nota'] ?>/5</span>
              </div>
              <?php if ($h['avaliacao_titulo']): ?>
                <div style="font-size:11px;color:#7f8c8d;margin-top:2px;font-style:italic;">
                  "<?= htmlspecialchars(mb_strimwidth($h['avaliacao_titulo'], 0, 28, '...')) ?>"
                </div>
              <?php endif; ?>
            <?php else: ?>
              <span style="color:#bbb;font-size:12px;">—</span>
            <?php endif; ?>
          </td>

          <td>
            <div class="actions-cell">
              <!-- Ver detalhes -->
              <button class="btn-sm btn-view"
                      onclick="abrirModal(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">
                <i class="fas fa-eye"></i> Detalhes
              </button>

              <!-- Ações de status -->
              <?php if ($h['status'] === 'pendente'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="orc_id" value="<?= $h['id'] ?>">
                  <input type="hidden" name="novo_status" value="aprovado">
                  <input type="hidden" name="filtro_atual" value="<?= htmlspecialchars($filtro) ?>">
                  <button type="submit" name="atualizar_status" class="btn-sm btn-aprovar"
                          onclick="return confirm('Aprovar este pedido?')">
                    <i class="fas fa-thumbs-up"></i> Aprovar
                  </button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="orc_id" value="<?= $h['id'] ?>">
                  <input type="hidden" name="novo_status" value="rejeitado">
                  <input type="hidden" name="filtro_atual" value="<?= htmlspecialchars($filtro) ?>">
                  <button type="submit" name="atualizar_status" class="btn-sm btn-rejeitar"
                          onclick="return confirm('Rejeitar este pedido?')">
                    <i class="fas fa-times"></i> Rejeitar
                  </button>
                </form>
              <?php elseif ($h['status'] === 'aprovado'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="orc_id" value="<?= $h['id'] ?>">
                  <input type="hidden" name="novo_status" value="concluido">
                  <input type="hidden" name="filtro_atual" value="<?= htmlspecialchars($filtro) ?>">
                  <button type="submit" name="atualizar_status" class="btn-sm btn-concluir"
                          onclick="return confirm('Marcar como concluído?')">
                    <i class="fas fa-check"></i> Concluir
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <!-- ── Paginação ────────────────────────────────────── -->
    <?php if ($totalPages > 1): ?>
    <div class="paginacao">
      <?php if ($page > 1): ?>
        <a href="?status=<?= urlencode($filtro) ?>&busca=<?= urlencode($busca) ?>&page=<?= $page-1 ?>">
          <i class="fas fa-chevron-left"></i>
        </a>
      <?php endif; ?>
      <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <?php if ($p === $page): ?>
          <span class="active-pg"><?= $p ?></span>
        <?php else: ?>
          <a href="?status=<?= urlencode($filtro) ?>&busca=<?= urlencode($busca) ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?status=<?= urlencode($filtro) ?>&busca=<?= urlencode($busca) ?>&page=<?= $page+1 ?>">
          <i class="fas fa-chevron-right"></i>
        </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div><!-- /historico-wrap -->

</div><!-- /page-wrap -->

<!-- ── Modal de detalhes ─────────────────────────────────── -->
<div class="modal-overlay" id="modalOverlay" onclick="if(event.target===this)fecharModal()">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle">Detalhes do Pedido</h3>
      <button class="modal-close" onclick="fecharModal()">✕</button>
    </div>
    <div id="modalBody"></div>
  </div>
</div>

<script>
const statusCfg = {
  pendente:  { label:'Pendente',   color:'#f39c12', icon:'fa-clock'        },
  aprovado:  { label:'Aprovado',   color:'#2980b9', icon:'fa-thumbs-up'    },
  concluido: { label:'Concluído',  color:'#27ae60', icon:'fa-check-circle' },
  rejeitado: { label:'Rejeitado',  color:'#e74c3c', icon:'fa-times-circle' },
  expirado:  { label:'Expirado',   color:'#95a5a6', icon:'fa-ban'          },
};

function estrelas(n) {
  let s = '';
  for (let i = 1; i <= 5; i++)
    s += `<i class="fas fa-star" style="color:${i<=n?'#f39c12':'#ddd'};font-size:16px;"></i>`;
  return s;
}

function abrirModal(d) {
  const st  = statusCfg[d.status] || {label:d.status,color:'#999',icon:'fa-circle'};
  const val = 'R$ ' + parseFloat(d.valor_total).toLocaleString('pt-BR',{minimumFractionDigits:2});
  const dt  = d.data_orcamento
    ? new Date(d.data_orcamento + 'T00:00:00').toLocaleDateString('pt-BR')
    : '—';
  const dtV = d.data_validade
    ? new Date(d.data_validade + 'T00:00:00').toLocaleDateString('pt-BR')
    : '—';

  let avHtml = '<span style="color:#bbb;font-size:13px;">Sem avaliação registrada.</span>';
  if (d.avaliacao_nota) {
    avHtml = `<div class="avaliacao-box">
      <div class="av-title">${estrelas(d.avaliacao_nota)} &nbsp;${d.avaliacao_titulo||''}</div>
      ${d.avaliacao_comentario ? `<div class="av-comment">"${d.avaliacao_comentario}"</div>` : ''}
    </div>`;
  }

  document.getElementById('modalTitle').textContent = 'Pedido #' + d.id;
  document.getElementById('modalBody').innerHTML = `
    <div class="modal-row">
      <span class="modal-lbl">Cliente</span>
      <span class="modal-val"><strong>${d.cliente_nome}</strong>${d.cliente_tel ? '<br><small>📞 '+d.cliente_tel+'</small>' : ''}</span>
    </div>
    <div class="modal-row">
      <span class="modal-lbl">Serviço</span>
      <span class="modal-val">${d.servico_nome||'(removido)'}${d.servico_cat?'<br><small>'+d.servico_cat+'</small>':''}</span>
    </div>
    <div class="modal-row">
      <span class="modal-lbl">Valor</span>
      <span class="modal-val" style="font-weight:700;font-size:16px;">${val}</span>
    </div>
    <div class="modal-row">
      <span class="modal-lbl">Data</span>
      <span class="modal-val">${dt} ${dtV!=='—'?'<small>(Validade: '+dtV+')</small>':''}</span>
    </div>
    <div class="modal-row">
      <span class="modal-lbl">Status</span>
      <span class="modal-val">
        <span style="color:${st.color};font-weight:700;">
          <i class="fas ${st.icon}"></i> ${st.label}
        </span>
      </span>
    </div>
    ${d.observacoes ? `<div class="modal-row"><span class="modal-lbl">Observações</span><span class="modal-val" style="font-style:italic;">"${d.observacoes}"</span></div>` : ''}
    <hr class="modal-divider">
    <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#555;">
      <i class="fas fa-star" style="color:#f39c12;"></i> Avaliação do cliente
    </div>
    ${avHtml}
  `;
  document.getElementById('modalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function fecharModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharModal(); });
</script>
</body>
</html>
