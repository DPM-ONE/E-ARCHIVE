<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();
    $agences = $pdo->query("
        SELECT a.*,
               dept.libelle  AS dept_libelle,
               arr.libelle   AS arr_libelle,
               dist.libelle  AS dist_libelle
        FROM agences_dpm a
        LEFT JOIN departements_dpm   dept ON dept.id = a.departement_id
        LEFT JOIN arrondissement_dpm arr  ON arr.id  = a.arrondissement_id
        LEFT JOIN district_dpm       dist ON dist.id = a.district_id
        ORDER BY a.nom_agence ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $depts        = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs      = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists     = $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $agences = $depts = $allArrs = $allDists = [];
    $deptsWithArr = [];
}

$total      = count($agences);
$totalActif = count(array_filter($agences, fn($a) => $a['is_active']));

$agencesJson = json_encode(array_map(fn($a) => [
    'id'                   => (int)$a['id'],
    'nom_agence'           => $a['nom_agence'],
    'responsable_prenom'   => $a['responsable_prenom'] ?? '',
    'responsable_nom'      => $a['responsable_nom'] ?? '',
    'responsable'          => trim(($a['responsable_prenom'] ?? '') . ' ' . ($a['responsable_nom'] ?? '')),
    'numero_decision'      => $a['numero_decision'] ?? '',
    'adresse'              => $a['adresse'],
    'localite'             => $a['localite'],
    'quartier'             => $a['quartier'] ?? '',
    'telephone'            => $a['telephone'] ?? '',
    'email'                => $a['email'] ?? '',
    'departement_id'       => (int)$a['departement_id'],
    'departement'          => $a['dept_libelle'] ?? '',
    'arrondissement_id'    => $a['arrondissement_id'] ? (int)$a['arrondissement_id'] : null,
    'arrondissement'       => $a['arr_libelle'] ?? '',
    'district_id'          => $a['district_id'] ? (int)$a['district_id'] : null,
    'district'             => $a['dist_libelle'] ?? '',
    'box_rangement'        => $a['box_rangement'] ?? '',
    'zone_archive'         => $a['zone_archive'] ?? '',
    'is_active'            => (bool)$a['is_active'],
    'created_at'           => $a['created_at'] ?? '',
], $agences));

$deptsJson        = json_encode($depts);
$allArrsJson      = json_encode($allArrs);
$allDistsJson     = json_encode($allDists);
$deptsWithArrJson = json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agences Pharmaceutiques — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/depots.css">
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
        <div class="page-head__info">
            <div class="page-head__eyebrow">Gestion</div>
            <h2 class="page-head__title">Agences Pharmaceutiques</h2>
            <p class="page-head__sub">
                <?= $total ?> agence<?= $total > 1 ? 's' : '' ?> • 
                Dernière mise à jour : <?= date('d/m/Y') ?>
            </p>
        </div>
        <a href="add-agence.php" class="btn-add">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouvelle agence
        </a>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat__dot stat__dot--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="stat__content">
                <div class="stat__val"><?= $totalActif ?></div>
                <div class="stat__label">Agences Actives</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </div>
            <div class="stat__content">
                <div class="stat__val"><?= $total ?></div>
                <div class="stat__label">Total Agences</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                </svg>
            </div>
            <div class="stat__content">
                <div class="stat__val"><?= count(array_unique(array_column($agences, 'departement_id'))) ?></div>
                <div class="stat__label">Départements</div>
            </div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
            </div>
            <div class="stat__content">
                <div class="stat__val"><?= count(array_filter($agences, fn($a) => !empty($a['box_rangement']))) ?></div>
                <div class="stat__label">En Box</div>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Rechercher une agence...">
        </div>
        <select class="filter-sel" id="filterDept">
            <option value="">Tous les départements</option>
            <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-sel" id="filterArr" style="display:none">
            <option value="">Tous les arrondissements</option>
        </select>
        <select class="filter-sel" id="filterDist" style="display:none">
            <option value="">Tous les districts</option>
        </select>
        <select class="filter-sel" id="filterStatut">
            <option value="">Tous les statuts</option>
            <option value="actif">Actif</option>
            <option value="inactif">Inactif</option>
        </select>
        <button class="btn-reset" id="btnReset" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
            </svg>
            Effacer
        </button>
    </div>

    <div class="results-bar">
        <div class="results-count" id="resultsCount">—</div>
        <div class="results-per-page">
            <span class="per-page-label">Par page:</span>
            <select class="per-page-sel" id="perPageSel" onchange="changePerPage(this.value)">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th onclick="sortBy('nom_agence')" id="th_nom">Agence</th>
                    <th onclick="sortBy('responsable')" id="th_prop">Responsable</th>
                    <th id="th_loc" class="no-sort">Localisation</th>
                    <th onclick="sortBy('numero_decision')" id="th_dec">N° Décision</th>
                    <th onclick="sortBy('box_rangement')" id="th_box">Box</th>
                    <th onclick="sortBy('is_active')" id="th_stat">Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        <div class="empty-state" id="emptyState" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <h3>Aucun résultat</h3>
            <p>Modifiez vos filtres pour afficher des agences</p>
        </div>
        <div class="pagination" id="pagination"></div>
    </div>
</div>
</div>

<div class="drawer-backdrop" id="drawerBackdrop">
    <div class="drawer" id="drawer" onclick="event.stopPropagation()">
    <div class="drawer-header">
        <div class="drawer-avatar" id="drawerAvatar">AG</div>
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
        <a href="#" class="df-btn df-btn--primary" id="btnDrawerEdit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
            Modifier
        </a>
        <button class="df-btn df-btn--danger" id="btnDrawerDel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/>
            </svg>
            Supprimer
        </button>
    </div>
    </div>
</div>

<div class="modal-overlay" id="delOverlay" onclick="if(event.target===this) closeDelModal()">
    <div class="modal-del">
        <div class="del-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <h3 class="del-title">Supprimer cette agence ?</h3>
        <p class="del-msg" id="delMsg">—</p>
        <p class="del-warn">Cette action est irréversible.</p>
        <div class="del-actions">
            <button class="btn-cancel" onclick="closeDelModal()">Annuler</button>
            <button class="btn-del-confirm" id="btnConfirmDel">Supprimer</button>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_DATA        = <?= $agencesJson ?>;
const ALL_DEPTS       = <?= $deptsJson ?>;
const ALL_ARRS        = <?= $allArrsJson ?>;
const ALL_DISTS       = <?= $allDistsJson ?>;
const DEPTS_WITH_ARR  = <?= $deptsWithArrJson ?>;
const API_BASE        = '../api/agences.php';
</script>
<script src="../assets/js/agences.js"></script>
</body>
</html>