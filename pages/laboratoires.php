<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();

    $agences = $pdo->query("SELECT id, nom_agence FROM agences_dpm ORDER BY nom_agence")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Tous les laboratoires avec jointures ── */
    $labos = $pdo->query("
        SELECT l.id, l.nom_laboratoire, l.pays, l.agence_id,
               a.nom_agence AS agence_nom
        FROM laboratoires_dpm l
        LEFT JOIN agences_dpm a ON a.id = l.agence_id
        ORDER BY l.nom_laboratoire ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* Stats */
    $totalLabos  = count($labos);
    $totalAgences = count(array_unique(array_filter(array_column($labos, 'agence_id'))));
    $totalPays   = count(array_unique(array_filter(array_column($labos, 'pays'))));

} catch (Exception $e) {
    $labos = $agences = [];
    $totalLabos = $totalAgences = $totalPays = 0;
}

$labosJson   = json_encode($labos);
$agencesJson = json_encode($agences);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratoires — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/laboratoires.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="labo-topbar">
    <button class="labo-topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()">
        <span></span><span></span><span></span>
    </button>
    <h1 class="labo-topbar__title">DPM Archive</h1>
    <div class="labo-topbar__sep"></div>
    <span class="labo-topbar__date"><?= date('d/m/Y') ?></span>
</header>

