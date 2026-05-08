<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();
$id = (int)($_GET['id'] ?? 0);
header($id ? "Location: detalhe_empresa.php?id=$id" : "Location: empresas.php");
exit;
