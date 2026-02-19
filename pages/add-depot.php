<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

/* ── Permissions ─────────────────────────────────────────── */
$role = $_SESSION['role']
     ?? $_SESSION['user']['role']
     ?? $_SESSION['utilisateur']['role']
     ?? '';
if (!in_array($role, ['super_admin', 'admin'])) {
    header('Location: depots.php'); exit;
}

/* ── Données de référence ────────────────────────────────── */
$depts       = [];
$allArrs     = [];
$allDists    = [];
$deptsWithArr= [];

try {
    $pdo = getPDO();
    $depts        = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs      = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists     = $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Les listes resteront vides — le formulaire s'affichera quand même
}

/* ── Traitement POST ─────────────────────────────────────── */
$errors  = [];
$success = false;
$old     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    // Validation champs obligatoires
    $required = ['depot_pharmaceutique', 'prenom', 'nom', 'numero_decision', 'adresse', 'localite', 'departement'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) {
            $errors[$f] = 'Ce champ est obligatoire';
        }
    }

    if (empty($errors)) {
        $deptId = (int)$_POST['departement'];
        $hasArr = in_array($deptId, array_map('intval', $deptsWithArr));

        try {
            $stmt = $pdo->prepare("
                INSERT INTO depots_dpm
                    (depot_pharmaceutique, prenom, nom, numero_decision, adresse, departement_id,
                     arrondissement_id, district_id, localite, telephone, email,
                     zone_archive, box_rangement, is_active)
                VALUES
                    (:depot_pharmaceutique, :prenom, :nom, :numero_decision, :adresse, :departement_id,
                     :arrondissement_id, :district_id, :localite, :telephone, :email,
                     :zone_archive, :box_rangement, :is_active)
            ");
            $stmt->execute([
                ':depot_pharmaceutique' => trim($_POST['depot_pharmaceutique']),
                ':prenom'               => trim($_POST['prenom']),
                ':nom'                  => trim($_POST['nom']),
                ':numero_decision'      => trim($_POST['numero_decision']),
                ':adresse'              => trim($_POST['adresse']),
                ':departement_id'       => $deptId,
                ':arrondissement_id'    => $hasArr  ? (($_POST['arrondissement_id'] ?? '') ?: null) : null,
                ':district_id'          => !$hasArr ? (($_POST['district_id']       ?? '') ?: null) : null,
                ':localite'             => trim($_POST['localite']),
                ':telephone'            => trim($_POST['telephone'] ?? '') ?: null,
                ':email'                => trim($_POST['email']     ?? '') ?: null,
                ':zone_archive'         => ($_POST['zone_archive']  ?? '') ?: null,
                ':box_rangement'        => trim($_POST['box_rangement'] ?? '') ?: null,
                ':is_active'            => isset($_POST['is_active']) ? 1 : 0,
            ]);
            $success = true;
            $old = [];
        } catch (PDOException $e) {
            $errors['_db'] = 'Erreur base de données : ' . $e->getMessage();
        }
    }
}

/* ── JSON pour JS ────────────────────────────────────────── */
$allArrsJson      = json_encode($allArrs);
$allDistsJson     = json_encode($allDists);
$deptsWithArrJson = json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un dépôt — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/depot-form.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()">
        <span></span><span></span><span></span>
    </button>
    <a href="depots.php" class="topbar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Dépôts
    </a>
    <h1 class="topbar__title">Ajouter un dépôt pharmaceutique</h1>
    <span class="topbar__meta"><?= date('d/m/Y') ?></span>
</header>

