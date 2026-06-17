<?php
session_start();
require_once 'includes/config.php';

if (isset($_SESSION['tipo_usuario'])) {
    header('Location: '.($_SESSION['tipo_usuario']==='cliente'?'dashboard_cliente.php':'dashboard_empresa.php'));
    exit;
}

$erro  = '';
$msg   = isset($_GET['msg'])  ? htmlspecialchars(urldecode($_GET['msg']))  : '';
$mtype = $_GET['type'] ?? 'success';
$tipo  = $_POST['tipo'] ?? $_GET['tipo'] ?? 'cliente';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/auth.php';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $tipo  = $_POST['tipo']  ?? 'cliente';

    if ($tipo === 'cliente') {
        if (loginCliente($email, $senha, $pdo)) { header('Location: dashboard_cliente.php'); exit; }
        else $erro = 'E-mail ou senha inválidos.';
    } else {
        if (loginEmpresa($email, $senha, $pdo)) { header('Location: dashboard_empresa.php'); exit; }
        else $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>ServiceHub — Entrar</title>
  <link rel="stylesheet" href="css/estilo.css">
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#0A192F">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="ServiceHub">
  <link rel="apple-touch-icon" href="icons/icon-192.png">
</head>
<body style="margin:0;padding:0;background:#fff">

<div class="auth-split">

  <!-- ── Painel esquerdo (desktop) ─────────────────────────── -->
  <aside class="auth-panel">
    <div class="auth-panel-logo">Service<span>Hub</span></div>

    <div class="auth-panel-tagline">
      <h2>Conecte sua empresa aos melhores clientes</h2>
      <p>A plataforma que simplifica orçamentos, contratos e avaliações em um só lugar.</p>
    </div>

    <ul class="auth-panel-features">
      <li>Orçamentos em minutos</li>
      <li>Chat direto com empresas</li>
      <li>Avaliações verificadas</li>
      <li>Histórico completo de serviços</li>
    </ul>
  </aside>

  <!-- ── Lado direito — formulário ─────────────────────────── -->
  <main class="auth-form-side">
    <div class="auth-form-inner">

      <!-- Logo mobile only -->
      <div class="auth-mobile-logo">
        <h1>Service<span>Hub</span></h1>
        <p>Conectando clientes e prestadores de serviço</p>
      </div>

      <div class="auth-form-heading">
        <h2>Bem-vindo de volta</h2>
        <p>Entre com sua conta para continuar</p>
      </div>

      <!-- Alertas -->
      <?php if ($msg): ?>
        <div class="<?= $mtype === 'success' ? 'auth-success' : 'auth-error' ?>"><?= $msg ?></div>
      <?php endif; ?>
      <?php if ($erro): ?>
        <div class="auth-error"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <!-- Tipo: cliente / empresa -->
      <div class="tipo-selector" id="tipoSelector">
        <button type="button" class="tipo-btn <?= $tipo !== 'empresa' ? 'active' : '' ?>"
                onclick="setTipo('cliente', this)">Sou Cliente</button>
        <button type="button" class="tipo-btn <?= $tipo === 'empresa' ? 'active' : '' ?>"
                onclick="setTipo('empresa', this)">Sou Empresa</button>
      </div>

      <!-- Social login -->
      <div class="social-btns">
        <!-- Google -->
        <a href="oauth/initiate.php?provider=google&tipo=<?= htmlspecialchars($tipo) ?>"
           class="btn-social" id="btnGoogle">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Continuar com Google
        </a>
        <!-- Facebook -->
        <a href="oauth/initiate.php?provider=facebook&tipo=<?= htmlspecialchars($tipo) ?>"
           class="btn-social" id="btnFacebook">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" xmlns="http://www.w3.org/2000/svg">
            <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.513c-1.491 0-1.956.93-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
          </svg>
          Continuar com Facebook
        </a>
      </div>

      <div class="auth-divider">ou entre com e-mail</div>

      <!-- Formulário de e-mail -->
      <form method="post" id="formLogin" novalidate>
        <input type="hidden" name="tipo" id="inputTipo" value="<?= htmlspecialchars($tipo) ?>">

        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email"
                 placeholder="voce@exemplo.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autocomplete="email">
        </div>

        <div class="field">
          <div class="field-row">
            <label for="senha">Senha</label>
            <a href="esqueci_senha.php">Esqueci a senha</a>
          </div>
          <input type="password" id="senha" name="senha"
                 placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn-submit" id="btnSubmit">Entrar</button>
      </form>

      <div class="auth-form-footer">
        <span id="footerCadastro">
          Não tem conta?
          <a href="clientes/cadastro.php" id="linkCadastro">Criar conta grátis</a>
        </span>
      </div>

    </div>
  </main>
</div>

<script>
// ── Tipo cliente / empresa ───────────────────────────────────
function setTipo(tipo, btn) {
  document.querySelectorAll('.tipo-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('inputTipo').value = tipo;

  // Atualiza links OAuth
  ['google','facebook'].forEach(p => {
    const el = document.getElementById('btn' + p.charAt(0).toUpperCase() + p.slice(1));
    if (el) el.href = 'oauth/initiate.php?provider=' + p + '&tipo=' + tipo;
  });

  // Atualiza link de cadastro
  const lnk = document.getElementById('linkCadastro');
  if (tipo === 'empresa') {
    lnk.href = 'empresas/cadastro.php';
    lnk.textContent = 'Cadastrar empresa';
  } else {
    lnk.href = 'clientes/cadastro.php';
    lnk.textContent = 'Criar conta grátis';
  }

  // Submit label
  document.getElementById('btnSubmit').textContent =
    tipo === 'empresa' ? 'Entrar como Empresa' : 'Entrar';
}

// Garante estado correto no carregamento
(function(){
  const tipo = document.getElementById('inputTipo').value;
  if (tipo === 'empresa') {
    const btns = document.querySelectorAll('.tipo-btn');
    btns.forEach(b => b.classList.remove('active'));
    btns[1] && btns[1].classList.add('active');
    document.getElementById('btnSubmit').textContent = 'Entrar como Empresa';
    const lnk = document.getElementById('linkCadastro');
    if (lnk) { lnk.href = 'empresas/cadastro.php'; lnk.textContent = 'Cadastrar empresa'; }
  }
})();

// ── PWA ──────────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .catch(e => console.warn('SW:', e));
  });
}

let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  if (!document.getElementById('btn-instalar')) {
    const btn = document.createElement('button');
    btn.id = 'btn-instalar';
    btn.textContent = 'Instalar como App';
    btn.style.cssText = 'display:block;width:100%;margin-top:10px;padding:10px;background:transparent;border:1.5px solid #0A192F;color:#0A192F;border-radius:5px;cursor:pointer;font-size:13px;font-weight:700;font-family:Arial,sans-serif';
    btn.addEventListener('click', async () => {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      if (outcome === 'accepted') btn.remove();
      deferredPrompt = null;
    });
    document.querySelector('.auth-form-inner')?.appendChild(btn);
  }
});
</script>
</body>
</html>
