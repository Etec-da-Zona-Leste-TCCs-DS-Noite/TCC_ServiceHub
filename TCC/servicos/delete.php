<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id=?");
    $msg  = $stmt->execute([$id]) ? urlencode('Serviço excluído com sucesso!') : urlencode('Erro ao excluir serviço.');
    $type = $stmt->rowCount() ? 'success' : 'error';
    header("Location: index.php?msg=$msg&type=$type");
} else {
    header('Location: index.php');
}
exit;
