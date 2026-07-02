<?php
/**
 * includes/bootstrap.php
 * Ponto único de inicialização: sessão endurecida + config + auth + funções + CSRF.
 * Use no lugar de "session_start(); require config/auth/functions" nas páginas novas.
 * (Páginas antigas continuam funcionando sem alteração — migração é opcional/gradual.)
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sessão endurecido — precisa ser configurado ANTES do session_start().
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

/* ──────────────────────────────────────────────────────────
 * CSRF — token por sessão, validado em toda ação de escrita.
 * ────────────────────────────────────────────────────────── */

/** Retorna o token atual, gerando um novo se ainda não existir. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Imprime o campo hidden pronto para colar dentro de um <form>. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Valida o token recebido em $_POST (ou array custom). Encerra a requisição com 403
 * se for inválido/ausente — chamar no topo de qualquer script que processe POST.
 */
function csrfVerify(?array $source = null): void {
    $source = $source ?? $_POST;
    $token  = $source['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Requisição inválida (token de segurança ausente ou expirado). Volte e tente novamente.');
    }
}
