<?php
// chat/unread.php — Retorna contagem de mensagens não lidas (para badge no nav)
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['tipo_usuario'])) { echo json_encode(['count' => 0]); exit; }

if (isCliente()) {
    $cid = $_SESSION['cliente_id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM mensagens m
        JOIN conversas cv ON cv.id = m.conversa_id
        WHERE cv.cliente_id = ? AND m.remetente = 'empresa' AND m.lida = 0");
    $stmt->execute([$cid]);
} else {
    $eid = $_SESSION['empresa_id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM mensagens m
        JOIN conversas cv ON cv.id = m.conversa_id
        WHERE cv.empresa_id = ? AND m.remetente = 'cliente' AND m.lida = 0");
    $stmt->execute([$eid]);
}

echo json_encode(['count' => (int)$stmt->fetchColumn()]);
