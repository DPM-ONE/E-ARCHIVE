<?php
/**
 * api/laboratoires.php — CRUD laboratoires_dpm
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

/* ── Helper : récupérer un laboratoire complet avec jointures ── */
function fetchLabo(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT l.id, l.nom_laboratoire, l.pays, l.agence_id,
               a.nom_agence AS agence_nom
        FROM laboratoires_dpm l
        LEFT JOIN agences_dpm a ON a.id = l.agence_id
        WHERE l.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id'] = (int)$row['id'];
        $row['agence_id'] = (int)$row['agence_id'];
    }
    return $row ?: null;
}

try {
    $pdo = getPDO();

    /* ── GET ─────────────────────────────────────────────── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        $row = fetchLabo($pdo, $id);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Laboratoire introuvable'], 404);
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

        $nom     = trim($data['nom_laboratoire'] ?? '');
        $pays    = trim($data['pays'] ?? '');
        $agenceId = (int)($data['agence_id'] ?? 0);

        if (!$nom)
            jsonOut(['success' => false, 'message' => 'Nom du laboratoire obligatoire']);
        if (!$agenceId)
            jsonOut(['success' => false, 'message' => 'Agence obligatoire']);

        $stmt = $pdo->prepare("
            INSERT INTO laboratoires_dpm (nom_laboratoire, pays, agence_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$nom, $pays, $agenceId]);
        $newId = (int)$pdo->lastInsertId();

        jsonOut(['success' => true, 'message' => 'Laboratoire ajouté', 'data' => fetchLabo($pdo, $newId)]);
    }

    /* ════════════
       UPDATE
    ════════════ */
    if ($action === 'update') {
        if (!canWrite()) {
            jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);
        }

        $id       = (int)($data['id'] ?? 0);
        $nom      = trim($data['nom_laboratoire'] ?? '');
        $pays     = trim($data['pays'] ?? '');
        $agenceId = (int)($data['agence_id'] ?? 0);

        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        if (!$nom) jsonOut(['success' => false, 'message' => 'Nom du laboratoire obligatoire']);
        if (!$agenceId) jsonOut(['success' => false, 'message' => 'Agence obligatoire']);

        $chkExist = $pdo->prepare("SELECT id FROM laboratoires_dpm WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) jsonOut(['success' => false, 'message' => 'Laboratoire introuvable'], 404);

        $stmt = $pdo->prepare("
            UPDATE laboratoires_dpm 
            SET nom_laboratoire = ?, pays = ?, agence_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$nom, $pays, $agenceId, $id]);

        jsonOut(['success' => true, 'message' => 'Laboratoire modifié', 'data' => fetchLabo($pdo, $id)]);
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

        $chk = $pdo->prepare("SELECT nom_laboratoire FROM laboratoires_dpm WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Laboratoire introuvable'], 404);

        $pdo->prepare("DELETE FROM laboratoires_dpm WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Laboratoire supprimé avec succès']);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    $msg = ($e->getCode() === '23000')
         ? 'Erreur : référence invalide ou doublon'
         : 'Erreur SQL : ' . $e->getMessage();
    jsonOut(['success' => false, 'message' => $msg], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}