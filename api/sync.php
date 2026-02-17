<?php
/**
 * API SYNCHRONISATION
 * Traitement de la file d'attente vers Hostinger
 * api/sync.php
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../config/sync.php';
require_once '../config/session.php';

requireLogin();

// Vérifier l'état de Hostinger
if (isset($_GET['check'])) {
    echo json_encode([
        'success' => true,
        'available' => SyncManager::isRemoteAvailable(),
        'message' => SyncManager::isRemoteAvailable()
            ? 'Hostinger accessible'
            : 'Hostinger inaccessible'
    ]);
    exit;
}

// Traiter la file d'attente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $results = SyncManager::processQueue();
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);