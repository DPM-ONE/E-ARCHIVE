<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();
    $pharmacies = $pdo->query("
        SELECT p.*,
               d.libelle  AS dept_libelle,
               g.groupe   AS groupe_libelle,
               a.libelle  AS arr_libelle,
               dt.libelle AS dist_libelle
        FROM pharmacies_dpm p
        LEFT JOIN departements_dpm   d  ON d.id  = p.departement
        LEFT JOIN groupe_dpm         g  ON g.id  = p.groupe_id
        LEFT JOIN arrondissement_dpm a  ON a.id  = p.arrondissement_id
        LEFT JOIN district_dpm       dt ON dt.id = p.district_id
        ORDER BY p.nom_pharmacie ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $depts        = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $groupes      = $pdo->query("SELECT id, groupe  FROM groupe_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs      = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists     = $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);

    /* Groupes en garde cette semaine (lundi–dimanche) */
    $now     = new DateTime();
    $dow     = (int)$now->format('N'); // 1=lundi, 7=dimanche
    $monday  = (clone $now)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
    $sunday  = (clone $now)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');
    $gardeStmt = $pdo->prepare("SELECT groupe_id FROM garde_dpm WHERE jour BETWEEN ? AND ?");
    $gardeStmt->execute([$monday, $sunday]);
    $gardeGroupIds = array_map('intval', $gardeStmt->fetchAll(PDO::FETCH_COLUMN));

    /* Dates de garde du mois en cours par groupe */
    $moisDebut = date('Y-m-01');
    $moisFin   = date('Y-m-t');
    $datesMoisStmt = $pdo->prepare("
        SELECT groupe_id, jour 
        FROM garde_dpm 
        WHERE jour BETWEEN ? AND ? 
        ORDER BY groupe_id, jour
    ");
    $datesMoisStmt->execute([$moisDebut, $moisFin]);
    $datesMoisData = $datesMoisStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Grouper par groupe_id
    $datesMoisParGroupe = [];
    foreach ($datesMoisData as $row) {
        $gid = (int)$row['groupe_id'];
        if (!isset($datesMoisParGroupe[$gid])) {
            $datesMoisParGroupe[$gid] = [];
        }
        $datesMoisParGroupe[$gid][] = $row['jour'];
    }

} catch (Exception $e) {
    $pharmacies = $depts = $groupes = $allArrs = $allDists = [];
    $deptsWithArr  = [];
    $gardeGroupIds = [];
}

$total      = count($pharmacies);
$totalActif = count(array_filter($pharmacies, fn($p) => $p['is_actif']));
$totalJour  = count(array_filter($pharmacies, fn($p) => $p['horaire'] === 'Jour'));
$totalNuit  = count(array_filter($pharmacies, fn($p) => $p['horaire'] === 'Nuit'));
$total24h   = count(array_filter($pharmacies, fn($p) => $p['horaire'] === '24h/24'));

$pharmaciesJson   = json_encode(array_map(fn($p) => [
    'id'                => (int)$p['id'],
    'nom_pharmacie'     => $p['nom_pharmacie'],
    'prenom'            => $p['prenom'],
    'nom'               => $p['nom'],
    'email'             => $p['email'] ?? '',
    'telephone_1'       => $p['telephone_1'],
    'telephone_2'       => $p['telephone_2'] ?? '',
    'adresse'           => $p['adresse'],
    'quartier'          => $p['quartier'],
    'departement_id'    => (int)$p['departement'],
    'departement'       => $p['dept_libelle'] ?? '',
    'arrondissement_id' => $p['arrondissement_id'] ? (int)$p['arrondissement_id'] : null,
    'arrondissement'    => $p['arr_libelle'] ?? '',
    'district_id'       => $p['district_id'] ? (int)$p['district_id'] : null,
    'district'          => $p['dist_libelle'] ?? '',
    'zone_bzv'          => $p['zone_bzv'] ?? '',
    'horaire'           => $p['horaire'],
    'box_dossier'       => $p['box_dossier'] ?? '',
    'zone_archive'      => $p['zone_archive'] ?? '',
    'groupe_id'         => $p['groupe_id'] ? (int)$p['groupe_id'] : null,
    'groupe_libelle'    => $p['groupe_libelle'] ?? '',
    'is_groupe'         => (bool)$p['is_groupe'],
    'is_actif'          => (bool)$p['is_actif'],
    'created_at'        => $p['created_at'] ?? '',
], $pharmacies));

$deptsJson        = json_encode($depts);
$groupesJson      = json_encode($groupes);
$allArrsJson      = json_encode($allArrs);
$allDistsJson     = json_encode($allDists);
$deptsWithArrJson = json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacies — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/pharmacies.css">
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
    <span class="topbar__date"><?= date('d/m/Y') ?></span>
</header>

<div class="page">

    <div class="page-head">
        <div>
            <div class="page-head__eyebrow">Gestion</div>
            <h2 class="page-head__title">Pharmacies</h2>
            <p class="page-head__sub"><?= $total ?> pharmacies enregistrées · <?= afficherDateComplete() ?></p>
        </div>
        <button class="btn-add" onclick="location.href='add-pharmacie.php'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle pharmacie
        </button>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat__dot stat__dot--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div><div class="stat__val"><?= $totalActif ?></div><div class="stat__label">Actives</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </div>
            <div><div class="stat__val"><?= $totalJour ?></div><div class="stat__label">De jour</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </div>
            <div><div class="stat__val"><?= $totalNuit ?></div><div class="stat__label">De nuit</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div><div class="stat__val"><?= $total24h ?></div><div class="stat__label">24h/24</div></div>
        </div>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Nom, adresse, propriétaire, quartier…">
        </div>
        <div class="filter-sep"></div>
        <select class="filter-sel" id="filterHoraire">
            <option value="">Tous horaires</option>
            <option value="Jour">☀ Jour</option>
            <option value="Nuit">☾ Nuit</option>
            <option value="24h/24">⏱ 24h/24</option>
        </select>
        <select class="filter-sel" id="filterDept">
            <option value="">Tous départements</option>
            <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-sel" id="filterArr" style="display:none">
            <option value="">Tous arrondissements</option>
        </select>
        <select class="filter-sel" id="filterDist" style="display:none">
            <option value="">Tous districts</option>
        </select>
        <select class="filter-sel" id="filterGroupe">
            <option value="">Tous groupes</option>
            <?php foreach ($groupes as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['groupe']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-sel" id="filterStatut">
            <option value="">Tous statuts</option>
            <option value="actif">Actives</option>
            <option value="inactif">Inactives</option>
        </select>
        <div class="filter-sep"></div>
        <button class="btn-reset" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Effacer
        </button>
    </div>

    <div class="results-bar">
        <span class="results-count" id="resultsCount"><strong>—</strong> résultats</span>
        <div class="results-per-page">
            Par page :
            <select id="perPageSel" onchange="changePerPage(this.value)">
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-scroll">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th onclick="sortBy('nom_pharmacie')" id="th_nom">Pharmacie</th>
                        <th onclick="sortBy('nom')" id="th_prop">Propriétaire</th>
                        <th id="th_loc" class="no-sort">Localisation</th>
                        <th onclick="sortBy('horaire')" id="th_hor">Horaire</th>
                        <th onclick="sortBy('is_actif')" id="th_stat">Statut</th>
                        <th class="no-sort" style="width:90px;text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
        <div class="empty-state" id="emptyState" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h3>Aucun résultat</h3>
            <p>Modifiez vos filtres pour afficher des pharmacies</p>
        </div>
        <div class="pagination" id="pagination"></div>
    </div>

</div>
</div>

<!-- DRAWER -->
<div class="drawer-backdrop" id="drawerBackdrop">
    <div class="drawer" id="drawer" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <div class="drawer-avatar" id="drawerAvatar">PH</div>
            <div class="drawer-title">
                <h3 id="drawerTitle">—</h3>
                <p id="drawerSub">—</p>
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
            <a class="df-btn df-btn--primary" id="btnDrawerEdit" href="#">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Modifier
            </a>
        </div>
    </div>
</div>

<!-- MODAL SUPPRESSION -->
<div class="modal-overlay" id="delOverlay">
    <div class="modal-del">
        <div class="del-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <p class="del-title">Supprimer cette pharmacie ?</p>
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
const ALL_DATA       = <?= $pharmaciesJson ?>;
const ALL_DEPTS      = <?= $deptsJson ?>;
const ALL_GROUPES    = <?= $groupesJson ?>;
const ALL_ARRS       = <?= $allArrsJson ?>;
const ALL_DISTS      = <?= $allDistsJson ?>;
const DEPTS_WITH_ARR  = <?= $deptsWithArrJson ?>;
const GARDE_GROUPS   = <?= json_encode($gardeGroupIds) ?>;
const GARDE_DATES    = <?= json_encode($datesMoisParGroupe) ?>;
const API_BASE       = '../api/pharmacies.php';
</script>
<script src="../assets/js/pharmacies.js"></script>
</body>
</html>