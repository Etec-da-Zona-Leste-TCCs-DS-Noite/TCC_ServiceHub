<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
verificarLogin();
if (!isEmpresa()) { header('Location: ../index.php'); exit; }
header('Location: perfil.php');
exit;
