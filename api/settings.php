<?php
/**
 * api/users.php — CRUD table users
 * Actions : create | update | change_password | delete
 * Accès réservé : super_admin uniquement
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

/* ── Sortie JSON garantie ── */
function jsonOut(array $payload, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur PHP fatale : ' . $err['message']]);
    }
});

/* ── Auth : super_admin requis ── */
if (empty($_SESSION['user_id'])) {
    jsonOut(['success' => false, 'message' => 'Session expirée.'], 401);
}

$role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? '';
if ($role !== 'super_admin') {
    jsonOut(['success' => false, 'message' => 'Accès réservé aux super administrateurs.'], 403);
}

$currentUserId = (int)$_SESSION['user_id'];

/* ── Lecture du body JSON ── */
$raw  = file_get_contents('php://input');
$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

/* ── Rôles autorisés ── */
const VALID_ROLES = ['super_admin', 'admin', 'archiviste', 'lecteur'];

/* ── Helper : construire l'objet user pour la réponse JS ── */
function buildUserRow(PDO $pdo, int $id, int $currentUserId): array {
    $stmt = $pdo->prepare("
        SELECT u.*,
               a.nom       AS agent_nom,
               a.prenom    AS agent_prenom,
               a.matricule AS agent_matricule,
               a.poste     AS agent_poste
        FROM users u
        LEFT JOIN agents_dpm a ON a.id = u.agent_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return [];
    return [
        'id'              => (int)$u['id'],
        'username'        => $u['username'],
        'email'           => $u['email'],
        'role'            => $u['role'],
        'is_active'       => (bool)$u['is_active'],
        'agent_id'        => $u['agent_id'] ? (int)$u['agent_id'] : null,
        'agent_nom'       => $u['agent_nom']       ?? '',
        'agent_prenom'    => $u['agent_prenom']    ?? '',
        'agent_matricule' => $u['agent_matricule'] ?? '',
        'agent_poste'     => $u['agent_poste']     ?? '',
        'last_login'      => $u['last_login']      ?? '',
        'failed_attempts' => (int)$u['failed_login_attempts'],
        'created_at'      => $u['created_at']      ?? '',
        'is_self'         => ((int)$u['id'] === $currentUserId),
    ];
}

/* ══════════════════════════════════════════════════════════
   Routing
══════════════════════════════════════════════════════════ */
try {
    $pdo = getPDO();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ════════════════════════════
       CREATE
    ════════════════════════════ */
    if ($action === 'create') {

        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');
        $password = $data['password']      ?? '';
        $role_val = $data['role']          ?? 'lecteur';
        $agentId  = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        /* Validation */
        if (strlen($username) < 3)
            jsonOut(['success' => false, 'message' => 'Le nom d\'utilisateur doit faire au moins 3 caractères.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonOut(['success' => false, 'message' => 'Email invalide.']);
        if (strlen($password) < 8)
            jsonOut(['success' => false, 'message' => 'Le mot de passe doit faire au moins 8 caractères.']);
        if (!in_array($role_val, VALID_ROLES, true))
            jsonOut(['success' => false, 'message' => 'Rôle invalide.']);

        /* Unicité username + email */
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        if ($row = $chk->fetch()) {
            // Distinguer lequel est en conflit
            $chkU = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chkU->execute([$username]);
            if ($chkU->fetch())
                jsonOut(['success' => false, 'message' => "Le nom d'utilisateur « $username » est déjà utilisé."]);
            jsonOut(['success' => false, 'message' => "L'email « $email » est déjà utilisé."]);
        }

        /* Vérifier que l'agent existe et n'est pas déjà lié */
        if ($agentId) {
            $chkA = $pdo->prepare("SELECT id FROM agents_dpm WHERE id = ? AND deleted_at IS NULL");
            $chkA->execute([$agentId]);
            if (!$chkA->fetch())
                jsonOut(['success' => false, 'message' => 'Agent introuvable ou supprimé.']);

            $chkAU = $pdo->prepare("SELECT id FROM users WHERE agent_id = ?");
            $chkAU->execute([$agentId]);
            if ($chkAU->fetch())
                jsonOut(['success' => false, 'message' => 'Cet agent est déjà lié à un autre compte.']);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO users
                (username, email, password_hash, role, agent_id, is_active,
                 password_changed_at, created_by, created_at, updated_at)
            VALUES
                (:username, :email, :hash, :role, :agent_id, :is_active,
                 NOW(), :created_by, NOW(), NOW())
        ");
        $stmt->execute([
            ':username'   => $username,
            ':email'      => $email,
            ':hash'       => $hash,
            ':role'       => $role_val,
            ':agent_id'   => $agentId,
            ':is_active'  => $isActive,
            ':created_by' => $currentUserId,
        ]);

        $newId = (int)$pdo->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Utilisateur créé.', 'user' => buildUserRow($pdo, $newId, $currentUserId)]);
    }

    /* ════════════════════════════
       UPDATE
    ════════════════════════════ */
    if ($action === 'update') {

        $id       = (int)($data['id'] ?? 0);
        $username = trim($data['username'] ?? '');
        $email    = trim($data['email']    ?? '');
        $role_val = $data['role']          ?? '';
        $agentId  = !empty($data['agent_id']) ? (int)$data['agent_id'] : null;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;

        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide.']);

        /* Vérifier que l'utilisateur existe */
        $chk = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
        $chk->execute([$id]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) jsonOut(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);

        /* Empêcher de se retirer le rôle super_admin à soi-même */
        if ($id === $currentUserId && $role_val !== 'super_admin' && $existing['role'] === 'super_admin') {
            jsonOut(['success' => false, 'message' => 'Vous ne pouvez pas modifier votre propre rôle super_admin.']);
        }
        /* Empêcher de se désactiver soi-même */
        if ($id === $currentUserId && !$isActive) {
            jsonOut(['success' => false, 'message' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }

        if (strlen($username) < 3)
            jsonOut(['success' => false, 'message' => 'Le nom d\'utilisateur doit faire au moins 3 caractères.']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            jsonOut(['success' => false, 'message' => 'Email invalide.']);
        if (!in_array($role_val, VALID_ROLES, true))
            jsonOut(['success' => false, 'message' => 'Rôle invalide.']);

        /* Unicité username + email (hors soi-même) */
        $chkU = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
        $chkU->execute([$username, $id]);
        if ($chkU->fetch())
            jsonOut(['success' => false, 'message' => "Le nom d'utilisateur « $username » est déjà utilisé."]);

        $chkE = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $chkE->execute([$email, $id]);
        if ($chkE->fetch())
            jsonOut(['success' => false, 'message' => "L'email « $email » est déjà utilisé."]);

        /* Vérifier agent disponible */
        if ($agentId) {
            $chkA = $pdo->prepare("SELECT id FROM agents_dpm WHERE id = ? AND deleted_at IS NULL");
            $chkA->execute([$agentId]);
            if (!$chkA->fetch())
                jsonOut(['success' => false, 'message' => 'Agent introuvable ou supprimé.']);

            $chkAU = $pdo->prepare("SELECT id FROM users WHERE agent_id = ? AND id <> ?");
            $chkAU->execute([$agentId, $id]);
            if ($chkAU->fetch())
                jsonOut(['success' => false, 'message' => 'Cet agent est déjà lié à un autre compte.']);
        }

        $stmt = $pdo->prepare("
            UPDATE users SET
                username   = :username,
                email      = :email,
                role       = :role,
                agent_id   = :agent_id,
                is_active  = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':username'  => $username,
            ':email'     => $email,
            ':role'      => $role_val,
            ':agent_id'  => $agentId,
            ':is_active' => $isActive,
            ':id'        => $id,
        ]);

        jsonOut(['success' => true, 'message' => 'Utilisateur mis à jour.', 'user' => buildUserRow($pdo, $id, $currentUserId)]);
    }

    /* ════════════════════════════
       CHANGE PASSWORD
    ════════════════════════════ */
    if ($action === 'change_password') {

        $id  = (int)($data['id']       ?? 0);
        $pwd = $data['password'] ?? '';

        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide.']);
        if (strlen($pwd) < 8)
            jsonOut(['success' => false, 'message' => 'Le mot de passe doit faire au moins 8 caractères.']);

        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);

        $hash = password_hash($pwd, PASSWORD_BCRYPT);

        $pdo->prepare("
            UPDATE users SET
                password_hash        = :hash,
                password_changed_at  = NOW(),
                session_token        = NULL,
                session_expires      = NULL,
                updated_at           = NOW()
            WHERE id = :id
        ")->execute([':hash' => $hash, ':id' => $id]);

        jsonOut(['success' => true, 'message' => 'Mot de passe mis à jour. Les sessions actives ont été invalidées.']);
    }

    /* ════════════════════════════
       DELETE
    ════════════════════════════ */
    if ($action === 'delete') {

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide.']);

        /* Interdit de se supprimer soi-même */
        if ($id === $currentUserId)
            jsonOut(['success' => false, 'message' => 'Vous ne pouvez pas supprimer votre propre compte.']);

        $chk = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $chk->execute([$id]);
        $u = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$u) jsonOut(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => "Utilisateur « {$u['username']} » supprimé."]);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    $msg = $e->getCode() === '23000'
         ? 'Contrainte de référence — impossible d\'effectuer cette opération.'
         : 'Erreur SQL : ' . $e->getMessage();
    jsonOut(['success' => false, 'message' => $msg], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}