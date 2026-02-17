<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])) {
    header('Location: grossistes.php?toast=permission_denied');
    exit;
}

$errors = [];
$success = false;
$old = [];

try {
    $pdo = getPDO();
    $depts = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists = $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $depts = $allArrs = $allDists = [];
    $deptsWithArr = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $required = ['nom_grossiste', 'responsable', 'telephone', 'adresse', 'quartier', 'departement'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? '')))
            $errors[$f] = 'Ce champ est obligatoire';
    }
    if (!empty($errors))
        goto render;

    $deptId = (int) $_POST['departement'];
    $hasArr = in_array($deptId, $deptsWithArr);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO grossistes_dpm
                (nom_grossiste, responsable, telephone, email,
                 adresse, quartier, departement, arrondissement_id, district_id,
                 box_rangement, zone_archive, is_actif)
            VALUES
                (:nom_grossiste, :responsable, :telephone, :email,
                 :adresse, :quartier, :departement, :arr_id, :dist_id,
                 :box_rangement, :zone_archive, :is_actif)
        ");
        $stmt->execute([
            ':nom_grossiste' => trim($_POST['nom_grossiste']),
            ':responsable' => trim($_POST['responsable']),
            ':telephone' => trim($_POST['telephone']),
            ':email' => trim($_POST['email'] ?? '') ?: null,
            ':adresse' => trim($_POST['adresse']),
            ':quartier' => trim($_POST['quartier']),
            ':departement' => $deptId,
            ':arr_id' => $hasArr ? ($_POST['arrondissement_id'] ?: null) : null,
            ':dist_id' => !$hasArr ? ($_POST['district_id'] ?: null) : null,
            ':box_rangement' => trim($_POST['box_rangement'] ?? '') ?: null,
            ':zone_archive' => $_POST['zone_archive'] ?: null,
            ':is_actif' => isset($_POST['is_actif']) ? 1 : 0,
        ]);
        $success = true;
        $old = [];
    } catch (PDOException $e) {
        $errors['_db'] = $e->getCode() === '23000'
            ? 'Référence invalide (département, arrondissement ou district)'
            : 'Erreur base de données : ' . $e->getMessage();
    }
}

