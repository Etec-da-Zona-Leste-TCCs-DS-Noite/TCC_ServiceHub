<?php
// includes/auth.php
session_start();

function loginCliente($email, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ?");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch();
    
    if ($cliente && $cliente['senha'] === md5($senha)) {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nome'] = $cliente['nome'];
        $_SESSION['cliente_email'] = $cliente['email'];
        $_SESSION['tipo_usuario'] = 'cliente';
        return true;
    }
    return false;
}

function loginEmpresa($email, $senha, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE email = ? AND status = 1");
    $stmt->execute([$email]);
    $empresa = $stmt->fetch();
    
    if ($empresa && $empresa['senha'] === md5($senha)) {
        $_SESSION['empresa_id'] = $empresa['id'];
        $_SESSION['empresa_nome'] = $empresa['nome_empresa'];
        $_SESSION['empresa_email'] = $empresa['email'];
        $_SESSION['tipo_usuario'] = 'empresa';
        return true;
    }
    return false;
}

function verificarLogin() {
    if (!isset($_SESSION['tipo_usuario'])) {
        header('Location: index.php');
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
?>