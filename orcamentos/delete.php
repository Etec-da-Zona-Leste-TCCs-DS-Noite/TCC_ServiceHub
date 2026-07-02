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
    // Verifica se o orçamento pertence à empresa logada
    $check = $pdo->prepare("SELECT id FROM orcamentos WHERE id=? AND empresa_id=?");
    $check->execute([$id, $empresa_id]);
    if (!$check->fetch()) {
        header('Location: index.php?msg='.urlencode('Acesso negado.').'&type=error');
        exit;
    }
    $pdo->prepare("DELETE FROM orcamento_itens WHERE orcamento_id=?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM orcamentos WHERE id=? AND empresa_id=?");
    $ok   = $stmt->execute([$id, $empresa_id]);
    header('Location: index.php?msg='.urlencode($ok?'Orçamento excluído!':'Erro ao excluir.').'&type='.($ok?'success':'error'));
} else {
    header('Location: index.php');
}
exit;
