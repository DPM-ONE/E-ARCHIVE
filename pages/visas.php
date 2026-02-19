<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
requireLogin();

$TYPES_VISA = [
    "Demande de visa",
    "Renouvellement de visa",
    "Renouvellement - variation",
    "Variation"
];
$STATUTS = ['En attente', 'Approuvé', 'Rejeté', 'Suspendu'];
$SALLES  = ['Salle I', 'Salle II', 'Salle III', 'Salle IV', 'Salle V'];

$visas = $laboratoires = $produits = [];
$dbError = null;

try {
    $pdo = getPDO();

    $visas = $pdo->query("
        SELECT v.*, l.nom_laboratoire AS laboratoire_nom
        FROM visa_dpm v
        LEFT JOIN laboratoires_dpm l ON l.id = v.laboratoire_id
        WHERE v.deleted_at IS NULL
        ORDER BY v.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $laboratoires = $pdo->query(
        "SELECT id, nom_laboratoire AS nom FROM laboratoires_dpm ORDER BY nom_laboratoire"
    )->fetchAll(PDO::FETCH_ASSOC);

    $produits = $pdo->query(
        "SELECT id, nom_produit, forme, dosage, laboratoire_id FROM produits_dpm WHERE is_active=1 ORDER BY nom_produit"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

$stats = ['total'=>0,'approuves'=>0,'en_attente'=>0,'rejetes'=>0,'cartes'=>0,'renouvellement'=>0];
$today = new DateTime();

foreach ($visas as &$v) {
    $v['produits_list'] = !empty($v['produits']) ? (json_decode($v['produits'], true) ?? []) : [];
    $stats['total']++;
    if ($v['statut'] === 'Approuvé')   $stats['approuves']++;
    if ($v['statut'] === 'En attente') $stats['en_attente']++;
    if ($v['statut'] === 'Rejeté')     $stats['rejetes']++;
    if ($v['carte_professionnelle'])   $stats['cartes']++;
    if (!empty($v['date_renouvellement'])) {
        $dr = new DateTime($v['date_renouvellement']);
        if ($dr >= $today && (int)$today->diff($dr)->days <= 90) $stats['renouvellement']++;
    }
}
unset($v);

function typeSlug(string $t): string {
    $tl = mb_strtolower($t,'UTF-8');
    if (str_contains($tl,'délégués') || str_contains($tl,'delegues')) {
        return str_contains($tl,'campagne') ? 'campagne' : 'delegues';
    }
    if (str_contains($tl,'campagne'))    return 'campagne';
    if (str_contains($tl,'agences'))     return 'agences';
    if (str_contains($tl,'grossistes'))  return 'grossistes';
    if (str_contains($tl,'organismes'))  return 'organismes';
    if (str_contains($tl,'structures'))  return 'structures';
    if (str_contains($tl,'enlèvement') || str_contains($tl,'enlevement')) return 'enlevement';
    if (str_contains($tl,'cartes'))      return 'cartes';
    if (str_contains($tl,'destruction')) return 'destruction';
    if (str_contains($tl,'laboratoires')) return 'laboratoires';
    return 'autre';
}
function statutCls(string $s): string {
    return match($s){ 'Approuvé'=>'badge--green','Rejeté'=>'badge--red','Suspendu'=>'badge--amber',default=>'badge--gray'};
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visas — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/visas.css">
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

<?php if ($dbError): ?>
<div class="alert-db">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong>Erreur BD :</strong> <?= htmlspecialchars($dbError) ?></div>
</div>
<?php endif; ?>

<div class="page-head">
    <div>
        <div class="page-head__eyebrow">Gestion des dossiers</div>
        <h2 class="page-head__title">Visas &amp; Autorisations</h2>
        <p class="page-head__sub"><?= $stats['total'] ?> dossier<?= $stats['total']>1?'s':'' ?> &middot; <?= date('d/m/Y') ?></p>
    </div>
    <button class="btn-add" onclick="openAddModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau visa
    </button>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon--blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
        <div><div class="stat-val"><?= $stats['total'] ?></div><div class="stat-lbl">Total dossiers</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div><div class="stat-val"><?= $stats['approuves'] ?></div><div class="stat-lbl">Approuvés</div></div>
    </div>
    <div class="stat-card <?= $stats['en_attente']>0?'stat-card--warn':'' ?>">
        <div class="stat-icon stat-icon--amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div><div class="stat-val"><?= $stats['en_attente'] ?></div><div class="stat-lbl">En attente</div></div>
    </div>
    <div class="stat-card <?= $stats['rejetes']>0?'stat-card--alert':'' ?>">
        <div class="stat-icon stat-icon--red"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
        <div><div class="stat-val"><?= $stats['rejetes'] ?></div><div class="stat-lbl">Rejetés</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon--violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20"/></svg></div>
        <div><div class="stat-val"><?= $stats['cartes'] ?></div><div class="stat-lbl">Cartes délivrées</div></div>
    </div>
    <?php if ($stats['renouvellement'] > 0): ?>
    <div class="stat-card stat-card--warn">
        <div class="stat-icon stat-icon--amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.3"/></svg></div>
        <div><div class="stat-val"><?= $stats['renouvellement'] ?></div><div class="stat-lbl">Renouvell. &lt;90j</div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <div class="toolbar__search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" class="toolbar__input" id="searchInput" placeholder="Numéro dossier, type, laboratoire…">
    </div>
    <div class="mf-select-wrap">
        <select class="mf-select toolbar__filter" id="filterType" style="height:36px;font-size:.79rem;min-width:190px">
            <option value="">Tous les types</option>
            <?php foreach ($TYPES_VISA as $tv): ?>
            <option value="<?= htmlspecialchars($tv) ?>"><?= htmlspecialchars(mb_substr($tv,0,44)).(mb_strlen($tv)>44?'…':'') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mf-select-wrap">
        <select class="mf-select toolbar__filter" id="filterStatut" style="height:36px;font-size:.8rem;min-width:130px">
            <option value="">Tous statuts</option>
            <?php foreach ($STATUTS as $s): ?>
            <option value="<?= $s ?>"><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn-reset" onclick="resetFilters()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Effacer
    </button>
    <div class="toolbar__sep"></div>
    <span class="toolbar__count" id="resultCount"><?= count($visas) ?> résultat<?= count($visas)>1?'s':'' ?></span>
</div>

<!-- Table -->
<div class="table-wrap">
    <div class="table-scroll">
        <table id="visaTable">
            <thead>
                <tr>
                    <th style="width:125px">N° Dossier</th>
                    <th>Type de visa</th>
                    <th style="width:165px">Laboratoire</th>
                    <th style="width:90px;text-align:center">Produits</th>
                    <th style="width:100px">Décision</th>
                    <th style="width:115px">Renouvellement</th>
                    <th style="width:100px">Statut</th>
                    <th style="width:65px;text-align:center">Carte</th>
                    <th style="width:110px">Archive</th>
                    <th style="width:86px"></th>
                </tr>
            </thead>
            <tbody id="visaBody">
            <?php foreach ($visas as $v):
                $nb = count($v['produits_list']);
                $rv = $v['date_renouvellement'] ?? null;
                $rc = '';
                if ($rv) {
                    $dr   = new DateTime($rv);
                    $diff = (int)$today->diff($dr)->days;
                    $rc   = $dr < $today ? 'date--expired' : ($diff<=90?'date--soon':'');
                }
            ?>
            <tr class="visa-row"
                data-id="<?= $v['id'] ?>"
                data-type="<?= htmlspecialchars($v['type_visa']) ?>"
                data-statut="<?= htmlspecialchars($v['statut']) ?>"
                data-search="<?= htmlspecialchars(mb_strtolower(($v['numero_dossier']??'').' '.($v['type_visa']??'').' '.($v['laboratoire_nom']??'').' '.($v['observations']??''))) ?>">
                <td class="td-mono"><?= htmlspecialchars($v['numero_dossier'] ?? '—') ?></td>
                <td><span class="type-pill type-pill--<?= typeSlug($v['type_visa']) ?>"><?= htmlspecialchars($v['type_visa']) ?></span></td>
                <td class="td-lab"><?= $v['laboratoire_nom'] ? htmlspecialchars($v['laboratoire_nom']) : '<span class="muted">—</span>' ?></td>
                <td class="td-center">
                    <?php if ($nb>0): ?><button class="prod-badge-btn" onclick="openDrawer(<?= $v['id'] ?>)"><?= $nb ?> produit<?= $nb>1?'s':'' ?></button>
                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                </td>
                <td class="td-date"><?= $v['date_decision'] ? date('d/m/Y',strtotime($v['date_decision'])) : '<span class="muted">—</span>' ?></td>
                <td class="td-date <?= $rc ?>"><?= $rv ? date('d/m/Y',strtotime($rv)) : '<span class="muted">—</span>' ?></td>
                <td><span class="badge <?= statutCls($v['statut']) ?>"><?= htmlspecialchars($v['statut']) ?></span></td>
                <td class="td-center">
                    <?php if ($v['carte_professionnelle']): ?>
                    <span class="carte-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span>
                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                </td>
                <td class="td-archive">
                    <?php if ($v['zone_archive']): ?>
                    <span class="archive-pill"><?= htmlspecialchars($v['zone_archive']) ?></span>
                    <?php if ($v['box_rangement']): ?><br><small class="box-ref"><?= htmlspecialchars($v['box_rangement']) ?></small><?php endif; ?>
                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                </td>
                <td class="td-actions">
                    <button class="action-btn" title="Détail" onclick="openDrawer(<?= $v['id'] ?>)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <button class="action-btn" title="Modifier" onclick="openEditModal(<?= $v['id'] ?>)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(<?= $v['id'] ?>, '<?= htmlspecialchars(addslashes($v['numero_dossier']??'ce visa'),ENT_QUOTES) ?>')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="table-empty" id="tableEmpty" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
            <p>Aucun visa trouvé</p>
            <span>Modifiez vos filtres ou ajoutez un nouveau dossier</span>
        </div>
    </div>
</div>

</div><!-- /.page -->
</div><!-- /.sb-main -->

<!-- ══════════ DRAWER DÉTAIL ══════════ -->
<div class="drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()">
<div class="drawer" id="drawer" onclick="event.stopPropagation()">
    <div class="drawer-header">
        <div class="drawer-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div class="drawer-title">
            <h3 id="dTitle">Détail du visa</h3>
            <p id="dSub"></p>
        </div>
        <button class="drawer-close" onclick="closeDrawer()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="drawer-body" id="drawerBody"></div>
    <div class="drawer-footer">
        <button class="df-btn df-btn--primary" id="dBtnEdit">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Modifier
        </button>
        <button class="df-btn" onclick="closeDrawer()">Fermer</button>
    </div>
</div>
</div>

<!-- ══════════ MODAL FORMULAIRE ══════════ -->
<div class="modal-overlay" id="formOverlay">
<div class="modal-form">
    <div class="modal-form__header">
        <div class="mf-avatar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <h3 id="mfTitle">Nouveau visa</h3>
            <p id="mfSub">Remplissez les informations, puis ajoutez les produits concernés</p>
        </div>
        <button class="modal-close" onclick="closeFormModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="modal-form__body">
        <input type="hidden" id="fId">

        <!-- Section 1 : Infos générales -->
        <div class="mf-section">
            <div class="mf-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Informations générales
            </div>
            <div style="margin-bottom:14px">
                <label class="mf-label required">Type de visa</label>
                <div class="mf-select-wrap">
                    <select class="mf-select" id="fTypeVisa">
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($TYPES_VISA as $tv): ?>
                        <option value="<?= htmlspecialchars($tv) ?>"><?= htmlspecialchars($tv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mf-error" id="eTypeVisa"></div>
            </div>
            <div class="mf-row mf-row--2">
                <div>
                    <label class="mf-label">N° Dossier</label>
                    <input type="text" class="mf-input" id="fNumeroDossier" placeholder="Ex : VISA-2024-031">
                </div>
                <div>
                    <label class="mf-label required">Statut</label>
                    <div class="mf-select-wrap">
                        <select class="mf-select" id="fStatut">
                            <?php foreach ($STATUTS as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="mf-row mf-row--2" style="margin-top:14px">
                <div>
                    <label class="mf-label">Laboratoire</label>
                    <div class="mf-select-wrap">
                        <select class="mf-select" id="fLaboratoire">
                            <option value="">— Aucun —</option>
                            <?php foreach ($laboratoires as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nom']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mf-label">Date de décision</label>
                    <input type="date" class="mf-input" id="fDateDecision" onchange="updateHintRenouv()">
                    <div class="mf-hint" id="hintRenouv"></div>
                </div>
            </div>
        </div>

        <!-- Section 2 : Archivage -->
        <div class="mf-section">
            <div class="mf-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Archivage &amp; Options
            </div>
            <div class="mf-row mf-row--2">
                <div>
                    <label class="mf-label">Zone d'archive</label>
                    <div class="mf-select-wrap">
                        <select class="mf-select" id="fZoneArchive">
                            <option value="">— Aucune —</option>
                            <?php foreach ($SALLES as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mf-label">Box de rangement</label>
                    <input type="text" class="mf-input" id="fBoxRangement" placeholder="Ex : BOX-VIS-031">
                </div>
            </div>
            <div style="margin-top:14px">
                <label class="mf-toggle-wrap">
                    <input type="checkbox" id="fCarte">
                    <span class="mf-toggle"></span>
                    <span class="mf-toggle-label">Carte professionnelle délivrée</span>
                </label>
            </div>
            <div style="margin-top:14px">
                <label class="mf-label">Observations</label>
                <textarea class="mf-input" id="fObservations" rows="3" style="height:auto;padding:10px 12px;resize:vertical" placeholder="Remarques, motif de rejet, conditions particulières…"></textarea>
            </div>
        </div>

        <!-- Section 3 : Produits -->
        <div class="mf-section">
            <div class="mf-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                Produits associés
                <span class="prod-count-badge" id="prodCountBadge" style="display:none">0</span>
            </div>

            <div class="prod-add-bar">
                <div class="mf-select-wrap" style="flex:1;min-width:0">
                    <select class="mf-select" id="prodSelector" style="height:36px;font-size:.83rem">
                        <option value="">— Sélectionner un produit —</option>
                        <?php foreach ($produits as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                data-nom="<?= htmlspecialchars($p['nom_produit']) ?>"
                                data-forme="<?= htmlspecialchars($p['forme']??'') ?>"
                                data-dosage="<?= htmlspecialchars($p['dosage']??'') ?>"
                                data-lab="<?= (int)$p['laboratoire_id'] ?>">
                            <?= htmlspecialchars($p['nom_produit']) ?><?= $p['dosage']?' — '.$p['dosage']:'' ?><?= $p['forme']?' ('.$p['forme'].')':'' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="number" class="mf-input" id="prodQte" placeholder="Qté" min="0" style="width:76px;height:36px;text-align:center">
                <input type="text"   class="mf-input" id="prodUnite" placeholder="Unité" value="boîtes" style="width:95px;height:36px">
                <button class="btn-prod-add" onclick="addProduit()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajouter
                </button>
            </div>

            <div class="prod-list" id="prodList">
                <div class="prod-empty" id="prodEmpty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    Aucun produit — sélectionnez un produit ci-dessus et cliquez Ajouter
                </div>
            </div>
        </div>

    </div><!-- /.modal-form__body -->

    <div class="modal-form__footer">
        <button class="mf-btn mf-btn--cancel" onclick="closeFormModal()">Annuler</button>
        <button class="mf-btn mf-btn--save" id="btnSave" onclick="saveVisa()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Enregistrer
        </button>
    </div>
</div>
</div>

<!-- ══════════ MODAL SUPPRESSION ══════════ -->
<div class="modal-overlay" id="delOverlay">
<div class="modal-del">
    <div class="del-icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
    </div>
    <div class="del-title">Supprimer ce visa ?</div>
    <div class="del-msg" id="delMsg"></div>
    <div class="del-warn">Cette action est irréversible. Le dossier sera archivé.</div>
    <div class="del-actions">
        <button class="btn-cancel" onclick="closeDelModal()">Annuler</button>
        <button class="btn-del-confirm" id="btnConfirmDel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            Supprimer
        </button>
    </div>
</div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const ALL_VISAS    = <?= json_encode(array_values($visas),        JSON_UNESCAPED_UNICODE) ?>;
const ALL_PRODUITS = <?= json_encode(array_values($produits),     JSON_UNESCAPED_UNICODE) ?>;
const ALL_LABS     = <?= json_encode(array_values($laboratoires), JSON_UNESCAPED_UNICODE) ?>;
const API_URL      = '../api/api_visas.php';
</script>
<script src="../assets/js/visas.js"></script>

</body>
</html>