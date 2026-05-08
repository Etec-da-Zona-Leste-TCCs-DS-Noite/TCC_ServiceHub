<?php
session_start();
http_response_code(404);
$logado = isset($_SESSION['tipo_usuario']);
$home   = $logado
    ? ($_SESSION['tipo_usuario'] === 'cliente' ? 'dashboard_cliente.php' : 'dashboard_empresa.php')
    : 'index.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Página não encontrada — ServiceHub</title>
  <link rel="stylesheet" href="css/estilo.css">
  <style>
    .not-found-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      text-align: center;
      padding: 40px 20px;
      background: linear-gradient(135deg, var(--navy) 0%, var(--navy-soft) 100%);
    }
    .code-404 {
      font-family: 'Playfair Display', serif;
      font-size: 120px;
      font-weight: 700;
      color: var(--gold);
      line-height: 1;
      margin-bottom: 16px;
      opacity: .85;
    }
    .not-found-wrap h2 { color: #fff; font-size: 26px; margin-bottom: 12px; }
    .not-found-wrap p  { color: var(--slate); font-size: 15px; margin-bottom: 32px; max-width: 420px; }
  </style>
</head>
<body>
<div class="not-found-wrap">
  <div class="code-404">404</div>
  <h2>Página não encontrada</h2>
  <p>A página que você tentou acessar não existe ou foi removida. Volte para o início e continue navegando.</p>
  <a href="<?= $home ?>" class="btn btn-primary btn-lg">← Voltar ao início</a>
</div>
</body>
</html>
