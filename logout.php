<?php
/**
 * logout.php
 */

require_once 'config/session.php';

// Supprimer toutes les variables de session
$_SESSION = [];

// Supprimer le cookie de session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Supprimer le cookie remember_me
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Detruire la session
session_destroy();

// Rediriger vers login
header('Location: login.php');
exit;