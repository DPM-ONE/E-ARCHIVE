<?php
/**
 * api/api_visas.php — CRUD visa_dpm
 * Colonnes DB exactes :
 *   visa_dpm : id, type_visa, numero_dossier, laboratoire_id, produits (JSON),
 *              carte_professionnelle, date_decision, statut, observations,
 *              date_renouvellement (GENERATED), zone_archive, box_rangement,
 *              is_active, created_at, updated_at, deleted_at,
 *              created_by, updated_by, deleted_by
 */

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

/* ── Sortie JSON propre ── */
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
        echo json_encode(['success' => false, 'message' => 'Erreur fatale : '.$err['message']]);
    }
});

/* ── Auth ── */
if (empty($_SESSION['user_id'])) {
    jsonOut(['success' => false, 'message' => 'Session expirée'], 401);
}

function canWrite(): bool {
    $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? '';
    return in_array($role, ['super_admin', 'admin'], true);
}

$raw  = file_get_contents('php://input');
$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

/* ── Types et statuts valides ── */
const TYPES_VALIDES = [
    "Demande de visa",
    "Renouvellement de visa",
    "Renouvellement - variation",
    "Variation"
];
const STATUTS_VALIDES  = ['En attente', 'Approuvé', 'Rejeté', 'Suspendu'];
const SALLES_VALIDES   = ['Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'];

/* ── Helper fetch visa avec JOIN ── */
function fetchVisa(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT v.*, l.nom_laboratoire AS laboratoire_nom
        FROM visa_dpm v
        LEFT JOIN laboratoires_dpm l ON l.id = v.laboratoire_id
        WHERE v.id = ? AND v.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $row['carte_professionnelle'] = (bool)$row['carte_professionnelle'];
    $row['is_active']             = (bool)$row['is_active'];
    $row['laboratoire_id']        = $row['laboratoire_id'] ? (int)$row['laboratoire_id'] : null;

    /* Décoder produits JSON */
    if (!empty($row['produits']) && is_string($row['produits'])) {
        $decoded = json_decode($row['produits'], true);
        $row['produits']      = $decoded;
        $row['produits_list'] = is_array($decoded) ? $decoded : [];
    } else {
        $row['produits']      = $row['produits'] ?? null;
        $row['produits_list'] = [];
    }
    return $row;
}

/* ── Validation produits JSON ── */
function validateProduits($raw): ?string {
    if ($raw === null || $raw === '') return null;
    if (is_array($raw)) {
        foreach ($raw as $p) {
            if (!isset($p['produit_id']) || !is_numeric($p['produit_id']))
                return json_encode([]);
        }
        return json_encode($raw);
    }
    return null;
}

