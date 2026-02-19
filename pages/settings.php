<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

if (($_SESSION['role'] ?? '') !== 'super_admin') {
    header('Location: ../index.php?toast=permission_denied'); exit;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

try {
    $pdo = getPDO();

    /* ── Liste utilisateurs avec agent lié ── */
    $users = $pdo->query("
        SELECT u.*,
               a.nom            AS agent_nom,
               a.prenom         AS agent_prenom,
               a.matricule      AS agent_matricule,
               a.poste          AS agent_poste,
               a.service        AS agent_service,
               cb.username      AS created_by_username
        FROM users u
        LEFT JOIN agents_dpm a ON a.id = u.agent_id
        LEFT JOIN users cb     ON cb.id = u.created_by
        ORDER BY u.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* ── TOUS les agents actifs (création + modification) ── */
    $allAgents = $pdo->query("
        SELECT a.id, a.matricule, a.nom, a.prenom, a.poste, a.service,
               u.id       AS taken_by_user_id,
               u.username AS taken_by_username
        FROM agents_dpm a
        LEFT JOIN users u ON u.agent_id = a.id
        WHERE a.deleted_at IS NULL
        ORDER BY a.nom, a.prenom
    ")->fetchAll(PDO::FETCH_ASSOC);

    /* ── Stats ── */
    $stats = $pdo->query("
        SELECT
            COUNT(*)                             AS total,
            SUM(is_active = 1)                   AS actifs,
            SUM(role IN ('super_admin','admin'))  AS admins,
            SUM(is_active = 0)                   AS inactifs
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $users = $allAgents = [];
    $stats = ['total'=>0,'actifs'=>0,'admins'=>0,'inactifs'=>0];
}

/* ── Préparer les données JS ── */
$usersJs = [];
foreach ($users as $u) {
    $usersJs[] = [
        'id'              => (int)$u['id'],
        'username'        => $u['username'],
        'email'           => $u['email'],
        'role'            => $u['role'],
        'is_active'       => (bool)$u['is_active'],
        'agent_id'        => $u['agent_id'] ? (int)$u['agent_id'] : null,
        'agent_nom'       => $u['agent_nom'] ?? '',
        'agent_prenom'    => $u['agent_prenom'] ?? '',
        'agent_matricule' => $u['agent_matricule'] ?? '',
        'agent_poste'     => $u['agent_poste'] ?? '',
        'last_login'      => $u['last_login'] ?? '',
        'failed_attempts' => (int)$u['failed_login_attempts'],
        'created_at'      => $u['created_at'] ?? '',
        'created_by_username' => $u['created_by_username'] ?? '',
        'is_self'         => ((int)$u['id'] === $currentUserId),
    ];
}

/* Tous les agents avec info "pris par qui" */
$allAgentsJs = [];
foreach ($allAgents as $a) {
    $allAgentsJs[] = [
        'id'                 => (int)$a['id'],
        'matricule'          => $a['matricule'],
        'nom'                => $a['nom'],
        'prenom'             => $a['prenom'],
        'poste'              => $a['poste'],
        'service'            => $a['service'],
        'taken_by_user_id'   => $a['taken_by_user_id'] ? (int)$a['taken_by_user_id'] : null,
        'taken_by_username'  => $a['taken_by_username'] ?? '',
    ];
}

$roles = [
    'super_admin' => 'Super Administrateur',
    'admin'       => 'Administrateur',
    'archiviste'  => 'Archiviste',
    'lecteur'     => 'Lecteur',
];

$toast = $_GET['toast'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Gestion des utilisateurs — DPM Archive</title>
    <link rel="icon" href="../assets/img/icons/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/settings.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="sb-main">

<header class="topbar">
    <button class="topbar__hamburger" onclick="window.sidebarToggleMobile&&window.sidebarToggleMobile()">
        <span></span><span></span><span></span>
    </button>
    <h1 class="topbar__title">Gestion des utilisateurs</h1>
    <span class="topbar__meta"><?= date('d/m/Y') ?></span>
</header>

<div class="page-inner">

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon stat-icon--total">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-val" id="statTotal"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-val" id="statActive"><?= $stats['actifs'] ?></div>
                <div class="stat-lbl">Actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--admin">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-val" id="statAdmins"><?= $stats['admins'] ?></div>
                <div class="stat-lbl">Admins</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--inactive">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
            </div>
            <div class="stat-info">
                <div class="stat-val" id="statInactive"><?= $stats['inactifs'] ?></div>
                <div class="stat-lbl">Inactifs</div>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar__left">
            <div class="search-wrap">
                <svg class="search-wrap__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="searchInput" class="search-wrap__input" placeholder="Rechercher par nom, email, rôle…">
            </div>
            <button class="btn-clear" id="btnClear" onclick="resetAll()" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Effacer
            </button>
            <div class="filter-pill-group">
                <button class="filter-pill active" data-filter="all">Tous</button>
                <button class="filter-pill" data-filter="actif">Actifs</button>
                <button class="filter-pill" data-filter="inactif">Inactifs</button>
                <button class="filter-pill" data-filter="super_admin">Super admin</button>
                <button class="filter-pill" data-filter="admin">Admin</button>
                <button class="filter-pill" data-filter="archiviste">Archiviste</button>
                <button class="filter-pill" data-filter="lecteur">Lecteur</button>
            </div>
        </div>
        <div class="toolbar__right">
            <button class="btn-add" onclick="openCreateModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nouvel utilisateur
            </button>
        </div>
    </div>

    <!-- Result count + pagination -->
    <div class="pagination-wrap">
        <span class="results-count" id="resultsCount"></span>
        <div id="pagination" class="pagination"></div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="sortable sort-asc" id="th_user" onclick="sortBy('username')">Utilisateur</th>
                    <th class="sortable" id="th_role" onclick="sortBy('role')">Rôle</th>
                    <th>Agent lié</th>
                    <th class="sortable" id="th_login" onclick="sortBy('last_login')">Dernière connexion</th>
                    <th>Statut</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody id="tableBody"></tbody>
        </table>
        <div id="emptyState" class="empty-state" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <p>Aucun utilisateur trouvé</p>
        </div>
    </div>

</div><!-- /page-inner -->
</div><!-- /sb-main -->

<!-- MODAL CRÉER / ÉDITER -->
<div class="modal-overlay" id="modalUser">
<div class="modal">
    <div class="modal-header">
        <div class="modal-header__left">
            <div class="modal-icon modal-icon--user" id="modalUserIcon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div>
                <div class="modal-title" id="modalUserTitle">Nouvel utilisateur</div>
                <div class="modal-sub"   id="modalUserSub">Créer un compte d'accès</div>
            </div>
        </div>
        <button class="modal-close" onclick="closeModal('modalUser')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="modal-body">
        <div class="modal-notif modal-notif--error" id="userFormError">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="userFormErrorMsg"></span>
        </div>
        <input type="hidden" id="editUserId" value="">

        <div class="field-row field-row--2">
            <div class="field">
                <label class="field__label required">Nom d'utilisateur</label>
                <input type="text" id="fUsername" class="field__input" placeholder="ex: j.dupont" autocomplete="off">
                <div class="field__error" id="errUsername"></div>
            </div>
            <div class="field">
                <label class="field__label required">Email</label>
                <input type="email" id="fEmail" class="field__input" placeholder="email@dpm.cg" autocomplete="off">
                <div class="field__error" id="errEmail"></div>
            </div>
        </div>

        <div class="field-row field-row--2">
            <div class="field">
                <label class="field__label required">Rôle</label>
                <select id="fRole" class="field__select">
                    <option value="lecteur">Lecteur</option>
                    <option value="archiviste">Archiviste</option>
                    <option value="admin">Administrateur</option>
                    <option value="super_admin">Super Administrateur</option>
                </select>
            </div>
            <div class="field">
                <label class="field__label">Agent lié</label>
                <select id="fAgentId" class="field__select">
                    <option value="">— Aucun —</option>
                </select>
            </div>
        </div>

        <!-- Info agent sélectionné -->
        <div id="agentInfo" style="display:none;margin-bottom:14px">
            <div class="agent-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div>
                    <div class="agent-card__text" id="agentInfoName"></div>
                    <div class="agent-card__sub"  id="agentInfoDetail"></div>
                </div>
            </div>
        </div>

        <div class="modal-sep"></div>

        <!-- Mot de passe (création seulement) -->
        <div id="pwdSection">
            <div class="field-row field-row--2">
                <div class="field">
                    <label class="field__label required" id="pwdLabel">Mot de passe</label>
                    <div class="pwd-wrap">
                        <input type="password" id="fPassword" class="field__input" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="pwd-toggle" onclick="togglePwd('fPassword',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="pwd-strength" id="pwdStrength" style="display:none">
                        <div class="pwd-strength__bar"><div class="pwd-strength__fill" id="pwdStrengthFill"></div></div>
                        <div class="pwd-strength__label" id="pwdStrengthLabel"></div>
                    </div>
                    <div class="field__error" id="errPassword"></div>
                </div>
                <div class="field">
                    <label class="field__label required" id="pwdConfirmLabel">Confirmer</label>
                    <div class="pwd-wrap">
                        <input type="password" id="fPasswordConfirm" class="field__input" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="pwd-toggle" onclick="togglePwd('fPasswordConfirm',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <div class="field__error" id="errPasswordConfirm"></div>
                </div>
            </div>
        </div>

        <div class="modal-sep" id="activeSep"></div>

        <div class="toggle-row">
            <div>
                <div class="toggle-row__label">Compte actif</div>
                <div class="toggle-row__sub">L'utilisateur peut se connecter</div>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" id="fIsActive" checked>
                <span class="toggle-track"></span>
            </label>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-modal-cancel" onclick="closeModal('modalUser')">Annuler</button>
        <button class="btn-modal-primary" id="btnUserSave" onclick="saveUser()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
            Enregistrer
        </button>
    </div>
</div>
</div>

<!-- MODAL MOT DE PASSE -->
<div class="modal-overlay" id="modalPwd">
<div class="modal">
    <div class="modal-header">
        <div class="modal-header__left">
            <div class="modal-icon modal-icon--pwd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </div>
            <div>
                <div class="modal-title">Changer le mot de passe</div>
                <div class="modal-sub" id="pwdModalSub">—</div>
            </div>
        </div>
        <button class="modal-close" onclick="closeModal('modalPwd')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="modal-body">
        <div class="modal-notif modal-notif--error" id="pwdFormError">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span id="pwdFormErrorMsg"></span>
        </div>
        <input type="hidden" id="pwdUserId">
        <div class="field-row field-row--2">
            <div class="field">
                <label class="field__label required">Nouveau mot de passe</label>
                <div class="pwd-wrap">
                    <input type="password" id="fNewPwd" class="field__input" placeholder="••••••••" autocomplete="new-password">
                    <button type="button" class="pwd-toggle" onclick="togglePwd('fNewPwd',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="pwd-strength" id="newPwdStrength" style="display:none">
                    <div class="pwd-strength__bar"><div class="pwd-strength__fill" id="newPwdFill"></div></div>
                    <div class="pwd-strength__label" id="newPwdLabel"></div>
                </div>
                <div class="field__error" id="errNewPwd"></div>
            </div>
            <div class="field">
                <label class="field__label required">Confirmer</label>
                <div class="pwd-wrap">
                    <input type="password" id="fNewPwdConfirm" class="field__input" placeholder="••••••••" autocomplete="new-password">
                    <button type="button" class="pwd-toggle" onclick="togglePwd('fNewPwdConfirm',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <div class="field__error" id="errNewPwdConfirm"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-modal-cancel" onclick="closeModal('modalPwd')">Annuler</button>
        <!-- Icône cadenas modifiée par l'utilisateur -->
        <button class="btn-modal-warning" id="btnPwdSave" onclick="savePwd()">
            <svg class="icon-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                <rect x="5" y="11" width="14" height="10" rx="2" />
                <path d="M7 11V7a5 5 0 0 1 9.9-1" />
            </svg>
            Mettre à jour
        </button>
    </div>
</div>
</div>

<!-- MODAL SUPPRESSION -->
<div class="modal-overlay" id="modalDel">
<div class="modal" style="max-width:420px">
    <div class="modal-header">
        <div class="modal-header__left">
            <div class="modal-icon modal-icon--danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
            </div>
            <div>
                <div class="modal-title">Supprimer l'utilisateur</div>
                <div class="modal-sub" id="delModalSub">Cette action est irréversible.</div>
            </div>
        </div>
        <button class="modal-close" onclick="closeModal('modalDel')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
    <div class="modal-body" style="padding-top:14px">
        <div class="modal-notif modal-notif--error show" style="margin-bottom:0" id="delMsg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span id="delMsgText">L'utilisateur sera définitivement supprimé.</span>
        </div>
    </div>
    <div class="modal-footer">
        <input type="hidden" id="delUserId">
        <button class="btn-modal-cancel" onclick="closeModal('modalDel')">Annuler</button>
        <button class="btn-modal-danger" id="btnDelConfirm" onclick="confirmDelete()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
            Supprimer
        </button>
    </div>
</div>
</div>

<div id="toastWrap" class="toast-wrap"></div>

<script src="../assets/js/sidebar.js"></script>
<script>
/* ── Données PHP → JS ── */
let ALL_USERS   = <?= json_encode(array_values($usersJs),    JSON_UNESCAPED_UNICODE) ?>;
const ALL_AGENTS= <?= json_encode(array_values($allAgentsJs), JSON_UNESCAPED_UNICODE) ?>;
const ROLES     = <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>;
const CURRENT_ID= <?= $currentUserId ?>;
const API_URL   = '../api/settings.php';

/* ── État ── */
let filtered    = [...ALL_USERS];
let currentPage = 1;
let perPage     = 15;
let sortKey     = 'username';
let sortDir     = 1;
let activeFilter= 'all';

/* ── Helpers ── */
function initials(str) {
    return (str||'?').trim().split(/\s+/).slice(0,2).map(w=>w[0]).join('').toUpperCase()||'?';
}
function avatarColor(role) {
    return { super_admin:'#9D174D', admin:'#D97706', archiviste:'#0284C7', lecteur:'#64748B' }[role] || '#64748B';
}
function roleBadge(role) {
    const labels = { super_admin:'Super Admin', admin:'Admin', archiviste:'Archiviste', lecteur:'Lecteur' };
    return `<span class="role-badge role-badge--${role}">${labels[role]||role}</span>`;
}
function fmtDate(d) {
    if (!d) return '<span class="last-login last-login--never">Jamais</span>';
    const dt   = new Date(d);
    const diff = (Date.now() - dt) / 1000;
    if (diff < 3600)   return `<span class="last-login">Il y a ${Math.round(diff/60)} min</span>`;
    if (diff < 86400)  return `<span class="last-login">Il y a ${Math.round(diff/3600)} h</span>`;
    if (diff < 604800) return `<span class="last-login">Il y a ${Math.round(diff/86400)} j</span>`;
    return `<span class="last-login">${dt.toLocaleDateString('fr-FR')}</span>`;
}

/* ── Filtres & tri ── */
document.querySelectorAll('.filter-pill').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeFilter = btn.dataset.filter;
        applyFilters();
    });
});
document.getElementById('searchInput').addEventListener('input', applyFilters);

function applyFilters() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    filtered = ALL_USERS.filter(u => {
        if (q) {
            const hay = [u.username,u.email,u.role,u.agent_nom,u.agent_prenom,u.agent_matricule].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (activeFilter === 'actif'   && !u.is_active)  return false;
        if (activeFilter === 'inactif' &&  u.is_active)  return false;
        if (['super_admin','admin','archiviste','lecteur'].includes(activeFilter) && u.role !== activeFilter) return false;
        return true;
    });
    filtered.sort((a,b) => {
        let av = a[sortKey]||'', bv = b[sortKey]||'';
        if (typeof av === 'boolean') { av=av?1:0; bv=bv?1:0; }
        if (typeof av === 'string')  { av=av.toLowerCase(); bv=bv.toLowerCase(); }
        return av < bv ? -sortDir : av > bv ? sortDir : 0;
    });
    currentPage = 1;
    renderTable();
    renderPagination();
    document.getElementById('resultsCount').innerHTML = `<strong>${filtered.length}</strong> résultat${filtered.length!==1?'s':''}`;
    // Afficher/cacher le bouton Effacer
    const dirty = q !== '' || activeFilter !== 'all';
    document.getElementById('btnClear').style.display = dirty ? '' : 'none';
}

function resetAll() {
    document.getElementById('searchInput').value = '';
    activeFilter = 'all';
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    document.querySelector('.filter-pill[data-filter="all"]').classList.add('active');
    applyFilters();
}

function sortBy(key) {
    sortDir = (sortKey===key) ? -sortDir : 1;
    sortKey = key;
    document.querySelectorAll('thead th').forEach(th => th.classList.remove('sort-asc','sort-desc'));
    const thMap = { username:'th_user', role:'th_role', last_login:'th_login' };
    if (thMap[key]) document.getElementById(thMap[key])?.classList.add(sortDir===1?'sort-asc':'sort-desc');
    applyFilters();
}

/* ── Rendu table ── */
function renderTable() {
    const tbody = document.getElementById('tableBody');
    const empty = document.getElementById('emptyState');
    const start = (currentPage-1)*perPage;
    const page  = filtered.slice(start, start+perPage);

    if (!filtered.length) { tbody.innerHTML=''; empty.style.display=''; return; }
    empty.style.display = 'none';

    tbody.innerHTML = page.map(u => {
        const agentLabel = u.agent_nom
            ? `<div class="td-agent">${u.agent_prenom} ${u.agent_nom}</div>`
            : '<span style="color:var(--gray-300);font-size:.78rem">—</span>';

        const statusHtml = u.is_active
            ? '<span class="status-dot status-dot--active">Actif</span>'
            : '<span class="status-dot status-dot--inactive">Inactif</span>';

        const selfBadge = u.is_self
            ? ' <span style="font-size:.65rem;background:#DCFCE7;color:#065F46;padding:1px 6px;border-radius:50px;font-weight:700;vertical-align:middle">Vous</span>'
            : '';

        return `<tr>
            <td>
                <div class="td-user">
                    <div class="td-avatar" style="background:${avatarColor(u.role)}22;color:${avatarColor(u.role)}">${initials(u.username)}</div>
                    <div>
                        <div class="td-name">${u.username}${selfBadge}</div>
                        <div class="td-email">${u.email}</div>
                    </div>
                </div>
            </td>
            <td>${roleBadge(u.role)}</td>
            <td>${agentLabel}</td>
            <td>${fmtDate(u.last_login)}</td>
            <td>${statusHtml}</td>
            <td>
                <div class="td-actions">
                    <button class="action-btn" title="Modifier" onclick="openEditModal(${u.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <button class="action-btn action-btn--warn" title="Mot de passe" onclick="openPwdModal(${u.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </button>
                    ${!u.is_self ? `<button class="action-btn action-btn--danger" title="Supprimer" onclick="openDelModal(${u.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg>
                    </button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

/* ── Pagination ── */
function renderPagination() {
    const pag   = document.getElementById('pagination');
    const total = Math.ceil(filtered.length/perPage);
    if (total<=1) { pag.innerHTML=''; return; }
    let html = `<button class="pag-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹</button>`;
    for (let i=1;i<=total;i++) {
        if (i===1||i===total||Math.abs(i-currentPage)<=2)
            html+=`<button class="pag-btn ${i===currentPage?'active':''}" onclick="goPage(${i})">${i}</button>`;
        else if (Math.abs(i-currentPage)===3)
            html+=`<span class="pag-ellipsis">…</span>`;
    }
    html+=`<button class="pag-btn" onclick="goPage(${currentPage+1})" ${currentPage===total?'disabled':''}>›</button>`;
    pag.innerHTML = html;
}
function goPage(n) {
    const t = Math.ceil(filtered.length/perPage);
    if (n<1||n>t) return;
    currentPage=n; renderTable(); renderPagination();
    document.querySelector('.table-wrap')?.scrollIntoView({behavior:'smooth',block:'start'});
}
function changePerPage(v) { perPage=parseInt(v)||15; currentPage=1; renderTable(); renderPagination(); }

/* ── Modals utils ── */
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow='hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow=''; }

document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if(e.target===o) closeModal(o.id); });
});
document.addEventListener('keydown', e => {
    if (e.key==='Escape') document.querySelectorAll('.modal-overlay.open').forEach(m=>closeModal(m.id));
});

