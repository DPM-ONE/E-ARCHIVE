<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['super_admin','admin'])) {
    header('Location: pharmacies.php?toast=permission_denied'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: pharmacies.php'); exit; }

$errors  = [];
$success = false;

try {
    $pdo     = getPDO();
    $depts   = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $groupes = $pdo->query("SELECT id, groupe  FROM groupe_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists= $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT * FROM pharmacies_dpm WHERE id = ?");
    $stmt->execute([$id]);
    $pharma = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pharma = null; $depts = $groupes = $allArrs = $allDists = []; $deptsWithArr = [];
}

if (!$pharma) { header('Location: pharmacies.php?toast=not_found'); exit; }

$old = $pharma; // valeurs par défaut depuis BDD

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array_merge($pharma, $_POST);
    $old['id'] = $id;

    $required = ['nom_pharmacie','prenom','nom','telephone_1','adresse','quartier','departement','horaire'];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) $errors[$f] = 'Ce champ est obligatoire';
    }
    if (!empty($errors)) goto render;

    $deptId  = (int)$_POST['departement'];
    $hasArr  = in_array($deptId, $deptsWithArr);
    $isBZV   = ($deptId === 1);
    $horaire = $_POST['horaire'];
    if (!in_array($horaire, ['Jour','Nuit','24h/24'])) { $errors['horaire'] = 'Horaire invalide'; goto render; }

    try {
        $stmt = $pdo->prepare("
            UPDATE pharmacies_dpm SET
                nom_pharmacie     = :nom_pharmacie,
                prenom            = :prenom,
                nom               = :nom,
                email             = :email,
                telephone_1       = :telephone_1,
                telephone_2       = :telephone_2,
                adresse           = :adresse,
                quartier          = :quartier,
                departement       = :departement,
                arrondissement_id = :arr_id,
                district_id       = :dist_id,
                zone_bzv          = :zone_bzv,
                horaire           = :horaire,
                box_dossier       = :box_dossier,
                zone_archive      = :zone_archive,
                groupe_id         = :groupe_id,
                is_groupe         = :is_groupe,
                is_actif          = :is_actif,
                updated_at        = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':nom_pharmacie' => trim($_POST['nom_pharmacie']),
            ':prenom'        => trim($_POST['prenom']),
            ':nom'           => trim($_POST['nom']),
            ':email'         => trim($_POST['email'] ?? '') ?: null,
            ':telephone_1'   => trim($_POST['telephone_1']),
            ':telephone_2'   => trim($_POST['telephone_2'] ?? '') ?: null,
            ':adresse'       => trim($_POST['adresse']),
            ':quartier'      => trim($_POST['quartier']),
            ':departement'   => $deptId,
            ':arr_id'        => $hasArr  ? ($_POST['arrondissement_id'] ?: null) : null,
            ':dist_id'       => !$hasArr ? ($_POST['district_id']       ?: null) : null,
            ':zone_bzv'      => $isBZV   ? ($_POST['zone_bzv']          ?: null) : null,
            ':horaire'       => $horaire,
            ':box_dossier'   => trim($_POST['box_dossier'] ?? '') ?: null,
            ':zone_archive'  => $_POST['zone_archive'] ?: null,
            ':groupe_id'     => $_POST['groupe_id'] ?: null,
            ':is_groupe'     => isset($_POST['is_groupe']) ? 1 : 0,
            ':is_actif'      => isset($_POST['is_actif'])  ? 1 : 0,
            ':id'            => $id,
        ]);
        $success = true;
        // Rafraîchir depuis BDD
        $st2 = $pdo->prepare("SELECT * FROM pharmacies_dpm WHERE id = ?");
        $st2->execute([$id]);
        $pharma = $st2->fetch(PDO::FETCH_ASSOC);
        $old = $pharma;
    } catch (PDOException $e) {
        $errors['_db'] = $e->getCode() === '23000'
            ? 'Référence invalide (département, arrondissement ou district)'
            : 'Erreur base de données : ' . $e->getMessage();
    }
}

