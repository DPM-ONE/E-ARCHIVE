<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();

    $groupes = $pdo->query("SELECT id, groupe FROM groupe_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $depts   = $pdo->query("SELECT id, libelle FROM departements_dpm ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Toutes les gardes avec jointures ── */
    $gardes = $pdo->query("
        SELECT g.id, g.mois, g.jour, g.groupe_id, g.departement_id,
               gr.groupe AS groupe_libelle,
               d.libelle AS dept_libelle,
               g.created_at, g.updated_at
        FROM garde_dpm g
        LEFT JOIN groupe_dpm       gr ON gr.id = g.groupe_id
        LEFT JOIN departements_dpm d  ON d.id  = g.departement_id
        ORDER BY g.jour ASC, g.departement_id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Garde cette semaine ── */
    $now    = new DateTime();
    $dow    = (int)$now->format('N');
    $monday = (clone $now)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
    $sunday = (clone $now)->modify('+' . (7 - $dow) . ' days')->format('Y-m-d');

    $gardeWeekStmt = $pdo->prepare("SELECT DISTINCT groupe_id FROM garde_dpm WHERE jour BETWEEN ? AND ?");
    $gardeWeekStmt->execute([$monday, $sunday]);
    $gardeGroupIds = array_map('intval', $gardeWeekStmt->fetchAll(PDO::FETCH_COLUMN));

    /* Stats */
    $totalGardes = count($gardes);
    $totalMois   = count(array_unique(array_map(fn($g) => substr($g['mois'], 0, 7), $gardes)));
    $totalDepts  = count(array_unique(array_filter(array_column($gardes, 'departement_id'))));

} catch (Exception $e) {
    $gardes = $groupes = $depts = [];
    $gardeGroupIds = [];
    $totalGardes = $totalMois = $totalDepts = 0;
}

$gardesJson  = json_encode($gardes);
$groupesJson = json_encode($groupes);
$deptsJson   = json_encode($depts);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gardes — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/gardes.css">
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
            <div class="page-head__eyebrow">Planning</div>
            <h2 class="page-head__title">Gardes de pharmacie</h2>
            <p class="page-head__sub"><?= $totalGardes ?> gardes planifiées · <?= afficherDateComplete() ?></p>
        </div>
        <button class="btn-add" onclick="openAddModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouvelle garde
        </button>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat">
            <div class="stat__dot stat__dot--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div><div class="stat__val"><?= $totalGardes ?></div><div class="stat__label">Gardes totales</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div><div class="stat__val">4</div><div class="stat__label">Groupes actifs</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/></svg>
            </div>
            <div><div class="stat__val"><?= $totalDepts ?></div><div class="stat__label">Départements</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="stat__val"><?= count($gardeGroupIds) ?></div>
                <div class="stat__label">En garde cette semaine</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Rechercher groupe, département, date…">
        </div>
        <div class="filter-sep"></div>
        <select class="filter-sel" id="filterGroupe">
            <option value="">Tous les groupes</option>
            <?php foreach ($groupes as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['groupe']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-sel" id="filterDept">
            <option value="">Tous les départements</option>
            <?php foreach ($depts as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['libelle']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="filter-sel" id="filterMois">
            <option value="">Tous les mois</option>
        </select>
        <div class="filter-sep"></div>
        <div class="view-toggle">
            <button class="view-btn view-btn--active" id="btnViewTimeline" onclick="setView('timeline')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Mois
            </button>
            <button class="view-btn" id="btnViewTable" onclick="setView('table')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                Tableau
            </button>
        </div>
        <button class="btn-reset" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Effacer
        </button>
    </div>

    <div class="results-bar">
        <span class="results-count" id="resultsCount"><strong>—</strong> gardes</span>
    </div>

    <!-- Vue timeline par mois -->
    <div id="viewTimeline"></div>

    <!-- Vue tableau -->
    <div id="viewTable" style="display:none">
        <div class="table-wrap">
            <div class="table-scroll">
                <table id="mainTable">
                    <thead>
                        <tr>
                            <th onclick="sortBy('jour')" id="th_jour">Date</th>
                            <th onclick="sortBy('groupe_libelle')" id="th_groupe">Groupe</th>
                            <th onclick="sortBy('dept_libelle')" id="th_dept">Département</th>
                            <th class="no-sort" style="width:80px;text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
            <div class="empty-state" id="emptyState" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <h3>Aucune garde trouvée</h3>
                <p>Modifiez vos filtres ou ajoutez une nouvelle garde</p>
            </div>
            <div class="pagination" id="pagination"></div>
        </div>
    </div>
</div>
</div>

<!-- MODAL AJOUT / MODIFICATION -->
<div class="modal-overlay" id="formOverlay">
    <div class="modal-form" onclick="event.stopPropagation()">
        <div class="modal-form__header">
            <div class="modal-form__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <h3 id="modalTitle">Nouvelle garde</h3>
                <p id="modalSub">Planifier un jour de garde</p>
            </div>
            <button class="modal-close" onclick="closeFormModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-form__body">
            <input type="hidden" id="editId" value="">
            <div class="mf-fields">
                <div class="mf-field">
                    <label class="mf-label required">Jour de garde</label>
                    <input type="date" class="mf-input" id="inputJour" required>
                    <div class="mf-error" id="errJour"></div>
                </div>
                <div class="mf-field">
                    <label class="mf-label required">Groupe de garde</label>
                    <div class="mf-select-wrap">
                        <select class="mf-select" id="inputGroupe" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($groupes as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['groupe']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mf-error" id="errGroupe"></div>
                </div>
                <div class="mf-field">
                    <label class="mf-label">Département</label>
                    <div class="mf-select-wrap">
                        <select class="mf-select" id="inputDept">
                            <option value="">— Tous —</option>
                            <?php foreach ($depts as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-form__footer">
            <button class="mf-btn mf-btn--cancel" onclick="closeFormModal()">Annuler</button>
            <button class="mf-btn mf-btn--save" id="btnSave" onclick="saveGarde()">
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
        <p class="del-title">Supprimer cette garde ?</p>
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
const ALL_DATA    = <?= $gardesJson ?>;
const ALL_GROUPES = <?= $groupesJson ?>;
const ALL_DEPTS   = <?= $deptsJson ?>;
const API_BASE    = '../api/gardes.php';
const GARDE_GROUPS_WEEK = <?= json_encode($gardeGroupIds) ?>;
</script>
<script src="../assets/js/gardes.js"></script>
</body>
</html>