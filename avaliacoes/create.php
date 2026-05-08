<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

if (!isCliente()) { header('Location: ../index.php'); exit; }

$cid = $_SESSION['cliente_id'];
$orc_id = (int)($_GET['orcamento_id'] ?? 0);
if (!$orc_id) { header('Location: ../orcamentos/index.php'); exit; }

// Busca o orçamento — deve ser concluído e do cliente logado
$stmt = $pdo->prepare("
    SELECT o.*, e.nome_empresa, s.nome AS servico_nome
    FROM orcamentos o
    LEFT JOIN empresas e ON e.id = o.empresa_id
    LEFT JOIN servicos s ON s.id = o.servico_id
    WHERE o.id = ? AND o.cliente_id = ? AND o.status = 'concluido'");
$stmt->execute([$orc_id, $cid]);
$orc = $stmt->fetch();
if (!$orc) {
    header('Location: ../orcamentos/index.php?msg='.urlencode('Orçamento inválido ou não concluído.').'&type=error');
    exit;
}

// Verifica se já avaliou
$jaAvaliou = $pdo->prepare("SELECT id FROM avaliacoes WHERE orcamento_id = ?");
$jaAvaliou->execute([$orc_id]);
if ($jaAvaliou->fetch()) {
    header('Location: ../orcamentos/view.php?id='.$orc_id.'&msg='.urlencode('Você já avaliou este serviço.').'&type=error');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nota      = (int)($_POST['nota'] ?? 0);
    $titulo    = trim($_POST['titulo'] ?? '');
    $comentario= trim($_POST['comentario'] ?? '');

    if ($nota < 1 || $nota > 5) {
        $erro = 'Selecione uma nota de 1 a 5 estrelas.';
    } else {
        $ins = $pdo->prepare("INSERT INTO avaliacoes (orcamento_id, cliente_id, empresa_id, nota, titulo, comentario) VALUES (?,?,?,?,?,?)");
        $ins->execute([$orc_id, $cid, $orc['empresa_id'], $nota, $titulo ?: null, $comentario ?: null]);
        header('Location: ../orcamentos/view.php?id='.$orc_id.'&msg='.urlencode('Avaliação enviada! Obrigado pelo seu feedback.').'&type=success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Avaliar Serviço — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3);}
    .dash-nav .inner{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px;}
    .nav-items{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .nav-items a{color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none;}
    .nav-items a:hover{color:#fff;background:rgba(201,168,76,.18);}

    /* ── Cartão central ── */
    .avaliacao-wrap{max-width:620px;margin:40px auto;}
    .avaliacao-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow);}
    .avaliacao-header{background:linear-gradient(135deg,var(--navy),var(--navy-soft));padding:28px 32px;color:#fff;}
    .avaliacao-header h2{font-size:20px;margin-bottom:4px;}
    .avaliacao-header p{color:var(--slate);font-size:13px;}
    .avaliacao-body{padding:32px;}

    /* ── Estrelas interativas ── */
    .star-picker{display:flex;gap:6px;margin-bottom:6px;}
    .star-picker input[type=radio]{display:none;}
    .star-picker label{font-size:36px;cursor:pointer;color:#d0d8e0;transition:color .15s,transform .15s;line-height:1;}
    .star-picker label:hover,
    .star-picker label:hover ~ label,
    .star-picker input:checked ~ label{color:#c9a84c;}
    /* Inverte a ordem para o CSS selector funcionar */
    .star-picker{flex-direction:row-reverse;justify-content:flex-end;}
    .star-picker label:hover,
    .star-picker label:hover ~ label{color:#c9a84c;}
    .star-picker input[type=radio]:checked ~ label{color:#c9a84c;}

    .star-hint{font-size:12px;color:var(--text-muted);margin-bottom:20px;min-height:18px;}
    .form-group{margin-bottom:20px;}
    .form-group label{display:block;font-size:13px;font-weight:600;margin-bottom:6px;color:var(--text);}
    .form-group input,
    .form-group textarea{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius-sm);font-family:inherit;font-size:14px;transition:border var(--transition);}
    .form-group input:focus,
    .form-group textarea:focus{outline:none;border-color:var(--gold);}
    .form-group textarea{resize:vertical;min-height:110px;}

    /* ── Serviço info box ── */
    .service-box{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;margin-bottom:24px;display:flex;gap:14px;align-items:flex-start;}
    .service-box .icon{font-size:26px;flex-shrink:0;}
    .service-box strong{display:block;font-size:14px;margin-bottom:2px;}
    .service-box small{font-size:12px;color:var(--text-muted);}
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="../dashboard_cliente.php">Início</a>
      <a href="../orcamentos/index.php">Orçamentos</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="avaliacao-wrap">
    <div style="margin-bottom:18px;">
      <a href="../orcamentos/view.php?id=<?=$orc_id?>" class="btn btn-ghost">← Voltar ao Orçamento</a>
    </div>

    <?php if ($erro): echo showMessage($erro, 'error'); endif; ?>

    <div class="avaliacao-card">
      <div class="avaliacao-header">
        <h2>⭐ Avaliar Serviço</h2>
        <p>Sua opinião ajuda outras pessoas a escolher bem!</p>
      </div>
      <div class="avaliacao-body">

        <div class="service-box">
          <span class="icon">🏢</span>
          <div>
            <strong><?= htmlspecialchars($orc['nome_empresa'] ?? '—') ?></strong>
            <small>Serviço: <?= htmlspecialchars($orc['servico_nome'] ?? '—') ?> · Orçamento #<?=$orc_id?></small>
          </div>
        </div>

        <form method="post">
          <div class="form-group">
            <label>Nota *</label>
            <div class="star-picker" id="starPicker">
              <?php for ($i = 5; $i >= 1; $i--): ?>
              <input type="radio" name="nota" id="star<?=$i?>" value="<?=$i?>">
              <label for="star<?=$i?>" title="<?=$i?> estrela<?=$i>1?'s':''?>">★</label>
              <?php endfor; ?>
            </div>
            <div class="star-hint" id="starHint">Clique para dar sua nota</div>
          </div>

          <div class="form-group">
            <label for="titulo">Título da avaliação <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
            <input type="text" id="titulo" name="titulo" maxlength="100"
                   placeholder="Ex: Ótimo trabalho, recomendo!"
                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="comentario">Comentário <span style="color:var(--text-muted);font-weight:400;">(opcional)</span></label>
            <textarea id="comentario" name="comentario" maxlength="1000"
                      placeholder="Conte como foi a experiência com este serviço..."><?= htmlspecialchars($_POST['comentario'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px;">
            <a href="../orcamentos/view.php?id=<?=$orc_id?>" class="btn btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary">Enviar Avaliação</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>

<script>
const hints = ['','Muito ruim','Ruim','Regular','Bom','Excelente'];
const radios = document.querySelectorAll('.star-picker input[type=radio]');
const hint   = document.getElementById('starHint');
radios.forEach(r => {
  r.addEventListener('change', () => { hint.textContent = hints[r.value] + ' (' + r.value + '/5)'; });
});
// hover hint
document.querySelectorAll('.star-picker label').forEach(lbl => {
  lbl.addEventListener('mouseenter', () => {
    const v = lbl.getAttribute('for').replace('star','');
    hint.textContent = hints[v] + ' (' + v + '/5)';
  });
  lbl.addEventListener('mouseleave', () => {
    const checked = document.querySelector('.star-picker input:checked');
    hint.textContent = checked ? hints[checked.value] + ' (' + checked.value + '/5)' : 'Clique para dar sua nota';
  });
});
</script>
</body>
</html>
