<?php
/**
 * CONFIGURATION DES SESSIONS
 * Gestion sécurisée des sessions utilisateur
 */

// Configuration session sécurisée
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mettre à 1 en HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Durée de vie session : 30 minutes d'inactivité
ini_set('session.gc_maxlifetime', 1800);

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'utilisateur est connecté
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Vérifier si la session est expirée
 */
function isSessionExpired()
{
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > 1800) { // 30 minutes
            return true;
        }
    }
    return false;
}

/**
 * Mettre à jour le timestamp d'activité
 */
function updateActivity()
{
    $_SESSION['last_activity'] = time();
}

/**
 * Créer une session utilisateur
 */
function createUserSession($user)
{
    // Régénérer l'ID de session (sécurité)
    session_regenerate_id(true);

    // Stocker les données utilisateur
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['agent_id'] = $user['agent_id'];
    $_SESSION['permissions'] = $user['permissions'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

    // Mettre à jour last_login dans la BDD
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
    } catch (Exception $e) {
        error_log("Erreur mise à jour last_login : " . $e->getMessage());
    }
}

/**
 * Détruire la session
 */
function destroyUserSession()
{
    $_SESSION = [];

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_destroy();
}

/**
 * Vérifier le rôle de l'utilisateur
 */
function hasRole($role)
{
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

/**
 * Vérifier une permission spécifique
 */
function hasPermission($module, $action)
{
    if (!isLoggedIn()) {
        return false;
    }

    // Super admin a tous les droits
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }

    // Vérifier dans les permissions JSON
    if (isset($_SESSION['permissions'])) {
        $permissions = json_decode($_SESSION['permissions'], true);
        if (isset($permissions[$module][$action])) {
            return $permissions[$module][$action] === true;
        }
    }

    return false;
}

/**
 * Rediriger si non connecté
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    if (isSessionExpired()) {
        destroyUserSession();
        header('Location: login.php?error=session_expired');
        exit;
    }

    updateActivity();
}

/**
 * Rediriger si pas le bon rôle
 */
function requireRole($role)
{
    requireLogin();
    if (!hasRole($role)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}

/**
 * Rediriger si pas la permission
 */
function requirePermission($module, $action)
{
    requireLogin();
    if (!hasPermission($module, $action)) {
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
}