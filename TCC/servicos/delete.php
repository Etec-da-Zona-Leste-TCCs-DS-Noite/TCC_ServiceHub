<?php
require_once '../includes/config.php';

$id = $_GET['id'] ?? 0;

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM servicos WHERE id = ?");
    if ($stmt->execute([$id])) {
        header('Location: index.php?msg=' . urlencode('Serviço excluído com sucesso!') . '&type=success');
    } else {
        header('Location: index.php?msg=' . urlencode('Erro ao excluir serviço') . '&type=error');
    }
} else {
    header('Location: index.php');
}
exit;