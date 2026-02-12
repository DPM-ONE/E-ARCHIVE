<?php
/**
 * DÉCONNEXION
 * Détruit la session et redirige vers login
 */

require_once 'config/session.php';

// Détruire la session
destroyUserSession();

// Supprimer le cookie remember_token si existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Rediriger vers login
header('Location: login.php?message=logout_success');
exit;