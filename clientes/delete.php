<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
    if ($stmt->execute([$id])) {
        header('Location: index.php?msg=' . urlencode('Cliente excluído com sucesso!') . '&type=success');
    } else {
        header('Location: index.php?msg=' . urlencode('Erro ao excluir cliente') . '&type=error');
    }
} else {
    header('Location: index.php');
}
exit;