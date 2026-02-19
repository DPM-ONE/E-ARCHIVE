 
<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

try {
    $pdo = getPDO();

    /* ── Tous les laboratoires avec leurs agences ── */
    $labos = $pdo->query("
        SELECT l.*,
               COUNT(al.id)  AS nb_agences
        FROM laboratoires_dpm l
        LEFT JOIN agences_laboratoire al ON al.laboratoire_id = l.id
        GROUP BY l.id
        ORDER BY l.nom ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* Enrichir avec les agences liées */
    foreach ($labos as &$labo) {
        $labo['id']        = (int)$labo['id'];
        $labo['is_active'] = (int)$labo['is_active'];
        $labo['nb_agences']= (int)$labo['nb_agences'];

        $stmt = $pdo->prepare("
            SELECT al.id, al.agence_id, al.date_debut, al.date_fin, al.note,
                   a.nom_agence, a.localite, a.telephone AS agence_tel
            FROM agences_laboratoire al
            JOIN agences_dpm a ON a.id = al.agence_id
            WHERE al.laboratoire_id = ?
            ORDER BY al.date_debut DESC
        ");
        $stmt->execute([$labo['id']]);
        $labo['agences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($labo);

    /* ── Liste de toutes les agences (pour le formulaire) ── */
    $agences = $pdo->query("SELECT id, nom_agence, localite FROM agences_dpm WHERE is_active = 1 ORDER BY nom_agence ASC")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Stats ── */
    $totalLabos   = count($labos);
    $totalActifs  = count(array_filter($labos, fn($l) => $l['is_active']));
    $totalPays    = count(array_unique(array_column($labos, 'pays_origine')));
    $totalAgences = count(array_unique(array_merge(...array_map(fn($l) => array_column($l['agences'], 'agence_id'), $labos))));

} catch (Exception $e) {
    $labos = $agences = [];
    $totalLabos = $totalActifs = $totalPays = $totalAgences = 0;
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
            <div class="page-head__eyebrow">Répertoire</div>
            <h2 class="page-head__title">Laboratoires pharmaceutiques</h2>
            <p class="page-head__sub"><?= $totalLabos ?> laboratoire<?= $totalLabos > 1 ? 's' : '' ?> enregistré<?= $totalLabos > 1 ? 's' : '' ?> · <?= afficherDateComplete() ?></p>
        </div>
        <button class="btn-add" onclick="openAddModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nouveau laboratoire
        </button>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat">
            <div class="stat__dot stat__dot--green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
            </div>
            <div><div class="stat__val" id="statTotal"><?= $totalLabos ?></div><div class="stat__label">Total laboratoires</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div><div class="stat__val" id="statActifs"><?= $totalActifs ?></div><div class="stat__label">Actifs</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <div><div class="stat__val" id="statPays"><?= $totalPays ?></div><div class="stat__label">Pays d'origine</div></div>
        </div>
        <div class="stat">
            <div class="stat__dot stat__dot--violet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div><div class="stat__val" id="statAgences"><?= $totalAgences ?></div><div class="stat__label">Agences liées</div></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" class="search-input" id="searchInput" placeholder="Rechercher nom, agrément, responsable, agence…">
        </div>
        <div class="filter-sep"></div>
        <select class="filter-sel" id="filterStatut">
            <option value="">Tous les statuts</option>
            <option value="1">Actifs</option>
            <option value="0">Inactifs</option>
        </select>
        <select class="filter-sel" id="filterPays">
            <option value="">Tous les pays</option>
        </select>
        <div class="filter-sep"></div>
        <button class="btn-reset" onclick="resetAll()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
            Effacer
        </button>
    </div>

    <div class="results-bar">
        <span class="results-count" id="resultsCount"><strong>—</strong> laboratoires</span>
    </div>

    <!-- Grille -->
    <div class="labo-grid" id="laboGrid"></div>
</div>
</div>

<!-- ══════════ MODAL FORM ══════════ -->
<div class="modal-overlay" id="formOverlay">
    <div class="modal-form" onclick="event.stopPropagation()">
        <div class="modal-form__header">
            <div class="modal-form__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
            </div>
            <div>
                <h3 id="modalTitle">Nouveau laboratoire</h3>
                <p id="modalSub">Enregistrer un laboratoire pharmaceutique</p>
            </div>
            <button class="modal-close" onclick="closeFormModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-form__body">
            <input type="hidden" id="editId">

            <div class="mf-section-title">Identification</div>
            <div class="mf-fields">
                <div class="mf-field">
                    <label class="mf-label required">Nom du laboratoire</label>
                    <input type="text" class="mf-input" id="inputNom" placeholder="Ex : SANOFI Congo">
                    <div class="mf-error" id="errNom"></div>
                </div>
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Pays d'origine</label>
                        <input type="text" class="mf-input" id="inputPays" placeholder="Ex : France" value="Congo">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">N° agrément</label>
                        <input type="text" class="mf-input" id="inputAgrement" placeholder="AGR-LAB-2024-XXX">
                    </div>
                </div>
                <div class="mf-field">
                    <label class="mf-label required">Responsable</label>
                    <input type="text" class="mf-input" id="inputResp" placeholder="Dr. Prénom NOM">
                    <div class="mf-error" id="errResp"></div>
                </div>
            </div>

            <div class="mf-section-title">Contact</div>
            <div class="mf-fields">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Téléphone</label>
                        <input type="tel" class="mf-input" id="inputTel" placeholder="+242 06 XXX XXXX">
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Email</label>
                        <input type="email" class="mf-input" id="inputEmail" placeholder="contact@labo.com">
                    </div>
                </div>
                <div class="mf-field">
                    <label class="mf-label">Adresse</label>
                    <textarea class="mf-textarea" id="inputAdresse" placeholder="Adresse complète…"></textarea>
                </div>
            </div>

            <div class="mf-section-title">Archive</div>
            <div class="mf-fields">
                <div class="mf-row">
                    <div class="mf-field">
                        <label class="mf-label">Zone d'archive</label>
                        <div class="mf-select-wrap">
                            <select class="mf-select" id="inputZone">
                                <option value="">— Aucune —</option>
                                <option>Salle I</option>
                                <option>Salle II</option>
                                <option>Salle III</option>
                                <option>Salle IV</option>
                                <option>Salle V</option>
                            </select>
                        </div>
                    </div>
                    <div class="mf-field">
                        <label class="mf-label">Réf. box</label>
                        <input type="text" class="mf-input" id="inputBox" placeholder="BOX-LAB-XXX">
                    </div>
                </div>
            </div>

            <div class="mf-section-title">Agences liées</div>
            <div class="agences-select-list">
                <?php foreach ($agences as $ag): ?>
                <label>
                    <input type="checkbox" class="agence-checkbox" value="<?= $ag['id'] ?>">
                    <?= htmlspecialchars($ag['nom_agence']) ?>
                    <?php if ($ag['localite']): ?>
                        <span style="color:var(--ink-soft);font-size:.75rem">(<?= htmlspecialchars($ag['localite']) ?>)</span>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="mf-section-title">Statut</div>
            <div class="toggle-row">
                <div>
                    <div class="toggle-label">Laboratoire actif</div>
                    <div class="toggle-sub">Un laboratoire inactif reste visible mais grisé</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="inputActive" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
        <div class="modal-form__footer">
            <button class="mf-btn mf-btn--cancel" onclick="closeFormModal()">Annuler</button>
            <button class="mf-btn mf-btn--save" id="btnSave" onclick="saveLabo()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                Enregistrer
            </button>
        </div>
    </div>
</div>

<!-- ══════════ MODAL DETAIL ══════════ -->
<div class="modal-overlay" id="detailOverlay">
    <div class="modal-detail" onclick="event.stopPropagation()" id="detailContent"></div>
</div>

<!-- ══════════ MODAL SUPPRESSION ══════════ -->
<div class="modal-overlay" id="delOverlay">
    <div class="modal-del" onclick="event.stopPropagation()">
        <div class="del-icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        </div>
        <p class="del-title">Supprimer ce laboratoire ?</p>
        <p class="del-msg" id="delMsg">—</p>
        <p class="del-warn">Toutes les liaisons avec les agences seront également supprimées.</p>
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
const ALL_DATA    = <?= $labosJson ?>;
const ALL_AGENCES = <?= $agencesJson ?>;
</script>
<script src="../assets/js/laboratoires.js"></script>
</body>
</html>