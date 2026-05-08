<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$erros = []; $nome=$descricao=$categoria=''; $valor=''; $duracao_estimada=''; $status=1; $valor_a_definir=false;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nome             = cleanInput($_POST['nome'] ?? '');
    $descricao        = cleanInput($_POST['descricao'] ?? '');
    $categoria        = cleanInput($_POST['categoria'] ?? '');
    $valor_a_definir  = isset($_POST['valor_a_definir']);
    $valor            = $valor_a_definir ? null : str_replace(',','.',($_POST['valor'] ?? ''));
    $duracao_estimada = (int)($_POST['duracao_estimada'] ?? 0);
    $status           = (int)($_POST['status'] ?? 1);

    if (empty($nome)) $erros['nome'] = 'Nome é obrigatório.';
    if (!$valor_a_definir) {
        if ($valor === '' || $valor === null) $erros['valor'] = 'Informe o valor ou marque "Valor a definir".';
        elseif (!is_numeric($valor))          $erros['valor'] = 'Valor inválido.';
    }
    elseif (!is_numeric($valor)) $erros['valor'] = 'Valor inválido.';

    if (empty($erros)) {
        $empresa_id = $_SESSION['empresa_id'];
        $stmt = $pdo->prepare("INSERT INTO servicos (empresa_id, nome, descricao, valor, duracao_estimada, categoria, status) VALUES (?,?,?,?,?,?,?)");
        if ($stmt->execute([$empresa_id, $nome, $descricao, $valor, $duracao_estimada ?: null, $categoria, $status])) {
            header('Location: index.php?msg='.urlencode('Serviço cadastrado com sucesso!').'&type=success'); exit;
        }
        $erros['geral'] = 'Erro ao cadastrar serviço.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Novo Serviço — ServiceHub</title>
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
    <h1>Novo Serviço</h1>
    <a href="index.php" class="btn btn-ghost">← Voltar</a>
  </div>

  <div class="form-container">
    <?php if (!empty($erros['geral'])): echo showMessage($erros['geral'],'error'); endif; ?>

    <form method="post">
      <div class="form-section">
        <div class="form-section-title">Informações do Serviço</div>

        <div class="form-group">
          <label>Nome do Serviço *</label>
          <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" placeholder="Ex: Desenvolvimento Web" required>
          <?php if (isset($erros['nome'])): ?><span class="error-text"><?= $erros['nome'] ?></span><?php endif; ?>
        </div>

        <div class="form-group">
          <label>Descrição</label>
          <textarea name="descricao" class="form-control" rows="3" placeholder="Descreva o serviço oferecido..."><?= htmlspecialchars($descricao) ?></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Categoria</label>
            <input type="text" name="categoria" class="form-control" value="<?= htmlspecialchars($categoria) ?>" placeholder="Ex: Desenvolvimento">
          </div>
          <div class="form-group">
            <label>Duração Estimada (horas)</label>
            <input type="number" name="duracao_estimada" class="form-control" value="<?= $duracao_estimada ?>" placeholder="Ex: 40" min="0">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Valor (R$)</label>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
              <input type="checkbox" name="valor_a_definir" id="valor_a_definir"
                     <?= $valor_a_definir ? 'checked' : '' ?>
                     onchange="toggleValor(this)">
              <label for="valor_a_definir" style="margin:0;font-weight:normal;cursor:pointer;">
                Valor a definir (orçamento sob consulta)
              </label>
            </div>
            <input type="text" name="valor" id="valor_input" class="form-control"
                   value="<?= htmlspecialchars($valor ?? '') ?>"
                   placeholder="0,00"
                   <?= $valor_a_definir ? 'disabled style="background:#f0f0f0;color:#999;"' : '' ?>>
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
        <button type="submit" class="btn btn-primary btn-lg">Salvar Serviço</button>
        <a href="index.php" class="btn btn-ghost btn-lg">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<script>
function toggleValor(cb) {
  const inp = document.getElementById('valor_input');
  inp.disabled = cb.checked;
  inp.style.background = cb.checked ? '#f0f0f0' : '';
  inp.style.color = cb.checked ? '#999' : '';
  if (cb.checked) inp.value = '';
}
</script>
</body>
</html>
