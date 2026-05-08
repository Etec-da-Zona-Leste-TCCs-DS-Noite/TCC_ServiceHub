<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

$is_cliente = isCliente();
$is_empresa = isEmpresa();
$remetente  = $is_cliente ? 'cliente' : 'empresa';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Busca a conversa e valida acesso
$stmt = $pdo->prepare("
    SELECT cv.*,
           c.nome          AS cliente_nome,
           e.nome_empresa  AS empresa_nome,
           o.id            AS orc_id, o.status AS orc_status, o.valor_total AS orc_valor
    FROM conversas cv
    JOIN clientes c  ON c.id = cv.cliente_id
    JOIN empresas e  ON e.id = cv.empresa_id
    LEFT JOIN orcamentos o ON o.id = cv.orcamento_id
    WHERE cv.id = ?");
$stmt->execute([$id]);
$conv = $stmt->fetch();
if (!$conv) { header('Location: index.php'); exit; }

if ($is_cliente && $conv['cliente_id'] != $_SESSION['cliente_id']) { header('Location: index.php'); exit; }
if ($is_empresa && $conv['empresa_id'] != $_SESSION['empresa_id']) { header('Location: index.php'); exit; }

$outro_nome = $is_cliente ? $conv['empresa_nome'] : $conv['cliente_nome'];

// Marca mensagens do outro como lidas
$outroPerfil = $is_cliente ? 'empresa' : 'cliente';
$pdo->prepare("UPDATE mensagens SET lida = 1 WHERE conversa_id = ? AND remetente = ? AND lida = 0")
    ->execute([$id, $outroPerfil]);

// Envia nova mensagem (submit normal — fallback sem JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conteudo'])) {
    $txt = trim($_POST['conteudo']);
    if ($txt !== '') {
        $pdo->prepare("INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?,?,?)")
            ->execute([$id, $remetente, $txt]);
        $pdo->prepare("UPDATE conversas SET updated_at = NOW() WHERE id = ?")->execute([$id]);
    }
    header('Location: conversa.php?id='.$id); exit;
}

