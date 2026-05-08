<?php
// admin/login.php — Acesso restrito ao painel administrativo
session_start();
require_once '../includes/config.php';

// Redireciona se já logado como admin
if (isset($_SESSION['admin_logado'])) {
    header('Location: index.php'); exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';

    // Credenciais hardcoded para TCC (em produção usar tabela admins com hash)
    // Usuário: admin | Senha: admin@servicehub2024
    $ADMIN_USER  = 'admin';
    $ADMIN_HASH  = '$2y$10$XKTsxqk5Kbkbf2m1G3OoQ.8Kh5rk2d3Bj6NtUl9kS7WvPaQz5e6G'; // admin@servicehub2024
    // Fallback MD5 para compatibilidade
    $ADMIN_MD5   = md5('admin@servicehub2024');

    if ($usuario === $ADMIN_USER && (
        password_verify($senha, $ADMIN_HASH) || md5($senha) === $ADMIN_MD5
    )) {
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_nome']   = 'Administrador';
        header('Location: index.php'); exit;
    } else {
        $erro = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Admin — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    body { background: var(--navy); display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .admin-box { background:#fff; border-radius:var(--r-lg); padding:40px; width:100%; max-width:400px; box-shadow:var(--sh-xl); }
    .admin-logo { text-align:center; margin-bottom:28px; }
    .admin-logo h1 { font-size:26px; color:var(--navy); }
    .admin-logo h1 span { color:var(--gold); }
    .admin-logo .badge { display:inline-block; background:var(--navy); color:var(--gold); font-size:11px; font-weight:700; letter-spacing:1px; padding:3px 10px; border-radius:100px; margin-top:6px; }
  </style>
</head>
<body>
<div class="admin-box">
  <div class="admin-logo">
    <h1>Service<span>Hub</span></h1>
    <div class="badge">PAINEL ADMIN</div>
  </div>

  <?php if ($erro): ?>
  <div class="error-msg"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label>Usuário</label>
      <input type="text" name="usuario" class="form-control" placeholder="admin" required autofocus>
    </div>
    <div class="form-group">
      <label>Senha</label>
      <input type="password" name="senha" class="form-control" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Entrar no Painel</button>
  </form>
  <div style="text-align:center;margin-top:20px;">
    <a href="../index.php" style="font-size:13px;color:var(--text-muted);">← Voltar ao site</a>
  </div>
</div>
</body>
</html>
