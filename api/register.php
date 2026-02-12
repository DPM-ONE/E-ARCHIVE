<?php
/**
 * API INSCRIPTION
 * Création d'un nouveau compte utilisateur
 */

// Headers JSON
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Inclure les dépendances
require_once '../config/database.php';

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

try {
    // Récupérer les données
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matricule = trim($_POST['matricule'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // ===== VALIDATIONS =====
    
    // Champs obligatoires
    if (empty($username) || empty($email) || empty($matricule) || empty($password)) {
        throw new Exception('Tous les champs sont obligatoires');
    }
    
    // Format username
    if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $username)) {
        throw new Exception('Le nom d\'utilisateur doit contenir 3-50 caractères (lettres, chiffres, ., _, -)');
    }
    
    // Format email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Format d\'email invalide');
    }
    
    // Email DPM uniquement
    if (!str_ends_with($email, '@dpm.cg')) {
        throw new Exception('Seuls les emails @dpm.cg sont autorisés');
    }
    
    // Format matricule
    if (!preg_match('/^DPM\d{7}$/', $matricule)) {
        throw new Exception('Format matricule invalide (ex: DPM2025001)');
    }
    
    // Longueur mot de passe
    if (strlen($password) < 8) {
        throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
    }
    
    // Confirmation mot de passe
    if ($password !== $passwordConfirm) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }
    
    // Complexité mot de passe (au moins 1 majuscule, 1 minuscule, 1 chiffre)
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        throw new Exception('Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre');
    }
    
    // ===== VÉRIFICATIONS BDD =====
    $pdo = getPDO();
    
    // Vérifier si username existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new Exception('Ce nom d\'utilisateur est déjà pris');
    }
    
    // Vérifier si email existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà enregistré');
    }
    
    // Vérifier si le matricule existe dans agents_dpm
    $stmt = $pdo->prepare("SELECT id FROM agents_dpm WHERE matricule = ? AND deleted_at IS NULL");
    $stmt->execute([$matricule]);
    $agent = $stmt->fetch();
    
    if (!$agent) {
        throw new Exception('Matricule non trouvé. Veuillez contacter l\'administrateur.');
    }
    
    // Vérifier si l'agent n'a pas déjà un compte
    $stmt = $pdo->prepare("SELECT id FROM users WHERE agent_id = ?");
    $stmt->execute([$agent['id']]);
    if ($stmt->fetch()) {
        throw new Exception('Un compte existe déjà pour ce matricule');
    }
    
    // ===== CRÉATION DU COMPTE =====
    
    // Hacher le mot de passe
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insérer dans users
    $stmt = $pdo->prepare("
        INSERT INTO users (
            username,
            email,
            password_hash,
            agent_id,
            role,
            is_active,
            password_changed_at
        ) VALUES (?, ?, ?, ?, 'lecteur', 1, NOW())
    ");
    
    $stmt->execute([
        $username,
        $email,
        $passwordHash,
        $agent['id']
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Permissions par défaut pour "lecteur"
    $defaultPermissions = json_encode([
        'courriers' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'visas' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'pharmacies' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'agences' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'depots' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'laboratoires' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'medicaments' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'delegues' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'formations' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'pharmaciens' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'depositaires' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'rapports' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false],
        'agents' => ['view' => true, 'create' => false, 'edit' => false, 'delete' => false, 'export' => false]
    ]);
    
    $stmt = $pdo->prepare("UPDATE users SET permissions = ? WHERE id = ?");
    $stmt->execute([$defaultPermissions, $userId]);
    
    // Logger l'inscription
    logRegistration($userId, $username);
    
    // Réponse succès
    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Logger l'inscription
 */
function logRegistration($userId, $username) {
    try {
        $pdo = getPDO();
        
        // Créer table si n'existe pas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS registration_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                username VARCHAR(100),
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO registration_logs (user_id, username, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $username,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        error_log("Erreur logRegistration : " . $e->getMessage());
    }
}