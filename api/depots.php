 
<?php
/**
 * API REST pour les dépôts pharmaceutiques
 */
require_once '../config/session.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
    }
});

try {
    requireLogin();
} catch (Exception $e) {
    jsonOut(['success' => false, 'error' => 'Non authentifié'], 401);
}

function canWrite(): bool {
    $role = $_SESSION['user']['role'] ?? $_SESSION['role'] ?? '';
    return in_array($role, ['super_admin', 'admin'], true);
}

function jsonOut(array $payload, int $code = 200): void {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $pdo = getPDO();
    
    if ($method === 'GET' && !empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        
        $stmt = $pdo->prepare("
            SELECT d.*, dept.libelle as dept_libelle, arr.libelle as arr_libelle, dist.libelle as dist_libelle
            FROM depots_dpm d
            LEFT JOIN departements_dpm dept ON d.departement_id = dept.id
            LEFT JOIN arrondissement_dpm arr ON d.arrondissement_id = arr.id
            LEFT JOIN district_dpm dist ON d.district_id = dist.id
            WHERE d.id = ?
        ");
        $stmt->execute([$id]);
        $depot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$depot) jsonOut(['success' => false, 'error' => 'Introuvable'], 404);
        jsonOut(['success' => true, 'depot' => $depot]);
    }
    
    if ($method === 'POST' && $action === 'delete') {
        if (!canWrite()) jsonOut(['success' => false, 'error' => 'Permission refusée'], 403);
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonOut(['success' => false, 'error' => 'ID manquant'], 400);
        
        $exists = $pdo->prepare("SELECT COUNT(*) FROM depots_dpm WHERE id = ?");
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) jsonOut(['success' => false, 'error' => 'Introuvable'], 404);
        
        $stmt = $pdo->prepare("DELETE FROM depots_dpm WHERE id = ?");
        $stmt->execute([$id]);
        
        jsonOut(['success' => true, 'message' => 'Dépôt supprimé']);
    }
    
    jsonOut(['success' => false, 'error' => 'Endpoint invalide'], 400);
    
} catch (PDOException $e) {
    jsonOut(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonOut(['success' => false, 'error' => $e->getMessage()], 500);
}