function showModalError(elId, msgId, msg) {
    document.getElementById(msgId).textContent = msg;
    document.getElementById(elId).classList.add('show');
}
function hideModalError(elId) { document.getElementById(elId)?.classList.remove('show'); }

/* ── Toggle mot de passe visible ── */
function togglePwd(inputId, btn) {
    const input = document.getElementById(inputId);
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    btn.innerHTML = show
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
}

/* ── Force mot de passe ── */
function checkPwdStrength(pwd, wrapId, fillId, labelId) {
    const wrap = document.getElementById(wrapId);
    if (!pwd) { wrap.style.display='none'; return; }
    wrap.style.display='';
    let score = 0;
    if (pwd.length >= 8)  score++;
    if (pwd.length >= 12) score++;
    if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
    if (/[0-9]/.test(pwd)) score++;
    if (/[^A-Za-z0-9]/.test(pwd)) score++;
    const levels = [{cls:'',label:''},{cls:'weak',label:'Faible'},{cls:'fair',label:'Moyen'},{cls:'good',label:'Bon'},{cls:'strong',label:'Fort'}];
    const lvl = levels[Math.min(score,4)];
    wrap.className = `pwd-strength pwd-strength--${lvl.cls}`;
    document.getElementById(labelId).textContent = lvl.label;
}
document.getElementById('fPassword').addEventListener('input', function() {
    checkPwdStrength(this.value,'pwdStrength','pwdStrengthFill','pwdStrengthLabel');
});
document.getElementById('fNewPwd').addEventListener('input', function() {
    checkPwdStrength(this.value,'newPwdStrength','newPwdFill','newPwdLabel');
});

