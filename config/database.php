<?php
/**
 * CONFIGURATION BASE DE DONNÉES
 * Connexion locale (Laragon) et serveur distant (Hostinger)
 */

// ===== ENVIRONNEMENT =====
// Définir l'environnement : 'local' ou 'production'
define('ENVIRONMENT', 'local');

// ===== CONFIGURATION LOCALE (LARAGON) =====
define('DB_LOCAL_HOST', 'localhost');
define('DB_LOCAL_NAME', 'archive_dpm');
define('DB_LOCAL_USER', 'root');
define('DB_LOCAL_PASS', '');
define('DB_LOCAL_CHARSET', 'utf8mb4');

// ===== CONFIGURATION SERVEUR (HOSTINGER) =====
define('DB_SERVER_HOST', 'votre-serveur.mysql.hostinger.com');
define('DB_SERVER_NAME', 'u123456789_archive_dpm');
define('DB_SERVER_USER', 'u123456789_dpm_user');
define('DB_SERVER_PASS', 'VotreMotDePasseSecurise123!');
define('DB_SERVER_CHARSET', 'utf8mb4');

// ===== SÉLECTION CONFIGURATION =====
if (ENVIRONMENT === 'local') {
    define('DB_HOST', DB_LOCAL_HOST);
    define('DB_NAME', DB_LOCAL_NAME);
    define('DB_USER', DB_LOCAL_USER);
    define('DB_PASS', DB_LOCAL_PASS);
} else {
    define('DB_HOST', DB_SERVER_HOST);
    define('DB_NAME', DB_SERVER_NAME);
    define('DB_USER', DB_SERVER_USER);
    define('DB_PASS', DB_SERVER_PASS);
}

define('DB_CHARSET', 'utf8mb4');

/**
 * Classe de connexion à la base de données
 */
class Database
{
    private static $instance = null;
    private $connection;

    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            error_log("Erreur de connexion BDD : " . $e->getMessage());
            die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
        }
    }

    /**
     * Récupérer l'instance unique
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Récupérer la connexion PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone()
    {
    }

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Fonction helper pour obtenir la connexion PDO
 */
function getPDO()
{
    return Database::getInstance()->getConnection();
}

/**
 * Tester la connexion
 */
function testDatabaseConnection()
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        error_log("Test connexion échoué : " . $e->getMessage());
        return false;
    }
}