<?php
session_start();
require_once 'config.php';
require_once 'config_multitenant.php';

// Registra logout no log
if (isLoggedIn()) {
    logActivity('Logout realizado');
}

// Destroi a sessão
session_destroy();

// Remove cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Redireciona para a página de login
header('Location: index.php');
exit;
