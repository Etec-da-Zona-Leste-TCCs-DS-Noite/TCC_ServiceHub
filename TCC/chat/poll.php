<?php
// chat/poll.php — Polling de novas mensagens + status de "digitando"
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();

header('Content-Type: application/json');

$is_cliente  = isCliente();
$is_empresa  = isEmpresa();
$remetente   = $is_cliente ? 'cliente' : 'empresa';
$outroPerfil = $is_cliente ? 'empresa' : 'cliente';

$conversa_id = (int)($_GET['conversa_id'] ?? 0);
$last_id     = (int)($_GET['last_id']     ?? 0);

if (!$conversa_id) { echo json_encode(['mensagens' => [], 'typing' => false]); exit; }

// Valida acesso
$stmt = $pdo->prepare("SELECT * FROM conversas WHERE id = ?");
$stmt->execute([$conversa_id]);
$conv = $stmt->fetch();

if (!$conv
    || ($is_cliente && $conv['cliente_id'] != $_SESSION['cliente_id'])
    || ($is_empresa && $conv['empresa_id'] != $_SESSION['empresa_id'])) {
    echo json_encode(['mensagens' => [], 'typing' => false]); exit;
}

// Busca mensagens novas
$msgs = $pdo->prepare("SELECT * FROM mensagens WHERE conversa_id = ? AND id > ? ORDER BY created_at ASC");
$msgs->execute([$conversa_id, $last_id]);
$novas = $msgs->fetchAll();

// Marca mensagens do outro como lidas
if (!empty($novas)) {
    $pdo->prepare("UPDATE mensagens SET lida = 1 WHERE conversa_id = ? AND remetente = ? AND lida = 0")
        ->execute([$conversa_id, $outroPerfil]);
}

// Verifica status "digitando" via tabela temporária (cache em sessão simplificado)
// Usamos uma tabela de sessão simples via campo auxiliar no banco
$typing = false;
try {
    $t = $pdo->prepare("SELECT status, updated_at FROM chat_typing WHERE conversa_id = ? AND remetente = ?");
    $t->execute([$conversa_id, $outroPerfil]);
    $row = $t->fetch();
    if ($row && $row['status'] == 1) {
        // Expira após 5 segundos sem atualização
        $diff = time() - strtotime($row['updated_at']);
        $typing = $diff < 5;
    }
} catch (Exception $e) {
    // Tabela de typing pode não existir ainda
}

echo json_encode(['mensagens' => $novas, 'typing' => $typing]);
