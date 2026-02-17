<?php
/**
 * SYNCHRONISATION LOCALE → HOSTINGER
 * Après chaque enregistrement local, sync automatique vers Hostinger
 * config/sync.php
 */

use PDO;
use PDOException;

// ============================================================
// ⚙️ CONFIGURATION HOSTINGER
// ============================================================
define('SYNC_HOST', 'votre-serveur.mysql.hostinger.com');
define('SYNC_NAME', 'u123456789_archive_dpm');
define('SYNC_USER', 'u123456789_dpm_user');
define('SYNC_PASS', 'VotreMotDePasseHostinger123!');
define('SYNC_CHARSET', 'utf8mb4');

// Activer ou désactiver la sync (true/false)
define('SYNC_ENABLED', true);

// Timeout connexion Hostinger (secondes)
define('SYNC_TIMEOUT', 5);

// ============================================================
// CLASSE SYNCHRONISATION
// ============================================================
class SyncManager
{

    private static ?\PDO $remoteConnection = null;

    /**
     * Obtenir la connexion distante (Hostinger)
     */
    private static function getRemoteConnection(): ?\PDO
    {
        if (self::$remoteConnection !== null) {
            return self::$remoteConnection;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s;connect_timeout=%d',
                SYNC_HOST,
                SYNC_NAME,
                SYNC_CHARSET,
                SYNC_TIMEOUT
            );

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . SYNC_CHARSET
            ];

