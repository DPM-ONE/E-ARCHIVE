<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['super_admin','admin'])) {
    header('Location: fosa.php?toast=permission_denied'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: fosa.php'); exit; }

$errors  = [];
$success = false;

try {
    $pdo   = getPDO();
    $depts = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allDS = $pdo->query("SELECT id, nom_ds, departement_id FROM districts_sanitaires_dpm ORDER BY departement_id, nom_ds")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM fosa_dpm WHERE id = ?");
    $stmt->execute([$id]);
    $fosa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fosa = null; $depts = $allDS = [];
}

if (!$fosa) { header('Location: fosa.php?toast=not_found'); exit; }

$old = $fosa;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array_merge($fosa, $_POST);
    $old['id'] = $id;

    $required = ['nom_fosa', 'departement_id', 'district_sanitaire_id'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) $errors[$f] = 'Ce champ est obligatoire';
    }
    if (!empty($errors)) goto render;

    try {
        $stmt = $pdo->prepare("
            UPDATE fosa_dpm SET
                nom_fosa               = :nom_fosa,
                departement_id         = :dept_id,
                district_sanitaire_id  = :ds_id,
                prenom_responsable     = :prenom,
                nom_responsable        = :nom,
                telephone              = :tel,
                adresse                = :adresse,
                updated_at             = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom_fosa' => trim($_POST['nom_fosa']),
            ':dept_id'  => (int)$_POST['departement_id'],
            ':ds_id'    => (int)$_POST['district_sanitaire_id'],
            ':prenom'   => trim($_POST['prenom_responsable'] ?? '') ?: null,
            ':nom'      => trim($_POST['nom_responsable']    ?? '') ?: null,
            ':tel'      => trim($_POST['telephone']          ?? '') ?: null,
            ':adresse'  => trim($_POST['adresse']            ?? '') ?: null,
            ':id'       => $id,
        ]);
        $success = true;
        $st2 = $pdo->prepare("SELECT * FROM fosa_dpm WHERE id = ?");
        $st2->execute([$id]);
        $fosa = $st2->fetch(PDO::FETCH_ASSOC);
        $old  = $fosa;
    } catch (PDOException $e) {
        $errors['_db'] = $e->getCode() === '23000'
            ? 'Référence invalide (département ou district sanitaire)'
            : 'Erreur base de données : ' . $e->getMessage();
    }
}