render:
function sel($val, $compare): string { return (string)$val === (string)$compare ? 'selected' : ''; }
function chk($val): string { return $val ? 'checked' : ''; }

$allArrsJson     = json_encode($allArrs);
$allDistsJson    = json_encode($allDists);
$deptsWithArrJson= json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Modifier — <?= htmlspecialchars($pharma['nom_pharmacie']) ?> — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/pharmacie-form.css">
    <style>
    @keyframes spin { to { transform:rotate(360deg); } }
    /* ── Horaire picker ── */
    .hp-group { display:flex; gap:8px; flex-wrap:wrap; margin-top:6px; }
    .hp {
        display:inline-flex; align-items:center; gap:7px;
        padding:9px 20px; border-radius:50px; border:2px solid #E5E7EB;
        background:#fff; color:#374151;
        font-family:'Outfit',sans-serif; font-size:.8125rem; font-weight:600;
        cursor:pointer; transition:all .15s ease; line-height:1; user-select:none;
    }
    .hp svg { width:13px; height:13px; flex-shrink:0; }
    .hp:hover { background:#F3F4F6; border-color:#9CA3AF; }
    .hp--jour.hp--on { background:#FFF9E6; color:#92600A; border-color:#F59E0B; box-shadow:0 0 0 3px rgba(245,158,11,.15); }
    .hp--nuit.hp--on { background:#EEF2FF; color:#3730A3; border-color:#6366F1; box-shadow:0 0 0 3px rgba(99,102,241,.15); }
    .hp--h24.hp--on  { background:#F0FDF4; color:#00A859; border-color:#00A859; box-shadow:0 0 0 3px rgba(0,168,89,.15); }
    @keyframes toast-in  { from{opacity:0;transform:translateY(12px) scale(.95)} to{opacity:1;transform:translateY(0) scale(1)} }
    @keyframes toast-out { from{opacity:1;transform:translateY(0)} to{opacity:0;transform:translateY(8px)} }
    .field-location{display:none}
    .field-location.visible{display:block}
    </style>
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">
<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()"><span></span><span></span><span></span></button>
    <a href="pharmacies.php" class="topbar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>Pharmacies
    </a>
    <h1 class="topbar__title">Modifier une pharmacie</h1>
    <span class="topbar__meta"><?= date('d/m/Y') ?></span>
</header>

<div class="form-page">
    <div class="form-page__header">
        <h2 class="form-page__title"><?= htmlspecialchars($pharma['nom_pharmacie']) ?></h2>
        <p class="form-page__sub">Modification des informations de la pharmacie</p>
    </div>

    <?php if ($success): ?>
    <div class="form-notif form-notif--success visible" style="margin-bottom:20px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Modifications enregistrées avec succès !
        <a href="pharmacies.php" style="margin-left:auto;color:inherit;font-weight:700;text-decoration:none;border-bottom:1px solid currentColor">Voir la liste →</a>
    </div>
    <?php endif; ?>
    <?php if (isset($errors['_db'])): ?>
    <div class="form-notif form-notif--error visible" style="margin-bottom:20px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['_db']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="pharmaForm" novalidate>
        <div class="form-layout">
            <!-- LEFT -->
            <div>
                <!-- Identité -->
                <div class="form-card">
                    <div class="form-card__header">
                        <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                        <div><div class="form-card__title">Identité de la pharmacie</div><div class="form-card__sub">Nom, propriétaire et contacts</div></div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid">
                            <div class="field field-full">
                                <label class="field__label required">Nom de la pharmacie</label>
                                <input type="text" name="nom_pharmacie" class="field__input <?= isset($errors['nom_pharmacie'])?'error':'' ?>" value="<?= htmlspecialchars($old['nom_pharmacie']??'') ?>" required>
                                <?php if(isset($errors['nom_pharmacie'])): ?><div class="field__error visible"><?= $errors['nom_pharmacie'] ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="fields-grid fields-grid--2" style="margin-top:16px">
                            <div class="field">
                                <label class="field__label required">Prénom du propriétaire</label>
                                <input type="text" name="prenom" class="field__input <?= isset($errors['prenom'])?'error':'' ?>" value="<?= htmlspecialchars($old['prenom']??'') ?>" required>
                                <?php if(isset($errors['prenom'])): ?><div class="field__error visible"><?= $errors['prenom'] ?></div><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label required">Nom du propriétaire</label>
                                <input type="text" name="nom" class="field__input <?= isset($errors['nom'])?'error':'' ?>" value="<?= htmlspecialchars($old['nom']??'') ?>" required>
                                <?php if(isset($errors['nom'])): ?><div class="field__error visible"><?= $errors['nom'] ?></div><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label required">Téléphone 1</label>
                                <input type="tel" name="telephone_1" class="field__input <?= isset($errors['telephone_1'])?'error':'' ?>" value="<?= htmlspecialchars($old['telephone_1']??'') ?>" required>
                                <?php if(isset($errors['telephone_1'])): ?><div class="field__error visible"><?= $errors['telephone_1'] ?></div><?php endif; ?>
                            </div>
                            <div class="field">
                                <label class="field__label">Téléphone 2</label>
                                <input type="tel" name="telephone_2" class="field__input" value="<?= htmlspecialchars($old['telephone_2']??'') ?>">
                            </div>
                            <div class="field field-full">
                                <label class="field__label">Email</label>
                                <input type="email" name="email" class="field__input" value="<?= htmlspecialchars($old['email']??'') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Localisation -->
                <div class="form-card" style="margin-top:20px">
                    <div class="form-card__header">
                        <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                        <div><div class="form-card__title">Localisation</div><div class="form-card__sub">Adresse et position géographique</div></div>
                    </div>
                    <div class="form-card__body">
                        <div class="fields-grid">
                            <div class="field field-full">
                                <label class="field__label required">Adresse</label>
                                <input type="text" name="adresse" class="field__input <?= isset($errors['adresse'])?'error':'' ?>" value="<?= htmlspecialchars($old['adresse']??'') ?>" required>
                                <?php if(isset($errors['adresse'])): ?><div class="field__error visible"><?= $errors['adresse'] ?></div><?php endif; ?>
                            </div>
                            <div class="field field-full">
                                <label class="field__label required">Quartier</label>
                                <input type="text" name="quartier" class="field__input <?= isset($errors['quartier'])?'error':'' ?>" value="<?= htmlspecialchars($old['quartier']??'') ?>" required>
                                <?php if(isset($errors['quartier'])): ?><div class="field__error visible"><?= $errors['quartier'] ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="fields-grid fields-grid--2" style="margin-top:16px">
                            <div class="field">
                                <label class="field__label required">Département</label>
                                <div class="field__select-wrap">
                                    <select name="departement" id="selectDept" class="field__select <?= isset($errors['departement'])?'error':'' ?>" required>
                                        <option value="">— Sélectionner —</option>
                                        <?php foreach($depts as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= sel($old['departement']??'',$d['id']) ?>><?= htmlspecialchars($d['libelle']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if(isset($errors['departement'])): ?><div class="field__error visible"><?= $errors['departement'] ?></div><?php endif; ?>
                            </div>

                            <div class="field field-location" id="fieldArrondissement">
                                <label class="field__label">Arrondissement</label>
                                <div class="field__select-wrap">
                                    <select name="arrondissement_id" id="selectArr" class="field__select">
                                        <option value="">— Sélectionner —</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field field-location" id="fieldDistrict">
                                <label class="field__label">District</label>
                                <div class="field__select-wrap">
                                    <select name="district_id" id="selectDist" class="field__select">
                                        <option value="">— Sélectionner —</option>
                                    </select>
                                </div>
                            </div>

                            <div class="field field-location" id="fieldZoneBzv">
                                <label class="field__label">Zone (BZV)</label>
                                <div class="field__select-wrap">
                                    <select name="zone_bzv" id="selectZone" class="field__select">
                                        <option value="">— Sélectionner —</option>
                                        <?php foreach(['Zone Nord','Zone Centre','Zone Sud'] as $z): ?>
                                            <option value="<?= $z ?>" <?= sel($old['zone_bzv']??'',$z) ?>><?= $z ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Garde & Archive -->
                <div class="form-card" style="margin-top:20px">
                    <div class="form-card__header">
                        <div class="form-card__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <div><div class="form-card__title">Garde &amp; Archive</div><div class="form-card__sub">Horaire, groupe et dossier</div></div>
                    </div>
                    <div class="form-card__body">
                        <div class="field field-full" style="margin-bottom:20px">
                            <label class="field__label required">Horaire de garde</label>
                            <div class="hp-group">
                                <button type="button" class="hp hp--jour <?= ($old['horaire']??'')==='Jour' ? 'hp--on' : '' ?>" data-val="Jour">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                                    Jour
                                </button>
                                <button type="button" class="hp hp--nuit <?= ($old['horaire']??'')==='Nuit' ? 'hp--on' : '' ?>" data-val="Nuit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                    Nuit
                                </button>
                                <button type="button" class="hp hp--h24 <?= ($old['horaire']??'')==='24h/24' ? 'hp--on' : '' ?>" data-val="24h/24">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    24h/24
                                </button>
                                <input type="hidden" name="horaire" id="inputHoraire" value="<?= htmlspecialchars($old['horaire']??'') ?>">
                            </div>
                            <?php if(isset($errors['horaire'])): ?><div class="field__error visible"><?= $errors['horaire'] ?></div><?php endif; ?>
                        </div>
                        <div class="fields-grid fields-grid--3">
                            <div class="field">
                                <label class="field__label">Groupe de garde</label>
                                <div class="field__select-wrap">
                                    <select name="groupe_id" class="field__select">
                                        <option value="">— Aucun —</option>
                                        <?php foreach($groupes as $g): ?>
                                            <option value="<?= $g['id'] ?>" <?= sel($old['groupe_id']??'',$g['id']) ?>><?= htmlspecialchars($g['groupe']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="field">
                                <label class="field__label">Box dossier</label>
                                <input type="text" name="box_dossier" class="field__input" value="<?= htmlspecialchars($old['box_dossier']??'') ?>">
                            </div>
                            <div class="field">
                                <label class="field__label">Zone archive</label>
                                <div class="field__select-wrap">
                                    <select name="zone_archive" class="field__select">
                                        <option value="">— Aucune —</option>
                                        <?php foreach(['Salle I','Salle II','Salle III','Salle IV','Salle V'] as $s): ?>
                                            <option value="<?= $s ?>" <?= sel($old['zone_archive']??'',$s) ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="toggle-group" style="margin-top:16px">
                            <label class="toggle-item"><input type="checkbox" name="is_groupe" <?= chk($old['is_groupe']) ?>><div class="toggle-switch"></div><span class="toggle-label">Chef de groupe</span></label>
                            <label class="toggle-item"><input type="checkbox" name="is_actif"  <?= chk($old['is_actif'])  ?>><div class="toggle-switch"></div><span class="toggle-label">Pharmacie active</span></label>
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
                        <div class="summary-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span id="summaryAdresse" class="empty">Adresse</span></div>
                        <div class="summary-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg><span id="summaryDept" class="empty">Département</span></div>
                        <div class="summary-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.45 2 2 0 0 1 3.59 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.85a16 16 0 0 0 6.29 6.29l.87-.88a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg><span id="summaryTel" class="empty">Téléphone</span></div>
                        <div class="summary-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span id="summaryHoraire" class="empty">Horaire</span></div>
                    </div>
                </div>
                <div class="action-card">
                    <div class="form-notif form-notif--success" id="formSuccess"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Modifications enregistrées !</div>
                    <div class="form-notif form-notif--error"   id="formError"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Veuillez corriger les erreurs</div>
                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                        Enregistrer les modifications
                    </button>
                    <a href="pharmacies.php" class="btn-reset" style="text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                        Annuler et revenir
                    </a>
                    <p class="required-note">* Champs obligatoires</p>
                </div>
            </div>
        </div>
    </form>
</div></div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_ARRS        = <?= $allArrsJson ?>;
const ALL_DISTS       = <?= $allDistsJson ?>;
const DEPTS_WITH_ARR  = <?= $deptsWithArrJson ?>;

function applyDeptChange(deptId, selectedArrId, selectedDistId) {
    const id = parseInt(deptId) || 0;
    const fieldArr  = document.getElementById('fieldArrondissement');
    const fieldDist = document.getElementById('fieldDistrict');
    const fieldZone = document.getElementById('fieldZoneBzv');
    const selArr    = document.getElementById('selectArr');
    const selDist   = document.getElementById('selectDist');

    fieldArr.classList.remove('visible');
    fieldDist.classList.remove('visible');
    fieldZone.classList.remove('visible');
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
        if (id === 1) fieldZone.classList.add('visible');
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

document.getElementById('selectDept').addEventListener('change', function() {
    applyDeptChange(this.value);
    updatePreview();
});

function initials(str) {
    return (str||'').trim().split(/\s+/).filter(w=>w.length>1).slice(0,2).map(w=>w[0]).join('').toUpperCase()||'—';
}
function updatePreview() {
    const nom     = document.querySelector('[name=nom_pharmacie]')?.value || '';
    const prenom  = document.querySelector('[name=prenom]')?.value || '';
    const nomProp = document.querySelector('[name=nom]')?.value || '';
    const tel     = document.querySelector('[name=telephone_1]')?.value || '';
    const adresse = document.querySelector('[name=adresse]')?.value || '';
    const deptEl  = document.getElementById('selectDept');
    const deptTxt = deptEl.options[deptEl.selectedIndex]?.text || '';
    const horaire = document.getElementById('inputHoraire')?.value || '';
    document.getElementById('summaryAvatar').textContent = nom ? initials(nom) : '—';
    document.getElementById('summaryName').textContent   = nom || '—';
    document.getElementById('summaryOwner').textContent  = `${prenom} ${nomProp}`.trim() || '—';
    const s = (id, v) => { const el=document.getElementById(id); el.textContent=v||id.replace('summary',''); el.className=v?'':'empty'; };
    s('summaryAdresse', adresse);
    s('summaryDept',    deptTxt==='— Sélectionner —'?'':deptTxt);
    s('summaryTel',     tel);
    s('summaryHoraire', horaire);
}

const requiredFields = ['nom_pharmacie','prenom','nom','telephone_1','adresse','quartier'];
function updateProgress() {
    let filled = requiredFields.filter(n => document.querySelector(`[name="${n}"]`)?.value.trim()).length;
    if (document.getElementById('selectDept')?.value) filled++;
    if (document.getElementById('inputHoraire')?.value) filled++;
    const pct = Math.round((filled / (requiredFields.length + 2)) * 100);
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressPct').textContent = pct + '%';
}

document.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('input',  () => { updatePreview(); updateProgress(); });
    el.addEventListener('change', () => { updatePreview(); updateProgress(); });
});

document.getElementById('pharmaForm').addEventListener('submit', function() {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite;width:17px;height:17px"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement...`;
});

<?php if($success): ?>document.getElementById('formSuccess').classList.add('visible');<?php elseif(!empty($errors)): ?>document.getElementById('formError').classList.add('visible');<?php endif; ?>

// Init avec valeurs de la BDD
const initDept = <?= (int)($old['departement']??0) ?>;
const initArr  = <?= (int)($old['arrondissement_id']??0) ?>;
const initDist = <?= (int)($old['district_id']??0) ?>;
applyDeptChange(initDept, initArr, initDist);
updatePreview();
updateProgress();

/* ── Horaire picker ── */
(function(){
    const btns  = document.querySelectorAll('.hp');
    const input = document.getElementById('inputHoraire');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            btns.forEach(b => b.classList.remove('hp--on'));
            btn.classList.add('hp--on');
            input.value = btn.dataset.val;
            updatePreview();
            updateProgress();
        });
    });
})();
</script>
</body></html>