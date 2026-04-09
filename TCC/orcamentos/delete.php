<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $pdo->prepare("DELETE FROM orcamento_itens WHERE orcamento_id=?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM orcamentos WHERE id=?");
    $ok   = $stmt->execute([$id]);
    header('Location: index.php?msg='.urlencode($ok?'Orçamento excluído!':'Erro ao excluir.').'&type='.($ok?'success':'error'));
} else {
    header('Location: index.php');
}
exit;
