<?php
/**
 * api/professionnels.php — CRUD professionnels_dpm
 *
 * Noms de colonnes exacts (vérifiés sur le dump SQL) :
 *   agences_dpm      → nom_agence             (PAS 'nom')
 *   pharmacies_dpm   → nom_pharmacie, is_actif (PAS is_active)
 *   depots_dpm       → depot_pharmaceutique    (PAS 'nom')
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

/* ── Détection catégorie (même logique que professionnels.php) ── */
function detecterCategorie(string $f): string {
    $fn = mb_strtolower($f, 'UTF-8');
    $fn = normalizer_normalize($fn, Normalizer::FORM_D) ?: $fn;
    $fn = preg_replace('/\p{M}/u', '', $fn);

    if (str_contains($fn, 'delegu'))       return 'delegue';
    if (str_contains($fn, 'depositaire')
     || preg_match('/responsable.{0,6}dep/u', $fn)
     || preg_match('/depot.{0,8}pharm/u', $fn))  return 'depositaire';
    if (str_contains($fn, 'pharmacien'))   return 'pharmacien';
    return 'autre';
}

/* ── Helper : fetch un enregistrement avec les bons JOIN ── */
function fetchPro(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            a.nom_agence               AS agence_nom,
            ph.nom_pharmacie           AS pharmacie_nom,
            dep.depot_pharmaceutique   AS depot_nom
        FROM professionnels_dpm p
        LEFT JOIN agences_dpm    a   ON a.id  = p.agence_id
        LEFT JOIN pharmacies_dpm ph  ON ph.id = p.pharmacie_id
        LEFT JOIN depots_dpm     dep ON dep.id = p.depot_id
        WHERE p.id = ? AND p.deleted_at IS NULL
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $row['is_active']    = (bool)$row['is_active'];
    $row['agence_id']    = $row['agence_id']    ? (int)$row['agence_id']    : null;
    $row['pharmacie_id'] = $row['pharmacie_id'] ? (int)$row['pharmacie_id'] : null;
    $row['depot_id']     = $row['depot_id']     ? (int)$row['depot_id']     : null;
    $row['categorie']    = detecterCategorie($row['fonction'] ?? '');
    return $row;
}

/* ── Validation date ── */
function validDate(?string $d): bool {
    if (!$d) return false;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    [$y, $m, $dd] = explode('-', $d);
    return checkdate((int)$m, (int)$dd, (int)$y);
}

