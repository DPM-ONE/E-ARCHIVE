<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$dept_id = (int) ($_GET['dept_id'] ?? 0);
$type = $_GET['type'] ?? '';

if (!$dept_id || !in_array($type, ['arrondissement', 'district'])) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getPDO();
    $table = $type === 'arrondissement' ? 'arrondissement_dpm' : 'district_dpm';
    $stmt = $pdo->prepare("SELECT id, libelle FROM {$table} WHERE departement_id = ? ORDER BY libelle ASC");
    $stmt->execute([$dept_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo json_encode([]);
}