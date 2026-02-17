<?php
/**
 * API AUTHENTIFICATION
 * Gestion de la connexion utilisateur
 * api/auth.php
 */

// Headers JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Inclure les dépendances
require_once '../config/database.php';
require_once '../config/session.php';

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

try {
    // Récupérer les données
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validation basique
    if (empty($username) || empty($password)) {
        throw new Exception('Tous les champs sont obligatoires');
    }

    // Vérifier le format email si l'identifiant est un email
    if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
        if (!str_ends_with($username, '@dpmcongo.org')) {
            throw new Exception('Seuls les emails @dpmcongo.org sont autorisés');
        }
    }

    // Connexion BDD
    $pdo = getPDO();

    // Récupérer l'utilisateur (par username OU email)
    $stmt = $pdo->prepare("
        SELECT
            u.*,
            a.nom,
            a.prenom,
            a.service,
            a.poste
        FROM users u
        LEFT JOIN agents_dpm a ON u.agent_id = a.id
        WHERE (u.username = ? OR u.email = ?)
        AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    // Utilisateur introuvable
    if (!$user) {
        logFailedLogin($username, 'Utilisateur inexistant');
        throw new Exception('Identifiants incorrects');
    }

    // Vérifier si le compte est bloqué (3 tentatives max)
    if ($user['failed_login_attempts'] >= 3) {
        $lastFailed = strtotime($user['last_failed_login']);
        $timeDiff = time() - $lastFailed;

        if ($timeDiff < 900) {
            // Encore bloqué
            $minutesLeft = ceil((900 - $timeDiff) / 60);
            throw new Exception("Compte temporairement bloqué. Réessayez dans {$minutesLeft} minute(s).");
        } else {
            // Délai expiré → débloquer automatiquement
            resetFailedAttempts($user['id']);
            $user['failed_login_attempts'] = 0;
        }
    }

    // Vérifier le mot de passe
    if (!password_verify($password, $user['password_hash'])) {
        incrementFailedAttempts($user['id']);
        logFailedLogin($username, 'Mot de passe incorrect');

        // Informer du nombre de tentatives restantes
        $remaining = 2 - (int) $user['failed_login_attempts'];
        $msg = 'Identifiants incorrects';
        if ($remaining > 0) {
            $msg .= " ({$remaining} tentative(s) restante(s) avant blocage)";
        }

        throw new Exception($msg);
    }

    // Connexion réussie
    resetFailedAttempts($user['id']);
    createUserSession($user);

    // Cookie "Se souvenir de moi" (30 jours)
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare("
            UPDATE users
            SET session_token = ?, session_expires = ?
            WHERE id = ?
        ");
        $stmt->execute([$token, $expires, $user['id']]);

        setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', false, true);
    }

    // Logger la connexion réussie
    logSuccessfulLogin($user['id']);

    // Réponse succès
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'nom_complet' => trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''))
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


// ============================================================
// FONCTIONS UTILITAIRES
// ============================================================

/**
 * Incrémenter les tentatives échouées
 */
function incrementFailedAttempts(int $userId): void
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            UPDATE users
            SET failed_login_attempts = failed_login_attempts + 1,
                last_failed_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Erreur incrementFailedAttempts : " . $e->getMessage());
    }
}

/**
 * Réinitialiser les tentatives échouées
 */
function resetFailedAttempts(int $userId): void
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                last_failed_login = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Erreur resetFailedAttempts : " . $e->getMessage());
    }
}

/**
 * Logger une tentative échouée
 */
function logFailedLogin(string $username, string $reason): void
{
    try {
        $pdo = getPDO();

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_logs (
                id         INT PRIMARY KEY AUTO_INCREMENT,
                username   VARCHAR(100),
                ip_address VARCHAR(45),
                user_agent TEXT,
                status     ENUM('success', 'failed') DEFAULT 'failed',
                reason     VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $pdo->prepare("
            INSERT INTO login_logs (username, ip_address, user_agent, status, reason)
            VALUES (?, ?, ?, 'failed', ?)
        ");
        $stmt->execute([
            $username,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $reason
        ]);
    } catch (Exception $e) {
        error_log("Erreur logFailedLogin : " . $e->getMessage());
    }
}

/**
 * Logger une connexion réussie
 */
function logSuccessfulLogin(int $userId): void
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (username, ip_address, user_agent, status, reason)
            SELECT username, ?, ?, 'success', 'Connexion réussie'
            FROM users WHERE id = ?
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $userId
        ]);
    } catch (Exception $e) {
        error_log("Erreur logSuccessfulLogin : " . $e->getMessage());
    }
}