<div class="form-page">

    <div class="form-page__header">
        <h2 class="form-page__title">Nouveau dépôt pharmaceutique</h2>
        <p class="form-page__sub">Remplissez les informations pour enregistrer un dépôt</p>
    </div>

    <?php if ($success): ?>
    <div class="form-notif form-notif--success visible">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Dépôt ajouté avec succès !
        <a href="depots.php" style="margin-left:auto;color:inherit;font-weight:700;text-decoration:none;border-bottom:1px solid currentColor">Voir la liste →</a>
    </div>
    <?php endif; ?>

    <?php if (isset($errors['_db'])): ?>
    <div class="form-notif form-notif--error visible">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['_db']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="depotForm" novalidate>
        <div class="form-layout">

            <!-- ── COLONNE GAUCHE ── -->
            <div>

                <!-- Identité -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <div>
                            <div class="form-card__title">Identité du dépôt</div>
                            <div class="form-card__sub">Nom officiel et numéro de décision</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid">
                            <div class="field field-full">
                                <label class="field__label required">Nom du dépôt pharmaceutique</label>
                                <input type="text" name="depot_pharmaceutique"
                                    class="field__input <?= isset($errors['depot_pharmaceutique']) ? 'error' : '' ?>"
                                    placeholder="Ex : Dépôt Central Brazzaville"
                                    value="<?= htmlspecialchars($old['depot_pharmaceutique'] ?? '') ?>" required>
                                <?php if (isset($errors['depot_pharmaceutique'])): ?>
                                    <div class="field__error visible"><?= $errors['depot_pharmaceutique'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="field field-full">
                                <label class="field__label required">Numéro de décision</label>
                                <input type="text" name="numero_decision"
                                    class="field__input <?= isset($errors['numero_decision']) ? 'error' : '' ?>"
                                    placeholder="Ex : DEC-2024-001"
                                    value="<?= htmlspecialchars($old['numero_decision'] ?? '') ?>" required>
                                <?php if (isset($errors['numero_decision'])): ?>
                                    <div class="field__error visible"><?= $errors['numero_decision'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Propriétaire -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <div class="form-card__title">Propriétaire</div>
                            <div class="form-card__sub">Informations du titulaire</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid fields-grid--2">
                            <div class="field">
                                <label class="field__label required">Prénom</label>
                                <input type="text" name="prenom"
                                    class="field__input <?= isset($errors['prenom']) ? 'error' : '' ?>"
                                    placeholder="Prénom"
                                    value="<?= htmlspecialchars($old['prenom'] ?? '') ?>" required>
                                <?php if (isset($errors['prenom'])): ?>
                                    <div class="field__error visible"><?= $errors['prenom'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label required">Nom</label>
                                <input type="text" name="nom"
                                    class="field__input <?= isset($errors['nom']) ? 'error' : '' ?>"
                                    placeholder="Nom de famille"
                                    value="<?= htmlspecialchars($old['nom'] ?? '') ?>" required>
                                <?php if (isset($errors['nom'])): ?>
                                    <div class="field__error visible"><?= $errors['nom'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label">Téléphone</label>
                                <input type="tel" name="telephone" class="field__input"
                                    placeholder="+242 06 000 0000"
                                    value="<?= htmlspecialchars($old['telephone'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label class="field__label">Email</label>
                                <input type="email" name="email" class="field__input"
                                    placeholder="contact@depot.com"
                                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Localisation -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div>
                            <div class="form-card__title">Localisation</div>
                            <div class="form-card__sub">Adresse et position géographique</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid">
                            <div class="field field-full">
                                <label class="field__label required">Adresse</label>
                                <input type="text" name="adresse"
                                    class="field__input <?= isset($errors['adresse']) ? 'error' : '' ?>"
                                    placeholder="Rue, avenue..."
                                    value="<?= htmlspecialchars($old['adresse'] ?? '') ?>" required>
                                <?php if (isset($errors['adresse'])): ?>
                                    <div class="field__error visible"><?= $errors['adresse'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="field field-full">
                                <label class="field__label required">Localité</label>
                                <input type="text" name="localite"
                                    class="field__input <?= isset($errors['localite']) ? 'error' : '' ?>"
                                    placeholder="Ville ou commune"
                                    value="<?= htmlspecialchars($old['localite'] ?? '') ?>" required>
                                <?php if (isset($errors['localite'])): ?>
                                    <div class="field__error visible"><?= $errors['localite'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="fields-grid fields-grid--2" style="margin-top:16px">
                            <div class="field">
                                <label class="field__label required">Département</label>
                                <div class="field__select-wrap">
                                    <select name="departement" id="selectDept"
                                        class="<?= isset($errors['departement']) ? 'error' : '' ?>" required>
                                        <option value="">— Sélectionner —</option>
                                        <?php foreach ($depts as $d): ?>
                                            <option value="<?= $d['id'] ?>"
                                                <?= (string)($old['departement'] ?? '') === (string)$d['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (isset($errors['departement'])): ?>
                                    <div class="field__error visible"><?= $errors['departement'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="field field-location" id="fieldArrondissement">
                                <label class="field__label">Arrondissement</label>
                                <div class="field__select-wrap">
                                    <select name="arrondissement_id" id="selectArr">
                                        <option value="">— Sélectionner —</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field field-location" id="fieldDistrict">
                                <label class="field__label">District</label>
                                <div class="field__select-wrap">
                                    <select name="district_id" id="selectDist">
                                        <option value="">— Sélectionner —</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Archivage -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                        </div>
                        <div>
                            <div class="form-card__title">Archivage</div>
                            <div class="form-card__sub">Box et zone de rangement</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid fields-grid--2">
                            <div class="field">
                                <label class="field__label">Box rangement</label>
                                <input type="text" name="box_rangement" class="field__input"
                                    placeholder="BOX-D001"
                                    value="<?= htmlspecialchars($old['box_rangement'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label class="field__label">Zone archive</label>
                                <div class="field__select-wrap">
                                    <select name="zone_archive">
                                        <option value="">— Aucune —</option>
                                        <?php foreach (['Salle I','Salle II','Salle III','Salle IV','Salle V'] as $s): ?>
                                            <option value="<?= $s ?>"
                                                <?= ($old['zone_archive'] ?? '') === $s ? 'selected' : '' ?>>
                                                <?= $s ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="toggle-group" style="margin-top:16px">
                            <label class="toggle-item">
                                <input type="checkbox" name="is_active"
                                    <?= (empty($_POST) || isset($old['is_active'])) ? 'checked' : '' ?>>
                                <div class="toggle-switch"></div>
                                <span class="toggle-label">Dépôt actif</span>
                            </label>
                        </div>
                    </div>
                </div>

            </div><!-- /colonne gauche -->

            <!-- ── SIDEBAR DROITE ── -->
            <div class="form-sidebar">
                <div class="floating-completion">
                    <div class="floating-completion__header">
                        <div class="floating-completion__title">Complétion</div>
                        <div class="floating-completion__percent" id="completionPercent">0%</div>
                    </div>
                    <div class="completion-bar">
                        <div class="completion-bar__fill" id="completionBar" style="width:0%"></div>
                    </div>

                    <div class="live-preview">
                        <div class="live-preview__item">
                            <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                            <span class="live-preview__label">Dépôt</span>
                            <span class="live-preview__value empty" id="previewNom">—</span>
                        </div>
                        <div class="live-preview__item">
                            <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <span class="live-preview__label">Prop.</span>
                            <span class="live-preview__value empty" id="previewProp">—</span>
                        </div>
                        <div class="live-preview__item">
                            <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span class="live-preview__label">Localité</span>
                            <span class="live-preview__value empty" id="previewLoc">—</span>
                        </div>
                        <div class="live-preview__item">
                            <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg>
                            <span class="live-preview__label">Dept.</span>
                            <span class="live-preview__value empty" id="previewDept">—</span>
                        </div>
                    </div>

                    <div class="validation-badges">
                        <div class="validation-badge" id="badgeIdentite">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                            Identité
                        </div>
                        <div class="validation-badge" id="badgeProprietaire">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                            Propriétaire
                        </div>
                        <div class="validation-badge" id="badgeLocalisation">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>
                            Localisation
                        </div>
                    </div>
                </div>

                <div class="form-sidebar__actions">
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                        Enregistrer
                    </button>
                    <a href="depots.php" class="btn-cancel">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M15 9l-6 6M9 9l6 6" />
                        </svg>Annuler
                    </a>
                </div>
            </div>

        </div><!-- /form-layout -->
    </form>
</div><!-- /form-page -->
</div><!-- /sb-main -->

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_ARRS       = <?= $allArrsJson ?>;
const ALL_DISTS      = <?= $allDistsJson ?>;
const DEPTS_WITH_ARR = <?= $deptsWithArrJson ?>;

/* ── Département → Arrondissement / District ─────────────── */
function applyDeptChange(deptId, selectedArrId, selectedDistId) {
    const id       = parseInt(deptId) || 0;
    const fieldArr = document.getElementById('fieldArrondissement');
    const fieldDist= document.getElementById('fieldDistrict');
    const selArr   = document.getElementById('selectArr');
    const selDist  = document.getElementById('selectDist');

    fieldArr.classList.remove('visible');
    fieldDist.classList.remove('visible');
    selArr.innerHTML  = '<option value="">— Sélectionner —</option>';
    selDist.innerHTML = '<option value="">— Sélectionner —</option>';
    if (!id) return;

    if (DEPTS_WITH_ARR.includes(id)) {
        ALL_ARRS.filter(a => a.departement_id == id).forEach(a => {
            const o = document.createElement('option');
            o.value = a.id; o.textContent = a.libelle;
            if (selectedArrId && String(a.id) === String(selectedArrId)) o.selected = true;
            selArr.appendChild(o);
        });
        fieldArr.classList.add('visible');
    } else {
        ALL_DISTS.filter(d => d.departement_id == id).forEach(d => {
            const o = document.createElement('option');
            o.value = d.id; o.textContent = d.libelle;
            if (selectedDistId && String(d.id) === String(selectedDistId)) o.selected = true;
            selDist.appendChild(o);
        });
        fieldDist.classList.add('visible');
    }
}

document.getElementById('selectDept').addEventListener('change', function () {
    applyDeptChange(this.value);
    updateCompletion();
});

/* ── Completion & live preview ───────────────────────────── */
const REQUIRED = ['depot_pharmaceutique','prenom','nom','numero_decision','adresse','localite','departement'];

function updateCompletion() {
    const filled = REQUIRED.filter(n => {
        const el = document.querySelector(`[name="${n}"]`);
        return el && el.value.trim();
    }).length;
    const pct = Math.round((filled / REQUIRED.length) * 100);

    document.getElementById('completionBar').style.width   = pct + '%';
    document.getElementById('completionPercent').textContent = pct + '%';

    const nom     = document.querySelector('[name=depot_pharmaceutique]')?.value.trim() || '';
    const prenom  = document.querySelector('[name=prenom]')?.value.trim() || '';
    const nomProp = document.querySelector('[name=nom]')?.value.trim() || '';
    const localite= document.querySelector('[name=localite]')?.value.trim() || '';
    const deptEl  = document.getElementById('selectDept');
    const deptTxt = deptEl.selectedIndex > 0 ? deptEl.options[deptEl.selectedIndex].text : '';

    const setPreview = (id, val) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val || '—';
        el.className   = val ? 'live-preview__value' : 'live-preview__value empty';
    };
    setPreview('previewNom',  nom);
    setPreview('previewProp', (prenom || nomProp) ? `${prenom} ${nomProp}`.trim() : '');
    setPreview('previewLoc',  localite);
    setPreview('previewDept', deptTxt);

    const setBadge = (id, ok) => {
        const b = document.getElementById(id);
        if (!b) return;
        if (ok) {
            b.classList.add('completed');
            b.querySelector('svg').innerHTML = '<polyline points="20 6 9 17 4 12"/>';
        } else {
            b.classList.remove('completed');
            b.querySelector('svg').innerHTML = '<circle cx="12" cy="12" r="10"/>';
        }
    };
    setBadge('badgeIdentite',    !!(nom && document.querySelector('[name=numero_decision]')?.value.trim()));
    setBadge('badgeProprietaire',!!(prenom && nomProp));
    setBadge('badgeLocalisation',!!(document.querySelector('[name=adresse]')?.value.trim() && localite && deptEl?.value));
}

document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('input',  updateCompletion);
    el.addEventListener('change', updateCompletion);
});

/* ── Submit spinner ──────────────────────────────────────── */
document.getElementById('depotForm').addEventListener('submit', function () {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
        style="animation:spin .7s linear infinite;width:17px;height:17px">
        <path d="M21 12a9 9 0 1 1-6.22-8.56"/>
    </svg> Enregistrement...`;
});

/* ── Init ────────────────────────────────────────────────── */
<?php if (!empty($_POST)): ?>
applyDeptChange(
    <?= (int)($old['departement'] ?? 0) ?>,
    <?= (int)($old['arrondissement_id'] ?? 0) ?>,
    <?= (int)($old['district_id'] ?? 0) ?>
);
<?php endif; ?>
updateCompletion();
</script>
</body>
</html>