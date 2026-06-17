<?php
// Lê o .env manualmente
$env = parse_ini_file(__DIR__ . '/../.env');

define('APP_BASE_URL',          $env['APP_BASE_URL']);
define('GOOGLE_CLIENT_ID',      $env['GOOGLE_CLIENT_ID']);
define('GOOGLE_CLIENT_SECRET',  $env['GOOGLE_CLIENT_SECRET']);
define('GOOGLE_REDIRECT_URI',   APP_BASE_URL . '/oauth/google_callback.php');
define('FACEBOOK_APP_ID',       $env['FACEBOOK_APP_ID']);
define('FACEBOOK_APP_SECRET',   $env['FACEBOOK_APP_SECRET']);
define('FACEBOOK_REDIRECT_URI', APP_BASE_URL . '/oauth/facebook_callback.php');
define('FACEBOOK_API_VERSION',  'v19.0');