try {
    $pdo = getPDO();

    /* ══ GET ══ */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        $row = fetchVisa($pdo, $id);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Visa introuvable'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ══ CREATE ══ */
    if ($action === 'create') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $typeVisa = trim($data['type_visa'] ?? '');
        if (!$typeVisa || !in_array($typeVisa, TYPES_VALIDES, true))
            jsonOut(['success' => false, 'message' => 'Type de visa invalide ou manquant']);

        $statut = trim($data['statut'] ?? 'En attente');
        if (!in_array($statut, STATUTS_VALIDES, true)) $statut = 'En attente';

        $zoneArchive = trim($data['zone_archive'] ?? '') ?: null;
        if ($zoneArchive && !in_array($zoneArchive, SALLES_VALIDES, true)) $zoneArchive = null;

        $dateDecision = trim($data['date_decision'] ?? '') ?: null;
        if ($dateDecision && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDecision)) $dateDecision = null;

        $labId = !empty($data['laboratoire_id']) ? (int)$data['laboratoire_id'] : null;

        $produits = null;
        if (!empty($data['produits']) && is_array($data['produits'])) {
            $produits = json_encode($data['produits']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO visa_dpm
                (type_visa, numero_dossier, laboratoire_id, produits,
                 carte_professionnelle, date_decision, statut, observations,
                 zone_archive, box_rangement, is_active, created_by)
            VALUES
                (:type_visa, :numero_dossier, :laboratoire_id, :produits,
                 :carte, :date_decision, :statut, :observations,
                 :zone_archive, :box_rangement, 1, :created_by)
        ");
        $stmt->execute([
            ':type_visa'      => $typeVisa,
            ':numero_dossier' => trim($data['numero_dossier'] ?? '') ?: null,
            ':laboratoire_id' => $labId,
            ':produits'       => $produits,
            ':carte'          => isset($data['carte_professionnelle']) ? (int)(bool)$data['carte_professionnelle'] : 0,
            ':date_decision'  => $dateDecision,
            ':statut'         => $statut,
            ':observations'   => trim($data['observations'] ?? '') ?: null,
            ':zone_archive'   => $zoneArchive,
            ':box_rangement'  => trim($data['box_rangement'] ?? '') ?: null,
            ':created_by'     => $_SESSION['user_id'] ?? null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Visa créé avec succès', 'data' => fetchVisa($pdo, $newId)]);
    }

    /* ══ UPDATE ══ */
    if ($action === 'update') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM visa_dpm WHERE id=? AND deleted_at IS NULL");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Visa introuvable'], 404);

        $typeVisa = trim($data['type_visa'] ?? '');
        if (!$typeVisa || !in_array($typeVisa, TYPES_VALIDES, true))
            jsonOut(['success' => false, 'message' => 'Type de visa invalide']);

        $statut = trim($data['statut'] ?? 'En attente');
        if (!in_array($statut, STATUTS_VALIDES, true)) $statut = 'En attente';

        $zoneArchive = trim($data['zone_archive'] ?? '') ?: null;
        if ($zoneArchive && !in_array($zoneArchive, SALLES_VALIDES, true)) $zoneArchive = null;

        $dateDecision = trim($data['date_decision'] ?? '') ?: null;
        if ($dateDecision && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDecision)) $dateDecision = null;

        $labId = !empty($data['laboratoire_id']) ? (int)$data['laboratoire_id'] : null;

        $produits = null;
        if (isset($data['produits']) && is_array($data['produits'])) {
            $produits = count($data['produits']) > 0 ? json_encode($data['produits']) : null;
        }

        $stmt = $pdo->prepare("
            UPDATE visa_dpm SET
                type_visa             = :type_visa,
                numero_dossier        = :numero_dossier,
                laboratoire_id        = :laboratoire_id,
                produits              = :produits,
                carte_professionnelle = :carte,
                date_decision         = :date_decision,
                statut                = :statut,
                observations          = :observations,
                zone_archive          = :zone_archive,
                box_rangement         = :box_rangement,
                updated_by            = :updated_by,
                updated_at            = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':type_visa'      => $typeVisa,
            ':numero_dossier' => trim($data['numero_dossier'] ?? '') ?: null,
            ':laboratoire_id' => $labId,
            ':produits'       => $produits,
            ':carte'          => isset($data['carte_professionnelle']) ? (int)(bool)$data['carte_professionnelle'] : 0,
            ':date_decision'  => $dateDecision,
            ':statut'         => $statut,
            ':observations'   => trim($data['observations'] ?? '') ?: null,
            ':zone_archive'   => $zoneArchive,
            ':box_rangement'  => trim($data['box_rangement'] ?? '') ?: null,
            ':updated_by'     => $_SESSION['user_id'] ?? null,
            ':id'             => $id,
        ]);

        jsonOut(['success' => true, 'message' => 'Visa modifié avec succès', 'data' => fetchVisa($pdo, $id)]);
    }

    /* ══ DELETE (soft) ══ */
    if ($action === 'delete') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM visa_dpm WHERE id=? AND deleted_at IS NULL");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Visa introuvable'], 404);

        $pdo->prepare("UPDATE visa_dpm SET deleted_at=NOW(), deleted_by=? WHERE id=?")
            ->execute([$_SESSION['user_id'] ?? null, $id]);

        jsonOut(['success' => true, 'message' => 'Visa supprimé avec succès']);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    jsonOut(['success' => false, 'message' => 'Erreur SQL : '.$e->getMessage()], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 500);
}