render:
$allArrsJson = json_encode($allArrs);
$allDistsJson = json_encode($allDists);
$deptsWithArrJson = json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Ajouter un grossiste — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/grossiste-form.css">
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <div class="sb-main">
        <header class="topbar">
            <button class="topbar__hamburger"
                onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()"><span></span><span></span><span></span></button>
            <a href="grossistes.php" class="topbar__back">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="15 18 9 12 15 6" />
                </svg>Grossistes
            </a>
            <h1 class="topbar__title">Ajouter un grossiste</h1>
            <span class="topbar__meta">
                <?= date('d/m/Y') ?>
            </span>
        </header>

        <div class="form-page">
            <div class="form-page__header">
                <h2 class="form-page__title">Nouveau grossiste</h2>
                <p class="form-page__sub">Remplissez les informations pour enregistrer un grossiste</p>
            </div>

            <?php if ($success): ?>
                <div class="form-notif form-notif--success visible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Grossiste ajouté avec succès !
                    <a href="grossistes.php"
                        style="margin-left:auto;color:inherit;font-weight:700;text-decoration:none;border-bottom:1px solid currentColor">Voir
                        la liste →</a>
                </div>
            <?php endif; ?>
            <?php if (isset($errors['_db'])): ?>
                <div class="form-notif form-notif--error visible">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" y2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <?= htmlspecialchars($errors['_db']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="grossisteForm" novalidate>
                <div class="form-layout">
                    <!-- LEFT COLUMN -->
                    <div>
                        <!-- Identité -->
                        <div class="form-card">
                            <div class="form-card__header">
                                <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.75">
                                        <path
                                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                        <line x1="12" y1="22.08" x2="12" y2="12" />
                                    </svg></div>
                                <div>
                                    <div class="form-card__title">Identité du grossiste</div>
                                    <div class="form-card__sub">Nom, responsable et contacts</div>
                                </div>
                            </div>
                            <div class="form-card__body">
                                <div class="fields-grid">
                                    <div class="field field-full">
                                        <label class="field__label required">Nom du grossiste</label>
                                        <input type="text" name="nom_grossiste"
                                            class="field__input <?= isset($errors['nom_grossiste']) ? 'error' : '' ?>"
                                            placeholder="Ex : UBIPHARM Congo"
                                            value="<?= htmlspecialchars($old['nom_grossiste'] ?? '') ?>" required>
                                        <?php if (isset($errors['nom_grossiste'])): ?>
                                            <div class="field__error visible">
                                                <?= $errors['nom_grossiste'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="fields-grid fields-grid--2" style="margin-top:16px">
                                    <div class="field">
                                        <label class="field__label required">Responsable</label>
                                        <input type="text" name="responsable"
                                            class="field__input <?= isset($errors['responsable']) ? 'error' : '' ?>"
                                            placeholder="Nom complet"
                                            value="<?= htmlspecialchars($old['responsable'] ?? '') ?>" required>
                                        <?php if (isset($errors['responsable'])): ?>
                                            <div class="field__error visible">
                                                <?= $errors['responsable'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="field">
                                        <label class="field__label required">Téléphone</label>
                                        <input type="tel" name="telephone"
                                            class="field__input <?= isset($errors['telephone']) ? 'error' : '' ?>"
                                            placeholder="+242 06 000 0000"
                                            value="<?= htmlspecialchars($old['telephone'] ?? '') ?>" required>
                                        <?php if (isset($errors['telephone'])): ?>
                                            <div class="field__error visible">
                                                <?= $errors['telephone'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="field field-full">
                                        <label class="field__label">Email</label>
                                        <input type="email" name="email" class="field__input"
                                            placeholder="contact@grossiste.com"
                                            value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Localisation -->
                        <div class="form-card">
                            <div class="form-card__header">
                                <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.75">
                                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg></div>
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
                                            <div class="field__error visible">
                                                <?= $errors['adresse'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="field field-full">
                                        <label class="field__label required">Quartier</label>
                                        <input type="text" name="quartier"
                                            class="field__input <?= isset($errors['quartier']) ? 'error' : '' ?>"
                                            placeholder="Nom du quartier"
                                            value="<?= htmlspecialchars($old['quartier'] ?? '') ?>" required>
                                        <?php if (isset($errors['quartier'])): ?>
                                            <div class="field__error visible">
                                                <?= $errors['quartier'] ?>
                                            </div>
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
                                                        <?= ($old['departement'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($d['libelle']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if (isset($errors['departement'])): ?>
                                            <div class="field__error visible">
                                                <?= $errors['departement'] ?>
                                            </div>
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

                        <!-- Archive -->
                        <div class="form-card">
                            <div class="form-card__header">
                                <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="1.75">
                                        <polyline points="21 8 21 21 3 21 3 8" />
                                        <rect x="1" y="3" width="22" height="5" />
                                        <line x1="10" y1="12" x2="14" y2="12" />
                                    </svg></div>
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
                                            placeholder="BOX-G001"
                                            value="<?= htmlspecialchars($old['box_rangement'] ?? '') ?>">
                                    </div>
                                    <div class="field">
                                        <label class="field__label">Zone archive</label>
                                        <div class="field__select-wrap">
                                            <select name="zone_archive">
                                                <option value="">— Aucune —</option>
                                                <?php foreach (['Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'] as $s): ?>
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
                                    <label class="toggle-item"><input type="checkbox" name="is_actif"
                                            <?= !$_POST || isset($old['is_actif']) ? 'checked' : '' ?>><div
                                            class="toggle-switch"></div><span class="toggle-label">Grossiste
                                            actif</span></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SIDEBAR: FLOATING COMPLETION CARD -->
                    <div class="form-sidebar">
                        <div class="floating-completion">
                            <div class="floating-completion__header">
                                <div class="floating-completion__title">Complétion</div>
                                <div class="floating-completion__percent" id="completionPercent">0%</div>
                            </div>
                            <div class="completion-bar">
                                <div class="completion-bar__fill" id="completionBar" style="width: 0%"></div>
                            </div>

                            <div class="live-preview">
                                <div class="live-preview__item">
                                    <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path
                                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                    </svg>
                                    <span class="live-preview__label">Nom</span>
                                    <span class="live-preview__value empty" id="previewNom">—</span>
                                </div>
                                <div class="live-preview__item">
                                    <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <span class="live-preview__label">Resp.</span>
                                    <span class="live-preview__value empty" id="previewResp">—</span>
                                </div>
                                <div class="live-preview__item">
                                    <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path
                                            d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6.01-6.01A19.79 19.79 0 0 1 1.61 3.45 2 2 0 0 1 3.59 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.85a16 16 0 0 0 6.29 6.29l.87-.88a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
                                    </svg>
                                    <span class="live-preview__label">Tél.</span>
                                    <span class="live-preview__value empty" id="previewTel">—</span>
                                </div>
                                <div class="live-preview__item">
                                    <svg class="live-preview__icon" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6" />
                                    </svg>
                                    <span class="live-preview__label">Dept.</span>
                                    <span class="live-preview__value empty" id="previewDept">—</span>
                                </div>
                            </div>

                            <div class="validation-badges">
                                <div class="validation-badge" id="badgeIdentite">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                    </svg>
                                    Identité
                                </div>
                                <div class="validation-badge" id="badgeLocalisation">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                    </svg>
                                    Localisation
                                </div>
                            </div>
                        </div>

                        <div class="form-sidebar__actions">
                            <button type="submit" class="btn-submit" id="btnSubmit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                    <polyline points="17 21 17 13 7 13 7 21" />
                                </svg>
                                Enregistrer
                            </button>
                            <a href="grossistes.php" class="btn-cancel">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
    <circle cx="12" cy="12" r="9" />
    <path d="M15 9l-6 6M9 9l6 6" />
</svg>Annuler</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/sidebar.js"></script>
    <script>
        const ALL_ARRS = <?= $allArrsJson ?>;
        const ALL_DISTS = <?= $allDistsJson ?>;
        const DEPTS_WITH_ARR = <?= $deptsWithArrJson ?>;

        function applyDeptChange(deptId, selectedArrId, selectedDistId) {
            const id = parseInt(deptId) || 0;
            const fieldArr = document.getElementById('fieldArrondissement');
            const fieldDist = document.getElementById('fieldDistrict');
            const selArr = document.getElementById('selectArr');
            const selDist = document.getElementById('selectDist');

            fieldArr.classList.remove('visible');
            fieldDist.classList.remove('visible');
            selArr.innerHTML = '<option value="">— Sélectionner —</option>';
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

        // Live preview & completion
        const requiredFields = ['nom_grossiste', 'responsable', 'telephone', 'adresse', 'quartier'];
        function updateCompletion() {
            // Calcul progression
            let filled = requiredFields.filter(n => {
                const el = document.querySelector(`[name="${n}"]`);
                return el && el.value.trim();
            }).length;
            if (document.getElementById('selectDept')?.value) filled++;
            const total = requiredFields.length + 1;
            const pct = Math.round((filled / total) * 100);

            document.getElementById('completionBar').style.width = pct + '%';
            document.getElementById('completionPercent').textContent = pct + '%';

            // Live preview
            const nom = document.querySelector('[name=nom_grossiste]')?.value || '';
            const resp = document.querySelector('[name=responsable]')?.value || '';
            const tel = document.querySelector('[name=telephone]')?.value || '';
            const deptEl = document.getElementById('selectDept');
            const deptTxt = deptEl.options[deptEl.selectedIndex]?.text || '';

            const updatePreview = (id, val) => {
                const el = document.getElementById(id);
                el.textContent = val || '—';
                el.className = val ? 'live-preview__value' : 'live-preview__value empty';
            };

            updatePreview('previewNom', nom);
            updatePreview('previewResp', resp);
            updatePreview('previewTel', tel);
            updatePreview('previewDept', deptTxt === '— Sélectionner —' ? '' : deptTxt);

            // Badges validation
            const identiteOk = nom && resp && tel;
            const localisationOk = document.querySelector('[name=adresse]')?.value && document.querySelector('[name=quartier]')?.value && deptEl?.value;

            const badgeIdentite = document.getElementById('badgeIdentite');
            const badgeLocalisation = document.getElementById('badgeLocalisation');

            if (identiteOk && !badgeIdentite.classList.contains('completed')) {
                badgeIdentite.classList.add('completed');
                badgeIdentite.querySelector('svg').innerHTML = '<polyline points="20 6 9 17 4 12"/>';
            } else if (!identiteOk) {
                badgeIdentite.classList.remove('completed');
                badgeIdentite.querySelector('svg').innerHTML = '<circle cx="12" cy="12" r="10"/>';
            }

            if (localisationOk && !badgeLocalisation.classList.contains('completed')) {
                badgeLocalisation.classList.add('completed');
                badgeLocalisation.querySelector('svg').innerHTML = '<polyline points="20 6 9 17 4 12"/>';
            } else if (!localisationOk) {
                badgeLocalisation.classList.remove('completed');
                badgeLocalisation.querySelector('svg').innerHTML = '<circle cx="12" cy="12" r="10"/>';
            }
        }

        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', updateCompletion);
            el.addEventListener('change', updateCompletion);
        });

        document.getElementById('grossisteForm').addEventListener('submit', function () {
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite;width:17px;height:17px"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement...`;
        });

// Init
<?php if ($_POST): ?>
                setTimeout(() => {
                    applyDeptChange(<?= (int) ($old['departement'] ?? 0) ?>, <?= (int) ($old['arrondissement_id'] ?? 0) ?>, <?= (int) ($old['district_id'] ?? 0) ?>);
                    updateCompletion();
                }, 50);
<?php else: ?>
                updateCompletion();
<?php endif; ?>
    </script>
</body>

</html>