<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['tipo_usuario'])) {
    header('Location: '.($_SESSION['tipo_usuario']==='cliente'?'dashboard_cliente.php':'dashboard_empresa.php')); exit;
}

$erro='';
$msg  = isset($_GET['msg'])  ? htmlspecialchars(urldecode($_GET['msg']))  : '';
$mtype= $_GET['type'] ?? 'success';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_once 'includes/auth.php';
    $email=$_POST['email']??''; $senha=$_POST['senha']??''; $tipo=$_POST['tipo']??'cliente';
    if ($tipo==='cliente') {
        if (loginCliente($email,$senha,$pdo)) { header('Location: dashboard_cliente.php'); exit; }
        else $erro='E-mail ou senha inválidos.';
    } else {
        if (loginEmpresa($email,$senha,$pdo)) { header('Location: dashboard_empresa.php'); exit; }
        else $erro='E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ServiceHub — Login</title>
  <link rel="stylesheet" href="css/estilo.css">

  <!-- PWA -->
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#c9a84c">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="ServiceHub">
  <link rel="apple-touch-icon" href="icons/icon-192.png">
</head>
<body>
<div class="auth-container">
  <div class="auth-box">
    <div class="auth-logo">
      <h1>Service<span>Hub</span></h1>
      <p>Conectando clientes e prestadores de serviço</p>
    </div>

    <?php if ($msg): ?>
      <div class="<?=$mtype==='success'?'success-msg':'error-msg'?>"><?=$msg?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="error-msg"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="auth-tabs">
      <button class="auth-tab active" onclick="switchTab('cliente',this)">Sou Cliente</button>
      <button class="auth-tab" onclick="switchTab('empresa',this)">Sou Empresa</button>
    </div>

    <form method="post" id="form-cliente" class="tab-pane active">
      <input type="hidden" name="tipo" value="cliente">
      <div class="form-group">
        <label>E-mail</label>
        <input type="email" name="email" class="form-control" placeholder="seu@email.com" required>
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-auth">Entrar como Cliente</button>
      <div class="auth-link">Não tem conta? <a href="clientes/cadastro.php">Cadastre-se</a></div>
      <div class="auth-link"><a href="esqueci_senha.php">Esqueci minha senha</a></div>
    </form>

    <form method="post" id="form-empresa" class="tab-pane">
      <input type="hidden" name="tipo" value="empresa">
      <div class="form-group">
        <label>E-mail da Empresa</label>
        <input type="email" name="email" class="form-control" placeholder="empresa@email.com" required>
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-auth">Entrar como Empresa</button>
      <div class="auth-link">Não tem conta? <a href="empresas/cadastro.php">Cadastre sua empresa</a></div>
      <div class="auth-link"><a href="esqueci_senha.php?tipo=empresa">Esqueci minha senha</a></div>
    </form>
  </div>
</div>

<script>
function switchTab(tipo, btn) {
  document.querySelectorAll('.auth-tab').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('form-'+tipo).classList.add('active');
}
<?php if (!empty($_POST['tipo'])&&$_POST['tipo']==='empresa'&&$erro): ?>
switchTab('empresa', document.querySelector('.auth-tab:last-child'));
<?php endif; ?>
</script>

<script>
// Registra o Service Worker para PWA
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(reg => console.log('ServiceHub PWA ativo:', reg.scope))
      .catch(err => console.warn('SW erro:', err));
  });
}

// Botão de instalação como app
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;

  // Cria botão de instalar se não existir
  if (!document.getElementById('btn-instalar')) {
    const btn = document.createElement('button');
    btn.id = 'btn-instalar';
    btn.textContent = '📲 Instalar como App';
    btn.style.cssText = `
      display:block; width:100%; margin-top:12px; padding:10px;
      background:transparent; border:1px solid #c9a84c; color:#c9a84c;
      border-radius:8px; cursor:pointer; font-size:14px; font-weight:500;
    `;
    btn.addEventListener('click', async () => {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      if (outcome === 'accepted') btn.remove();
      deferredPrompt = null;
    });
    // Insere após o formulário ativo
    const box = document.querySelector('.auth-box');
    if (box) box.appendChild(btn);
  }
});
</script>
</body>
</html>
