<?php
/**
 * api/fosa.php — CRUD fosa_dpm
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

/* ── Auth ── */
if (empty($_SESSION['user_id'])) {
    jsonOut(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 401);
}

function canWrite(): bool {
    $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? '';
    return in_array($role, ['super_admin', 'admin'], true);
}

/* ── Body JSON ── */
$raw  = file_get_contents('php://input');
$data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
if (!is_array($data)) $data = [];

/* ══════════════════════════════════════════════════════════
   Routing
══════════════════════════════════════════════════════════ */
try {
    $pdo = getPDO();

    /* ── GET ── */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'message' => 'ID invalide']);

        $stmt = $pdo->prepare("
            SELECT f.*,
                   d.libelle  AS dept_libelle,
                   ds.nom_ds  AS ds_libelle
            FROM fosa_dpm f
            LEFT JOIN departements_dpm         d  ON d.id  = f.departement_id
            LEFT JOIN districts_sanitaires_dpm ds ON ds.id = f.district_sanitaire_id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row
            ? jsonOut(['success' => true, 'data' => $row])
            : jsonOut(['success' => false, 'message' => 'FOSA introuvable'], 404);
    }

    /* ── POST uniquement ── */
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

        $chk = $pdo->prepare("SELECT id FROM fosa_dpm WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) {
            jsonOut(['success' => false, 'message' => "FOSA id=$id introuvable"], 404);
        }

        $pdo->prepare("DELETE FROM fosa_dpm WHERE id = ?")->execute([$id]);
        jsonOut(['success' => true, 'message' => 'Formation sanitaire supprimée avec succès']);
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

        /* Champs obligatoires */
        foreach ([
            'nom_fosa'              => 'Nom de la FOSA',
            'departement_id'        => 'Département',
            'district_sanitaire_id' => 'District sanitaire',
        ] as $field => $label) {
            if (empty(trim((string)($data[$field] ?? '')))) {
                jsonOut(['success' => false, 'message' => "Champ obligatoire manquant : « $label »"]);
            }
        }

        $deptId = (int)($data['departement_id'] ?? 0);
        $dsId   = (int)($data['district_sanitaire_id'] ?? 0);

        /* Vérifier cohérence département / district sanitaire */
        $chkDS = $pdo->prepare("SELECT id FROM districts_sanitaires_dpm WHERE id = ? AND departement_id = ?");
        $chkDS->execute([$dsId, $deptId]);
        if (!$chkDS->fetch()) {
            jsonOut(['success' => false, 'message' => 'Le district sanitaire ne correspond pas au département sélectionné'], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE fosa_dpm SET
                nom_fosa              = :nom_fosa,
                departement_id        = :dept_id,
                district_sanitaire_id = :ds_id,
                prenom_responsable    = :prenom,
                nom_responsable       = :nom,
                telephone             = :tel,
                adresse               = :adresse,
                updated_at            = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom_fosa' => trim((string)$data['nom_fosa']),
            ':dept_id'  => $deptId,
            ':ds_id'    => $dsId,
            ':prenom'   => trim((string)($data['prenom_responsable'] ?? '')) ?: null,
            ':nom'      => trim((string)($data['nom_responsable']    ?? '')) ?: null,
            ':tel'      => trim((string)($data['telephone']          ?? '')) ?: null,
            ':adresse'  => trim((string)($data['adresse']            ?? '')) ?: null,
            ':id'       => $id,
        ]);

        /* Retourner la ligne enrichie */
        $sel = $pdo->prepare("
            SELECT f.*,
                   d.libelle  AS dept_libelle,
                   ds.nom_ds  AS ds_libelle
            FROM fosa_dpm f
            LEFT JOIN departements_dpm         d  ON d.id  = f.departement_id
            LEFT JOIN districts_sanitaires_dpm ds ON ds.id = f.district_sanitaire_id
            WHERE f.id = ?
        ");
        $sel->execute([$id]);
        jsonOut(['success' => true, 'message' => 'FOSA modifiée', 'data' => $sel->fetch(PDO::FETCH_ASSOC)]);
    }

    jsonOut(['success' => false, 'message' => "Action inconnue : \"$action\""], 400);

} catch (PDOException $e) {
    $msg = ($e->getCode() === '23000')
         ? 'Référence invalide (département / district sanitaire)'
         : 'Erreur SQL : ' . $e->getMessage();
    jsonOut(['success' => false, 'message' => $msg], 500);
} catch (Throwable $e) {
    jsonOut(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
}