/* ══════════════════════════════════════
   SELECT AGENT — logique corrigée
   Affiche TOUS les agents actifs.
   En création  : agents déjà pris → disabled
   En édition   : agent de l'user courant → sélectionné et activé
                  autres déjà pris → disabled
══════════════════════════════════════ */
function populateAgentSelect(selectEl, currentUserId, currentAgentId) {
    selectEl.innerHTML = '<option value="">— Aucun —</option>';

    ALL_AGENTS.forEach(a => {
        const opt  = document.createElement('option');
        opt.value  = a.id;

        // L'agent est pris par un AUTRE user (pas celui qu'on édite)
        const takenByOther = a.taken_by_user_id && a.taken_by_user_id !== currentUserId;

        if (takenByOther) {
            opt.textContent = `${a.prenom} ${a.nom}  (lié à ${a.taken_by_username})`;
            opt.disabled    = true;
            opt.style.color = '#94A3B8';
        } else {
            opt.textContent = `${a.prenom} ${a.nom}`;
        }

        if (currentAgentId && a.id === currentAgentId) opt.selected = true;
        selectEl.appendChild(opt);
    });
}

document.getElementById('fAgentId').addEventListener('change', function() {
    const id   = parseInt(this.value)||0;
    const info = document.getElementById('agentInfo');
    if (!id) { info.style.display='none'; return; }
    const a = ALL_AGENTS.find(x=>x.id===id);
    if (a) {
        document.getElementById('agentInfoName').textContent   = `${a.prenom} ${a.nom} — ${a.matricule}`;
        document.getElementById('agentInfoDetail').textContent = a.poste||'';
        info.style.display = '';
    }
});

