<?php
/**
 * api/gardes.php — CRUD garde_dpm
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

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

if (empty($_SESSION['user_id'])) {
    jsonOut(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 401);
}

function canWrite(): bool {
    $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? '';
    return in_array($role, ['super_admin', 'admin'], true);
}

$raw  = file_get_contents('php://input');
$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

/* ── Helper : récupérer une garde complète avec jointures ── */
function fetchGarde(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT g.id, g.mois, g.jour, g.groupe_id, g.departement_id,
               gr.groupe AS groupe_libelle,
               d.libelle AS dept_libelle,
               g.created_at, g.updated_at
        FROM garde_dpm g
        LEFT JOIN groupe_dpm       gr ON gr.id = g.groupe_id
        LEFT JOIN departements_dpm d  ON d.id  = g.departement_id
        WHERE g.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['groupe_id']      = (int)$row['groupe_id'];
        $row['departement_id'] = $row['departement_id'] ? (int)$row['departement_id'] : null;
    }
    return $row ?: null;
}

try {
    $pdo = getPDO();

    /* ── GET ─────────────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        $row = fetchGarde($pdo, $id);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Garde introuvable'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ════════════
       CREATE
    ════════════ */
    if ($action === 'create') {
        if (!canWrite()) {
            jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);
        }

        $jour    = trim($data['jour']    ?? '');
        $grpId   = (int)($data['groupe_id']    ?? 0);
        $deptId  = !empty($data['departement_id']) ? (int)$data['departement_id'] : null;

        if (!$jour || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour))
            jsonOut(['success' => false, 'message' => 'Date invalide (format attendu : YYYY-MM-DD)']);
        if (!$grpId)
            jsonOut(['success' => false, 'message' => 'Groupe obligatoire']);

        /* Calculer mois = premier jour du mois du jour */
        $mois = date('Y-m-01', strtotime($jour));

        /* Vérifier contrainte unique (jour, groupe_id) */
        $chk = $pdo->prepare("SELECT id FROM garde_dpm WHERE jour = ? AND groupe_id = ?");
        $chk->execute([$jour, $grpId]);
        if ($chk->fetch()) {
            jsonOut(['success' => false, 'message' => 'Ce groupe a déjà une garde planifiée ce jour-là'], 409);
        }

        $stmt = $pdo->prepare("
            INSERT INTO garde_dpm (mois, jour, groupe_id, departement_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$mois, $jour, $grpId, $deptId]);
        $newId = (int)$pdo->lastInsertId();

        jsonOut(['success' => true, 'message' => 'Garde ajoutée', 'data' => fetchGarde($pdo, $newId)]);
    }

    /* ════════════
       UPDATE
    ════════════ */
    if ($action === 'update') {
        if (!canWrite()) {
            jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);
        }

        $id      = (int)($data['id'] ?? 0);
        $jour    = trim($data['jour']    ?? '');
        $grpId   = (int)($data['groupe_id']    ?? 0);
        $deptId  = !empty($data['departement_id']) ? (int)$data['departement_id'] : null;

        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        if (!$jour || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour))
            jsonOut(['success' => false, 'message' => 'Date invalide']);
        if (!$grpId)
            jsonOut(['success' => false, 'message' => 'Groupe obligatoire']);

        /* Vérifier existence */
        $chkExist = $pdo->prepare("SELECT id FROM garde_dpm WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) jsonOut(['success' => false, 'message' => 'Garde introuvable'], 404);

        /* Vérifier contrainte unique (en excluant l'enregistrement courant) */
        $chkDup = $pdo->prepare("SELECT id FROM garde_dpm WHERE jour = ? AND groupe_id = ? AND id != ?");
        $chkDup->execute([$jour, $grpId, $id]);
        if ($chkDup->fetch()) {
            jsonOut(['success' => false, 'message' => 'Ce groupe a déjà une garde planifiée ce jour-là'], 409);
        }

        $mois = date('Y-m-01', strtotime($jour));

        $stmt = $pdo->prepare("
            UPDATE garde_dpm SET mois = ?, jour = ?, groupe_id = ?, departement_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$mois, $jour, $grpId, $deptId, $id]);

        jsonOut(['success' => true, 'message' => 'Garde modifiée', 'data' => fetchGarde($pdo, $id)]);
    }

    /* ════════════
       DELETE
    ════════════ */
    if ($action === 'delete') {
        if (!canWrite()) {
            jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM garde_dpm WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Garde introuvable'], 404);

        $pdo->prepare("DELETE FROM garde_dpm WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Garde supprimée avec succès']);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    $msg = ($e->getCode() === '23000')
         ? 'Doublon : cette combinaison (jour, groupe) existe déjà'
         : 'Erreur SQL : ' . $e->getMessage();
    jsonOut(['success' => false, 'message' => $msg], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}