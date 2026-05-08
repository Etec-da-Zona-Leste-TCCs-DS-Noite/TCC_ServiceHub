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

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: empresas.php');
    exit;
}

// Buscar dados da empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? AND status = 1");
$stmt->execute([$id]);
$empresa = $stmt->fetch();

if (!$empresa) {
    header('Location: empresas.php');
    exit;
}

// Buscar serviços da empresa
$stmtServ = $pdo->prepare("SELECT * FROM servicos WHERE empresa_id = ? AND status = 1 ORDER BY categoria, nome");
$stmtServ->execute([$id]);
$servicos = $stmtServ->fetchAll();

// Avaliações
$av = mediaAvaliacoes($pdo, $id);

// Buscar avaliações detalhadas
$stmtAv = $pdo->prepare("
    SELECT av.*, c.nome AS nome_cliente
    FROM avaliacoes av
    JOIN clientes c ON c.id = av.cliente_id
    WHERE av.empresa_id = ?
    ORDER BY av.created_at DESC
    LIMIT 5
");
$stmtAv->execute([$id]);
$avaliacoes = $stmtAv->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ServiceHub - <?= htmlspecialchars($empresa['nome_empresa']) ?></title>
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
            display: flex; justify-content: space-between; align-items: center;
        }
        .main-content { max-width: 1100px; margin: 0 auto; padding: 30px 20px; }

        /* Hero da empresa */
        .empresa-hero {
            background: linear-gradient(135deg, #1a4a6f 0%, #0a2b3e 100%);
            border-radius: 16px; color: white;
            padding: 35px 40px; margin-bottom: 30px;
            display: flex; gap: 30px; align-items: center; flex-wrap: wrap;
        }
        .empresa-hero-info { flex: 1; min-width: 200px; }
        .empresa-hero h1 { margin: 0 0 8px; font-size: 26px; }
        .empresa-hero .desc { opacity: 0.85; font-size: 14px; line-height: 1.6; margin-bottom: 14px; }
        .empresa-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; opacity: 0.9; }
        .empresa-meta span { display: flex; align-items: center; gap: 6px; }
        .empresa-meta i { color: #d4af37; }

        /* Grid de serviços */
        .section-title {
            font-size: 20px; color: #0a2b3e; margin: 0 0 20px;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title i { color: #d4af37; }

        .servicos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .servico-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            padding: 22px; transition: all 0.3s;
            display: flex; flex-direction: column;
        }
        .servico-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .servico-cat {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: #1a4a6f;
            background: #e8f0f8; border-radius: 20px;
            padding: 3px 10px; display: inline-block; margin-bottom: 10px;
        }
        .servico-nome { font-size: 16px; font-weight: 700; color: #0a2b3e; margin-bottom: 8px; }
        .servico-desc { font-size: 13px; color: #666; line-height: 1.55; flex: 1; margin-bottom: 16px; }
        .servico-footer {
            display: flex; justify-content: space-between;
            align-items: center; gap: 10px; flex-wrap: wrap;
        }
        .servico-preco { font-size: 18px; font-weight: 800; color: #1a4a6f; }
        .servico-duracao { font-size: 12px; color: #888; }
        .btn-solicitar {
            background: #d4af37; color: #0a2b3e;
            border: none; padding: 9px 18px; border-radius: 7px;
            font-weight: 700; font-size: 13px; cursor: pointer;
            text-decoration: none; transition: background 0.2s; white-space: nowrap;
        }
        .btn-solicitar:hover { background: #c4a02e; }

        /* Avaliações */
        .av-card {
            background: white; border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 18px 22px; margin-bottom: 14px;
        }
        .av-header { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .av-autor { font-weight: 700; color: #0a2b3e; font-size: 14px; }
        .av-data { font-size: 12px; color: #999; }
        .av-titulo { font-weight: 600; color: #333; margin: 6px 0 4px; font-size: 14px; }
        .av-texto { font-size: 13px; color: #555; line-height: 1.5; }
        .av-resposta {
            margin-top: 10px; padding: 10px 14px;
            background: #f0f4f8; border-left: 3px solid #1a4a6f;
            border-radius: 0 6px 6px 0; font-size: 13px; color: #444;
        }
        .av-resposta strong { color: #1a4a6f; }

        .empty-box {
            text-align: center; padding: 40px;
            background: #f8f9fa; border-radius: 12px; color: #aaa;
        }
        .rating-summary {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.15); border-radius: 20px;
            padding: 6px 14px; margin-top: 10px;
        }
        .rating-summary .nota-num { font-size: 20px; font-weight: 800; }
        .rating-summary .total { font-size: 12px; opacity: 0.8; }

        @media (max-width: 600px) {
            .empresa-hero { padding: 25px 20px; }
            .navbar .inner { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="inner">
        <h2 style="color:white;">Service<span style="color:#d4af37;">Hub</span></h2>
        <div>
            <a href="empresas.php" style="color:white;margin-right:20px;">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="../logout.php" style="color:white;">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</nav>

<div class="main-content">

    <!-- Hero da empresa -->
    <div class="empresa-hero">
        <div class="empresa-hero-info">
            <h1><?= htmlspecialchars($empresa['nome_empresa']) ?></h1>
            <div class="desc"><?= htmlspecialchars($empresa['descricao'] ?? 'Empresa especializada em serviços de qualidade.') ?></div>
            <div class="empresa-meta">
                <?php if ($empresa['telefone']): ?>
                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($empresa['telefone']) ?></span>
                <?php endif; ?>
                <?php if ($empresa['endereco']): ?>
                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($empresa['endereco']) ?></span>
                <?php endif; ?>
                <?php if ($empresa['site']): ?>
                <span><i class="fas fa-globe"></i> <?= htmlspecialchars($empresa['site']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-briefcase"></i> <?= count($servicos) ?> serviço(s) disponível(is)</span>
            </div>
            <?php if ($av['total'] > 0): ?>
            <div class="rating-summary">
                <?= starRating($av['media']) ?>
                <span class="nota-num"><?= number_format($av['media'], 1, ',', '') ?></span>
                <span class="total">(<?= $av['total'] ?> avaliação(ões))</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Serviços -->
    <h2 class="section-title"><i class="fas fa-tools"></i> Serviços Disponíveis</h2>

    <?php if (empty($servicos)): ?>
        <div class="empty-box">
            <i class="fas fa-tools" style="font-size:40px;display:block;margin-bottom:12px;"></i>
            <p>Esta empresa ainda não possui serviços cadastrados.</p>
        </div>
    <?php else: ?>
    <div class="servicos-grid">
        <?php foreach ($servicos as $srv): ?>
        <div class="servico-card">
            <?php if ($srv['categoria']): ?>
                <span class="servico-cat"><?= htmlspecialchars($srv['categoria']) ?></span>
            <?php endif; ?>
            <div class="servico-nome"><?= htmlspecialchars($srv['nome']) ?></div>
            <div class="servico-desc"><?= htmlspecialchars($srv['descricao'] ?? '') ?></div>
            <div class="servico-footer">
                <div>
                    <div class="servico-preco"><?= $srv['valor'] !== null ? formatMoney($srv['valor']) : '<span style="color:#888;font-style:italic;font-size:14px;">A definir</span>' ?></div>
                    <?php if ($srv['duracao_estimada']): ?>
                        <div class="servico-duracao">
                            <i class="fas fa-clock"></i> ~<?= $srv['duracao_estimada'] ?>h estimadas
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($srv['valor'] !== null): ?>
                <a href="../orcamentos/create.php?empresa_id=<?= $empresa['id'] ?>&servico_id=<?= $srv['id'] ?>"
                   class="btn-solicitar" title="Orçamento gerado automaticamente">
                    <i class="fas fa-bolt"></i> Solicitar
                </a>
                <?php else: ?>
                <a href="../orcamentos/create.php?empresa_id=<?= $empresa['id'] ?>&servico_id=<?= $srv['id'] ?>"
                   class="btn-solicitar" style="background:#6c8ebf;"
                   title="Valor a combinar — você enviará uma solicitação">
                    <i class="fas fa-comments-dollar"></i> Solicitar consulta
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Avaliações -->
    <?php if (!empty($avaliacoes)): ?>
    <h2 class="section-title"><i class="fas fa-star"></i> Avaliações</h2>
    <?php foreach ($avaliacoes as $aval): ?>
    <div class="av-card">
        <div class="av-header">
            <span class="av-autor"><?= htmlspecialchars($aval['nome_cliente']) ?></span>
            <span class="av-data"><?= formatDate($aval['created_at']) ?></span>
        </div>
        <?= starRating($aval['nota'], true) ?>
        <?php if ($aval['titulo']): ?>
            <div class="av-titulo"><?= htmlspecialchars($aval['titulo']) ?></div>
        <?php endif; ?>
        <?php if ($aval['comentario']): ?>
            <div class="av-texto"><?= htmlspecialchars($aval['comentario']) ?></div>
        <?php endif; ?>
        <?php if ($aval['resposta']): ?>
        <div class="av-resposta">
            <strong><?= htmlspecialchars($empresa['nome_empresa']) ?>:</strong>
            <?= htmlspecialchars($aval['resposta']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>