<div class="labo-page">
    <div class="labo-page-head">
        <div>
            <div class="labo-page-head__eyebrow">Gestion</div>
            <h2 class="labo-page-head__title">Laboratoires Pharmaceutiques</h2>
            <p class="labo-page-head__sub"><?= $totalLabos ?> laboratoire<?= $totalLabos > 1 ? 's' : '' ?> · <?= afficherDateComplete() ?></p>
        </div>
        <button class="labo-btn-add" onclick="openAddModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouveau laboratoire
        </button>
    </div>

    <!-- Stats -->
    <div class="labo-stats">
        <div class="labo-stat">
            <div class="labo-stat__dot labo-stat__dot--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/>
                    <path d="M3 21h18"/><path d="M9 7h.01"/><path d="M9 11h.01"/>
                    <path d="M9 15h.01"/><path d="M13 7h.01"/><path d="M13 11h.01"/><path d="M13 15h.01"/>
                </svg>
            </div>
            <div><div class="labo-stat__val"><?= $totalLabos ?></div><div class="labo-stat__label">Laboratoires totaux</div></div>
        </div>
        <div class="labo-stat">
            <div class="labo-stat__dot labo-stat__dot--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </div>
            <div><div class="labo-stat__val"><?= $totalAgences ?></div><div class="labo-stat__label">Agences associées</div></div>
        </div>
        <div class="labo-stat">
            <div class="labo-stat__dot labo-stat__dot--violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                </svg>
            </div>
            <div><div class="labo-stat__val"><?= $totalPays ?></div><div class="labo-stat__label">Pays</div></div>
        </div>
        <div class="labo-stat">
            <div class="labo-stat__dot labo-stat__dot--blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="labo-stat__val"><?= date('Y') ?></div>
                <div class="labo-stat__label">Année en cours</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="labo-toolbar">
        <div class="labo-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="labo-search-input" id="searchInput" placeholder="Rechercher laboratoire, pays, agence…">
        </div>
        <div class="labo-filter-sep"></div>
        <select class="labo-filter-sel" id="filterAgence">
            <option value="">Toutes les agences</option>
            <?php foreach ($agences as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom_agence']) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="labo-filter-sep"></div>
        <div class="labo-view-toggle">
            <button class="labo-view-btn labo-view-btn--active" id="btnViewCards" onclick="setView('cards')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                Cartes
            </button>
            <button class="labo-view-btn" id="btnViewTable" onclick="setView('table')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
                Tableau
            </button>
        </div>
        <button class="labo-btn-reset" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Effacer
        </button>
    </div>

    <div class="labo-results-bar">
        <span class="labo-results-count" id="resultsCount"><strong>—</strong> laboratoires</span>
    </div>

    <!-- Vue cartes par agence -->
    <div id="viewCards"></div>

    <!-- Vue tableau -->
    <div id="viewTable" style="display:none">
        <div class="labo-table-wrap">
            <div class="labo-table-scroll">
                <table class="labo-table" id="mainTable">
                    <thead>
                        <tr>
                            <th onclick="sortBy('nom_laboratoire')" id="th_nom">Laboratoire</th>
                            <th onclick="sortBy('pays')" id="th_pays">Pays</th>
                            <th onclick="sortBy('agence_nom')" id="th_agence">Agence</th>
                            <th class="no-sort" style="width:80px;text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
            <div class="labo-empty-state" id="emptyState" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/>
                    <path d="M3 21h18"/>
                </svg>
                <h3>Aucun laboratoire trouvé</h3>
                <p>Modifiez vos filtres ou ajoutez un nouveau laboratoire</p>
            </div>
            <div class="labo-pagination" id="pagination"></div>
        </div>
    </div>
</div>
</div>

<!-- MODAL AJOUT / MODIFICATION -->
<div class="labo-modal-overlay" id="formOverlay">
    <div class="labo-modal-form" onclick="event.stopPropagation()">
        <div class="labo-modal-form__header">
            <div class="labo-modal-form__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16"/>
                    <path d="M3 21h18"/>
                </svg>
            </div>
            <div>
                <h3 id="modalTitle">Nouveau laboratoire</h3>
                <p id="modalSub">Ajouter un laboratoire pharmaceutique</p>
            </div>
            <button class="labo-modal-close" onclick="closeFormModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="labo-modal-form__body">
            <input type="hidden" id="editId" value="">
            <div class="labo-mf-fields">
                <div class="labo-mf-field">
                    <label class="labo-mf-label required">Nom du laboratoire</label>
                    <input type="text" class="labo-mf-input" id="inputNom" required placeholder="Ex: Laboratoire Pfizer Congo">
                    <div class="labo-mf-error" id="errNom"></div>
                </div>
                <div class="labo-mf-field">
                    <label class="labo-mf-label">Pays</label>
                    <input type="text" class="labo-mf-input" id="inputPays" placeholder="Ex: Congo, France...">
                </div>
                <div class="labo-mf-field">
                    <label class="labo-mf-label required">Agence</label>
                    <div class="labo-mf-select-wrap">
                        <select class="labo-mf-select" id="inputAgence" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($agences as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nom_agence']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="labo-mf-error" id="errAgence"></div>
                </div>
            </div>
        </div>
        <div class="labo-modal-form__footer">
            <button class="labo-mf-btn labo-mf-btn--cancel" onclick="closeFormModal()">Annuler</button>
            <button class="labo-mf-btn labo-mf-btn--save" id="btnSave" onclick="saveLabo()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                Enregistrer
            </button>
        </div>
    </div>
</div>

<!-- MODAL SUPPRESSION -->
<div class="labo-modal-overlay" id="delOverlay">
    <div class="labo-modal-del" onclick="event.stopPropagation()">
        <div class="labo-del-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <p class="labo-del-title">Supprimer ce laboratoire ?</p>
        <p class="labo-del-msg" id="delMsg">—</p>
        <p class="labo-del-warn">Cette action est irréversible.</p>
        <div class="labo-del-actions">
            <button class="labo-btn-cancel" onclick="closeDelModal()">Annuler</button>
            <button class="labo-btn-del-confirm" id="btnConfirmDel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                Supprimer
            </button>
        </div>
    </div>
</div>

<div class="labo-toast-wrap" id="toastWrap"></div>

<script src="../assets/js/sidebar.js"></script>
<script>
const ALL_DATA    = <?= $labosJson ?>;
const ALL_AGENCES = <?= $agencesJson ?>;
const API_BASE    = '../api/laboratoires.php';
</script>
<script src="../assets/js/laboratoires.js"></script>
</body>
</html>