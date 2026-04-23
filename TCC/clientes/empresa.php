<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

verificarLogin();

if (!isCliente()) {
    header('Location: ../index.php');
    exit;
}

$empresa_id = (int)($_GET['id'] ?? 0);
if (!$empresa_id) { header('Location: empresas.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? AND status = 1");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch();
if (!$empresa) { header('Location: empresas.php?msg='.urlencode('Empresa não encontrada').'&type=error'); exit; }

$catFiltro = trim($_GET['categoria'] ?? '');

$sqlSvc = "SELECT * FROM servicos WHERE empresa_id = ? AND status = 1";
$paramsSvc = [$empresa_id];
if ($catFiltro !== '') {
    $sqlSvc .= " AND categoria = ?";
    $paramsSvc[] = $catFiltro;
}
$sqlSvc .= " ORDER BY nome";
$stmtSvc = $pdo->prepare($sqlSvc);
$stmtSvc->execute($paramsSvc);
$servicos = $stmtSvc->fetchAll();

$categorias = $pdo->prepare(
    "SELECT DISTINCT categoria FROM servicos WHERE empresa_id = ? AND categoria IS NOT NULL AND categoria != '' AND status = 1 ORDER BY categoria"
);
$categorias->execute([$empresa_id]);
$categorias = $categorias->fetchAll(PDO::FETCH_COLUMN);

$cid = $_SESSION['cliente_id'];
$orcEmpresaStmt = $pdo->prepare("SELECT COUNT(*) FROM orcamentos WHERE cliente_id = ? AND empresa_id = ?");
$orcEmpresaStmt->execute([$cid, $empresa_id]);
$totalOrcEmpresa = $orcEmpresaStmt->fetchColumn();

// Avaliações da empresa
$avalDados = mediaAvaliacoes($pdo, $empresa_id);
$avalStmt = $pdo->prepare("
    SELECT a.*, c.nome AS cliente_nome, s.nome AS servico_nome
    FROM avaliacoes a
    JOIN clientes c ON c.id = a.cliente_id
    JOIN orcamentos o ON o.id = a.orcamento_id
    LEFT JOIN servicos s ON s.id = o.servico_id
    WHERE a.empresa_id = ?
    ORDER BY a.created_at DESC LIMIT 6");
$avalStmt->execute([$empresa_id]);
$avaliacoes = $avalStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($empresa['nome_empresa']) ?> — ServiceHub</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            padding: 15px 0;
            position: sticky; top: 0; z-index: 100;
        }
        .navbar .inner {
            max-width: 1280px; margin: 0 auto; padding: 0 20px;
            display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
        }
        .empresa-hero {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            color: white;
            padding: 40px 0;
        }
        .empresa-hero .inner { max-width: 1280px; margin: 0 auto; padding: 0 20px; }
        .empresa-hero h1 { font-size: 28px; margin-bottom: 6px; }
        .empresa-hero p { color: rgba(255,255,255,.75); font-size: 15px; }
        .empresa-meta { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 16px; }
        .empresa-meta span { font-size: 13px; color: rgba(255,255,255,.8); }
        .empresa-meta i { color: #d4af37; margin-right: 5px; }
        .stat-pill {
            background: rgba(212,175,55,.15);
            border: 1px solid rgba(212,175,55,.3);
            color: #d4af37;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .main-content { max-width: 1280px; margin: 0 auto; padding: 30px 20px; }
        .categorias-filtro {
            display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 25px;
        }
        .categoria-btn {
            padding: 6px 15px; background: #e9ecef; border-radius: 20px;
            text-decoration: none; color: #495057; font-size: 13px;
            transition: all 0.2s;
        }
        .categoria-btn:hover, .categoria-btn.active {
            background: #d4af37; color: #0a2b3e; font-weight: 600;
        }
        .servicos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .servico-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
            transition: all 0.3s;
            display: flex; flex-direction: column;
        }
        .servico-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            border-color: #d4af37;
        }
        .servico-card-header {
            background: linear-gradient(135deg, #f8fafc, #eef2f7);
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .servico-card-header .categoria-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1a4a6f;
            background: rgba(26,74,111,.1);
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
        }
        .servico-card-header h3 { font-size: 16px; color: #1a2d42; margin: 0; }
        .servico-card-body {
            padding: 16px 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .servico-desc { color: #666; font-size: 14px; line-height: 1.5; flex: 1; margin-bottom: 14px; }
        .servico-info { font-size: 13px; color: #555; margin-bottom: 4px; }
        .servico-info i { color: #d4af37; width: 18px; }
        .servico-preco { font-size: 22px; font-weight: 700; color: #1a4a6f; margin: 12px 0; }
        .servico-preco small { font-size: 13px; font-weight: 400; color: #888; }
        .btn-solicitar {
            background: #d4af37;
            color: #0a2b3e;
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            transition: background 0.2s;
            display: block;
        }
        .btn-solicitar:hover { background: #c4a02e; }
        .empty-state {
            text-align: center; padding: 60px; background: white;
            border-radius: 12px; color: #999; grid-column: 1/-1;
        }
        @media (max-width: 600px) {
            .navbar .inner { flex-direction: column; align-items: flex-start; }
            .empresa-hero h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="inner">
        <h2 style="color:white; margin:0;">Service<span style="color:#d4af37;">Hub</span></h2>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <a href="empresas.php" style="color:rgba(255,255,255,.8);font-size:13px;">
                <i class="fas fa-arrow-left"></i> Todas as Empresas
            </a>
            <a href="../dashboard_cliente.php" style="color:rgba(255,255,255,.8);font-size:13px;">
                <i class="fas fa-home"></i> Início
            </a>
            <a href="../orcamentos/index.php" style="color:rgba(255,255,255,.8);font-size:13px;">
                <i class="fas fa-file-invoice-dollar"></i> Meus Orçamentos
            </a>
            <a href="../logout.php" style="color:rgba(255,255,255,.6);font-size:13px;">Sair</a>
        </div>
    </div>
</nav>

<div class="empresa-hero">
    <div class="inner">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
            <div>
                <h1><i class="fas fa-building" style="color:#d4af37;margin-right:10px;"></i><?= htmlspecialchars($empresa['nome_empresa']) ?></h1>
                <p><?= htmlspecialchars($empresa['descricao'] ?? 'Empresa especializada em serviços de qualidade.') ?></p>
                <div class="empresa-meta">
                    <?php if ($empresa['telefone']): ?><span><i class="fas fa-phone"></i><?= htmlspecialchars($empresa['telefone']) ?></span><?php endif; ?>
                    <?php if ($empresa['endereco']): ?><span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($empresa['endereco']) ?></span><?php endif; ?>
                    <?php if ($empresa['site']): ?><span><i class="fas fa-globe"></i><a href="<?= htmlspecialchars($empresa['site']) ?>" target="_blank" style="color:rgba(255,255,255,.8);"><?= htmlspecialchars($empresa['site']) ?></a></span><?php endif; ?>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                <span class="stat-pill"><?= count($servicos) ?> serviço(s) disponível(is)</span>
                <?php if ($totalOrcEmpresa > 0): ?>
                <span class="stat-pill" style="color:#6ee7b7;border-color:rgba(110,231,183,.3);">
                    <?= $totalOrcEmpresa ?> orçamento(s) solicitado(s)
                </span>
                <?php endif; ?>
                <a href="../chat/iniciar.php?empresa_id=<?=$empresa_id?>"
                   style="display:inline-flex;align-items:center;gap:6px;background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.4);color:var(--gold-lt);border-radius:8px;padding:8px 16px;font-size:13px;font-weight:600;text-decoration:none;transition:all .2s;"
                   onmouseover="this.style.background='rgba(201,168,76,.3)'"
                   onmouseout="this.style.background='rgba(201,168,76,.15)'">
                  💬 Enviar Mensagem
                </a>
            </div>
        </div>
    </div>
</div>

<div class="main-content">
    <?php if (isset($_GET['msg'])): echo showMessage(htmlspecialchars(urldecode($_GET['msg'])), $_GET['type'] ?? 'success'); endif; ?>

    <?php if (!empty($categorias)): ?>
    <div class="categorias-filtro">
        <a href="empresa.php?id=<?= $empresa_id ?>" class="categoria-btn <?= $catFiltro === '' ? 'active' : '' ?>">Todos</a>
        <?php foreach ($categorias as $cat): ?>
            <a href="empresa.php?id=<?= $empresa_id ?>&categoria=<?= urlencode($cat) ?>"
               class="categoria-btn <?= $catFiltro === $cat ? 'active' : '' ?>">
                <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($servicos)): ?>
    <div class="empty-state">
        <i class="fas fa-briefcase" style="font-size:48px;display:block;margin-bottom:15px;color:#ccc;"></i>
        <h3>Nenhum serviço encontrado</h3>
        <p><?= $catFiltro ? 'Tente outra categoria.' : 'Esta empresa ainda não cadastrou serviços.' ?></p>
        <a href="empresas.php" style="color:#1a4a6f;">← Ver outras empresas</a>
    </div>
    <?php else: ?>
    <div class="servicos-grid">
        <?php foreach ($servicos as $s): ?>
        <div class="servico-card">
            <div class="servico-card-header">
                <?php if ($s['categoria']): ?>
                <span class="categoria-tag"><?= htmlspecialchars($s['categoria']) ?></span>
                <?php endif; ?>
                <h3><?= htmlspecialchars($s['nome']) ?></h3>
            </div>
            <div class="servico-card-body">
                <p class="servico-desc"><?= htmlspecialchars($s['descricao'] ?? 'Sem descrição disponível.') ?></p>
                <?php if ($s['duracao_estimada']): ?>
                <p class="servico-info"><i class="fas fa-clock"></i> Duração estimada: <?= $s['duracao_estimada'] ?>h</p>
                <?php endif; ?>
                <div class="servico-preco">
                    <?= formatMoney($s['valor']) ?>
                    <small>/ serviço</small>
                </div>
                <a href="../orcamentos/create.php?servico_id=<?= $s['id'] ?>&empresa_id=<?= $empresa_id ?>"
                   class="btn-solicitar">
                    <i class="fas fa-file-invoice-dollar"></i> Solicitar Orçamento
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ══ SEÇÃO DE AVALIAÇÕES ══ -->
<div class="container" style="padding-top:0;">
  <div style="border-top:1px solid var(--border);padding-top:36px;margin-bottom:48px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
      <h2 style="border-left:3px solid var(--gold);padding-left:12px;font-size:20px;">⭐ Avaliações dos Clientes</h2>
      <?php if ($avalDados['total'] > 0): ?>
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:28px;font-weight:700;color:var(--gold);font-family:'Playfair Display',serif;"><?= number_format($avalDados['media'],1,',','') ?></span>
          <?= starRating($avalDados['media']) ?>
          <span style="font-size:13px;color:var(--text-muted);">(<?=$avalDados['total']?> avaliação<?= $avalDados['total']!=1?'ões':'' ?>)</span>
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($avaliacoes)): ?>
      <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
        <span style="font-size:40px;display:block;margin-bottom:10px;">💬</span>
        <p>Esta empresa ainda não possui avaliações.</p>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
        <?php foreach ($avaliacoes as $av): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:18px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
            <?= starRating($av['nota']) ?>
            <span style="font-size:12px;color:var(--text-muted);"><?= formatDate($av['created_at'],'d/m/Y') ?></span>
          </div>
          <?php if ($av['titulo']): ?>
            <div style="font-weight:600;font-size:14px;margin-bottom:4px;"><?= htmlspecialchars($av['titulo']) ?></div>
          <?php endif; ?>
          <?php if ($av['comentario']): ?>
            <p style="font-size:13px;color:var(--text-muted);line-height:1.6;margin-bottom:8px;">"<?= htmlspecialchars(mb_substr($av['comentario'],0,200)).(strlen($av['comentario'])>200?'…':'') ?>"</p>
          <?php endif; ?>
          <div style="font-size:12px;color:var(--text-muted);">
            Por <strong><?= htmlspecialchars($av['cliente_nome']) ?></strong>
            <?= $av['servico_nome'] ? '· '.$av['servico_nome'] : '' ?>
          </div>
          <?php if ($av['resposta']): ?>
          <div style="background:var(--bg);border-left:3px solid var(--gold);border-radius:0 6px 6px 0;padding:8px 12px;margin-top:10px;">
            <strong style="font-size:11px;color:var(--gold);display:block;margin-bottom:2px;">🏢 Resposta da empresa</strong>
            <p style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(mb_substr($av['resposta'],0,150)).(strlen($av['resposta'])>150?'…':'') ?></p>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<footer style="background:#0a2b3e;color:rgba(255,255,255,.5);text-align:center;padding:20px;margin-top:0;font-size:13px;">
    © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
