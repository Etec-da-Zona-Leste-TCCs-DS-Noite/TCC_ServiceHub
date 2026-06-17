<?php
// ================================================================
//  ServiceHub — GitHub OAuth 2.0 Callback
//  API: GitHub OAuth Apps
//  Docs: https://docs.github.com/en/apps/oauth-apps
// ================================================================
session_start();
require_once '../includes/config.php';
require_once '../includes/oauth_config.php';

if (!isset($_GET['state']) || !isset($_SESSION['oauth_state'])
    || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: ../index.php?msg=Acesso+inválido&type=error'); exit;
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    header('Location: ../index.php?msg=Autenticação+cancelada&type=error'); exit;
}

// ── Troca code por access_token ──────────────────────────────
$tokenResp = file_get_contents('https://github.com/login/oauth/access_token', false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => http_build_query([
            'client_id'     => GITHUB_CLIENT_ID,
            'client_secret' => GITHUB_CLIENT_SECRET,
            'code'          => $_GET['code'],
            'redirect_uri'  => GITHUB_REDIRECT_URI,
        ]),
    ]])
);

$token = json_decode($tokenResp, true);
if (empty($token['access_token'])) {
    header('Location: ../index.php?msg=Falha+ao+obter+token+GitHub&type=error'); exit;
}

// ── Obtém dados do usuário ───────────────────────────────────
$opts = ['http' => ['header' =>
    "Authorization: Bearer {$token['access_token']}\r\nUser-Agent: ServiceHub/1.0\r\n"]];

$gu = json_decode(file_get_contents('https://api.github.com/user', false,
    stream_context_create($opts)), true);

// E-mail pode ser nulo no GitHub — busca na API de emails
if (empty($gu['email'])) {
    $emails = json_decode(file_get_contents('https://api.github.com/user/emails', false,
        stream_context_create($opts)), true);
    foreach ((array)$emails as $e) {
        if (!empty($e['primary']) && !empty($e['email'])) {
            $gu['email'] = $e['email']; break;
        }
    }
}

if (empty($gu['email'])) {
    header('Location: ../index.php?msg=Não+foi+possível+obter+o+e-mail+do+GitHub&type=error'); exit;
}

$email  = strtolower(trim($gu['email']));
$nome   = $gu['name'] ?: $gu['login'] ?: explode('@', $email)[0];
$avatar = $gu['avatar_url'] ?? null;
$tipo   = $_SESSION['oauth_tipo'] ?? 'cliente';

// ── Verifica / cria conta (mesmo padrão do Google) ───────────
if ($tipo === 'cliente') {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare("INSERT INTO clientes (nome, email, senha, created_at) VALUES (?,?,?,NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $user = $pdo->query("SELECT * FROM clientes WHERE email=" . $pdo->quote($email))->fetch();
    }
    $_SESSION['cliente_id']    = $user['id'];
    $_SESSION['cliente_nome']  = $user['nome'];
    $_SESSION['cliente_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'cliente';
    if ($avatar) $_SESSION['avatar_url'] = $avatar;
    header('Location: ../dashboard_cliente.php');
} else {
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare("INSERT INTO empresas (nome_empresa, email, senha, status, created_at) VALUES (?,?,?,1,NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $user = $pdo->query("SELECT * FROM empresas WHERE email=" . $pdo->quote($email))->fetch();
    }
    $_SESSION['empresa_id']    = $user['id'];
    $_SESSION['empresa_nome']  = $user['nome_empresa'];
    $_SESSION['empresa_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'empresa';
    header('Location: ../dashboard_empresa.php');
}
exit;
