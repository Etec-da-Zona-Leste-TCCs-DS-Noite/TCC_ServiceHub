<?php
// ================================================================
//  ServiceHub — Google OAuth 2.0 Callback
//  API: Google Identity Platform (OAuth 2.0)
//  Docs: https://developers.google.com/identity/protocols/oauth2
// ================================================================
session_start();
require_once '../includes/config.php';
require_once '../includes/oauth_config.php';

// ── Valida state (proteção CSRF) ─────────────────────────────
if (!isset($_GET['state']) || !isset($_SESSION['oauth_state'])
    || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: ../index.php?msg=Acesso+inválido&type=error');
    exit;
}
unset($_SESSION['oauth_state']);

// ── Troca code por access_token ──────────────────────────────
if (!isset($_GET['code'])) {
    header('Location: ../index.php?msg=Autenticação+cancelada&type=error');
    exit;
}

$tokenResp = file_get_contents('https://oauth2.googleapis.com/token', false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ]])
);

$token = json_decode($tokenResp, true);
if (empty($token['access_token'])) {
    header('Location: ../index.php?msg=Falha+ao+obter+token&type=error');
    exit;
}

// ── Obtém dados do usuário via Google People API ─────────────
$userResp = file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false,
    stream_context_create(['http' => [
        'header' => "Authorization: Bearer {$token['access_token']}\r\n",
    ]])
);

$gu = json_decode($userResp, true);
if (empty($gu['email'])) {
    header('Location: ../index.php?msg=Não+foi+possível+obter+o+e-mail&type=error');
    exit;
}

$email  = strtolower(trim($gu['email']));
$nome   = $gu['name']  ?? explode('@', $email)[0];
$avatar = $gu['picture'] ?? null;
$tipo   = $_SESSION['oauth_tipo'] ?? 'cliente';

// ── Verifica / cria conta ────────────────────────────────────
if ($tipo === 'cliente') {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Cria conta automática — senha aleatória (nunca será usada)
        $pdo->prepare("INSERT INTO clientes (nome, email, senha, created_at)
                        VALUES (?, ?, ?, NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $user = $pdo->query("SELECT * FROM clientes WHERE email = " . $pdo->quote($email))->fetch();
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
        $pdo->prepare("INSERT INTO empresas (nome_empresa, email, senha, status, created_at)
                        VALUES (?, ?, ?, 1, NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $user = $pdo->query("SELECT * FROM empresas WHERE email = " . $pdo->quote($email))->fetch();
    }

    $_SESSION['empresa_id']    = $user['id'];
    $_SESSION['empresa_nome']  = $user['nome_empresa'];
    $_SESSION['empresa_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'empresa';

    header('Location: ../dashboard_empresa.php');
}
exit;
