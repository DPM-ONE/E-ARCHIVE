<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

/*
 * ── Schéma vérifié sur les exports SQL ──────────────────────────────────────
 * agences_dpm      : id, nom_agence           (deleted_at présent)
 * pharmacies_dpm   : id, nom_pharmacie, is_actif  (pas is_active)
 * depots_dpm       : id, depot_pharmaceutique, is_active
 * ────────────────────────────────────────────────────────────────────────────
 */

$professionnels = [];
$agences        = [];
$pharmacies     = [];
$depots         = [];
$dbError        = null;

function detecterCategorie(string $f): string {
    // Normalisation robuste : supprime les accents via regex Unicode
    // N'utilise PAS iconv() qui peut produire '?' sur certains serveurs Linux
    $fn = mb_strtolower($f, 'UTF-8');
    $fn = normalizer_normalize($fn, Normalizer::FORM_D) ?: $fn;
    $fn = preg_replace('/\p{M}/u', '', $fn); // supprime les combining marks (accents)

    // 1. Délégué  → après normalisation : 'delegue'
    if (str_contains($fn, 'delegu')) return 'delegue';

    // 2. Dépôtaire AVANT pharmacien (car "Pharmacien Dépositaire" contient les deux)
    //    Formes : 'pharmacien depositaire', 'pharmacienne depositaire',
    //             'responsable depot pharmaceutique'
    if (str_contains($fn, 'depositaire')
     || preg_match('/responsable.{0,6}dep/u', $fn)
     || preg_match('/depot.{0,8}pharm/u', $fn))   return 'depositaire';

    // 3. Pharmacien pur (titulaire, adjoint…)
    if (str_contains($fn, 'pharmacien')) return 'pharmacien';

    return 'autre';
}

