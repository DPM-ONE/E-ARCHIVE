<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['super_admin', 'admin'], true)) {
    header('Location: laboratoires.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: laboratoires.php');
    exit;
}

$success = false;
$errors  = [];
$old     = [];
$labo    = null;

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM laboratoires_dpm WHERE id = ?");
    $stmt->execute([$id]);
    $labo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$labo) {
        header('Location: laboratoires.php');
        exit;
    }
    
    $agences = $pdo->query("SELECT id, nom_agence FROM agences_dpm ORDER BY nom_agence")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $agences = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['nom_laboratoire', 'agence_id'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) $errors[$f] = 'Ce champ est obligatoire';
    }
    if (!empty($errors)) goto render;

    try {
        $stmt = $pdo->prepare("
            UPDATE laboratoires_dpm SET
                nom_laboratoire = :nom,
                pays = :pays,
                agence_id = :agence
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom'    => trim($_POST['nom_laboratoire']),
            ':pays'   => trim($_POST['pays'] ?? '') ?: '',
            ':agence' => (int)$_POST['agence_id'],
            ':id'     => $id
        ]);

        $stmt = $pdo->prepare("SELECT * FROM laboratoires_dpm WHERE id = ?");
        $stmt->execute([$id]);
        $labo = $stmt->fetch(PDO::FETCH_ASSOC);
        $old = $labo;
        $success = true;
    } catch (PDOException $e) {
        $errors['_db'] = $e->getCode() === '23000'
            ? 'Erreur : référence invalide'
            : 'Erreur base de données : ' . $e->getMessage();
    }
}

render:
if (!$success) $old = $_POST ?: $labo;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Laboratoire — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/depot-form.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="topbar">
    <a href="laboratoires.php" class="topbar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
        </svg>
        Retour
    </a>
    <h1 class="topbar__title">DPM Archive</h1>
    <span class="topbar__meta"><?= date('d/m/Y') ?></span>
</header>

<div class="page">
    <div class="page-head">
        <div class="page-head__eyebrow">Modifier</div>
        <h2 class="page-head__title"><?= htmlspecialchars($labo['nom_laboratoire'] ?? 'Laboratoire') ?></h2>
    </div>

    <?php if ($success): ?>
        <div class="alert alert--success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <div>
                <strong>Modifications enregistrées</strong>
                <p><a href="laboratoires.php">Retour à la liste</a></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors['_db'])): ?>
        <div class="alert alert--error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div><strong>Erreur</strong><p><?= htmlspecialchars($errors['_db']) ?></p></div>
        </div>
    <?php endif; ?>

    <div class="completion-card">
        <div class="completion-bar" id="completionBar"></div>
        <div class="completion-text">
            <strong id="completionCount">0</strong> / <span id="completionTotal">2</span> champs complétés
        </div>
    </div>

    <form method="POST" class="form" id="mainForm">
        <div class="form-section">
            <h3 class="form-section-title">Informations</h3>
            <div class="form-row">
                <div class="form-col">
                    <label class="label" for="nom_laboratoire">
                        Nom du laboratoire <span class="required">*</span>
                    </label>
                    <input type="text" class="input" id="nom_laboratoire" name="nom_laboratoire"
                           value="<?= htmlspecialchars($old['nom_laboratoire'] ?? '') ?>" required>
                    <?php if (!empty($errors['nom_laboratoire'])): ?>
                        <div class="error"><?= $errors['nom_laboratoire'] ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label class="label" for="pays">Pays</label>
                    <input type="text" class="input" id="pays" name="pays"
                           value="<?= htmlspecialchars($old['pays'] ?? '') ?>"
                           placeholder="Ex: Congo, France...">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label class="label" for="agence_id">
                        Agence <span class="required">*</span>
                    </label>
                    <select class="select" id="agence_id" name="agence_id" required>
                        <option value="">Sélectionner une agence</option>
                        <?php foreach ($agences as $a): ?>
                            <option value="<?= $a['id'] ?>"
                                <?= (int)($old['agence_id'] ?? 0) === (int)$a['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nom_agence']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['agence_id'])): ?>
                        <div class="error"><?= $errors['agence_id'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="laboratoires.php" class="btn btn--secondary">Annuler</a>
            <button type="submit" class="btn btn--primary">Enregistrer</button>
        </div>
    </form>
</div>

</div>

<script>
const requiredFields = ['nom_laboratoire', 'agence_id'];
const completionBar = document.getElementById('completionBar');
const completionCount = document.getElementById('completionCount');
const completionTotal = document.getElementById('completionTotal');
completionTotal.textContent = requiredFields.length;

function updateCompletion() {
    let filled = requiredFields.filter(n => {
        const el = document.getElementsByName(n)[0];
        return el && el.value && el.value.trim();
    }).length;
    const pct = (filled / requiredFields.length) * 100;
    completionBar.style.width = pct + '%';
    completionCount.textContent = filled;
}

requiredFields.forEach(n => {
    const el = document.getElementsByName(n)[0];
    if (el) {
        el.addEventListener('input', updateCompletion);
        el.addEventListener('change', updateCompletion);
    }
});

updateCompletion();
</script>
</body>
</html>