<?php
session_start();
require_once 'includes/config.php';

$token = trim($_GET['token'] ?? '');
$tipo  = $_GET['tipo'] ?? 'cliente';
$tabela = $tipo === 'empresa' ? 'empresas' : 'clientes';

$erros   = [];
$success = false;
$user    = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT id FROM $tabela WHERE reset_token=? AND reset_expira > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
}

if (!$user) {
    $erro_fatal = 'Link inválido ou expirado. Solicite um novo link de recuperação.';
}

if (!isset($erro_fatal) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha          = $_POST['senha'] ?? '';
    $confirmar      = $_POST['confirmar_senha'] ?? '';

    if (strlen($senha) < 6)    $erros['senha']    = 'Senha deve ter no mínimo 6 caracteres.';
    if ($senha !== $confirmar) $erros['confirmar'] = 'As senhas não conferem.';

    if (empty($erros)) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE $tabela SET senha=?, reset_token=NULL, reset_expira=NULL WHERE id=?")
            ->execute([$hash, $user['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Redefinir Senha — ServiceHub</title>
  <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-box" style="max-width:420px;">
    <div class="auth-logo">
      <h1>Service<span>Hub</span></h1>
      <p>Redefinição de senha</p>
    </div>

    <?php if (isset($erro_fatal)): ?>
      <div class="error-msg"><?= htmlspecialchars($erro_fatal) ?></div>
      <div class="auth-link"><a href="esqueci_senha.php">← Solicitar novo link</a></div>

    <?php elseif ($success): ?>
      <div class="success-msg">✓ Senha alterada com sucesso! Faça login com sua nova senha.</div>
      <a href="index.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">Ir para o Login</a>

    <?php else: ?>
      <form method="post">
        <div class="form-group">
          <label>Nova Senha</label>
          <input type="password" name="senha" class="form-control" placeholder="Mínimo 6 caracteres" required>
          <?php if (isset($erros['senha'])): ?><span class="error-text"><?= $erros['senha'] ?></span><?php endif; ?>
        </div>
        <div class="form-group">
          <label>Confirmar Nova Senha</label>
          <input type="password" name="confirmar_senha" class="form-control" placeholder="Repita a senha" required>
          <?php if (isset($erros['confirmar'])): ?><span class="error-text"><?= $erros['confirmar'] ?></span><?php endif; ?>
        </div>
        <button type="submit" class="btn-auth">Salvar Nova Senha</button>
      </form>
      <div class="auth-link" style="margin-top:20px;"><a href="index.php">← Cancelar</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