/* ── Modal Créer ── */
function openCreateModal() {
    document.getElementById('editUserId').value = '';
    document.getElementById('fUsername').value  = '';
    document.getElementById('fEmail').value     = '';
    document.getElementById('fRole').value      = 'lecteur';
    document.getElementById('fPassword').value  = '';
    document.getElementById('fPasswordConfirm').value = '';
    document.getElementById('fIsActive').checked = true;
    document.getElementById('agentInfo').style.display  = 'none';
    document.getElementById('pwdSection').style.display = '';
    document.getElementById('pwdStrength').style.display = 'none';
    document.getElementById('modalUserTitle').textContent = 'Nouvel utilisateur';
    document.getElementById('modalUserSub').textContent   = 'Créer un compte d\'accès';
    document.getElementById('modalUserIcon').className    = 'modal-icon modal-icon--user';
    document.getElementById('btnUserSave').innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Créer`;
    ['errUsername','errEmail','errPassword','errPasswordConfirm'].forEach(id => { document.getElementById(id).textContent=''; });
    hideModalError('userFormError');

    // Création : null comme userId courant → tous les pris seront disabled
    populateAgentSelect(document.getElementById('fAgentId'), null, null);
    openModal('modalUser');
    setTimeout(() => document.getElementById('fUsername').focus(), 100);
}

/* ── Modal Éditer ── */
function openEditModal(id) {
    const u = ALL_USERS.find(x=>x.id===id);
    if (!u) return;

    document.getElementById('editUserId').value  = u.id;
    document.getElementById('fUsername').value   = u.username;
    document.getElementById('fEmail').value      = u.email;
    document.getElementById('fRole').value       = u.role;
    document.getElementById('fIsActive').checked = u.is_active;
    document.getElementById('pwdSection').style.display = 'none';
    document.getElementById('activeSep').style.display  = '';
    document.getElementById('modalUserTitle').textContent = `Modifier — ${u.username}`;
    document.getElementById('modalUserSub').textContent   = u.email;
    document.getElementById('modalUserIcon').className    = 'modal-icon modal-icon--edit';
    document.getElementById('btnUserSave').innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Enregistrer`;
    ['errUsername','errEmail','errPassword','errPasswordConfirm'].forEach(id => { document.getElementById(id).textContent=''; });
    hideModalError('userFormError');

    // Édition : passer l'id de l'user pour débloquer son propre agent
    populateAgentSelect(document.getElementById('fAgentId'), u.id, u.agent_id);

    const info = document.getElementById('agentInfo');
    if (u.agent_id) {
        document.getElementById('agentInfoName').textContent   = `${u.agent_prenom} ${u.agent_nom} — ${u.agent_matricule}`;
        document.getElementById('agentInfoDetail').textContent = u.agent_poste||'';
        info.style.display = '';
    } else {
        info.style.display = 'none';
    }

    openModal('modalUser');
    setTimeout(() => document.getElementById('fUsername').focus(), 100);
}