render:
function sel($val, $compare): string { return (string)$val === (string)$compare ? 'selected' : ''; }
$allDSJson = json_encode($allDS, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Modifier — <?= htmlspecialchars($fosa['nom_fosa']) ?> — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/pharmacie-form.css">
    <style>
    @keyframes spin { to { transform:rotate(360deg); } }
    .field-ds { display:none }
    .field-ds.visible { display:block }
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">
<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()"><span></span><span></span><span></span></button>
    <a href="fosa.php" class="topbar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>FOSA
    </a>
    <h1 class="topbar__title">Modifier une FOSA</h1>
    <span class="topbar__meta"><?= date('d/m/Y') ?></span>
</header>

<div class="form-page">
    <div class="form-page__header">
        <h2 class="form-page__title"><?= htmlspecialchars($fosa['nom_fosa']) ?></h2>
        <p class="form-page__sub">Modification des informations de la formation sanitaire</p>
    </div>

    <?php if ($success): ?>
    <div class="form-notif form-notif--success visible" style="margin-bottom:20px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Modifications enregistrées avec succès !
        <a href="fosa.php" style="margin-left:auto;color:inherit;font-weight:700;text-decoration:none;border-bottom:1px solid currentColor">Voir la liste →</a>
    </div>
    <?php endif; ?>
    <?php if (isset($errors['_db'])): ?>
    <div class="form-notif form-notif--error visible" style="margin-bottom:20px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['_db']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="fosaForm" novalidate>
        <div class="form-layout">
            <!-- LEFT -->
            <div>
                <!-- Identité FOSA -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <div>
                            <div class="form-card__title">Identité de la FOSA</div>
                            <div class="form-card__sub">Nom et responsable</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid">
                            <div class="field field-full">
                                <label class="field__label required">Nom de la formation sanitaire</label>
                                <input type="text" name="nom_fosa"
                                    class="field__input <?= isset($errors['nom_fosa'])?'error':'' ?>"
                                    value="<?= htmlspecialchars($old['nom_fosa']??'') ?>" required>
                                <?php if(isset($errors['nom_fosa'])): ?><div class="field__error visible"><?= $errors['nom_fosa'] ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="fields-grid fields-grid--2" style="margin-top:16px">
                            <div class="field">
                                <label class="field__label">Prénom du responsable</label>
                                <input type="text" name="prenom_responsable" class="field__input"
                                    value="<?= htmlspecialchars($old['prenom_responsable']??'') ?>">
                            </div>
                            <div class="field">
                                <label class="field__label">Nom du responsable</label>
                                <input type="text" name="nom_responsable" class="field__input"
                                    value="<?= htmlspecialchars($old['nom_responsable']??'') ?>">
                            </div>
                            <div class="field field-full">
                                <label class="field__label">Téléphone</label>
                                <input type="tel" name="telephone" class="field__input"
                                    value="<?= htmlspecialchars($old['telephone']??'') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Localisation -->
                <div class="form-card" style="margin-top:20px">
                    <div class="form-card__header">
                        <div class="form-card__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                        <div>
                            <div class="form-card__title">Localisation</div>
                            <div class="form-card__sub">Département, district sanitaire et adresse</div>
                        </div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid fields-grid--2">
                            <div class="field">
                                <label class="field__label required">Département</label>
                                <div class="field__select-wrap">
                                    <select name="departement_id" id="selectDept"
                                        class="field__select <?= isset($errors['departement_id'])?'error':'' ?>" required>
                                        <option value="">— Sélectionner —</option>
                                        <?php foreach($depts as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= sel($old['departement_id']??'',$d['id']) ?>>
                                                <?= htmlspecialchars($d['libelle']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if(isset($errors['departement_id'])): ?><div class="field__error visible"><?= $errors['departement_id'] ?></div><?php endif; ?>
                            </div>

                            <div class="field field-ds" id="fieldDS">
                                <label class="field__label required">District sanitaire</label>
                                <div class="field__select-wrap">
                                    <select name="district_sanitaire_id" id="selectDS"
                                        class="field__select <?= isset($errors['district_sanitaire_id'])?'error':'' ?>">
                                        <option value="">— Sélectionner —</option>
                                    </select>
                                </div>
                                <?php if(isset($errors['district_sanitaire_id'])): ?><div class="field__error visible"><?= $errors['district_sanitaire_id'] ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="fields-grid" style="margin-top:16px">
                            <div class="field field-full">
                                <label class="field__label">Adresse / Coordonnées GPS</label>
                                <input type="text" name="adresse" class="field__input"
                                    value="<?= htmlspecialchars($old['adresse']??'') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /left -->

            <!-- RIGHT SIDEBAR -->
            <div class="form-sidebar">
                <div class="form-card" style="padding:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <span style="font-size:.78rem;font-weight:700;color:var(--gray-700)">Complétion</span>
                        <span style="font-size:.78rem;font-weight:700;color:var(--green)" id="progressPct">0%</span>
                    </div>
                    <div class="form-progress"><div class="form-progress__bar" id="progressBar"></div></div>
                </div>
                <div class="summary-card">
                    <div class="summary-card__header">
                        <div class="summary-card__avatar" id="summaryAvatar">—</div>
                        <div class="summary-card__name" id="summaryName">—</div>
                        <div class="summary-card__sub"  id="summaryOwner">—</div>
                    </div>
                    <div class="summary-card__body">
                        <div class="summary-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span id="summaryAdresse" class="empty">Adresse</span>
                        </div>
                        <div class="summary-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                            <span id="summaryDept" class="empty">Département</span>
                        </div>
                        <div class="summary-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.45 2 2 0 0 1 3.59 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.85a16 16 0 0 0 6.29 6.29l.87-.88a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span id="summaryTel" class="empty">Téléphone</span>
                        </div>
                    </div>
                </div>
                <div class="action-card">
                    <div class="form-notif form-notif--success" id="formSuccess"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Modifications enregistrées !</div>
                    <div class="form-notif form-notif--error"   id="formError"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Veuillez corriger les erreurs</div>
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                        Enregistrer les modifications
                    </button>
                    <a href="fosa.php" class="btn-reset" style="text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Annuler et revenir
                    </a>
                    <p class="required-note">* Champs obligatoires</p>
                </div>
            </div>
        </div>
    </form>
</div>
</div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_DS = <?= $allDSJson ?>;

function applyDeptChange(deptId, selectedDsId) {
    const id    = parseInt(deptId) || 0;
    const field = document.getElementById('fieldDS');
    const selDS = document.getElementById('selectDS');

    field.classList.remove('visible');
    selDS.innerHTML = '<option value="">— Sélectionner —</option>';

    if (!id) return;

    ALL_DS.filter(d => d.departement_id == id).forEach(d => {
        const o = document.createElement('option');
        o.value = d.id; o.textContent = d.nom_ds;
        if (selectedDsId && String(d.id) === String(selectedDsId)) o.selected = true;
        selDS.appendChild(o);
    });
    field.classList.add('visible');
}

document.getElementById('selectDept').addEventListener('change', function() {
    applyDeptChange(this.value);
    updatePreview();
});

function initials(str) {
    return (str||'').trim().split(/\s+/).filter(w=>w.length>1).slice(0,2).map(w=>w[0]).join('').toUpperCase()||'—';
}

function updatePreview() {
    const nom    = document.querySelector('[name=nom_fosa]')?.value || '';
    const prenom = document.querySelector('[name=prenom_responsable]')?.value || '';
    const nomR   = document.querySelector('[name=nom_responsable]')?.value || '';
    const tel    = document.querySelector('[name=telephone]')?.value || '';
    const adr    = document.querySelector('[name=adresse]')?.value || '';
    const dEl    = document.getElementById('selectDept');
    const dTxt   = dEl.options[dEl.selectedIndex]?.text || '';

    document.getElementById('summaryAvatar').textContent = nom ? initials(nom) : '—';
    document.getElementById('summaryName').textContent   = nom || '—';
    document.getElementById('summaryOwner').textContent  = `${prenom} ${nomR}`.trim() || '—';

    const s = (id, v) => { const el=document.getElementById(id); el.textContent=v||''; el.className=v?'':'empty'; };
    s('summaryAdresse', adr);
    s('summaryDept',    dTxt==='— Sélectionner —'?'':dTxt);
    s('summaryTel',     tel);
}

function updateProgress() {
    let f = document.querySelector('[name=nom_fosa]')?.value.trim() ? 1 : 0;
    if (document.getElementById('selectDept')?.value)  f++;
    if (document.getElementById('selectDS')?.value)    f++;
    const pct = Math.round((f / 3) * 100);
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPct').textContent = pct + '%';
}

document.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('input',  () => { updatePreview(); updateProgress(); });
    el.addEventListener('change', () => { updatePreview(); updateProgress(); });
});

document.getElementById('fosaForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite;width:17px;height:17px"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement...`;
});

<?php if($success): ?>document.getElementById('formSuccess').classList.add('visible');<?php elseif(!empty($errors)): ?>document.getElementById('formError').classList.add('visible');<?php endif; ?>

const initDept = <?= (int)($old['departement_id']??0) ?>;
const initDS   = <?= (int)($old['district_sanitaire_id']??0) ?>;
applyDeptChange(initDept, initDS);
updatePreview();
updateProgress();
</script>
</body>
</html>