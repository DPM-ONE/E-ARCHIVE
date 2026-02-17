<?php
/**
 * api/grossistes.php — CRUD grossistes_dpm
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
            SELECT g.*,
                   d.libelle  AS dept_libelle,
                   a.libelle  AS arr_libelle,
                   dt.libelle AS dist_libelle
            FROM grossistes_dpm g
            LEFT JOIN departements_dpm   d  ON d.id  = g.departement
            LEFT JOIN arrondissement_dpm a  ON a.id  = g.arrondissement_id
            LEFT JOIN district_dpm       dt ON dt.id = g.district_id
            WHERE g.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'Grossiste introuvable'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonOut(['success' => false, 'message' => 'Méthode non autorisée'], 405);
    }

    $action = trim($data['action'] ?? '');

    /* ════════════
       DELETE
    ════════════ */
    if ($action === 'delete') {
        if (!canWrite()) {
            $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'non défini';
            jsonOut(['success' => false, 'message' => "Permission refusée (rôle : $role)"], 403);
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'ID invalide (reçu : ' . json_encode($data['id'] ?? null) . ')']);
        }

        $chk = $pdo->prepare("SELECT id FROM grossistes_dpm WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            jsonOut(['success' => false, 'message' => "Grossiste id=$id introuvable"], 404);
        }

        $pdo->prepare("DELETE FROM grossistes_dpm WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Grossiste supprimé avec succès']);
    }

    /* ════════════
       UPDATE
    ════════════ */
    if ($action === 'update') {
        if (!canWrite()) {
            $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? 'non défini';
            jsonOut(['success' => false, 'message' => "Permission refusée (rôle : $role)"], 403);
        }

        $id = (int)($data['id'] ?? 0);
        if (!$id) {
            jsonOut(['success' => false, 'message' => 'ID invalide (reçu : ' . json_encode($data['id'] ?? null) . ')']);
        }

        foreach ([
            'nom_grossiste' => 'Nom du grossiste',
            'responsable'   => 'Responsable',
            'telephone'     => 'Téléphone',
            'adresse'       => 'Adresse',
            'quartier'      => 'Quartier',
        ] as $field => $label) {
            if (empty(trim((string)($data[$field] ?? '')))) {
                jsonOut(['success' => false, 'message' => "Champ obligatoire manquant : « $label »"]);
            }
        }

        $deptId = (int)($data['departement'] ?? 0);
        if (!$deptId) jsonOut(['success' => false, 'message' => 'Département obligatoire']);

        $arrDepts = array_map('intval',
            $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN)
        );
        $hasArr = in_array($deptId, $arrDepts, true);

        $arrId  = ($hasArr  && !empty($data['arrondissement_id'])) ? (int)$data['arrondissement_id'] : null;
        $distId = (!$hasArr && !empty($data['district_id']))        ? (int)$data['district_id']       : null;

        $zoneOk      = ['', 'Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'];
        $zoneArchive = in_array($data['zone_archive'] ?? '', $zoneOk)
                       ? ($data['zone_archive'] ?: null) : null;

        $stmt = $pdo->prepare("
            UPDATE grossistes_dpm SET
                nom_grossiste     = :nom_grossiste,
                responsable       = :responsable,
                telephone         = :telephone,
                email             = :email,
                adresse           = :adresse,
                quartier          = :quartier,
                departement       = :departement,
                arrondissement_id = :arr_id,
                district_id       = :dist_id,
                box_rangement     = :box_rangement,
                zone_archive      = :zone_archive,
                is_actif          = :is_actif
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom_grossiste' => trim((string)$data['nom_grossiste']),
            ':responsable'   => trim((string)$data['responsable']),
            ':telephone'     => trim((string)$data['telephone']),
            ':email'         => trim((string)($data['email'] ?? '')) ?: null,
            ':adresse'       => trim((string)$data['adresse']),
            ':quartier'      => trim((string)$data['quartier']),
            ':departement'   => $deptId,
            ':arr_id'        => $arrId,
            ':dist_id'       => $distId,
            ':box_rangement' => trim((string)($data['box_rangement'] ?? '')) ?: null,
            ':zone_archive'  => $zoneArchive,
            ':is_actif'      => (int)(bool)($data['is_actif'] ?? 1),
            ':id'            => $id,
        ]);

        $sel = $pdo->prepare("
            SELECT g.*,
                   d.libelle  AS dept_libelle,
                   a.libelle  AS arr_libelle,
                   dt.libelle AS dist_libelle
            FROM grossistes_dpm g
            LEFT JOIN departements_dpm   d  ON d.id  = g.departement
            LEFT JOIN arrondissement_dpm a  ON a.id  = g.arrondissement_id
            LEFT JOIN district_dpm       dt ON dt.id = g.district_id
            WHERE g.id = ?
        ");
        $sel->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Grossiste modifié', 'data' => $sel->fetch(PDO::FETCH_ASSOC)]);
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