<?php
/**
 * SETTINGS - Gestion des utilisateurs
 * pages/settings.php
 */

require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
requireRole('super_admin');

$pdo = getPDO();

// Récupérer tous les utilisateurs avec infos agent
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.username,
        u.email,
        u.role,
        u.is_active,
        u.failed_login_attempts,
        u.last_login,
        u.created_at,
        u.permissions,
        u.agent_id,
        a.nom,
        a.prenom,
        a.matricule,
        a.service,
        a.poste,
        a.statut_emploi,
        a.telephone_principal
    FROM users u
    LEFT JOIN agents_dpm a ON u.agent_id = a.id
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();

// Agents sans compte utilisateur
$stmtAgents = $pdo->prepare("
    SELECT
        a.id,
        a.matricule,
        a.nom,
        a.prenom,
        a.service,
        a.poste,
        a.statut_emploi
    FROM agents_dpm a
    LEFT JOIN users u ON u.agent_id = a.id
    WHERE u.id IS NULL
    AND a.deleted_at IS NULL
    AND a.statut_emploi = 'Actif'
    ORDER BY a.nom ASC
");
$stmtAgents->execute();
$agentsSansCompte = $stmtAgents->fetchAll();

// Statistiques
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active'] == 1));
$lockedUsers = count(array_filter($users, fn($u) => $u['failed_login_attempts'] >= 3));
$superAdmins = count(array_filter($users, fn($u) => $u['role'] === 'super_admin'));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs - DPM Archive</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/icons/favicon.ico">
    <link rel="stylesheet" href="../assets/css/variables.css">
    <link rel="stylesheet" href="../assets/css/reset.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <link rel="stylesheet" href="../assets/css/layout.css">
    <link rel="stylesheet" href="../assets/css/components.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/tables.css">
    <link rel="stylesheet" href="../assets/css/modals.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="stylesheet" href="../assets/css/settings.css">
</head>

<body>

    <div class="preloader" id="preloader">
        <div class="preloader__spinner"></div>
        <p class="preloader__text">Chargement...</p>
    </div>

    <div class="dashboard-layout" id="appLayout">

        <?php include '../includes/sidebar.php'; ?>
        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header__left">
                    <h1 class="page-title">
                        <span class="page-title__icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg>
                        </span>
                        Gestion des utilisateurs
                    </h1>
                    <p class="page-subtitle">Gérez les comptes, rôles et permissions des agents DPM</p>
                </div>
                <div class="page-header__right">
                    <button class="btn btn-primary" id="btnAddUser">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="8.5" cy="7" r="4" />
                            <line x1="20" y1="8" x2="20" y2="14" />
                            <line x1="23" y1="11" x2="17" y2="11" />
                        </svg>
                        Nouvel utilisateur
                    </button>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $totalUsers ?></span>
                        <span class="stat-card__label">Total utilisateurs</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--success">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                            <polyline points="22 4 12 14.01 9 11.01" />
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $activeUsers ?></span>
                        <span class="stat-card__label">Comptes actifs</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--danger">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $lockedUsers ?></span>
                        <span class="stat-card__label">Comptes bloqués</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon
                                points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value"><?= $superAdmins ?></span>
                        <span class="stat-card__label">Super admins</span>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="card">
                <div class="filters-bar">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" id="searchUsers" placeholder="Rechercher un utilisateur..."
                            class="search-input">
                    </div>
                    <div class="filters-group">
                        <select id="filterRole" class="form-select filter-select">
                            <option value="">Tous les rôles</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="archiviste">Archiviste</option>
                            <option value="lecteur">Lecteur</option>
                        </select>
                        <select id="filterStatus" class="form-select filter-select">
                            <option value="">Tous les statuts</option>
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                        <button class="btn btn-outline" id="btnResetFilters">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <polyline points="1 4 1 10 7 10" />
                                <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                            </svg>
                            Réinitialiser
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tableau -->
            <div class="card">
                <div class="card__header">
                    <h2 class="card__title">Liste des utilisateurs</h2>
                    <span class="badge badge-primary" id="userCount"><?= $totalUsers ?> utilisateurs</span>
                </div>
                <div class="table-responsive">
                    <table class="table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Agent lié</th>
                                <th>Service / Poste</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Dernière connexion</th>
                                <th class="th-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($users as $user): ?>
                                <tr class="user-row" data-role="<?= htmlspecialchars($user['role']) ?>"
                                    data-status="<?= $user['is_active'] ?>" data-search="<?= strtolower(htmlspecialchars(
                                          $user['username'] . ' ' .
                                          $user['email'] . ' ' .
                                          ($user['nom'] ?? '') . ' ' .
                                          ($user['prenom'] ?? '') . ' ' .
                                          ($user['matricule'] ?? '')
                                      )) ?>">

                                    <!-- Utilisateur -->
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar user-avatar--<?= $user['role'] ?>">
                                                <?= strtoupper(substr($user['username'], 0, 2)) ?>
                                            </div>
                                            <div class="user-details">
                                                <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
                                                <span class="user-email"><?= htmlspecialchars($user['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Agent lié -->
                                    <td>
                                        <?php if ($user['nom']): ?>
                                            <div class="agent-info">
                                                <span class="agent-name">
                                                    <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>
                                                </span>
                                                <span class="agent-matricule">
                                                    <?= htmlspecialchars($user['matricule']) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">— Non lié</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Service / Poste -->
                                    <td>
                                        <?php if ($user['service']): ?>
                                            <div class="agent-info">
                                                <span class="agent-name" style="font-size:0.8rem">
                                                    <?= htmlspecialchars($user['poste'] ?? '') ?>
                                                </span>
                                                <span class="agent-matricule" style="font-size:0.72rem">
                                                    <?= htmlspecialchars(substr($user['service'], 0, 40)) . (strlen($user['service']) > 40 ? '…' : '') ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Rôle -->
                                    <td>
                                        <span class="role-badge role-badge--<?= $user['role'] ?>">
                                            <?= match ($user['role']) {
                                                'super_admin' => 'Super Admin',
                                                'admin' => 'Admin',
                                                'archiviste' => 'Archiviste',
                                                'lecteur' => 'Lecteur',
                                                default => $user['role']
                                            } ?>
                                        </span>
                                    </td>

                                    <!-- Statut -->
                                    <td>
                                        <?php if ($user['failed_login_attempts'] >= 3): ?>
                                            <span class="status-badge status-badge--locked">
                                                <span class="status-dot"></span> Bloqué
                                            </span>
                                        <?php elseif ($user['is_active']): ?>
                                            <span class="status-badge status-badge--active">
                                                <span class="status-dot"></span> Actif
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-badge--inactive">
                                                <span class="status-dot"></span> Inactif
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Dernière connexion -->
                                    <td>
                                        <span class="date-text">
                                            <?= $user['last_login']
                                                ? date('d/m/Y H:i', strtotime($user['last_login']))
                                                : '— Jamais' ?>
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="actions-group">

                                            <button class="action-btn action-btn--info" title="Voir les permissions"
                                                onclick="viewPermissions(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', <?= htmlspecialchars($user['permissions'] ?? '{}') ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                            </button>

                                            <button class="action-btn action-btn--edit" title="Modifier"
                                                onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>

                                            <button class="action-btn action-btn--warning"
                                                title="Réinitialiser le mot de passe"
                                                onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                                </svg>
                                            </button>

                                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>

                                                <button
                                                    class="action-btn <?= $user['is_active'] ? 'action-btn--toggle-off' : 'action-btn--toggle-on' ?>"
                                                    title="<?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>"
                                                    onclick="toggleUser(<?= $user['id'] ?>, <?= $user['is_active'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                    <?php if ($user['is_active']): ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7" />
                                                            <circle cx="16" cy="12" r="3" fill="currentColor" />
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="1" y="5" width="22" height="14" rx="7" ry="7" />
                                                            <circle cx="8" cy="12" r="3" fill="currentColor" />
                                                        </svg>
                                                    <?php endif; ?>
                                                </button>

                                                <?php if ($user['failed_login_attempts'] >= 3): ?>
                                                    <button class="action-btn action-btn--unlock" title="Débloquer le compte"
                                                        onclick="unlockUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                                            <path d="M7 11V7a5 5 0 0 1 9.9-1" />
                                                        </svg>
                                                    </button>
                                                <?php endif; ?>

                                                <button class="action-btn action-btn--delete" title="Supprimer"
                                                    onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                        <path d="M10 11v6M14 11v6" />
                                                        <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                                                    </svg>
                                                </button>

                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>


    <!-- MODAL : CRÉER / MODIFIER UTILISATEUR -->
    <div class="modal-overlay" id="modalUser">
        <div class="modal modal--lg">
            <div class="modal__header">
                <h2 class="modal__title" id="modalUserTitle">Nouvel utilisateur</h2>
                <button class="modal__close" onclick="closeModal('modalUser')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <form id="formUser" autocomplete="off">
                <input type="hidden" id="userId" name="user_id" value="">
                <input type="hidden" id="userAction" name="action" value="create">
                <div class="modal__body">
                    <div class="form-grid form-grid--2">

                        <!-- Username -->
                        <div class="form-group">
                            <label class="form-label form-label--required">Nom d'utilisateur</label>
                            <div class="input-container">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                                <input type="text" id="inputUsername" name="username" class="form-input"
                                    placeholder="ex: jp.moukoko" required>
                            </div>
                            <span class="form-error" id="errUsername"></span>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label form-label--required">Email</label>
                            <div class="input-container">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path
                                        d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                    <polyline points="22,6 12,13 2,6" />
                                </svg>
                                <input type="email" id="inputEmail" name="email" class="form-input"
                                    placeholder="prenom.nom@dpm.cg" required>
                            </div>
                            <span class="form-error" id="errEmail"></span>
                        </div>

                        <!-- Agent lié -->
                        <div class="form-group">
                            <label class="form-label">Agent DPM lié</label>
                            <div class="input-container">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                                </svg>
                                <select id="inputAgentId" name="agent_id" class="form-select">
                                    <option value="">— Aucun agent lié —</option>
                                    <?php foreach ($agentsSansCompte as $agent): ?>
                                        <option value="<?= $agent['id'] ?>"
                                            data-service="<?= htmlspecialchars($agent['service'] ?? '') ?>"
                                            data-poste="<?= htmlspecialchars($agent['poste'] ?? '') ?>">
                                            <?= htmlspecialchars($agent['matricule'] . ' — ' . $agent['prenom'] . ' ' . $agent['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Rôle -->
                        <div class="form-group">
                            <label class="form-label form-label--required">Rôle</label>
                            <div class="input-container">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <polygon
                                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                                </svg>
                                <select id="inputRole" name="role" class="form-select" required
                                    onchange="updatePermissionsFromRole(this.value)">
                                    <option value="lecteur">Lecteur</option>
                                    <option value="archiviste">Archiviste</option>
                                    <option value="admin">Admin</option>
                                    <option value="super_admin">Super Admin</option>
                                </select>
                            </div>
                        </div>

                        <!-- Mot de passe -->
                        <div class="form-group" id="passwordGroup">
                            <label class="form-label form-label--required">Mot de passe</label>
                            <div class="input-container">
                                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                                <input type="password" id="inputPassword" name="password" class="form-input"
                                    placeholder="Min. 8 caractères">
                                <span class="input-icon input-icon--right password-toggle"
                                    data-target="inputPassword"></span>
                            </div>
                            <span class="form-error" id="errPassword"></span>
                        </div>

                        <!-- Statut -->
                        <div class="form-group form-group--center">
                            <label class="form-label">Statut du compte</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="inputIsActive" name="is_active" value="1" checked>
                                <span class="toggle-switch__slider"></span>
                                <span class="toggle-switch__label" id="toggleLabel">Compte actif</span>
                            </label>
                        </div>

                    </div>

                    <!-- Permissions -->
                    <div class="permissions-section">
                        <div class="permissions-header">
                            <h3 class="permissions-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                                Permissions par module
                            </h3>
                            <div class="permissions-actions">
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="toggleAllPermissions(true)">Tout autoriser</button>
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="toggleAllPermissions(false)">Tout refuser</button>
                            </div>
                        </div>
                        <div class="permissions-grid" id="permissionsGrid">
                            <?php
                            $modules = [
                                'courriers' => 'Courriers',
                                'visas' => 'Visas',
                                'pharmacies' => 'Pharmacies',
                                'agences' => 'Agences',
                                'depots' => 'Dépôts',
                                'laboratoires' => 'Laboratoires',
                                'medicaments' => 'Médicaments',
                                'delegues' => 'Délégués médicaux',
                                'formations' => 'Formations sanitaires',
                                'pharmaciens' => 'Pharmaciens',
                                'depositaires' => 'Dépositaires',
                                'rapports' => 'Rapports',
                                'agents' => 'Agents DPM',
                            ];
                            $actions = [
                                'view' => 'Voir',
                                'create' => 'Créer',
                                'edit' => 'Modifier',
                                'delete' => 'Supprimer',
                                'export' => 'Exporter',
                            ];
                            ?>
                            <div class="permissions-table-wrapper">
                                <table class="permissions-table">
                                    <thead>
                                        <tr>
                                            <th>Module</th>
                                            <?php foreach ($actions as $label): ?>
                                                <th><?= $label ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $moduleKey => $moduleName): ?>
                                            <tr>
                                                <td class="module-name"><?= $moduleName ?></td>
                                                <?php foreach ($actions as $actionKey => $actionLabel): ?>
                                                    <td>
                                                        <label class="perm-checkbox">
                                                            <input type="checkbox"
                                                                name="permissions[<?= $moduleKey ?>][<?= $actionKey ?>]"
                                                                class="perm-input" data-module="<?= $moduleKey ?>"
                                                                data-action="<?= $actionKey ?>" value="1">
                                                            <span class="perm-checkmark"></span>
                                                        </label>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalUser')">Annuler</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitUser">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- MODAL : VOIR PERMISSIONS -->
    <div class="modal-overlay" id="modalPermissions">
        <div class="modal modal--lg">
            <div class="modal__header">
                <h2 class="modal__title" id="modalPermTitle">Permissions de —</h2>
                <button class="modal__close" onclick="closeModal('modalPermissions')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal__body" id="modalPermBody"></div>
            <div class="modal__footer">
                <button class="btn btn-outline" onclick="closeModal('modalPermissions')">Fermer</button>
            </div>
        </div>
    </div>


    <!-- MODAL : RÉINITIALISER MOT DE PASSE -->
    <div class="modal-overlay" id="modalResetPwd">
        <div class="modal modal--sm">
            <div class="modal__header">
                <h2 class="modal__title">Réinitialiser le mot de passe</h2>
                <button class="modal__close" onclick="closeModal('modalResetPwd')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal__body">
                <input type="hidden" id="resetUserId">
                <p class="modal-text" id="resetUserMsg"></p>
                <div class="form-group" style="margin-top: var(--space-lg);">
                    <label class="form-label form-label--required">Nouveau mot de passe</label>
                    <div class="input-container">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password" id="newPassword" class="form-input" placeholder="Min. 8 caractères">
                        <span class="input-icon input-icon--right password-toggle" data-target="newPassword"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label form-label--required">Confirmer</label>
                    <div class="input-container">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                        <input type="password" id="newPasswordConfirm" class="form-input" placeholder="Confirmer">
                        <span class="input-icon input-icon--right password-toggle"
                            data-target="newPasswordConfirm"></span>
                    </div>
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn-outline" onclick="closeModal('modalResetPwd')">Annuler</button>
                <button class="btn btn-warning" onclick="confirmResetPassword()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 9.9-1" />
                    </svg>
                    Réinitialiser
                </button>
            </div>
        </div>
    </div>


    <!-- MODAL : CONFIRMATION SUPPRESSION -->
    <div class="modal-overlay" id="modalDelete">
        <div class="modal modal--sm">
            <div class="modal__header modal__header--danger">
                <h2 class="modal__title">Confirmer la suppression</h2>
                <button class="modal__close" onclick="closeModal('modalDelete')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
            <div class="modal__body">
                <input type="hidden" id="deleteUserId">
                <div class="delete-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <line x1="12" y1="9" x2="12" y2="13" />
                        <line x1="12" y1="17" x2="12.01" y2="17" />
                    </svg>
                    <p id="deleteUserMsg"></p>
                </div>
            </div>
            <div class="modal__footer">
                <button class="btn btn-outline" onclick="closeModal('modalDelete')">Annuler</button>
                <button class="btn btn-danger" onclick="confirmDelete()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                    </svg>
                    Supprimer définitivement
                </button>
            </div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/preloader.js"></script>
    <script src="../assets/js/settings.js"></script>

</body>

</html>