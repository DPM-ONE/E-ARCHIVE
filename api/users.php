<?php
/**
 * API USERS - CRUD complet
 * api/users.php
 * DPM Archive
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once '../config/database.php';
require_once '../config/session.php';

// Vérification authentification + rôle
requireLogin();

if ($_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    $pdo = getPDO();
    $action = trim($_POST['action'] ?? '');

    switch ($action) {

        // ============================================================
        // CREATE
        // ============================================================
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'lecteur');
            $agentId = !empty($_POST['agent_id']) ? (int) $_POST['agent_id'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $permissions = $_POST['permissions'] ?? '{}';

            // Validation
            validateUsername($username);
            validateEmail($email);
            validatePassword($password);
            validateRole($role);
            validatePermissionsJson($permissions);

            // Vérifier unicité
            checkUnique($pdo, 'username', $username);
            checkUnique($pdo, 'email', $email);

            // Vérifier agent disponible
            if ($agentId) {
                checkAgentAvailable($pdo, $agentId);
            }

            // Hash password
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, email, password_hash,
                    agent_id, role, permissions,
                    is_active, password_changed_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $username,
                $email,
                $hash,
                $agentId,
                $role,
                $permissions,
                $isActive,
                $_SESSION['user_id']
            ]);

            $newId = $pdo->lastInsertId();

            // Audit
            logAudit($pdo, 'CREATE_USER', 'users', $newId, "Création utilisateur: $username");

            echo json_encode([
                'success' => true,
                'message' => "Utilisateur « $username » créé avec succès",
                'id' => $newId
            ]);
            break;


        // ============================================================
        // UPDATE
        // ============================================================
        case 'update':
            $userId = (int) ($_POST['user_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = trim($_POST['role'] ?? 'lecteur');
            $agentId = !empty($_POST['agent_id']) ? (int) $_POST['agent_id'] : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $permissions = $_POST['permissions'] ?? '{}';

            if (!$userId)
                throw new Exception('ID utilisateur invalide');

            // Vérifier que l'utilisateur existe
            $existing = getUserById($pdo, $userId);

            // Validation
            validateUsername($username);
            validateEmail($email);
            validateRole($role);
            validatePermissionsJson($permissions);

            // Vérifier unicité (exclure l'utilisateur courant)
            checkUnique($pdo, 'username', $username, $userId);
            checkUnique($pdo, 'email', $email, $userId);

            // Préparer la mise à jour
            if (!empty($password)) {
                validatePassword($password);
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $stmt = $pdo->prepare("
                    UPDATE users SET
                        username = ?, email = ?, password_hash = ?,
                        agent_id = ?, role = ?, permissions = ?,
                        is_active = ?, password_changed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username,
                    $email,
                    $hash,
                    $agentId,
                    $role,
                    $permissions,
                    $isActive,
                    $userId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        username = ?, email = ?,
                        agent_id = ?, role = ?, permissions = ?,
                        is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username,
                    $email,
                    $agentId,
                    $role,
                    $permissions,
                    $isActive,
                    $userId
                ]);
            }

            // Audit
            logAudit($pdo, 'UPDATE_USER', 'users', $userId, "Modification utilisateur: $username");

            echo json_encode([
                'success' => true,
                'message' => "Utilisateur « $username » modifié avec succès"
            ]);
            break;


        // ============================================================
        // TOGGLE STATUS (Activer / Désactiver)
        // ============================================================
        case 'toggle_status':
            $userId = (int) ($_POST['user_id'] ?? 0);
            $isActive = (int) ($_POST['is_active'] ?? 0);

            if (!$userId)
                throw new Exception('ID utilisateur invalide');

            // Empêcher l'auto-désactivation
            if ($userId === (int) $_SESSION['user_id']) {
                throw new Exception('Vous ne pouvez pas modifier votre propre statut');
            }

            $existing = getUserById($pdo, $userId);

            $stmt = $pdo->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$isActive, $userId]);

            $msg = $isActive ? 'activé' : 'désactivé';
            logAudit($pdo, 'TOGGLE_USER', 'users', $userId, "Compte {$msg}: {$existing['username']}");

            echo json_encode([
                'success' => true,
                'message' => "Compte « {$existing['username']} » $msg avec succès"
            ]);
            break;


        // ============================================================
        // UNLOCK (Débloquer)
        // ============================================================
        case 'unlock':
            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId)
                throw new Exception('ID utilisateur invalide');

            $existing = getUserById($pdo, $userId);

            $stmt = $pdo->prepare("
                UPDATE users
                SET failed_login_attempts = 0,
                    last_failed_login = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId]);

            logAudit($pdo, 'UNLOCK_USER', 'users', $userId, "Déblocage compte: {$existing['username']}");

            echo json_encode([
                'success' => true,
                'message' => "Compte « {$existing['username']} » débloqué avec succès"
            ]);
            break;


        // ============================================================
        // RESET PASSWORD
        // ============================================================
        case 'reset_password':
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';

            if (!$userId)
                throw new Exception('ID utilisateur invalide');

            validatePassword($newPassword);

            $existing = getUserById($pdo, $userId);
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password_hash = ?,
                    password_changed_at = NOW(),
                    failed_login_attempts = 0,
                    session_token = NULL,
                    session_expires = NULL,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$hash, $userId]);

            logAudit($pdo, 'RESET_PASSWORD', 'users', $userId, "Réinitialisation MDP: {$existing['username']}");

            echo json_encode([
                'success' => true,
                'message' => "Mot de passe réinitialisé pour « {$existing['username']} »"
            ]);
            break;


        // ============================================================
        // DELETE
        // ============================================================
        case 'delete':
            $userId = (int) ($_POST['user_id'] ?? 0);
            if (!$userId)
                throw new Exception('ID utilisateur invalide');

            // Empêcher l'auto-suppression
            if ($userId === (int) $_SESSION['user_id']) {
                throw new Exception('Vous ne pouvez pas supprimer votre propre compte');
            }

            $existing = getUserById($pdo, $userId);

            // Empêcher la suppression du dernier super_admin
            if ($existing['role'] === 'super_admin') {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
                if ($stmt->fetchColumn() <= 1) {
                    throw new Exception('Impossible de supprimer le dernier Super Admin actif');
                }
            }

            // Invalider la session de l'utilisateur supprimé
            $stmt = $pdo->prepare("
                UPDATE users
                SET session_token = NULL, session_expires = NULL
                WHERE id = ?
            ");
            $stmt->execute([$userId]);

            // Suppression physique
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);

            logAudit($pdo, 'DELETE_USER', 'users', $userId, "Suppression utilisateur: {$existing['username']}");

            echo json_encode([
                'success' => true,
                'message' => "Utilisateur « {$existing['username']} » supprimé"
            ]);
            break;


        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Action « $action » non reconnue"]);
            break;
    }

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
 * Récupérer un utilisateur par ID
 */