/* ── Save user ── */
async function saveUser() {
    const editId   = parseInt(document.getElementById('editUserId').value)||0;
    const isCreate = !editId;
    const username = document.getElementById('fUsername').value.trim();
    const email    = document.getElementById('fEmail').value.trim();
    const role     = document.getElementById('fRole').value;
    const agentId  = parseInt(document.getElementById('fAgentId').value)||null;
    const isActive = document.getElementById('fIsActive').checked;
    const pwd      = document.getElementById('fPassword').value;
    const pwdCfm   = document.getElementById('fPasswordConfirm').value;

    let valid = true;
    const setErr = (id, msg) => { document.getElementById(id).textContent=msg; if(msg) valid=false; };
    setErr('errUsername', !username?'Champ obligatoire':username.length<3?'Minimum 3 caractères':'');
    setErr('errEmail',    !email?'Champ obligatoire':!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)?'Email invalide':'');
    if (isCreate) {
        setErr('errPassword',        !pwd?'Champ obligatoire':pwd.length<8?'Minimum 8 caractères':'');
        setErr('errPasswordConfirm', !pwdCfm?'Champ obligatoire':pwd!==pwdCfm?'Les mots de passe ne correspondent pas':'');
    }
    if (!valid) return;

    const btn = document.getElementById('btnUserSave');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Enregistrement…`;

    try {
        const payload = { action:isCreate?'create':'update', username, email, role, agent_id:agentId, is_active:isActive };
        if (isCreate)  payload.password = pwd;
        if (!isCreate) payload.id = editId;

        const res  = await fetch(API_URL, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Erreur inconnue');

        if (isCreate) {
            ALL_USERS.unshift(data.user);
        } else {
            const idx = ALL_USERS.findIndex(u=>u.id===editId);
            if (idx!==-1) ALL_USERS[idx] = data.user;
        }
        closeModal('modalUser');
        applyFilters();
        updateStats();
        toast(isCreate?'Utilisateur créé avec succès':'Modifications enregistrées','success');
    } catch(e) {
        showModalError('userFormError','userFormErrorMsg',e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Enregistrer`;
    }
}

