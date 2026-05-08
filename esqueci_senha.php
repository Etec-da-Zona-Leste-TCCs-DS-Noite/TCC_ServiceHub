<?php
/**
 * esqueci_senha.php — Recuperação de senha (versão sem SMTP)
 * Gera um token e exibe o link na tela (para ambiente local/TCC).
 * Em produção, basta substituir o bloco de exibição por mail().
 */
session_start();
require_once 'includes/config.php';

// Adicionar coluna de token se não existir (executar apenas uma vez)
try {
    $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL");
    $pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL");
    $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL");
    $pdo->exec("ALTER TABLE empresas ADD COLUMN IF NOT EXISTS reset_expira DATETIME NULL");
} catch (Exception $e) { /* colunas já existem */ }

$msg   = '';
$mtype = 'success';
$link  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $tipo  = $_POST['tipo'] ?? 'cliente';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg   = 'Informe um e-mail válido.';
        $mtype = 'error';
    } else {
        $tabela = $tipo === 'empresa' ? 'empresas' : 'clientes';
        $stmt = $pdo->prepare("SELECT id FROM $tabela WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $pdo->prepare("UPDATE $tabela SET reset_token=?, reset_expira=? WHERE id=?")
                ->execute([$token, $expira, $user['id']]);

            $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
                . dirname($_SERVER['SCRIPT_NAME'])
                . "/redefinir_senha.php?token=$token&tipo=$tipo";

            $msg = 'Link de recuperação gerado! Clique no botão abaixo para redefinir sua senha. (Em produção, este link seria enviado por e-mail.)';
        } else {
            // Mensagem neutra por segurança
            $msg = 'Se este e-mail estiver cadastrado, você receberá as instruções de recuperação.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Recuperar Senha — ServiceHub</title>
  <link rel="stylesheet" href="css/estilo.css">
</head>
<body>
<div class="auth-container">
  <div class="auth-box" style="max-width:420px;">
    <div class="auth-logo">
      <h1>Service<span>Hub</span></h1>
      <p>Recuperação de senha</p>
    </div>

    <?php if ($msg): ?>
      <div class="<?= $mtype === 'success' ? 'success-msg' : 'error-msg' ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($link): ?>
      <div style="text-align:center;margin-bottom:24px;">
        <a href="<?= htmlspecialchars($link) ?>" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
          🔑 Redefinir minha senha
        </a>
        <p style="font-size:11px;color:var(--text-muted);margin-top:10px;">Link válido por 1 hora.</p>
      </div>
    <?php else: ?>
      <form method="post">
        <div class="form-group">
          <label>Tipo de conta</label>
          <select name="tipo" class="form-control">
            <option value="cliente">Sou Cliente</option>
            <option value="empresa">Sou Empresa</option>
          </select>
        </div>
        <div class="form-group">
          <label>E-mail cadastrado</label>
          <input type="email" name="email" class="form-control" placeholder="seu@email.com" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn-auth">Recuperar Senha</button>
      </form>
    <?php endif; ?>

    <div class="auth-link" style="margin-top:20px;">
      <a href="index.php">← Voltar ao login</a>
    </div>
  </div>
</div>
</body>
</html>