try {
    $pdo = getPDO();

    /* ══ GET ══ */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);
        $row = fetchPro($pdo, $id);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Professionnel introuvable'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ══ CREATE ══ */
    if ($action === 'create') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $required = ['prenom','nom','sexe','date_naissance','fonction','date_delivrance','lieu_delivrance','date_validite'];
        foreach ($required as $f) {
            if (empty(trim((string)($data[$f] ?? ''))))
                jsonOut(['success' => false, 'message' => "Champ obligatoire manquant : « $f »"]);
        }

        if (!in_array($data['sexe'], ['Masculin','Féminin'], true))
            jsonOut(['success' => false, 'message' => 'Sexe invalide']);

        foreach (['date_naissance','date_delivrance','date_validite'] as $df) {
            if (!validDate($data[$df] ?? ''))
                jsonOut(['success' => false, 'message' => "Date invalide pour : $df"]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO professionnels_dpm
                (prenom, nom, sexe, date_naissance, lieu_naissance, fonction,
                 agence_id, pharmacie_id, depot_id, telephone, email, numero_cni,
                 date_validite, date_delivrance, lieu_delivrance, is_active, created_by)
            VALUES
                (:prenom, :nom, :sexe, :dnaiss, :lnaiss, :fonction,
                 :agence, :pharma, :depot, :tel, :email, :cni,
                 :dval, :ddeliv, :ldeliv, :actif, :created_by)
        ");
        $stmt->execute([
            ':prenom'     => trim($data['prenom']),
            ':nom'        => trim($data['nom']),
            ':sexe'       => $data['sexe'],
            ':dnaiss'     => $data['date_naissance'],
            ':lnaiss'     => trim($data['lieu_naissance'] ?? ''),
            ':fonction'   => trim($data['fonction']),
            ':agence'     => !empty($data['agence_id'])    ? (int)$data['agence_id']    : null,
            ':pharma'     => !empty($data['pharmacie_id']) ? (int)$data['pharmacie_id'] : null,
            ':depot'      => !empty($data['depot_id'])     ? (int)$data['depot_id']     : null,
            ':tel'        => trim($data['telephone']  ?? '') ?: null,
            ':email'      => trim($data['email']      ?? '') ?: null,
            ':cni'        => trim($data['numero_cni'] ?? '') ?: null,
            ':dval'       => $data['date_validite'],
            ':ddeliv'     => $data['date_delivrance'],
            ':ldeliv'     => trim($data['lieu_delivrance']),
            ':actif'      => isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1,
            ':created_by' => $_SESSION['user_id'] ?? null,
        ]);

        $newId = (int)$pdo->lastInsertId();
        jsonOut(['success' => true, 'message' => 'Professionnel ajouté', 'data' => fetchPro($pdo, $newId)]);
    }

    /* ══ UPDATE ══ */
    if ($action === 'update') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM professionnels_dpm WHERE id=? AND deleted_at IS NULL");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Professionnel introuvable'], 404);

        $required = ['prenom','nom','sexe','date_naissance','fonction','date_delivrance','lieu_delivrance','date_validite'];
        foreach ($required as $f) {
            if (empty(trim((string)($data[$f] ?? ''))))
                jsonOut(['success' => false, 'message' => "Champ obligatoire manquant : « $f »"]);
        }

        if (!in_array($data['sexe'], ['Masculin','Féminin'], true))
            jsonOut(['success' => false, 'message' => 'Sexe invalide']);

        foreach (['date_naissance','date_delivrance','date_validite'] as $df) {
            if (!validDate($data[$df] ?? ''))
                jsonOut(['success' => false, 'message' => "Date invalide pour : $df"]);
        }

        $stmt = $pdo->prepare("
            UPDATE professionnels_dpm SET
                prenom          = :prenom,
                nom             = :nom,
                sexe            = :sexe,
                date_naissance  = :dnaiss,
                lieu_naissance  = :lnaiss,
                fonction        = :fonction,
                agence_id       = :agence,
                pharmacie_id    = :pharma,
                depot_id        = :depot,
                telephone       = :tel,
                email           = :email,
                numero_cni      = :cni,
                date_validite   = :dval,
                date_delivrance = :ddeliv,
                lieu_delivrance = :ldeliv,
                is_active       = :actif,
                updated_by      = :updated_by,
                updated_at      = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute([
            ':prenom'     => trim($data['prenom']),
            ':nom'        => trim($data['nom']),
            ':sexe'       => $data['sexe'],
            ':dnaiss'     => $data['date_naissance'],
            ':lnaiss'     => trim($data['lieu_naissance'] ?? ''),
            ':fonction'   => trim($data['fonction']),
            ':agence'     => !empty($data['agence_id'])    ? (int)$data['agence_id']    : null,
            ':pharma'     => !empty($data['pharmacie_id']) ? (int)$data['pharmacie_id'] : null,
            ':depot'      => !empty($data['depot_id'])     ? (int)$data['depot_id']     : null,
            ':tel'        => trim($data['telephone']  ?? '') ?: null,
            ':email'      => trim($data['email']      ?? '') ?: null,
            ':cni'        => trim($data['numero_cni'] ?? '') ?: null,
            ':dval'       => $data['date_validite'],
            ':ddeliv'     => $data['date_delivrance'],
            ':ldeliv'     => trim($data['lieu_delivrance']),
            ':actif'      => (int)(bool)($data['is_active'] ?? 1),
            ':updated_by' => $_SESSION['user_id'] ?? null,
            ':id'         => $id,
        ]);

        jsonOut(['success' => true, 'message' => 'Professionnel modifié', 'data' => fetchPro($pdo, $id)]);
    }

    /* ══ DELETE (soft) ══ */
    if ($action === 'delete') {
        if (!canWrite()) jsonOut(['success' => false, 'message' => 'Permission refusée'], 403);

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM professionnels_dpm WHERE id=? AND deleted_at IS NULL");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => 'Professionnel introuvable'], 404);

        $pdo->prepare("UPDATE professionnels_dpm SET deleted_at=NOW(), deleted_by=? WHERE id=?")
            ->execute([$_SESSION['user_id'] ?? null, $id]);

        jsonOut(['success' => true, 'message' => 'Professionnel supprimé avec succès']);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    jsonOut(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}