<?php
// includes/auth.php
// NOTA: session_start() NÃO deve ficar aqui — já é chamado nos arquivos que o incluem.

const LOGIN_MAX_TENTATIVAS = 5;
const LOGIN_BLOQUEIO_SEGUNDOS = 60;

/**
 * Freio simples de força bruta por sessão + e-mail: após LOGIN_MAX_TENTATIVAS
 * falhas seguidas, bloqueia novas tentativas por LOGIN_BLOQUEIO_SEGUNDOS.
 */
function loginLiberado($email) {
    $chave = 'login_falhas_' . md5(strtolower(trim($email)));
    $dados = $_SESSION[$chave] ?? ['tentativas' => 0, 'bloqueado_ate' => 0];
    return $dados['bloqueado_ate'] <= time();
}

function registrarFalhaLogin($email) {
    $chave = 'login_falhas_' . md5(strtolower(trim($email)));
    $dados = $_SESSION[$chave] ?? ['tentativas' => 0, 'bloqueado_ate' => 0];
    $dados['tentativas']++;
    if ($dados['tentativas'] >= LOGIN_MAX_TENTATIVAS) {
        $dados['bloqueado_ate'] = time() + LOGIN_BLOQUEIO_SEGUNDOS;
        $dados['tentativas'] = 0;
    }
    $_SESSION[$chave] = $dados;
}

function limparFalhasLogin($email) {
    unset($_SESSION['login_falhas_' . md5(strtolower(trim($email)))]);
}

function segundosRestantesBloqueio($email) {
    $chave = 'login_falhas_' . md5(strtolower(trim($email)));
    $dados = $_SESSION[$chave] ?? null;
    if (!$dados) return 0;
    return max(0, $dados['bloqueado_ate'] - time());
}

function loginCliente($email, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();

    if ($cliente && password_verify($senha, $cliente['senha'])) {
        $_SESSION['cliente_id']    = $cliente['id'];
        $_SESSION['cliente_nome']  = $cliente['nome'];
        $_SESSION['cliente_email'] = $cliente['email'];
        $_SESSION['tipo_usuario']  = 'cliente';
        return true;
    }
    // Fallback para md5 (migração de contas antigas)
    if ($cliente && $cliente['senha'] === md5($senha)) {
        // Atualiza para password_hash
        $pdo->prepare("UPDATE clientes SET senha=? WHERE id=?")->execute([password_hash($senha, PASSWORD_DEFAULT), $cliente['id']]);
        $_SESSION['cliente_id']    = $cliente['id'];
        $_SESSION['cliente_nome']  = $cliente['nome'];
        $_SESSION['cliente_email'] = $cliente['email'];
        $_SESSION['tipo_usuario']  = 'cliente';
        return true;
    }
    return false;
}

function loginEmpresa($email, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ? AND status = 1");
    $stmt->execute([$email]);
    $empresa = $stmt->fetch();

    if ($empresa && password_verify($senha, $empresa['senha'])) {
        $_SESSION['empresa_id']    = $empresa['id'];
        $_SESSION['empresa_nome']  = $empresa['nome_empresa'];
        $_SESSION['empresa_email'] = $empresa['email'];
        $_SESSION['tipo_usuario']  = 'empresa';
        return true;
    }
    // Fallback para md5 (migração de contas antigas)
    if ($empresa && $empresa['senha'] === md5($senha)) {
        $pdo->prepare("UPDATE empresas SET senha=? WHERE id=?")->execute([password_hash($senha, PASSWORD_DEFAULT), $empresa['id']]);
        $_SESSION['empresa_id']    = $empresa['id'];
        $_SESSION['empresa_nome']  = $empresa['nome_empresa'];
        $_SESSION['empresa_email'] = $empresa['email'];
        $_SESSION['tipo_usuario']  = 'empresa';
        return true;
    }
    return false;
}

/**
 * Redireciona para a raiz do projeto independentemente da subpasta atual.
 */
function verificarLogin() {
    if (!isset($_SESSION['tipo_usuario'])) {
        $scriptDir = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $depth     = ($scriptDir === '' || $scriptDir === '.') ? 0 : substr_count($scriptDir, '/') + 1;
        $prefix    = $depth > 0 ? str_repeat('../', $depth) : '';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }
}

function isCliente() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'cliente';
}

function isEmpresa() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'empresa';
}

function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}
