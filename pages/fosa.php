<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

try {
    $pdo = getPDO();

    /* ── Données pour filtres ── */
    $depts = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $allDS = $pdo->query("SELECT id, nom_ds, departement_id FROM districts_sanitaires_dpm ORDER BY departement_id, nom_ds")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Liste FOSA avec jointures ── */
    $rows = $pdo->query("
        SELECT f.*,
               d.libelle  AS dept_libelle,
               ds.nom_ds  AS ds_libelle
        FROM fosa_dpm f
        LEFT JOIN departements_dpm          d  ON d.id  = f.departement_id
        LEFT JOIN districts_sanitaires_dpm  ds ON ds.id = f.district_sanitaire_id
        ORDER BY f.nom_fosa
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $rows = $depts = $allDS = [];
}

/* Préparer les données JS */
$allDataJs = [];
foreach ($rows as $r) {
    $allDataJs[] = [
        'id'                   => (int)$r['id'],
        'nom_fosa'             => $r['nom_fosa'],
        'departement_id'       => (int)$r['departement_id'],
        'departement'          => $r['dept_libelle'] ?? '',
        'district_sanitaire_id'=> (int)$r['district_sanitaire_id'],
        'district_sanitaire'   => $r['ds_libelle'] ?? '',
        'prenom_responsable'   => $r['prenom_responsable'] ?? '',
        'nom_responsable'      => $r['nom_responsable'] ?? '',
        'telephone'            => $r['telephone'] ?? '',
        'adresse'              => $r['adresse'] ?? '',
        'created_at'           => $r['created_at'] ?? '',
    ];
}

/* ── Toast depuis redirect ── */
$toastMap = [
    'permission_denied' => ['error',   'Accès refusé — droits insuffisants'],
    'not_found'         => ['error',   'Formation sanitaire introuvable'],
    'saved'             => ['success', 'Formation sanitaire enregistrée avec succès'],
];
$toast = $_GET['toast'] ?? '';

$canWrite = in_array($_SESSION['role'] ?? '', ['super_admin', 'admin']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>FOSA — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/fosa.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()">
        <span></span><span></span><span></span>
    </button>
    <h1 class="topbar__title">Formations sanitaires (FOSA)</h1>
    <div class="topbar__sep"></div>
    <span class="topbar__date"><?= date('d/m/Y') ?></span>
</header>

<div class="page-inner">

    <!-- ── Barre d'outils ── -->
    <div class="toolbar">
        <div class="toolbar__left">
            <div class="search-wrap">
                <svg class="search-wrap__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="searchInput" class="search-wrap__input" placeholder="Rechercher une FOSA, responsable, adresse…">
            </div>
        </div>
        <div class="toolbar__right">
            <?php if ($canWrite): ?>
            <a href="add-fosa.php" class="btn-add">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvelle une FOSA
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filtres ── -->
    <div class="filters-bar">
        <div class="filter-group">
            <label class="filter-label">Département</label>
            <div class="field__select-wrap">
                <select id="filterDept" class="filter-select">
                    <option value="">Tous les départements</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="filter-group">
            <label class="filter-label">District sanitaire</label>
            <div class="field__select-wrap">
                <select id="filterDS" class="filter-select" style="display:none">
                    <option value="">Tous les districts</option>
                </select>
                <select id="filterDSAll" class="filter-select">
                    <option value="">Tous les districts</option>
                    <?php foreach ($allDS as $ds): ?>
                        <option value="<?= $ds['id'] ?>"><?= htmlspecialchars($ds['nom_ds']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn-reset-filters" id="btnResetFilters" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Effacer
        </button>
    </div>
     <!-- ── resultat ── -->
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
    <!-- ── Table ── -->
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th id="th_nom"  onclick="sortBy('nom_fosa')"    class="sortable sort-asc">Nom FOSA</th>
                    <th id="th_resp" onclick="sortBy('nom_responsable')" class="sortable">Responsable</th>
                    <th id="th_dept" onclick="sortBy('departement')"  class="sortable">Localisation</th>
                    <th>Contact</th>
                    <th class="th-actions"></th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        <div id="emptyState" class="empty-state" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <p>Aucune formation sanitaire trouvée</p>
        </div>
    </div>

    <!-- ── Pagination ── -->
    <div class="pagination-wrap">
        <div id="pagination" class="pagination"></div>
    </div>

</div><!-- /page-inner -->
</div><!-- /sb-main -->

<!-- ══════════════════════════
     DRAWER DÉTAIL
══════════════════════════ -->
<div class="drawer-backdrop" id="drawerBackdrop">
    <div class="drawer">
        <div class="drawer-header">
            <div class="drawer-header__top">
                <button class="drawer-close" onclick="closeDrawer()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="drawer-avatar" id="drawerAvatar">—</div>
            <div class="drawer-title" id="drawerTitle">—</div>
            <div class="drawer-sub"   id="drawerSub">—</div>
            <div class="drawer-actions" style="margin-top:12px;display:flex;gap:8px;justify-content:center">
                <?php if ($canWrite): ?>
                <a href="#" class="btn-drawer-edit" id="btnDrawerEdit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Modifier
                </a>
                <button class="btn-drawer-del" id="btnDrawerDelete">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    Supprimer
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="drawer-body" id="drawerBody"></div>
    </div>
</div>

<!-- ══════════════════════════
     MODAL SUPPRESSION
══════════════════════════ -->
<div class="del-overlay" id="delOverlay">
    <div class="del-modal">
        <div class="del-modal__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
        </div>
        <div class="del-modal__title">Confirmer la suppression</div>
        <div class="del-modal__msg" id="delMsg"></div>
        <div class="del-modal__actions">
            <button class="btn-del-cancel" onclick="closeDelModal()">Annuler</button>
            <button class="btn-del-ok" id="btnConfirmDel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                Supprimer
            </button>
        </div>
    </div>
</div>

<!-- ── Toasts ── -->
<div id="toastWrap" class="toast-wrap"></div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_DATA      = <?= json_encode(array_values($allDataJs), JSON_UNESCAPED_UNICODE) ?>;
const ALL_DS        = <?= json_encode($allDS, JSON_UNESCAPED_UNICODE) ?>;
const DEPTS         = <?= json_encode($depts, JSON_UNESCAPED_UNICODE) ?>;
const API_BASE      = '../api/fosa.php';
const CAN_WRITE     = <?= $canWrite ? 'true' : 'false' ?>;

<?php if ($toast && isset($toastMap[$toast])): ?>
window.addEventListener('DOMContentLoaded', () => {
    toast(<?= json_encode($toastMap[$toast][1]) ?>, <?= json_encode($toastMap[$toast][0]) ?>);
});
<?php endif; ?>
</script>
<script src="../assets/js/fosa.js"></script>
</body>
</html>