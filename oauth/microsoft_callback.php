<?php
// ================================================================
//  ServiceHub — Microsoft OAuth 2.0 Callback
//  API: Microsoft Identity Platform (MSAL)
//  Docs: https://learn.microsoft.com/en-us/azure/active-directory/develop/
// ================================================================
session_start();
require_once '../includes/config.php';
require_once '../includes/oauth_config.php';

if (!isset($_GET['state']) || !isset($_SESSION['oauth_state'])
    || $_GET['state'] !== $_SESSION['oauth_state']) {
    header('Location: ../login.php?msg=Acesso+inválido&type=error'); exit;
}
unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    header('Location: ../login.php?msg=Autenticação+cancelada&type=error'); exit;
}

$tokenUrl = "https://login.microsoftonline.com/" . MICROSOFT_TENANT_ID . "/oauth2/v2.0/token";

$tokenResp = file_get_contents($tokenUrl, false,
    stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'client_id'     => MICROSOFT_CLIENT_ID,
            'client_secret' => MICROSOFT_CLIENT_SECRET,
            'code'          => $_GET['code'],
            'redirect_uri'  => MICROSOFT_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
            'scope'         => 'openid email profile User.Read',
        ]),
    ]])
);

$token = json_decode($tokenResp, true);
if (empty($token['access_token'])) {
    header('Location: ../login.php?msg=Falha+ao+obter+token+Microsoft&type=error'); exit;
}

$gu = json_decode(file_get_contents('https://graph.microsoft.com/v1.0/me', false,
    stream_context_create(['http' => ['header' =>
        "Authorization: Bearer {$token['access_token']}\r\n"]])), true);

$email = strtolower(trim($gu['mail'] ?? $gu['userPrincipalName'] ?? ''));
if (!$email) {
    header('Location: ../login.php?msg=Não+foi+possível+obter+o+e-mail+Microsoft&type=error'); exit;
}

$nome = $gu['displayName'] ?? explode('@', $email)[0];
$tipo = $_SESSION['oauth_tipo'] ?? 'cliente';

if ($tipo === 'cliente') {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare("INSERT INTO clientes (nome, email, senha, created_at) VALUES (?,?,?,NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    }
    $_SESSION['cliente_id']    = $user['id'];
    $_SESSION['cliente_nome']  = $user['nome'];
    $_SESSION['cliente_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'cliente';
    header('Location: ../dashboard_cliente.php');
} else {
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $pdo->prepare("INSERT INTO empresas (nome_empresa, email, senha, status, created_at) VALUES (?,?,?,1,NOW())")
            ->execute([$nome, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ?"); $stmt->execute([$email]); $user = $stmt->fetch();
    }
    $_SESSION['empresa_id']    = $user['id'];
    $_SESSION['empresa_nome']  = $user['nome_empresa'];
    $_SESSION['empresa_email'] = $user['email'];
    $_SESSION['tipo_usuario']  = 'empresa';
    header('Location: ../dashboard_empresa.php');
}
exit;
