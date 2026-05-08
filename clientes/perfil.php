<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();

if (!isCliente()) { header('Location: ../index.php'); exit; }

$cid = $_SESSION['cliente_id'];
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$cid]);
$cliente = $stmt->fetch();

$erros = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome      = cleanInput($_POST['nome']      ?? '');
    $email     = cleanInput($_POST['email']     ?? '');
    $telefone  = cleanInput($_POST['telefone']  ?? '');
    $endereco  = cleanInput($_POST['endereco']  ?? '');
    $cpf_cnpj  = cleanInput($_POST['cpf_cnpj'] ?? '');
    $tipo      = $_POST['tipo'] ?? 'fisica';

    if (empty($nome))  $erros['nome']  = 'Nome é obrigatório.';
    if (empty($email)) $erros['email'] = 'E-mail é obrigatório.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros['email'] = 'E-mail inválido.';

    if ($email !== $cliente['email']) {
        $check = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
        $check->execute([$email, $cid]);
        if ($check->fetch()) $erros['email'] = 'E-mail já cadastrado.';
    }

    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    if (!empty($senha)) {
        if (strlen($senha) < 6) $erros['senha'] = 'Senha deve ter no mínimo 6 caracteres.';
        elseif ($senha !== $confirmar_senha) $erros['confirmar_senha'] = 'As senhas não conferem.';
    }

    if (empty($erros)) {
        $campos = "nome=?, email=?, telefone=?, endereco=?, cpf_cnpj=?, tipo=?";
        $params = [$nome, $email, $telefone, $endereco, $cpf_cnpj, $tipo];
        if (!empty($senha)) {
            $campos .= ", senha=?";
            $params[] = password_hash($senha, PASSWORD_DEFAULT);
        }
        $params[] = $cid;
        $pdo->prepare("UPDATE clientes SET $campos WHERE id=?")->execute($params);
        $_SESSION['cliente_nome']  = $nome;
        $_SESSION['cliente_email'] = $email;
        $success = 'Perfil atualizado com sucesso!';
        $stmt->execute([$cid]);
        $cliente = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Meu Perfil — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
  <style>
    .dash-nav { background:linear-gradient(135deg,var(--navy) 0%,var(--navy-soft) 100%);border-bottom:1px solid rgba(201,168,76,.2);position:sticky;top:0;z-index:200;box-shadow:0 2px 20px rgba(13,27,42,.3); }
    .dash-nav .inner { max-width:1200px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;min-height:64px;flex-wrap:wrap;gap:12px; }
    .nav-items { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .nav-items a { color:var(--slate-lt);font-size:13px;font-weight:500;padding:7px 14px;border-radius:var(--radius-sm);transition:all var(--transition);text-decoration:none; }
    .nav-items a:hover { color:#fff;background:rgba(201,168,76,.18); }
    .avatar-grande { width:80px;height:80px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:var(--navy);margin-bottom:12px; }
    .perfil-header { display:flex;align-items:center;gap:20px;background:linear-gradient(135deg,var(--navy),var(--navy-soft));color:#fff;border-radius:var(--radius);padding:28px;margin-bottom:28px;border:1px solid rgba(201,168,76,.15); }
    .perfil-header h1 { font-size:20px;margin-bottom:4px; }
    .perfil-header p { color:var(--slate);font-size:13px; }
  </style>
</head>
<body>
<nav class="dash-nav">
  <div class="inner">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1></div>
    <div class="nav-items">
      <a href="../dashboard_cliente.php">Início</a>
      <a href="empresas.php">Empresas</a>
      <a href="../orcamentos/index.php">Meus Orçamentos</a>
      <a href="perfil.php" style="color:var(--gold);">Meu Perfil</a>
      <a href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="page-title-row">
    <h1>Meu Perfil</h1>
    <a href="../dashboard_cliente.php" class="btn btn-ghost">← Voltar</a>
  </div>

  <div class="perfil-header">
    <div class="avatar-grande"><?= strtoupper(substr($cliente['nome'],0,1)) ?></div>
    <div>
      <h1><?= htmlspecialchars($cliente['nome']) ?></h1>
      <p><?= htmlspecialchars($cliente['email']) ?></p>
      <p>Cliente desde <?= formatDate($cliente['created_at'], 'd/m/Y') ?></p>
    </div>
  </div>

  <?php if ($success): echo showMessage($success, 'success'); endif; ?>

  <div class="form-container">
    <form method="post">
      <div class="form-section">
        <div class="form-section-title">Dados Pessoais</div>

        <div class="form-row">
          <div class="form-group">
            <label>Nome completo *</label>
            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
            <?php if (isset($erros['nome'])): ?><span class="error-text"><?= $erros['nome'] ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Tipo de pessoa</label>
            <select name="tipo" class="form-control">
              <option value="fisica" <?= $cliente['tipo']==='fisica'?'selected':'' ?>>Pessoa Física</option>
              <option value="juridica" <?= $cliente['tipo']==='juridica'?'selected':'' ?>>Pessoa Jurídica</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>E-mail *</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email']) ?>" required>
            <?php if (isset($erros['email'])): ?><span class="error-text"><?= $erros['email'] ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>CPF / CNPJ</label>
            <input type="text" name="cpf_cnpj" class="form-control" value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Telefone</label>
            <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Endereço</label>
            <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="form-section">
        <div class="form-section-title">Alterar Senha <small style="font-weight:400;font-size:12px;color:var(--text-muted)">(deixe em branco para manter a atual)</small></div>
        <div class="form-row">
          <div class="form-group">
            <label>Nova Senha</label>
            <input type="password" name="senha" class="form-control" placeholder="Mínimo 6 caracteres">
            <?php if (isset($erros['senha'])): ?><span class="error-text"><?= $erros['senha'] ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Confirmar Nova Senha</label>
            <input type="password" name="confirmar_senha" class="form-control" placeholder="Repita a nova senha">
            <?php if (isset($erros['confirmar_senha'])): ?><span class="error-text"><?= $erros['confirmar_senha'] ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary btn-lg">Salvar Alterações</button>
        <a href="../dashboard_cliente.php" class="btn btn-ghost btn-lg">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<footer style="background:var(--navy);color:var(--slate);text-align:center;padding:20px;margin-top:48px;font-size:13px;">
  © <?= date('Y') ?> ServiceHub — Todos os direitos reservados.
</footer>
</body>
</html>