try {
    $pdo = getPDO();

    $professionnels = $pdo->query("
        SELECT
            p.*,
            a.nom_agence                  AS agence_nom,
            ph.nom_pharmacie              AS pharmacie_nom,
            dep.depot_pharmaceutique      AS depot_nom
        FROM professionnels_dpm p
        LEFT JOIN agences_dpm      a   ON a.id   = p.agence_id
        LEFT JOIN pharmacies_dpm   ph  ON ph.id  = p.pharmacie_id
        LEFT JOIN depots_dpm       dep ON dep.id  = p.depot_id
        WHERE p.deleted_at IS NULL
        ORDER BY p.nom ASC, p.prenom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $agences = $pdo->query(
        "SELECT id, nom_agence AS nom FROM agences_dpm
         WHERE deleted_at IS NULL ORDER BY nom_agence"
    )->fetchAll(PDO::FETCH_ASSOC);

    $pharmacies = $pdo->query(
        "SELECT id, nom_pharmacie AS nom FROM pharmacies_dpm
         WHERE is_actif = 1 ORDER BY nom_pharmacie"
    )->fetchAll(PDO::FETCH_ASSOC);

    $depots = $pdo->query(
        "SELECT id, depot_pharmaceutique AS nom FROM depots_dpm
         WHERE is_active = 1 ORDER BY depot_pharmaceutique"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dbError = $e->getMessage();
    try {
        $pdo    = getPDO();
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sel    = 'p.*';
        $join   = '';
        if (in_array('agences_dpm', $tables))    { $sel .= ', a.nom_agence AS agence_nom'; $join .= ' LEFT JOIN agences_dpm a ON a.id=p.agence_id'; }
        else { $sel .= ', NULL AS agence_nom'; }
        if (in_array('pharmacies_dpm', $tables)) { $sel .= ', ph.nom_pharmacie AS pharmacie_nom'; $join .= ' LEFT JOIN pharmacies_dpm ph ON ph.id=p.pharmacie_id'; }
        else { $sel .= ', NULL AS pharmacie_nom'; }
        if (in_array('depots_dpm', $tables))     { $sel .= ', dep.depot_pharmaceutique AS depot_nom'; $join .= ' LEFT JOIN depots_dpm dep ON dep.id=p.depot_id'; }
        else { $sel .= ', NULL AS depot_nom'; }
        $professionnels = $pdo->query("SELECT $sel FROM professionnels_dpm p $join WHERE p.deleted_at IS NULL ORDER BY p.nom, p.prenom")->fetchAll(PDO::FETCH_ASSOC);
        if (in_array('agences_dpm',    $tables)) $agences    = $pdo->query("SELECT id, nom_agence AS nom FROM agences_dpm WHERE deleted_at IS NULL ORDER BY nom_agence")->fetchAll(PDO::FETCH_ASSOC);
        if (in_array('pharmacies_dpm', $tables)) $pharmacies = $pdo->query("SELECT id, nom_pharmacie AS nom FROM pharmacies_dpm WHERE is_actif=1 ORDER BY nom_pharmacie")->fetchAll(PDO::FETCH_ASSOC);
        if (in_array('depots_dpm',     $tables)) $depots     = $pdo->query("SELECT id, depot_pharmaceutique AS nom FROM depots_dpm WHERE is_active=1 ORDER BY depot_pharmaceutique")->fetchAll(PDO::FETCH_ASSOC);
        $dbError = null;
    } catch (Exception $e2) {}
}

$stats = ['total'=>0,'actifs'=>0,'delegues'=>0,'pharmaciens'=>0,'depositaires'=>0,'hommes'=>0,'femmes'=>0,'expires'=>0,'bientot'=>0];
$today     = new DateTime();
$threshold = (clone $today)->modify('+90 days');

foreach ($professionnels as &$p) {
    $p['categorie']    = detecterCategorie($p['fonction'] ?? '');
    $p['is_active']    = (bool)($p['is_active'] ?? false);
    $p['agence_id']    = $p['agence_id']    ? (int)$p['agence_id']    : null;
    $p['pharmacie_id'] = $p['pharmacie_id'] ? (int)$p['pharmacie_id'] : null;
    $p['depot_id']     = $p['depot_id']     ? (int)$p['depot_id']     : null;

    $stats['total']++;
    if ($p['is_active'])           $stats['actifs']++;
    if ($p['sexe'] === 'Masculin') $stats['hommes']++; else $stats['femmes']++;
    match ($p['categorie']) {
        'delegue'     => $stats['delegues']++,
        'pharmacien'  => $stats['pharmaciens']++,
        'depositaire' => $stats['depositaires']++,
        default       => null,
    };
    if (!empty($p['date_validite']) && $p['date_validite'] !== '0000-00-00') {
        $dv = new DateTime($p['date_validite']);
        if ($dv < $today) $stats['expires']++;
        elseif ($dv < $threshold) $stats['bientot']++;
    }
}
unset($p);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professionnels — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/professionnels.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()">
        <span></span><span></span><span></span>
    </button>
    <h1 class="topbar__title">DPM Archive</h1>
    <div class="topbar__sep"></div>
    <span class="topbar__date" id="liveDateDisplay"><?= date('d/m/Y') ?></span>
</header>

<div class="page">

<?php if ($dbError): ?>
<div class="alert-db">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong>Erreur base de données :</strong> <?= htmlspecialchars($dbError) ?></div>
</div>
<?php endif; ?>

<!-- En-tête -->
<div class="page-head">
    <div>
        <div class="page-head__eyebrow">Répertoire</div>
        <h2 class="page-head__title">Professionnels pharmaceutiques</h2>
        <p class="page-head__sub">
            <?= $stats['total'] ?> professionnel<?= $stats['total'] > 1 ? 's' : '' ?> enregistré<?= $stats['total'] > 1 ? 's' : '' ?> &middot; <?= date('d/m/Y') ?>
        </p>
    </div>
    <button class="btn-add" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau professionnel
    </button>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['actifs'] ?></div><div class="stat-lbl">Actifs</div></div>
    </div>
    <div class="stat-card stat-card--link" onclick="clickStatCat('delegue')">
        <div class="stat-icon stat-icon--blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['delegues'] ?></div><div class="stat-lbl">Délégués médicaux</div></div>
    </div>
    <div class="stat-card stat-card--link" onclick="clickStatCat('pharmacien')">
        <div class="stat-icon stat-icon--violet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['pharmaciens'] ?></div><div class="stat-lbl">Pharmaciens</div></div>
    </div>
    <div class="stat-card stat-card--link" onclick="clickStatCat('depositaire')">
        <div class="stat-icon stat-icon--amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['depositaires'] ?></div><div class="stat-lbl">Dépôtaires</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--teal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="10" cy="8" r="4"/><line x1="10" y1="12" x2="10" y2="22"/><line x1="7" y1="18" x2="13" y2="18"/><line x1="17" y1="3" x2="21" y2="7"/><line x1="21" y1="3" x2="17" y2="3"/><line x1="21" y1="3" x2="21" y2="7"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['hommes'] ?></div><div class="stat-lbl">Hommes</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--rose">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="8" r="4"/><line x1="12" y1="12" x2="12" y2="18"/><line x1="9" y1="21" x2="15" y2="21"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['femmes'] ?></div><div class="stat-lbl">Femmes</div></div>
    </div>
    <?php if ($stats['bientot'] > 0): ?>
    <div class="stat-card stat-card--warn">
        <div class="stat-icon stat-icon--amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['bientot'] ?></div><div class="stat-lbl">Expirent bientôt</div></div>
    </div>
    <?php endif; ?>
    <?php if ($stats['expires'] > 0): ?>
    <div class="stat-card stat-card--alert">
        <div class="stat-icon stat-icon--red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div><div class="stat-val"><?= $stats['expires'] ?></div><div class="stat-lbl">Expirés</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Onglets — Délégués ACTIF par défaut -->
<div class="cat-tabs">
    <button class="cat-tab" data-cat="" onclick="setCategory(this,'')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Tous <span class="cat-count" id="cnt_all"><?= $stats['total'] ?></span>
    </button>
    <button class="cat-tab cat-tab--active" data-cat="delegue" onclick="setCategory(this,'delegue')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        Délégués médicaux <span class="cat-count" id="cnt_delegue"><?= $stats['delegues'] ?></span>
    </button>
    <button class="cat-tab" data-cat="pharmacien" onclick="setCategory(this,'pharmacien')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Pharmaciens <span class="cat-count" id="cnt_pharmacien"><?= $stats['pharmaciens'] ?></span>
    </button>
    <button class="cat-tab" data-cat="depositaire" onclick="setCategory(this,'depositaire')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        Dépôtaires <span class="cat-count" id="cnt_depositaire"><?= $stats['depositaires'] ?></span>
    </button>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <div class="search-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Nom, prénom, fonction, CNI, rattachement…">
    </div>
    <select class="filter-sel" id="filterSexe">
        <option value="">Tout genre</option>
        <option value="Masculin">♂ Masculin</option>
        <option value="Féminin">♀ Féminin</option>
    </select>
    <select class="filter-sel" id="filterStatut">
        <option value="">Tous statuts</option>
        <option value="actif">Actifs</option>
        <option value="inactif">Inactifs</option>
        <option value="expire">Expirés</option>
        <option value="bientot">Expire bientôt</option>
    </select>
    <div class="filter-sep"></div>
    <div class="view-toggle">
        <button class="view-btn view-btn--active" id="btnCards" onclick="setView('cards')" title="Vue cartes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Cards
        </button>
        <button class="view-btn" id="btnTable" onclick="setView('table')" title="Vue tableau">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            Tableau
        </button>
    </div>
    <button class="btn-reset" onclick="resetFilters()" title="Réinitialiser">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
        Effacer
    </button>
</div>

<div class="results-bar">
    <span class="results-count" id="resultsCount"><strong>—</strong> résultats</span>
</div>

<!-- Vue Cards -->
<div id="viewCards">
    <div class="cards-grid" id="cardsGrid"></div>
    <div class="empty-state" id="emptyCards" style="display:none">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <h3>Aucun professionnel trouvé</h3>
        <p>Modifiez vos filtres ou ajoutez un nouveau professionnel</p>
    </div>
    <div class="pagination" id="paginationCards"></div>
</div>

<!-- Vue Tableau -->
<div id="viewTable" style="display:none">
    <div class="table-wrap">
        <div class="table-scroll">
            <table>
                <thead><tr>
                    <th onclick="sortBy('nom')" id="th_nom">Professionnel</th>
                    <th onclick="sortBy('fonction')" id="th_fonc">Fonction</th>
                    <th class="no-sort">Rattachement</th>
                    <th class="no-sort">Contact</th>
                    <th onclick="sortBy('date_validite')" id="th_val">Validité</th>
                    <th onclick="sortBy('is_active')" id="th_stat">Statut</th>
                    <th class="no-sort" style="width:80px;text-align:right">Actions</th>
                </tr></thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        <div class="empty-state" id="emptyTable" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <h3>Aucun professionnel trouvé</h3>
        </div>
        <div class="pagination" id="paginationTable"></div>
    </div>
</div>

</div><!-- /page -->
</div><!-- /sb-main -->

<!-- DRAWER -->
<div class="drawer-backdrop" id="drawerBackdrop">
    <div class="drawer" id="drawer" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <div class="drawer-avatar" id="dAvatar"></div>
            <div class="drawer-title">
                <h3 id="dName">—</h3>
                <p id="dFonction">—</p>
            </div>
            <button class="drawer-close" onclick="closeDrawer()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="drawer-body" id="drawerBody"></div>
        <div class="drawer-footer">
            <button class="df-btn df-btn--danger" id="btnDrawerDelete">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                Supprimer
            </button>
            <button class="df-btn df-btn--primary" id="btnDrawerEdit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Modifier
            </button>
        </div>
    </div>
</div>

<!-- MODAL FORMULAIRE -->
<div class="modal-overlay" id="formOverlay">
    <div class="modal-form" onclick="event.stopPropagation()">
        <div class="modal-form__header">
            <div class="mf-avatar" id="mfAvatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            <div>
                <h3 id="mfTitle">Nouveau professionnel</h3>
                <p id="mfSub">Remplir les informations</p>
            </div>
            <button class="modal-close" onclick="closeFormModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-form__body">
            <input type="hidden" id="fId">
            <!-- Identité -->
            <div class="mf-section">
                <div class="mf-section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    Identité
                </div>
                <div class="mf-row mf-row--2">
                    <div class="mf-field">
                        <label class="mf-label required">Prénom</label>
                        <input type="text" class="mf-input" id="fPrenom" placeholder="Ex : Jean-Paul">
                        <div class="mf-error" id="ePrenom"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Nom</label>
                        <input type="text" class="mf-input" id="fNom" placeholder="Ex : Mbemba">
                        <div class="mf-error" id="eNom"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Sexe</label>
                        <div class="mf-select-wrap">
                            <select class="mf-select" id="fSexe">
                                <option value="">— Sélectionner —</option>
                                <option value="Masculin">♂ Masculin</option>
                                <option value="Féminin">♀ Féminin</option>
                            </select>
                        </div>
                        <div class="mf-error" id="eSexe"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Date de naissance</label>
                        <input type="date" class="mf-input" id="fDateNaiss">
                        <div class="mf-error" id="eDateNaiss"></div>
                    </div>
                    <div class="mf-field mf-field--full">
                        <label class="mf-label">Lieu de naissance</label>
                        <input type="text" class="mf-input" id="fLieuNaiss" placeholder="Ex : Brazzaville">
                    </div>
                </div>
            </div>
            <!-- Profession -->
            <div class="mf-section">
                <div class="mf-section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                    Profession &amp; Rattachement
                </div>
                <div class="mf-row mf-row--2">
                    <div class="mf-field mf-field--full">
                        <label class="mf-label required">Fonction</label>
                        <input type="text" class="mf-input" id="fFonction" placeholder="Ex : Délégué Médical, Pharmacien Titulaire, Pharmacien Dépôtaire…">
                        <div class="mf-hint">La catégorie sera déduite automatiquement de la fonction.</div>
                        <div class="mf-error" id="eFonction"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mf-label-ico"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                            Agence DPM
                        </label>
                        <div class="mf-select-wrap">
                            <select class="mf-select" id="fAgence">
                                <option value="">— Aucune —</option>
                                <?php foreach ($agences as $ag): ?>
                                <option value="<?= $ag['id'] ?>"><?= htmlspecialchars($ag['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mf-label-ico"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                            Pharmacie
                        </label>
                        <div class="mf-select-wrap">
                            <select class="mf-select" id="fPharmacie">
                                <option value="">— Aucune —</option>
                                <?php foreach ($pharmacies as $ph): ?>
                                <option value="<?= $ph['id'] ?>"><?= htmlspecialchars($ph['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="mf-label-ico"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            Dépôt pharmaceutique
                        </label>
                        <div class="mf-select-wrap">
                            <select class="mf-select" id="fDepot">
                                <option value="">— Aucun —</option>
                                <?php foreach ($depots as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Contact & Documents -->
            <div class="mf-section">
                <div class="mf-section-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.68 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.59 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6.29 6.29l1.48-1.48a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Contact &amp; Documents
                </div>
                <div class="mf-row mf-row--2">
                    <div class="mf-field">
                        <label class="mf-label">Téléphone</label>
                        <input type="tel" class="mf-input" id="fTel" placeholder="+242 06 000 0000">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Email</label>
                        <input type="email" class="mf-input" id="fEmail" placeholder="nom@domaine.cg">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Numéro CNI</label>
                        <input type="text" class="mf-input" id="fCni" placeholder="CNI-2024-XXX">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Date de délivrance</label>
                        <input type="date" class="mf-input" id="fDateDeliv">
                        <div class="mf-error" id="eDateDeliv"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Lieu de délivrance</label>
                        <input type="text" class="mf-input" id="fLieuDeliv" placeholder="Ex : Brazzaville">
                        <div class="mf-error" id="eLieuDeliv"></div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label required">Date de validité</label>
                        <input type="date" class="mf-input" id="fDateVal">
                        <div class="mf-error" id="eDateVal"></div>
                    </div>
                    <div class="mf-field mf-field--full">
                        <label class="mf-toggle-wrap">
                            <input type="checkbox" id="fActif" checked>
                            <span class="mf-toggle"></span>
                            <span class="mf-toggle-label">Professionnel actif</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-form__footer">
            <button class="mf-btn mf-btn--cancel" onclick="closeFormModal()">Annuler</button>
            <button class="mf-btn mf-btn--save" id="btnSave" onclick="savePro()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                Enregistrer
            </button>
        </div>
    </div>
</div>

<!-- MODAL SUPPRESSION -->
<div class="modal-overlay" id="delOverlay">
    <div class="modal-del" onclick="event.stopPropagation()">
        <div class="del-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <p class="del-title">Supprimer ce professionnel ?</p>
        <p class="del-msg" id="delMsg">—</p>
        <p class="del-warn">Cette action est irréversible.</p>
        <div class="del-actions">
            <button class="btn-cancel" onclick="closeDelModal()">Annuler</button>
            <button class="btn-del-confirm" id="btnConfirmDel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                Supprimer
            </button>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_DATA   = <?= json_encode(array_values($professionnels), JSON_UNESCAPED_UNICODE) ?>;
const AGENCES    = <?= json_encode($agences,    JSON_UNESCAPED_UNICODE) ?>;
const PHARMACIES = <?= json_encode($pharmacies, JSON_UNESCAPED_UNICODE) ?>;
const DEPOTS     = <?= json_encode($depots,     JSON_UNESCAPED_UNICODE) ?>;
const API_BASE   = '../api/professionnels.php';

console.log('[DPM Pro] Chargés :', ALL_DATA.length, 'enregistrements');
if (ALL_DATA.length) {
    const cats = { delegue:0, pharmacien:0, depositaire:0, autre:0 };
    ALL_DATA.forEach(p => cats[p.categorie] = (cats[p.categorie]||0)+1);
    console.table(cats);
    const r = ALL_DATA.filter(p => p.agence_nom || p.pharmacie_nom || p.depot_nom).length;
    console.log('[DPM Pro] Avec rattachement résolu :', r, '/', ALL_DATA.length);
}

function clickStatCat(cat) {
    const btn = document.querySelector(`.cat-tab[data-cat="${cat}"]`);
    if (btn) btn.click();
}
</script>
<script src="../assets/js/professionnels.js"></script>
</body>
</html>