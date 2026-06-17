<?php
// ================================================================
//  ServiceHub — Facebook Login Callback
//  API: Facebook Login (OAuth 2.0)
//  Docs: https://developers.facebook.com/docs/facebook-login/web
//  Graph API: https://graph.facebook.com/v19.0/me
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

// ── Usuário cancelou ─────────────────────────────────────────
if (isset($_GET['error'])) {
    header('Location: ../index.php?msg=Login+cancelado&type=error');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: ../index.php?msg=Código+não+recebido&type=error');
    exit;
}

// ── Troca code por access_token ──────────────────────────────
$tokenUrl = 'https://graph.facebook.com/' . FACEBOOK_API_VERSION . '/oauth/access_token?'
    . http_build_query([
        'client_id'     => FACEBOOK_APP_ID,
        'client_secret' => FACEBOOK_APP_SECRET,
        'redirect_uri'  => FACEBOOK_REDIRECT_URI,
        'code'          => $_GET['code'],
    ]);

$tokenResp = file_get_contents($tokenUrl);
$token     = json_decode($tokenResp, true);

if (empty($token['access_token'])) {
    header('Location: ../index.php?msg=Falha+ao+obter+token+Facebook&type=error');
    exit;
}

// ── Obtém dados do usuário via Graph API ─────────────────────
// Campos: id, name, email, picture
$userUrl = 'https://graph.facebook.com/' . FACEBOOK_API_VERSION . '/me?'
    . http_build_query([
        'fields'       => 'id,name,email,picture.type(large)',
        'access_token' => $token['access_token'],
    ]);

$gu = json_decode(file_get_contents($userUrl), true);

if (empty($gu['email'])) {
    // Alguns usuários do Facebook não têm e-mail público — gera um fictício com o ID
    $gu['email'] = 'fb_' . $gu['id'] . '@facebook.servicehub';
}

$email  = strtolower(trim($gu['email']));
$nome   = $gu['name'] ?? explode('@', $email)[0];
$avatar = $gu['picture']['data']['url'] ?? null;
$tipo   = $_SESSION['oauth_tipo'] ?? 'cliente';

// ── Verifica / cria conta ────────────────────────────────────
if ($tipo === 'cliente') {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $pdo->prepare("INSERT INTO clientes (nome, email, senha, created_at)
                        VALUES (?, ?, ?, NOW())")
            ->execute([$nome, $email,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
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
            ->execute([$nome, $email,
                password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }

    $_SESSION['empresa_id']    = $user['id'];
    $_SESSION['empresa_nome']  = $user['nome_empresa'];
    $_SESSION['empresa_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'empresa';
    if ($avatar) $_SESSION['avatar_url'] = $avatar;

    header('Location: ../dashboard_empresa.php');
}
exit;
