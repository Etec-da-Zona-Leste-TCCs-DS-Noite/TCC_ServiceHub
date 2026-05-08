<?php
// chat/typing.php — Registra status "digitando"
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();

header('Content-Type: application/json');

$conversa_id = (int)($_GET['conversa_id'] ?? 0);
$remetente   = $_GET['remetente'] === 'empresa' ? 'empresa' : 'cliente';
$status      = (int)($_GET['status'] ?? 0) ? 1 : 0;

if (!$conversa_id) { echo json_encode(['ok' => false]); exit; }

// Cria tabela de typing se não existir (auto-bootstrap)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_typing (
        conversa_id INT NOT NULL,
        remetente   ENUM('cliente','empresa') NOT NULL,
        status      TINYINT DEFAULT 0,
        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (conversa_id, remetente)
    ) ENGINE=InnoDB");

    $pdo->prepare("INSERT INTO chat_typing (conversa_id, remetente, status)
                   VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()")
        ->execute([$conversa_id, $remetente, $status]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false]);
}
