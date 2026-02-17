<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();
    $grossistes = $pdo->query("
        SELECT g.*,
               d.libelle  AS dept_libelle,
               a.libelle  AS arr_libelle,
               dt.libelle AS dist_libelle
        FROM grossistes_dpm g
        LEFT JOIN departements_dpm   d  ON d.id  = g.departement
        LEFT JOIN arrondissement_dpm a  ON a.id  = g.arrondissement_id
        LEFT JOIN district_dpm       dt ON dt.id = g.district_id
        ORDER BY g.nom_grossiste ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $depts = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allArrs = $pdo->query("SELECT id, libelle, departement_id FROM arrondissement_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $allDists = $pdo->query("SELECT id, libelle, departement_id FROM district_dpm ORDER BY departement_id, libelle")->fetchAll(PDO::FETCH_ASSOC);
    $deptsWithArr = $pdo->query("SELECT DISTINCT departement_id FROM arrondissement_dpm")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $grossistes = $depts = $allArrs = $allDists = [];
    $deptsWithArr = [];
}

$total = count($grossistes);
$totalActif = count(array_filter($grossistes, fn($g) => $g['is_actif']));

$grossistesJson = json_encode(array_map(fn($g) => [
    'id' => (int) $g['id'],
    'nom_grossiste' => $g['nom_grossiste'],
    'responsable' => $g['responsable'],
    'telephone' => $g['telephone'] ?? '',
    'email' => $g['email'] ?? '',
    'adresse' => $g['adresse'],
    'quartier' => $g['quartier'],
    'departement_id' => (int) $g['departement'],
    'departement' => $g['dept_libelle'] ?? '',
    'arrondissement_id' => $g['arrondissement_id'] ? (int) $g['arrondissement_id'] : null,
    'arrondissement' => $g['arr_libelle'] ?? '',
    'district_id' => $g['district_id'] ? (int) $g['district_id'] : null,
    'district' => $g['dist_libelle'] ?? '',
    'box_rangement' => $g['box_rangement'] ?? '',
    'zone_archive' => $g['zone_archive'] ?? '',
    'is_actif' => (bool) $g['is_actif'],
    'created_at' => $g['created_at'] ?? '',
], $grossistes));

$deptsJson = json_encode($depts);
$allArrsJson = json_encode($allArrs);
$allDistsJson = json_encode($allDists);
$deptsWithArrJson = json_encode(array_map('intval', $deptsWithArr));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grossistes — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/grossistes.css">
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
                    <h2 class="page-head__title">Grossistes</h2>
                    <p class="page-head__sub"><?= $total ?> grossistes enregistrés · <?= afficherDateComplete() ?></p>
                </div>
                <button class="btn-add" onclick="location.href='add-grossiste.php'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19" />
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                    Ajouter
                </button>
            </div>

            <div class="stats">
                <div class="stat">
                    <div class="stat__dot stat__dot--green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div>
                        <div class="stat__val"><?= $totalActif ?></div>
                        <div class="stat__label">Actifs</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__dot stat__dot--blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </div>
                    <div>
                        <div class="stat__val"><?= $total ?></div>
                        <div class="stat__label">Total</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__dot stat__dot--amber">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <line x1="9" y1="3" x2="9" y2="21" />
                        </svg>
                    </div>
                    <div>
                        <div class="stat__val"><?= count(array_unique(array_column($grossistes, 'departement'))) ?>
                        </div>
                        <div class="stat__label">Départements</div>
                    </div>
                </div>
                <div class="stat">
                    <div class="stat__dot stat__dot--violet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                            <line x1="12" y1="22.08" x2="12" y2="12" />
                        </svg>
                    </div>
                    <div>
                        <div class="stat__val">
                            <?= count(array_filter($grossistes, fn($g) => !empty($g['box_rangement']))) ?></div>
                        <div class="stat__label">En box</div>
                    </div>
                </div>
            </div>

            <div class="toolbar">
                <div class="search-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input type="text" class="search-input" id="searchInput"
                        placeholder="Nom, responsable, adresse, quartier…">
                </div>
                <div class="filter-sep"></div>
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
                <select class="filter-sel" id="filterStatut">
                    <option value="">Tous statuts</option>
                    <option value="actif">Actifs</option>
                    <option value="inactif">Inactifs</option>
                </select>
                <div class="filter-sep"></div>
                <button class="btn-reset" onclick="resetAll()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10" />
                        <path d="M3.51 15a9 9 0 1 0 .49-3.95" />
                    </svg>
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
                                <th onclick="sortBy('nom_grossiste')" id="th_nom">Grossiste</th>
                                <th onclick="sortBy('responsable')" id="th_resp">Responsable</th>
                                <th id="th_loc" class="no-sort">Localisation</th>
                                <th onclick="sortBy('box_rangement')" id="th_box">Box</th>
                                <th onclick="sortBy('is_actif')" id="th_stat">Statut</th>
                                <th class="no-sort" style="width:90px;text-align:right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
                <div class="empty-state" id="emptyState" style="display:none">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <h3>Aucun résultat</h3>
                    <p>Modifiez vos filtres pour afficher des grossistes</p>
                </div>
                <div class="pagination" id="pagination"></div>
            </div>

        </div>
    </div>

    <!-- DRAWER -->
    <div class="drawer-backdrop" id="drawerBackdrop">
        <div class="drawer" id="drawer" onclick="event.stopPropagation()">
            <div class="drawer-header">
                <div class="drawer-avatar" id="drawerAvatar">GR</div>
                <div class="drawer-title">
                    <h3 id="drawerTitle">—</h3>
                    <p id="drawerSub">—</p>
                </div>
                <button class="drawer-close" onclick="closeDrawer()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="drawer-body" id="drawerBody"></div>
            <div class="drawer-footer">
                <button class="df-btn df-btn--danger" id="btnDrawerDelete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14H6L5 6" />
                        <path d="M9 6V4h6v2" />
                    </svg>
                    Supprimer
                </button>
                <a class="df-btn df-btn--primary" id="btnDrawerEdit" href="#">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                    </svg>
                    Modifier
                </a>
            </div>
        </div>
    </div>

    <!-- MODAL SUPPRESSION -->
    <div class="modal-overlay" id="delOverlay">
        <div class="modal-del">
            <div class="del-icon-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6l-1 14H6L5 6" />
                    <path d="M9 6V4h6v2" />
                    <line x1="10" y1="11" x2="10" y2="17" />
                    <line x1="14" y1="11" x2="14" y2="17" />
                </svg>
            </div>
            <p class="del-title">Supprimer ce grossiste ?</p>
            <p class="del-msg" id="delMsg">—</p>
            <p class="del-warn">Cette action est irréversible.</p>
            <div class="del-actions">
                <button class="btn-cancel" onclick="closeDelModal()">Annuler</button>
                <button class="btn-del-confirm" id="btnConfirmDel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14H6L5 6" />
                        <path d="M9 6V4h6v2" />
                    </svg>
                    Supprimer
                </button>
            </div>
        </div>
    </div>

    <div class="toast-wrap" id="toastWrap"></div>

    <script src="../assets/js/sidebar.js"></script>
    <script>
        const ALL_DATA = <?= $grossistesJson ?>;
        const ALL_DEPTS = <?= $deptsJson ?>;
        const ALL_ARRS = <?= $allArrsJson ?>;
        const ALL_DISTS = <?= $allDistsJson ?>;
        const DEPTS_WITH_ARR = <?= $deptsWithArrJson ?>;
        const API_BASE = '../api/grossistes.php';
    </script>
    <script src="../assets/js/grossistes.js"></script>
</body>

</html>