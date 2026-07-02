<?php
require_once '../includes/bootstrap.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
csrfVerify();

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $empresa_id = $_SESSION['empresa_id'];
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id=? AND empresa_id=?");
    $msg  = $stmt->execute([$id, $empresa_id]) ? urlencode('Serviço excluído com sucesso!') : urlencode('Erro ao excluir serviço.');
    $type = $stmt->rowCount() ? 'success' : 'error';
    header("Location: index.php?msg=$msg&type=$type");
} else {
    header('Location: index.php');
}
exit;
