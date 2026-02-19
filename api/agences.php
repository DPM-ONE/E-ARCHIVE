<?php
/**
 * api/agences.php — CRUD agences_dpm
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

try {
    $pdo = getPDO();

    /* ── GET ── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $stmt = $pdo->prepare("
            SELECT a.*,
                   dept.libelle  AS dept_libelle,
                   arr.libelle   AS arr_libelle,
                   dist.libelle  AS dist_libelle
            FROM agences_dpm a
            LEFT JOIN departements_dpm   dept ON dept.id = a.departement_id
            LEFT JOIN arrondissement_dpm arr  ON arr.id  = a.arrondissement_id
            LEFT JOIN district_dpm       dist ON dist.id = a.district_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Agence introuvable'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ══ DELETE ══ */
    if ($action === 'delete') {
        if (!canWrite()) {
            $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'non défini';
            jsonOut(['success' => false, 'message' => "Permission refusée (rôle : $role)"], 403);
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $chk = $pdo->prepare("SELECT id FROM agences_dpm WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) jsonOut(['success' => false, 'message' => "Agence id=$id introuvable"], 404);

        $pdo->prepare("DELETE FROM agences_dpm WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Agence supprimée avec succès']);
    }

    /* ══ UPDATE ══ */
    if ($action === 'update') {
        if (!canWrite()) {
            $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'non défini';
            jsonOut(['success' => false, 'message' => "Permission refusée (rôle : $role)"], 403);
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        foreach ([
            'nom_agence'         => 'Nom de l\'agence',
            'responsable_prenom' => 'Prénom',
            'responsable_nom'    => 'Nom',
            'adresse'            => 'Adresse',
            'localite'           => 'Localité',
        ] as $field => $label) {
            if (empty(trim((string)($data[$field] ?? '')))) {
                jsonOut(['success' => false, 'message' => "Champ obligatoire : « $label »"]);
            }
        }

        $deptId = (int)($data['departement_id'] ?? 0);
        if (!$deptId) jsonOut(['success' => false, 'message' => 'Département obligatoire']);

        $arrDepts = array_map('intval',
            $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN)
        );
        $hasArr = in_array($deptId, $arrDepts, true);
        $arrId  = ($hasArr  && !empty($data['arrondissement_id'])) ? (int)$data['arrondissement_id'] : null;
        $distId = (!$hasArr && !empty($data['district_id']))        ? (int)$data['district_id']       : null;

        $zoneOk      = ['', 'Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'];
        $zoneArchive = in_array($data['zone_archive'] ?? '', $zoneOk) ? ($data['zone_archive'] ?: null) : null;

        $stmt = $pdo->prepare("
            UPDATE agences_dpm SET
                nom_agence          = :nom_agence,
                numero_decision     = :numero_decision,
                responsable_prenom  = :responsable_prenom,
                responsable_nom     = :responsable_nom,
                adresse             = :adresse,
                localite            = :localite,
                quartier            = :quartier,
                departement_id      = :departement_id,
                arrondissement_id   = :arrondissement_id,
                district_id         = :district_id,
                telephone           = :telephone,
                email               = :email,
                box_rangement       = :box_rangement,
                zone_archive        = :zone_archive,
                is_active           = :is_active,
                updated_at          = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom_agence'         => trim((string)$data['nom_agence']),
            ':numero_decision'    => trim((string)($data['numero_decision'] ?? '')) ?: null,
            ':responsable_prenom' => trim((string)$data['responsable_prenom']),
            ':responsable_nom'    => trim((string)$data['responsable_nom']),
            ':adresse'            => trim((string)$data['adresse']),
            ':localite'           => trim((string)$data['localite']),
            ':quartier'           => trim((string)($data['quartier'] ?? '')) ?: null,
            ':departement_id'     => $deptId,
            ':arrondissement_id'  => $arrId,
            ':district_id'        => $distId,
            ':telephone'          => trim((string)($data['telephone'] ?? '')) ?: null,
            ':email'              => trim((string)($data['email']     ?? '')) ?: null,
            ':box_rangement'      => trim((string)($data['box_rangement'] ?? '')) ?: null,
            ':zone_archive'       => $zoneArchive,
            ':is_active'          => (int)(bool)($data['is_active'] ?? 1),
            ':id'                 => $id,
        ]);

        $sel = $pdo->prepare("
            SELECT a.*,
                   dept.libelle  AS dept_libelle,
                   arr.libelle   AS arr_libelle,
                   dist.libelle  AS dist_libelle
            FROM agences_dpm a
            LEFT JOIN departements_dpm   dept ON dept.id = a.departement_id
            LEFT JOIN arrondissement_dpm arr  ON arr.id  = a.arrondissement_id
            LEFT JOIN district_dpm       dist ON dist.id = a.district_id
            WHERE a.id = ?
        ");
        $sel->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Agence modifiée', 'data' => $sel->fetch(PDO::FETCH_ASSOC)]);
    }

    /* ══ CREATE ══ */
    if ($action === 'create') {
        if (!canWrite()) {
            $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'non défini';
            jsonOut(['success' => false, 'message' => "Permission refusée (rôle : $role)"], 403);
        }

        foreach ([
            'nom_agence'         => 'Nom de l\'agence',
            'responsable_prenom' => 'Prénom',
            'responsable_nom'    => 'Nom',
            'adresse'            => 'Adresse',
            'localite'           => 'Localité',
        ] as $field => $label) {
            if (empty(trim((string)($data[$field] ?? '')))) {
                jsonOut(['success' => false, 'message' => "Champ obligatoire : « $label »"]);
            }
        }

        $deptId = (int)($data['departement_id'] ?? 0);
        if (!$deptId) jsonOut(['success' => false, 'message' => 'Département obligatoire']);

        $arrDepts = array_map('intval',
            $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN)
        );
        $hasArr = in_array($deptId, $arrDepts, true);
        $arrId  = ($hasArr  && !empty($data['arrondissement_id'])) ? (int)$data['arrondissement_id'] : null;
        $distId = (!$hasArr && !empty($data['district_id']))        ? (int)$data['district_id']       : null;

        $zoneOk      = ['', 'Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'];
        $zoneArchive = in_array($data['zone_archive'] ?? '', $zoneOk) ? ($data['zone_archive'] ?: null) : null;

        $stmt = $pdo->prepare("
            INSERT INTO agences_dpm
                (nom_agence, numero_decision, responsable_prenom, responsable_nom,
                 adresse, localite, quartier,
                 departement_id, arrondissement_id, district_id,
                 telephone, email, box_rangement, zone_archive, is_active)
            VALUES
                (:nom_agence, :numero_decision, :responsable_prenom, :responsable_nom,
                 :adresse, :localite, :quartier,
                 :departement_id, :arrondissement_id, :district_id,
                 :telephone, :email, :box_rangement, :zone_archive, :is_active)
        ");
        $stmt->execute([
            ':nom_agence'         => trim((string)$data['nom_agence']),
            ':numero_decision'    => trim((string)($data['numero_decision'] ?? '')) ?: null,
            ':responsable_prenom' => trim((string)$data['responsable_prenom']),
            ':responsable_nom'    => trim((string)$data['responsable_nom']),
            ':adresse'            => trim((string)$data['adresse']),
            ':localite'           => trim((string)$data['localite']),
            ':quartier'           => trim((string)($data['quartier'] ?? '')) ?: null,
            ':departement_id'     => $deptId,
            ':arrondissement_id'  => $arrId,
            ':district_id'        => $distId,
            ':telephone'          => trim((string)($data['telephone'] ?? '')) ?: null,
            ':email'              => trim((string)($data['email']     ?? '')) ?: null,
            ':box_rangement'      => trim((string)($data['box_rangement'] ?? '')) ?: null,
            ':zone_archive'       => $zoneArchive,
            ':is_active'          => (int)(bool)($data['is_active'] ?? 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        $sel = $pdo->prepare("
            SELECT a.*,
                   dept.libelle  AS dept_libelle,
                   arr.libelle   AS arr_libelle,
                   dist.libelle  AS dist_libelle
            FROM agences_dpm a
            LEFT JOIN departements_dpm   dept ON dept.id = a.departement_id
            LEFT JOIN arrondissement_dpm arr  ON arr.id  = a.arrondissement_id
            LEFT JOIN district_dpm       dist ON dist.id = a.district_id
            WHERE a.id = ?
        ");
        $sel->execute([$newId]);
        jsonOut(['success' => true, 'message' => 'Agence créée avec succès', 'data' => $sel->fetch(PDO::FETCH_ASSOC)], 201);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    $msg = ($e->getCode() === '23000')
         ? 'Référence invalide (département / arrondissement / district)'
         : 'Erreur SQL : ' . $e->getMessage();
    jsonOut(['success' => false, 'message' => $msg], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}