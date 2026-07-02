<?php
require_once 'includes/bootstrap.php';

if (isset($_SESSION['tipo_usuario'])) {
    header('Location: '.($_SESSION['tipo_usuario']==='cliente'?'dashboard_cliente.php':'dashboard_empresa.php'));
    exit;
}

/* ── Categorias reais (com fallback estático se o banco ainda estiver vazio) ── */
$categoriasIcones = [
    'Reformas'            => '🛠️',
    'Limpeza'             => '🧹',
    'Elétrica'            => '💡',
    'Encanamento'         => '🚰',
    'Beleza'              => '💇',
    'Aulas Particulares'  => '📚',
    'Jardinagem'          => '🌿',
    'Tecnologia'          => '💻',
    'Eventos'             => '🎉',
    'Pet Care'            => '🐾',
    'Consultoria'         => '📊',
    'Design'              => '🎨',
];
$categoriasDb = $pdo->query("SELECT categoria, COUNT(*) AS total FROM servicos
                              WHERE status = 1 AND categoria IS NOT NULL AND categoria != ''
                              GROUP BY categoria ORDER BY total DESC LIMIT 6")->fetchAll();
$categorias = [];
foreach ($categoriasDb as $c) {
    $categorias[] = ['nome' => $c['categoria'], 'icone' => $categoriasIcones[$c['categoria']] ?? '⭐'];
}
if (count($categorias) < 6) {
    foreach ($categoriasIcones as $nome => $icone) {
        if (count($categorias) >= 6) break;
        if (!in_array($nome, array_column($categorias, 'nome'))) {
            $categorias[] = ['nome' => $nome, 'icone' => $icone];
        }
    }
}

/* ── Empresas em destaque (melhor avaliadas, com pelo menos 1 serviço ativo) ── */
$destaques = $pdo->query("
    SELECT e.id, e.nome_empresa, e.descricao,
           (SELECT ROUND(AVG(a.nota),1) FROM avaliacoes a WHERE a.empresa_id = e.id) AS media_nota,
           (SELECT COUNT(*) FROM avaliacoes a WHERE a.empresa_id = e.id) AS total_aval,
           (SELECT categoria FROM servicos s WHERE s.empresa_id = e.id AND s.status = 1 LIMIT 1) AS categoria
    FROM empresas e
    WHERE e.status = 1
    ORDER BY media_nota DESC, total_aval DESC, e.created_at DESC
    LIMIT 6
")->fetchAll();

/* ── Depoimentos reais (só exibe se já existirem avaliações no banco) ── */
$depoimentos = $pdo->query("
    SELECT a.titulo, a.comentario, a.nota, c.nome AS cliente_nome, e.nome_empresa
    FROM avaliacoes a
    JOIN clientes c ON c.id = a.cliente_id
    JOIN empresas e ON e.id = a.empresa_id
    WHERE a.comentario IS NOT NULL AND a.comentario != ''
    ORDER BY a.nota DESC, a.created_at DESC
    LIMIT 3
")->fetchAll();

$totalEmpresas = (int)$pdo->query("SELECT COUNT(*) FROM empresas WHERE status = 1")->fetchColumn();
$totalServicos = (int)$pdo->query("SELECT COUNT(*) FROM servicos WHERE status = 1")->fetchColumn();
$totalOrc      = (int)$pdo->query("SELECT COUNT(*) FROM orcamentos WHERE status = 'concluido'")->fetchColumn();
$mediaGeral    = $pdo->query("SELECT ROUND(AVG(nota),1) FROM avaliacoes")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ServiceHub — Encontre profissionais de confiança perto de você</title>
  <meta name="description" content="ServiceHub conecta clientes a prestadores de serviço avaliados: orçamentos, chat e contratação em um só lugar.">
  <link rel="stylesheet" href="css/estilo.css">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0A192F">
  <link rel="apple-touch-icon" href="icons/icon-192.png">
</head>
<body style="background:#fff;">

<!-- ── Navbar pública ─────────────────────────────────────────── -->
<header class="pub-nav">
  <div class="pub-nav-inner">
    <a href="index.php" class="pub-logo">Service<span>Hub</span></a>

    <div class="pub-nav-search">
      <form action="clientes/cadastro.php" method="get">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6C757D" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="q" placeholder="Reforma, limpeza, aulas, eventos...">
        <button type="submit" aria-label="Buscar">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </form>
    </div>

    <nav class="pub-nav-actions">
      <a href="empresas/cadastro.php" class="pub-nav-link">Anuncie sua empresa</a>
      <a href="login.php" class="pub-nav-link">Entrar</a>
      <a href="clientes/cadastro.php" class="pub-nav-cta gold">Criar conta grátis</a>
    </nav>
  </div>
</header>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-inner">
    <span class="hero-eyebrow">★ Marketplace de serviços</span>
    <h1>O profissional certo para o seu próximo <em>projeto</em>.</h1>
    <p class="lead">Compare orçamentos, converse direto com empresas avaliadas por outros clientes e contrate com segurança — tudo em um só lugar.</p>

    <form class="hero-search" action="clientes/cadastro.php" method="get">
      <input type="text" name="q" placeholder="O que você precisa resolver hoje?">
      <button type="submit">Buscar profissionais</button>
    </form>

    <div class="hero-chips">
      <?php foreach (array_slice($categorias, 0, 5) as $c): ?>
        <a href="clientes/cadastro.php"><?= htmlspecialchars($c['icone']) ?> <?= htmlspecialchars($c['nome']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Stats ──────────────────────────────────────────────────── -->
<section class="stats-bar">
  <div class="stats-bar-inner">
    <div>
      <div class="stat-num"><?= $totalEmpresas > 0 ? $totalEmpresas . '+' : 'Novo' ?></div>
      <div class="stat-label">Empresas parceiras</div>
    </div>
    <div>
      <div class="stat-num"><?= $totalServicos > 0 ? $totalServicos . '+' : '—' ?></div>
      <div class="stat-label">Serviços ativos</div>
    </div>
    <div>
      <div class="stat-num"><?= $totalOrc > 0 ? $totalOrc . '+' : '—' ?></div>
      <div class="stat-label">Projetos concluídos</div>
    </div>
    <div>
      <div class="stat-num"><?= $mediaGeral ? $mediaGeral . ' ★' : '—' ?></div>
      <div class="stat-label">Avaliação média</div>
    </div>
  </div>
</section>

<!-- ── Categorias ─────────────────────────────────────────────── -->
<section class="lp-section">
  <div class="lp-section-head">
    <div>
      <span class="lp-eyebrow">Explore</span>
      <h2>Navegue por categoria</h2>
      <p>Do reparo urgente ao projeto planejado — encontre quem resolve.</p>
    </div>
    <a href="clientes/cadastro.php" class="lp-link">Ver todas as categorias →</a>
  </div>
  <div class="cat-grid">
    <?php foreach ($categorias as $c): ?>
      <a class="cat-card" href="clientes/cadastro.php">
        <span class="cat-icon"><?= htmlspecialchars($c['icone']) ?></span>
        <span class="cat-name"><?= htmlspecialchars($c['nome']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── Empresas em destaque ──────────────────────────────────── -->
<section class="lp-section" style="padding-top:0;">
  <div class="lp-section-head">
    <div>
      <span class="lp-eyebrow">Confiança</span>
      <h2>Prestadores em destaque</h2>
      <p>Empresas com as melhores avaliações da nossa comunidade.</p>
    </div>
    <a href="clientes/cadastro.php" class="lp-link">Ver todos →</a>
  </div>

  <?php if ($destaques): ?>
    <div class="provider-grid">
      <?php foreach ($destaques as $d): ?>
        <a class="provider-card" href="clientes/cadastro.php">
          <div class="provider-card-top">
            <div class="provider-avatar"><?= strtoupper(mb_substr($d['nome_empresa'], 0, 1)) ?></div>
          </div>
          <div class="provider-card-body">
            <h3><?= htmlspecialchars($d['nome_empresa']) ?></h3>
            <div class="provider-cat"><?= htmlspecialchars($d['categoria'] ?? 'Serviços gerais') ?></div>
            <div class="provider-rating">
              <?= starRating($d['media_nota'] ?? 0, true) ?>
              <span><?= $d['media_nota'] ? $d['media_nota'] : 'Novo' ?><?= $d['total_aval'] ? ' (' . $d['total_aval'] . ')' : '' ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="testi-card" style="text-align:center;padding:2.5rem;">
      <p style="font-family:var(--font-body);color:var(--text-muted);">
        Ainda não há empresas cadastradas na plataforma. <a href="empresas/cadastro.php" class="lp-link">Seja a primeira</a> a anunciar seus serviços.
      </p>
    </div>
  <?php endif; ?>
</section>

<!-- ── Como funciona ──────────────────────────────────────────── -->
<section class="lp-section" style="background:var(--off-white);max-width:100%;">
  <div style="max-width:1150px;margin:0 auto;">
    <div class="lp-section-head">
      <div>
        <span class="lp-eyebrow">Simples assim</span>
        <h2>Como funciona</h2>
      </div>
    </div>
    <div class="steps-grid">
      <div class="step-item">
        <div class="step-num">1</div>
        <h3>Descreva o que precisa</h3>
        <p>Conte o que você precisa e receba propostas de empresas qualificadas na sua região.</p>
      </div>
      <div class="step-item">
        <div class="step-num">2</div>
        <h3>Compare e converse</h3>
        <p>Veja avaliações reais, compare orçamentos e tire dúvidas pelo chat direto com a empresa.</p>
      </div>
      <div class="step-item">
        <div class="step-num">3</div>
        <h3>Contrate com segurança</h3>
        <p>Aprove o orçamento, acompanhe o serviço e avalie a experiência ao final.</p>
      </div>
    </div>
  </div>
</section>

<!-- ── Depoimentos (só com dados reais) ─────────────────────────── -->
<?php if ($depoimentos): ?>
<section class="lp-section testi-section" style="max-width:100%;">
  <div style="max-width:1150px;margin:0 auto;">
    <div class="lp-section-head">
      <div>
        <span class="lp-eyebrow">Comunidade</span>
        <h2>O que dizem nossos clientes</h2>
      </div>
    </div>
    <div class="testi-grid">
      <?php foreach ($depoimentos as $dep): ?>
        <div class="testi-card">
          <?= starRating($dep['nota'], true) ?>
          <p class="quote">"<?= htmlspecialchars($dep['titulo'] ?: mb_strimwidth($dep['comentario'], 0, 100, '…')) ?>"</p>
          <div class="testi-who">
            <div class="testi-avatar"><?= strtoupper(mb_substr($dep['cliente_nome'], 0, 1)) ?></div>
            <div>
              <div class="testi-name"><?= htmlspecialchars($dep['cliente_nome']) ?></div>
              <div class="testi-role">Cliente de <?= htmlspecialchars($dep['nome_empresa']) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── CTA dupla ─────────────────────────────────────────────── -->
<section class="lp-section">
  <div class="dual-cta">
    <div class="dual-cta-card navy">
      <h3>Procurando um serviço?</h3>
      <p>Crie sua conta grátis, peça orçamentos e converse direto com empresas avaliadas.</p>
      <a href="clientes/cadastro.php" class="pub-nav-cta gold">Criar conta de cliente</a>
    </div>
    <div class="dual-cta-card gold">
      <h3>Presta serviços?</h3>
      <p>Cadastre sua empresa, publique seus serviços e receba pedidos de orçamento de novos clientes.</p>
      <a href="empresas/cadastro.php" class="pub-nav-cta">Cadastrar minha empresa</a>
    </div>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────── -->
<footer class="pub-footer">
  <div class="pub-footer-inner">
    <div class="pub-footer-brand">
      <span class="pub-logo">Service<span>Hub</span></span>
      <p>Plataforma que conecta clientes a prestadores de serviço avaliados, com orçamentos, chat e contratação em um só lugar.</p>
    </div>
    <div>
      <h4>Para clientes</h4>
      <ul>
        <li><a href="clientes/cadastro.php">Criar conta</a></li>
        <li><a href="login.php">Entrar</a></li>
        <li><a href="esqueci_senha.php">Esqueci minha senha</a></li>
      </ul>
    </div>
    <div>
      <h4>Para empresas</h4>
      <ul>
        <li><a href="empresas/cadastro.php">Anunciar serviços</a></li>
        <li><a href="login.php?tipo=empresa">Entrar como empresa</a></li>
      </ul>
    </div>
    <div>
      <h4>ServiceHub</h4>
      <ul>
        <li><a href="login.php">Login</a></li>
        <li><a href="admin/login.php">Painel administrativo</a></li>
      </ul>
    </div>
  </div>
  <div class="pub-footer-bottom">
    <span>&copy; <?= date('Y') ?> ServiceHub. Projeto acadêmico (TCC).</span>
    <span>Feito com PHP &amp; MySQL</span>
  </div>
</footer>

</body>
</html>
