<?php
// chat/iniciar.php — Cria ou abre uma conversa entre cliente e empresa
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();

if (!isCliente()) { header('Location: ../index.php'); exit; }

$cid        = $_SESSION['cliente_id'];
$empresa_id = (int)($_GET['empresa_id']   ?? $_POST['empresa_id']  ?? 0);
$orc_id     = (int)($_GET['orcamento_id'] ?? $_POST['orcamento_id'] ?? 0);

if (!$empresa_id) { header('Location: ../clientes/empresas.php'); exit; }

// Verifica se empresa existe
$emp = $pdo->prepare("SELECT id, nome_empresa FROM empresas WHERE id = ? AND status = 1");
$emp->execute([$empresa_id]);
if (!$emp->fetch()) { header('Location: ../clientes/empresas.php'); exit; }

// Tenta achar conversa existente (ignorando orcamento_id para não duplicar)
$stmt = $pdo->prepare("SELECT id FROM conversas WHERE cliente_id = ? AND empresa_id = ?");
$stmt->execute([$cid, $empresa_id]);
$existing = $stmt->fetch();

if ($existing) {
    // Atualiza o orcamento_id se fornecido
    if ($orc_id) {
        $pdo->prepare("UPDATE conversas SET orcamento_id = ? WHERE id = ?")
            ->execute([$orc_id, $existing['id']]);
    }
    header('Location: conversa.php?id='.$existing['id']); exit;
}

// Cria nova conversa
$ins = $pdo->prepare("INSERT INTO conversas (cliente_id, empresa_id, orcamento_id) VALUES (?,?,?)");
$ins->execute([$cid, $empresa_id, $orc_id ?: null]);
$conv_id = $pdo->lastInsertId();

header('Location: conversa.php?id='.$conv_id); exit;
