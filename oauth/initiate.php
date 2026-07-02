<?php
// ================================================================
//  ServiceHub — OAuth Initiator
//  Uso: /oauth/initiate.php?provider=google&tipo=cliente
//       /oauth/initiate.php?provider=facebook&tipo=cliente
// ================================================================
session_start();
require_once '../includes/oauth_config.php';

$provider = $_GET['provider'] ?? '';
$tipo     = in_array($_GET['tipo'] ?? '', ['cliente', 'empresa']) ? $_GET['tipo'] : 'cliente';

// Estado anti-CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_tipo']  = $tipo;

switch ($provider) {

    case 'google':
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => GOOGLE_CLIENT_ID,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
        break;

    case 'facebook':
        $url = 'https://www.facebook.com/' . FACEBOOK_API_VERSION . '/dialog/oauth?' . http_build_query([
            'client_id'     => FACEBOOK_APP_ID,
            'redirect_uri'  => FACEBOOK_REDIRECT_URI,
            'response_type' => 'code',
            'scope'         => 'email,public_profile',
            'state'         => $state,
        ]);
        break;

    default:
        header('Location: ../login.php?msg=Provedor+inválido&type=error');
        exit;
}

header('Location: ' . $url);
exit;
