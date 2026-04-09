<?php
// includes/auth.php
// NOTA: session_start() NÃO deve ficar aqui — já é chamado nos arquivos que o incluem.

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
        header('Location: ' . $prefix . 'index.php');
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
