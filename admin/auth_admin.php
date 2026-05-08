<?php
// admin/auth_admin.php — Inclua no topo de todas as páginas do painel admin
if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: login.php');
    exit;
}