            self::$remoteConnection = new \PDO($dsn, SYNC_USER, SYNC_PASS, $options);
            return self::$remoteConnection;

        } catch (\PDOException $e) {
            self::logSyncError("Connexion Hostinger échouée : " . $e->getMessage());
            self::queueForLater(null, null, null, []);
            return null;
        }
    }

    // ============================================================
    // MÉTHODE PRINCIPALE : sync après INSERT local
    // ============================================================

    /**
     * Synchroniser un INSERT vers Hostinger
     *
     * @param string $table     Nom de la table
     * @param array  $data      Données insérées (colonnes => valeurs)
     * @param int    $localId   ID inséré en local
     */
    public static function syncInsert(string $table, array $data, int $localId): void
    {
        if (!SYNC_ENABLED)
            return;

        $remote = self::getRemoteConnection();

        if (!$remote) {
            // Hostinger inaccessible → mettre en file d'attente
            self::queueForLater('INSERT', $table, $localId, $data);
            return;
        }

        try {
            // Forcer l'ID local sur la table distante
            $data['id'] = $localId;

            $columns = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));

            $stmt = $remote->prepare("
                INSERT IGNORE INTO `$table` ($columns)
                VALUES ($placeholders)
            ");
            $stmt->execute(array_values($data));

            self::logSyncSuccess('INSERT', $table, $localId);

        } catch (\PDOException $e) {
            self::logSyncError("syncInsert [$table#$localId] : " . $e->getMessage());
            self::queueForLater('INSERT', $table, $localId, $data);
        }
    }

    // ============================================================
    /**
     * Synchroniser un UPDATE vers Hostinger
     *
     * @param string $table   Nom de la table
     * @param array  $data    Données modifiées (colonnes => valeurs)
     * @param int    $id      ID de l'enregistrement
     */
    public static function syncUpdate(string $table, array $data, int $id): void
    {
        if (!SYNC_ENABLED)
            return;

        $remote = self::getRemoteConnection();

        if (!$remote) {
            self::queueForLater('UPDATE', $table, $id, $data);
            return;
        }

        try {
            $setParts = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));

            $stmt = $remote->prepare("
                UPDATE `$table`
                SET $setParts
                WHERE id = ?
            ");
            $stmt->execute([...array_values($data), $id]);

            self::logSyncSuccess('UPDATE', $table, $id);

        } catch (\PDOException $e) {
            self::logSyncError("syncUpdate [$table#$id] : " . $e->getMessage());
            self::queueForLater('UPDATE', $table, $id, $data);
        }
    }

    // ============================================================
    /**
     * Synchroniser un DELETE (soft delete) vers Hostinger
     *
     * @param string $table Nom de la table
     * @param int    $id    ID de l'enregistrement
     */
    public static function syncDelete(string $table, int $id): void
    {
        if (!SYNC_ENABLED)
            return;

        $remote = self::getRemoteConnection();

        if (!$remote) {
            self::queueForLater('DELETE', $table, $id, []);
            return;
        }

        try {
            $stmt = $remote->prepare("
                UPDATE `$table`
                SET deleted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            self::logSyncSuccess('DELETE', $table, $id);

        } catch (\PDOException $e) {
            self::logSyncError("syncDelete [$table#$id] : " . $e->getMessage());
            self::queueForLater('DELETE', $table, $id, []);
        }
    }

    // ============================================================
    // FILE D'ATTENTE (queue) : si Hostinger inaccessible
    // ============================================================

    /**
     * Mettre en file d'attente pour sync différée
     */
    private static function queueForLater(?string $operation, ?string $table, ?int $recordId, array $data): void
    {
        try {
            $pdo = getPDO();

            // Créer la table sync_queue si elle n'existe pas
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sync_queue (
                    id          INT PRIMARY KEY AUTO_INCREMENT,
                    operation   ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
                    table_name  VARCHAR(100) NOT NULL,
                    record_id   INT NOT NULL,
                    data        JSON,
                    attempts    INT DEFAULT 0,
                    last_attempt DATETIME DEFAULT NULL,
                    status      ENUM('pending', 'success', 'failed') DEFAULT 'pending',
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_table (table_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            if ($operation && $table && $recordId) {
                $stmt = $pdo->prepare("
                    INSERT INTO sync_queue (operation, table_name, record_id, data)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $operation,
                    $table,
                    $recordId,
                    json_encode($data)
                ]);
            }

        } catch (\Exception $e) {
            error_log("[DPM] Erreur sync_queue : " . $e->getMessage());
        }
    }

    // ============================================================
    // TRAITER LA FILE D'ATTENTE (à appeler périodiquement)
    // ============================================================

    /**
     * Rejouer les opérations en attente vers Hostinger
     * Appeler depuis api/sync.php ou un cron job
     */
    public static function processQueue(): array
    {
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        try {
            $pdo = getPDO();
            $remote = self::getRemoteConnection();

            if (!$remote) {
                return ['error' => 'Hostinger inaccessible'];
            }

            // Récupérer les entrées en attente (max 50 par cycle)
            $stmt = $pdo->prepare("
                SELECT * FROM sync_queue
                WHERE status = 'pending' AND attempts < 3
                ORDER BY created_at ASC
                LIMIT 50
            ");
            $stmt->execute();
            $queue = $stmt->fetchAll();

            foreach ($queue as $item) {
                $results['processed']++;
                $data = json_decode($item['data'], true) ?? [];

                try {
                    switch ($item['operation']) {
                        case 'INSERT':
                            self::syncInsert($item['table_name'], $data, $item['record_id']);
                            break;
                        case 'UPDATE':
                            self::syncUpdate($item['table_name'], $data, $item['record_id']);
                            break;
                        case 'DELETE':
                            self::syncDelete($item['table_name'], $item['record_id']);
                            break;
                    }

                    // Marquer comme succès
                    $pdo->prepare("
                        UPDATE sync_queue
                        SET status = 'success', last_attempt = NOW()
                        WHERE id = ?
                    ")->execute([$item['id']]);

                    $results['success']++;

                } catch (\Exception $e) {
                    // Incrémenter les tentatives
                    $pdo->prepare("
                        UPDATE sync_queue
                        SET attempts = attempts + 1,
                            last_attempt = NOW(),
                            status = IF(attempts + 1 >= 3, 'failed', 'pending')
                        WHERE id = ?
                    ")->execute([$item['id']]);

                    $results['failed']++;
                    self::logSyncError("Queue item #{$item['id']} échoué : " . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            self::logSyncError("processQueue : " . $e->getMessage());
        }

        return $results;
    }

    // ============================================================
    // VÉRIFIER LA CONNEXION HOSTINGER
    // ============================================================
    public static function isRemoteAvailable(): bool
    {
        try {
            $remote = self::getRemoteConnection();
            if (!$remote)
                return false;
            $remote->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ============================================================
    // LOGS
    // ============================================================
    private static function logSyncSuccess(string $op, string $table, int $id): void
    {
        error_log("[DPM SYNC ✅] $op → $table #$id");
    }

    private static function logSyncError(string $message): void
    {
        error_log("[DPM SYNC ❌] $message");

        // Écrire dans logs/sync.log
        $logFile = __DIR__ . '/../logs/sync.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}