function getUserById(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user)
        throw new Exception("Utilisateur #$id introuvable");
    return $user;
}

/**
 * Vérifier l'unicité d'un champ
 */
function checkUnique(PDO $pdo, string $field, string $value, int $excludeId = 0): void
{
    $sql = "SELECT id FROM users WHERE `$field` = ?";
    $params = [$value];
    if ($excludeId > 0) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        throw new Exception("Ce « $field » est déjà utilisé");
    }
}

/**
 * Vérifier qu'un agent n'a pas déjà de compte
 */
function checkAgentAvailable(PDO $pdo, int $agentId): void
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE agent_id = ?");
    $stmt->execute([$agentId]);
    if ($stmt->fetch()) {
        throw new Exception("Cet agent possède déjà un compte utilisateur");
    }
}

/**
 * Validation username
 */
function validateUsername(string $username): void
{
    if (empty($username)) {
        throw new Exception("Le nom d'utilisateur est obligatoire");
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) {
        throw new Exception("Nom d'utilisateur invalide (3-50 caractères : lettres, chiffres, . _ -)");
    }
}

/**
 * Validation email
 */
function validateEmail(string $email): void
{
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Format d'email invalide");
    }
}

/**
 * Validation mot de passe
 */
function validatePassword(string $password): void
{
    if (strlen($password) < 8) {
        throw new Exception("Le mot de passe doit contenir au moins 8 caractères");
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        throw new Exception("Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre");
    }
}

/**
 * Validation rôle
 */
function validateRole(string $role): void
{
    $allowed = ['super_admin', 'admin', 'archiviste', 'lecteur'];
    if (!in_array($role, $allowed)) {
        throw new Exception("Rôle invalide");
    }
}

/**
 * Validation JSON permissions
 */
function validatePermissionsJson(string $json): void
{
    if (!empty($json)) {
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Format des permissions invalide");
        }
    }
}

/**
 * Journalisation des actions (audit)
 */
function logAudit(PDO $pdo, string $action, string $table, int $recordId, string $details = ''): void
{
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_log (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                action VARCHAR(50),
                table_name VARCHAR(50),
                record_id INT,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_table (table_name),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $table,
            $recordId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);
    } catch (Exception $e) {
        error_log("Erreur audit_log : " . $e->getMessage());
    }
}