// Carrega mensagens
$msgs = $pdo->prepare("SELECT * FROM mensagens WHERE conversa_id = ? ORDER BY created_at ASC");
$msgs->execute([$id]);
$mensagens = $msgs->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Chat com <?= htmlspecialchars($outro_nome) ?> — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    /* ── Layout do chat ── */
    body { display:flex; flex-direction:column; min-height:100vh; }
    .dash-nav{background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3);}
    .dash-nav .inner{max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px;}
    .nav-items{display:flex;gap:6px;flex-wrap:wrap;align-items:center;}
    .nav-items a{color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none;}
    .nav-items a:hover{color:#fff;background:rgba(201,168,76,.18);}

    .chat-page{flex:1;display:flex;flex-direction:column;max-width:820px;margin:0 auto;width:100%;padding:24px 24px 0;}

    /* ── Cabeçalho da conversa ── */
    .chat-header{background:#fff;border:1px solid var(--border);border-radius:var(--radius) var(--radius) 0 0;padding:16px 20px;display:flex;align-items:center;gap:14px;border-bottom:none;}
    .chat-header-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--navy),var(--navy-soft));display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;color:var(--gold);flex-shrink:0;font-family:'Playfair Display',serif;}
    .chat-header-info{flex:1;}
    .chat-header-info strong{display:block;font-size:16px;}
    .chat-header-info small{font-size:12px;color:var(--text-muted);}
    .orc-chip{display:inline-flex;align-items:center;gap:5px;background:var(--gold-dim);border:1px solid rgba(201,168,76,.25);color:#78530a;border-radius:100px;padding:3px 10px;font-size:11px;font-weight:600;text-decoration:none;}
    .orc-chip:hover{background:rgba(201,168,76,.25);color:#78530a;}

    /* ── Área de mensagens ── */
    .chat-body{flex:1;background:#f7f9fc;border:1px solid var(--border);border-top:2px solid var(--gold-dim);padding:20px;overflow-y:auto;min-height:340px;max-height:calc(100vh - 320px);display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;}

    /* ── Balões ── */
    .msg-row{display:flex;gap:8px;align-items:flex-end;}
    .msg-row.me{flex-direction:row-reverse;}
    .msg-avatar{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;}
    .msg-avatar.them{background:linear-gradient(135deg,var(--navy),var(--navy-soft));color:var(--gold);}
    .msg-avatar.me{background:var(--gold);color:var(--navy);}
    .bubble{max-width:68%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.6;word-break:break-word;position:relative;}
    .bubble.them{background:#fff;border:1px solid var(--border);border-bottom-left-radius:4px;color:var(--text);}
    .bubble.me{background:linear-gradient(135deg,var(--navy),var(--navy-soft));color:#fff;border-bottom-right-radius:4px;}
    .bubble-time{font-size:10px;margin-top:4px;opacity:.65;display:block;text-align:right;}
    .bubble.them .bubble-time{text-align:left;color:var(--text-muted);}

    /* ── Separador de data ── */
    .date-divider{display:flex;align-items:center;gap:10px;margin:6px 0;}
    .date-divider span{font-size:11px;color:var(--text-muted);white-space:nowrap;background:#f7f9fc;padding:0 8px;}
    .date-divider::before,.date-divider::after{content:'';flex:1;height:1px;background:var(--border);}

    /* ── Input bar ── */
    .chat-input-bar{background:#fff;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius) var(--radius);padding:14px 16px;}
    .input-row{display:flex;gap:10px;align-items:flex-end;}
    .input-row textarea{flex:1;resize:none;border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;font-family:inherit;font-size:14px;min-height:44px;max-height:120px;line-height:1.5;transition:border var(--transition);}
    .input-row textarea:focus{outline:none;border-color:var(--gold);}
    .send-btn{background:var(--gold);color:var(--navy);border:none;border-radius:var(--radius-sm);padding:10px 18px;font-weight:700;font-size:15px;cursor:pointer;transition:all var(--transition);height:44px;flex-shrink:0;}
    .send-btn:hover{background:var(--gold-lt);}
    .send-btn:active{transform:scale(.97);}

    .typing-indicator{font-size:12px;color:var(--text-muted);min-height:18px;padding:0 2px 4px;}

    @media(max-width:600px){
      .chat-page{padding:0;}
      .chat-header{border-radius:0;}
      .chat-input-bar{border-radius:0;}
      .chat-body{max-height:calc(100vh - 280px);}
    }
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <?php if ($is_cliente): ?>
        <a href="../dashboard_cliente.php">Início</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
      <?php else: ?>
        <a href="../dashboard_empresa.php">Início</a>
        <a href="../orcamentos/index.php">Orçamentos</a>
      <?php endif; ?>
      <a href="index.php">💬 Mensagens</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="chat-page">

  <!-- Cabeçalho -->
  <div class="chat-header">
    <a href="index.php" style="color:var(--text-muted);font-size:18px;text-decoration:none;" title="Voltar">←</a>
    <div class="chat-header-avatar"><?= strtoupper(substr($outro_nome, 0, 1)) ?></div>
    <div class="chat-header-info">
      <strong><?= htmlspecialchars($outro_nome) ?></strong>
      <small>
        <?= $is_cliente ? 'Empresa' : 'Cliente' ?>
        <?php if ($conv['orc_id']): ?>
          &nbsp;·&nbsp;
          <a href="../orcamentos/view.php?id=<?=$conv['orc_id']?>" class="orc-chip">
            📄 Orçamento #<?=$conv['orc_id']?> · <?= statusBadge($conv['orc_status']) ?>
          </a>
        <?php endif; ?>
      </small>
    </div>
  </div>

  <!-- Mensagens -->
  <div class="chat-body" id="chatBody">
    <?php
    $lastDate = '';
    foreach ($mensagens as $msg):
        $isMe   = ($msg['remetente'] === $remetente);
        $rowCls = $isMe ? 'me' : 'them';
        $msgDate = (new DateTime($msg['created_at']))->format('d/m/Y');
        if ($msgDate !== $lastDate):
            $lastDate = $msgDate;
    ?>
    <div class="date-divider"><span><?= $msgDate === date('d/m/Y') ? 'Hoje' : $msgDate ?></span></div>
    <?php endif; ?>
    <div class="msg-row <?= $rowCls ?>" data-id="<?=$msg['id']?>">
      <div class="msg-avatar <?= $rowCls ?>"><?= strtoupper(substr($isMe ? ($is_cliente ? $_SESSION['cliente_nome'] : $_SESSION['empresa_nome']) : $outro_nome, 0, 1)) ?></div>
      <div class="bubble <?= $rowCls ?>">
        <?= nl2br(htmlspecialchars($msg['conteudo'])) ?>
        <span class="bubble-time"><?= (new DateTime($msg['created_at']))->format('H:i') ?><?= $isMe ? ($msg['lida'] ? ' ✓✓' : ' ✓') : '' ?></span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($mensagens)): ?>
    <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
      <span style="font-size:36px;display:block;margin-bottom:8px;">👋</span>
      <p>Nenhuma mensagem ainda. Diga olá!</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Indicador de digitação -->
  <div class="typing-indicator" id="typingIndicator" style="padding:4px 16px;background:#fff;border-left:1px solid var(--border);border-right:1px solid var(--border);"></div>

  <!-- Input -->
  <div class="chat-input-bar">
    <div class="input-row">
      <textarea id="msgInput" placeholder="Digite uma mensagem…" rows="1" maxlength="2000"></textarea>
      <button class="send-btn" id="sendBtn" title="Enviar (Enter)">➤</button>
    </div>
  </div>

</div><!-- .chat-page -->

<script>
const CONVERSA_ID  = <?= $id ?>;
const REMETENTE    = '<?= $remetente ?>';
const OUTRO        = '<?= $outroPerfil ?>';
const OUTRO_NOME   = <?= json_encode($outro_nome) ?>;
let   lastId       = <?= $mensagens ? max(array_column($mensagens, 'id')) : 0 ?>;
let   pollInterval = null;
let   isTyping     = false;
let   typingTimer  = null;

const chatBody    = document.getElementById('chatBody');
const msgInput    = document.getElementById('msgInput');
const sendBtn     = document.getElementById('sendBtn');
const typingEl    = document.getElementById('typingIndicator');

// Scroll to bottom
function scrollBottom(smooth = true) {
  chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
}
scrollBottom(false);

// Auto-resize textarea
msgInput.addEventListener('input', () => {
  msgInput.style.height = 'auto';
  msgInput.style.height = Math.min(msgInput.scrollHeight, 120) + 'px';
});

// Send on Enter (Shift+Enter = newline)
msgInput.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});
sendBtn.addEventListener('click', sendMessage);

// ── Enviar mensagem via AJAX ──────────────────────────────
function sendMessage() {
  const txt = msgInput.value.trim();
  if (!txt) return;
  sendBtn.disabled = true;
  msgInput.value   = '';
  msgInput.style.height = 'auto';

  fetch('send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ conversa_id: CONVERSA_ID, conteudo: txt })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      appendMessage(data.mensagem, true);
      lastId = data.mensagem.id;
      scrollBottom();
    }
  })
  .catch(() => {})
  .finally(() => { sendBtn.disabled = false; msgInput.focus(); });
}

// ── Renderizar balão ──────────────────────────────────────
function appendMessage(msg, isMe) {
  // Separador de data se necessário
  const d = new Date(msg.created_at.replace(' ', 'T'));
  const dateStr = d.toLocaleDateString('pt-BR');
  const lastDividers = chatBody.querySelectorAll('.date-divider span');
  const lastDiv = lastDividers.length ? lastDividers[lastDividers.length-1].textContent : '';
  const todayStr = new Date().toLocaleDateString('pt-BR');
  const label    = dateStr === todayStr ? 'Hoje' : dateStr;
  if (label !== lastDiv) {
    const div = document.createElement('div');
    div.className = 'date-divider';
    div.innerHTML = `<span>${label}</span>`;
    chatBody.appendChild(div);
  }

  const rowCls = isMe ? 'me' : 'them';
  const initial = isMe ? (REMETENTE === 'cliente' ? msgInput.dataset.meInit || 'U' : msgInput.dataset.meInit || 'U') : OUTRO_NOME[0].toUpperCase();
  const time    = d.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
  const check   = isMe ? ' ✓' : '';

  const row = document.createElement('div');
  row.className = `msg-row ${rowCls}`;
  row.dataset.id = msg.id;
  row.innerHTML = `
    <div class="msg-avatar ${rowCls}">${initial}</div>
    <div class="bubble ${rowCls}">
      ${escapeHtml(msg.conteudo).replace(/\n/g,'<br>')}
      <span class="bubble-time">${time}${check}</span>
    </div>`;
  chatBody.appendChild(row);
}

function escapeHtml(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Polling de novas mensagens ────────────────────────────
function pollMessages() {
  fetch(`poll.php?conversa_id=${CONVERSA_ID}&last_id=${lastId}&remetente=${REMETENTE}`)
    .then(r => r.json())
    .then(data => {
      if (data.mensagens && data.mensagens.length) {
        const wasAtBottom = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 60;
        data.mensagens.forEach(m => {
          const isMe = m.remetente === REMETENTE;
          // Evita duplicata
          if (!chatBody.querySelector(`[data-id="${m.id}"]`)) {
            appendMessage(m, isMe);
            if (isMe) {
              // Atualiza check duplo nas mensagens enviadas por mim
              const lastMe = chatBody.querySelector(`[data-id="${m.id}"] .bubble-time`);
              if (lastMe) lastMe.textContent = lastMe.textContent.replace('✓','✓✓');
            }
            lastId = Math.max(lastId, parseInt(m.id));
          }
        });
        if (wasAtBottom) scrollBottom();
      }

      // Indicador de "digitando"
      if (data.typing) {
        typingEl.textContent = OUTRO_NOME + ' está digitando…';
      } else {
        typingEl.textContent = '';
      }
    })
    .catch(() => {});
}

pollInterval = setInterval(pollMessages, 2500);

// ── Sinalizar que estou digitando ────────────────────────
msgInput.addEventListener('input', () => {
  clearTimeout(typingTimer);
  fetch(`typing.php?conversa_id=${CONVERSA_ID}&remetente=${REMETENTE}&status=1`);
  typingTimer = setTimeout(() => {
    fetch(`typing.php?conversa_id=${CONVERSA_ID}&remetente=${REMETENTE}&status=0`);
  }, 3000);
});

// ── Limpar polling ao sair ────────────────────────────────
window.addEventListener('beforeunload', () => {
  clearInterval(pollInterval);
  fetch(`typing.php?conversa_id=${CONVERSA_ID}&remetente=${REMETENTE}&status=0`);
});

// Guardar inicial do usuário logado para os balões
msgInput.dataset.meInit = '<?= $is_cliente ? strtoupper(substr($_SESSION['cliente_nome'],0,1)) : strtoupper(substr($_SESSION['empresa_nome'],0,1)) ?>';
</script>
</body>
</html>
