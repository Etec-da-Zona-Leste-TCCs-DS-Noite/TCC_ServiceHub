<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE id=?");
$stmt->execute([$id]);
$servico = $stmt->fetch();
if (!$servico) { header('Location: index.php?msg='.urlencode('Serviço não encontrado').'&type=error'); exit; }

$erros = [];
$nome=$servico['nome']; $descricao=$servico['descricao']; $categoria=$servico['categoria'];
$valor=number_format($servico['valor'],2,',','.'); $duracao_estimada=$servico['duracao_estimada']; $status=$servico['status'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nome             = cleanInput($_POST['nome'] ?? '');
    $descricao        = cleanInput($_POST['descricao'] ?? '');
    $categoria        = cleanInput($_POST['categoria'] ?? '');
    $valor            = str_replace(',','.',($_POST['valor'] ?? ''));
    $duracao_estimada = (int)($_POST['duracao_estimada'] ?? 0);
    $status           = (int)($_POST['status'] ?? 1);

    if (empty($nome))  $erros['nome']  = 'Nome é obrigatório.';
    if ($valor==='')   $erros['valor'] = 'Valor é obrigatório.';
    elseif (!is_numeric($valor)) $erros['valor'] = 'Valor inválido.';

    if (empty($erros)) {
        $u = $pdo->prepare("UPDATE servicos SET nome=?,descricao=?,valor=?,duracao_estimada=?,categoria=?,status=? WHERE id=?");
        if ($u->execute([$nome,$descricao,$valor,$duracao_estimada?:null,$categoria,$status,$id])) {
            header('Location: index.php?msg='.urlencode('Serviço atualizado!').'&type=success'); exit;
        }
        $erros['geral'] = 'Erro ao atualizar.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Editar Serviço — ServiceHub</title>
  <link rel="stylesheet" href="../css/estilo.css">
</head>
<body>
<header class="main-header">
  <div class="header-content">
    <div class="logo"><h1>Service<span class="logo-span">Hub</span></h1><p>Gestão de Serviços &amp; Orçamentos</p></div>
    <nav class="main-nav"><ul>
      <li><a href="../index.php">Início</a></li>
      <li><a href="index.php" class="active">Serviços</a></li>
      <li><a href="../clientes/index.php">Clientes</a></li>
      <li><a href="../orcamentos/index.php">Orçamentos</a></li>
      <li><a href="../relatorios/index.php">Relatórios</a></li>
    </ul></nav>
  </div>
</header>

<div class="container">
  <div class="page-title-row">
    <h1>Editar Serviço</h1>
    <a href="index.php" class="btn btn-ghost">← Voltar</a>
  </div>

  <div class="form-container">
    <?php if (!empty($erros['geral'])): echo showMessage($erros['geral'],'error'); endif; ?>

    <form method="post">
      <div class="form-section">
        <div class="form-section-title">Informações do Serviço</div>

        <div class="form-group">
          <label>Nome *</label>
          <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" required>
          <?php if (isset($erros['nome'])): ?><span class="error-text"><?= $erros['nome'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Descrição</label>
          <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars($descricao) ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($categoria) ?>">
          </div>
          <div class="form-group">
            <label>Duração (horas)</label>
            <input type="number" name="duracao_estimada" class="form-control" value="<?= $duracao_estimada ?>" min="0">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Valor (R$) *</label>
            <input type="text" name="valor" class="form-control" value="<?= htmlspecialchars($valor) ?>" required>
            <?php if (isset($erros['valor'])): ?><span class="error-text"><?= $erros['valor'] ?></span><?php endif; ?>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
              <option value="1" <?= $status==1?'selected':'' ?>>Ativo</option>
              <option value="0" <?= $status==0?'selected':'' ?>>Inativo</option>
            </select>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary btn-lg">Atualizar Serviço</button>
        <a href="index.php" class="btn btn-ghost btn-lg">Cancelar</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
