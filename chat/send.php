<?php
// chat/send.php — Endpoint AJAX para envio de mensagem
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();

header('Content-Type: application/json');

$is_cliente = isCliente();
$is_empresa = isEmpresa();
$remetente  = $is_cliente ? 'cliente' : 'empresa';

$conversa_id = (int)($_POST['conversa_id'] ?? 0);
$conteudo    = trim($_POST['conteudo'] ?? '');

if (!$conversa_id || $conteudo === '') {
    echo json_encode(['ok' => false, 'erro' => 'Dados inválidos.']); exit;
}

// Valida acesso
$stmt = $pdo->prepare("SELECT * FROM conversas WHERE id = ?");
$stmt->execute([$conversa_id]);
$conv = $stmt->fetch();

if (!$conv) { echo json_encode(['ok' => false, 'erro' => 'Conversa não encontrada.']); exit; }
if ($is_cliente && $conv['cliente_id'] != $_SESSION['cliente_id']) { echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']); exit; }
if ($is_empresa && $conv['empresa_id'] != $_SESSION['empresa_id']) { echo json_encode(['ok' => false, 'erro' => 'Acesso negado.']); exit; }

// Insere mensagem
$ins = $pdo->prepare("INSERT INTO mensagens (conversa_id, remetente, conteudo) VALUES (?,?,?)");
$ins->execute([$conversa_id, $remetente, $conteudo]);
$msg_id = $pdo->lastInsertId();

// Atualiza timestamp da conversa
$pdo->prepare("UPDATE conversas SET updated_at = NOW() WHERE id = ?")->execute([$conversa_id]);

// Retorna a mensagem inserida
$msg = $pdo->prepare("SELECT * FROM mensagens WHERE id = ?");
$msg->execute([$msg_id]);
$mensagem = $msg->fetch();

echo json_encode(['ok' => true, 'mensagem' => $mensagem]);