/* ── Modal mot de passe ── */
function openPwdModal(id) {
    const u = ALL_USERS.find(x=>x.id===id);
    if (!u) return;
    document.getElementById('pwdUserId').value   = id;
    document.getElementById('pwdModalSub').textContent = u.username+' — '+u.email;
    document.getElementById('fNewPwd').value        = '';
    document.getElementById('fNewPwdConfirm').value = '';
    document.getElementById('newPwdStrength').style.display = 'none';
    document.getElementById('errNewPwd').textContent = '';
    document.getElementById('errNewPwdConfirm').textContent = '';
    hideModalError('pwdFormError');
    openModal('modalPwd');
    setTimeout(()=>document.getElementById('fNewPwd').focus(),100);
}

async function savePwd() {
    const id  = parseInt(document.getElementById('pwdUserId').value)||0;
    const pwd = document.getElementById('fNewPwd').value;
    const cfm = document.getElementById('fNewPwdConfirm').value;

    let valid = true;
    const setErr = (elId,msg) => { document.getElementById(elId).textContent=msg; if(msg) valid=false; };
    setErr('errNewPwd',        !pwd?'Champ obligatoire':pwd.length<8?'Minimum 8 caractères':'');
    setErr('errNewPwdConfirm', !cfm?'Champ obligatoire':pwd!==cfm?'Les mots de passe ne correspondent pas':'');
    if (!valid) return;

    const btn = document.getElementById('btnPwdSave');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Mise à jour…`;

    try {
        const res  = await fetch(API_URL, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'change_password',id,password:pwd})});
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Erreur inconnue');
        closeModal('modalPwd');
        toast('Mot de passe mis à jour avec succès','success');
    } catch(e) {
        showModalError('pwdFormError','pwdFormErrorMsg',e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" style="width:15px;height:15px"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg> Mettre à jour`;
    }
}

/* ── Modal suppression ── */
function openDelModal(id) {
    const u = ALL_USERS.find(x=>x.id===id);
    if (!u) return;
    document.getElementById('delUserId').value    = id;
    document.getElementById('delMsgText').textContent = `« ${u.username} » (${u.email}) sera définitivement supprimé. Cette action est irréversible.`;
    openModal('modalDel');
}

async function confirmDelete() {
    const id  = parseInt(document.getElementById('delUserId').value)||0;
    const btn = document.getElementById('btnDelConfirm');
    btn.disabled = true;
    btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg> Suppression…`;

    try {
        const res  = await fetch(API_URL, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id})});
        const data = await res.json();
        if (!data.success) throw new Error(data.message||'Erreur inconnue');
        const idx = ALL_USERS.findIndex(u=>u.id===id);
        if (idx!==-1) ALL_USERS.splice(idx,1);
        closeModal('modalDel');
        applyFilters();
        updateStats();
        toast('Utilisateur supprimé','success');
    } catch(e) {
        toast(e.message||'Erreur réseau','error');
        closeModal('modalDel');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M9 6V4h6v2"/></svg> Supprimer`;
    }
}

/* ── Stats ── */
function updateStats() {
    document.getElementById('statTotal').textContent    = ALL_USERS.length;
    document.getElementById('statActive').textContent   = ALL_USERS.filter(u=>u.is_active).length;
    document.getElementById('statAdmins').textContent   = ALL_USERS.filter(u=>['super_admin','admin'].includes(u.role)).length;
    document.getElementById('statInactive').textContent = ALL_USERS.filter(u=>!u.is_active).length;
}

/* ── Toast ── */
function toast(msg, type='default') {
    const wrap = document.getElementById('toastWrap');
    const el   = document.createElement('div');
    const ok   = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;
    const err  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`;
    el.className = `toast toast--${type}`;
    el.innerHTML = `${type==='success'?ok:err}${msg}`;
    wrap.appendChild(el);
    setTimeout(()=>{ el.classList.add('out'); el.addEventListener('animationend',()=>el.remove()); }, 3500);
}

<?php if ($toast === 'permission_denied'): ?>
window.addEventListener('DOMContentLoaded', () => toast('Accès refusé','error'));
<?php endif; ?>

applyFilters();
</script>
</body>
</html>