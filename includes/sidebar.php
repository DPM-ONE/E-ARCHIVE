<?php
/**
 * includes/sidebar.php
 */

if (!isset($GLOBALS["outfit_loaded"])) {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">';
    $GLOBALS["outfit_loaded"] = true;
}

$_sb_role = $role ?? $_SESSION['role'] ?? 'lecteur';
$_sb_username = $username ?? $_SESSION['username'] ?? 'Utilisateur';

$_sb_roleLabel = match ($_sb_role) {
    'super_admin' => 'Super Administrateur',
    'admin' => 'Administrateur',
    'archiviste' => 'Archiviste',
    default => 'Lecteur'
};

$_sb_current = basename($_SERVER['PHP_SELF']);
$_sb_dir = basename(dirname($_SERVER['PHP_SELF']));

function sb_active(string $page): string
{
    global $_sb_current;
    return (basename($page) === $_sb_current) ? 'sb-nav__item--active' : '';
}

$_sb_prefix = ($_sb_dir === 'pages') ? '../' : '';
?>

<aside class="sb" id="sidebar">

    <!-- TOGGLE : flèche gauche = hide / flèche droite = show -->
    <button class="sb-toggle" id="sidebarToggle" title="Réduire le menu" aria-label="Réduire le menu">
        <!-- Flèche gauche (sidebar ouvert) -->
        <svg class="sb-toggle__icon sb-toggle__icon--hide" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 18l-6-6 6-6" />
        </svg>
        <!-- Flèche droite (sidebar réduit) -->
        <svg class="sb-toggle__icon sb-toggle__icon--show" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 18l6-6-6-6" />
        </svg>
    </button>

    <!-- LOGO -->
    <div class="sb-logo">
        <div class="sb-logo__icon">
            <img src="<?= $_sb_prefix ?>assets/img/logo-poster-192x192.png" alt="DPM" />
        </div>
        <div class="sb-logo__text">
            <span class="sb-logo__name">e-Archive</span>
            <span class="sb-logo__sub">DPM Congo</span>
        </div>
    </div>

    <!-- NAV -->
    <nav class="sb-nav" role="navigation">

        <!-- NAVIGATION (figée) -->
        <div class="sb-nav__section sb-nav__section--fixed">
            <span class="sb-nav__label">Navigation</span>

            <a href="<?= $_sb_prefix ?>dashboard.php" class="sb-nav__item <?= sb_active('dashboard.php') ?>"
                data-tooltip="Tableau de bord">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                </span>
                <span class="sb-nav__text">Tableau de bord</span>
            </a>
        </div>

        <!-- MODULES (scrollable) -->
        <div class="sb-nav__section sb-nav__section--scroll">
            <span class="sb-nav__label">Modules</span>

            <a href="<?= $_sb_prefix ?>pages/courriers.php" class="sb-nav__item <?= sb_active('courriers.php') ?>"
                data-tooltip="Courriers">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg>
                </span>
                <span class="sb-nav__text">Courriers</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/visas.php" class="sb-nav__item <?= sb_active('visas.php') ?>"
                data-tooltip="Visas">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                        <polyline points="14 2 14 8 20 8" />
                        <line x1="16" y1="13" x2="8" y2="13" />
                        <line x1="16" y1="17" x2="8" y2="17" />
                    </svg>
                </span>
                <span class="sb-nav__text">Visas</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/pharmacies.php" class="sb-nav__item <?= sb_active('pharmacies.php') ?>"
                data-tooltip="Pharmacies">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        <polyline points="9 22 9 12 15 12 15 22" />
                    </svg>
                </span>
                <span class="sb-nav__text">Pharmacies</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/gardes.php" class="sb-nav__item <?= sb_active('gardes.php') ?>"
                data-tooltip="Gardes">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 6v6l4 2" />
                    </svg>
                </span>
                <span class="sb-nav__text">Gardes</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/grossistes.php" class="sb-nav__item <?= sb_active('grossistes.php') ?>"
                data-tooltip="Grossistes">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <rect x="3" y="12" width="7" height="7" rx="1" />
                        <rect x="14" y="12" width="7" height="7" rx="1" />
                        <rect x="8" y="5" width="8" height="6" rx="1" />
                        <path d="M6 15h2M17 15h2" />
                    </svg>
                </span>
                <span class="sb-nav__text">Grossistes</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/depots.php" class="sb-nav__item <?= sb_active('depots.php') ?>"
                data-tooltip="Dépôts">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path
                            d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                        <line x1="12" y1="22.08" x2="12" y2="12" />
                    </svg>
                </span>
                <span class="sb-nav__text">Dépôts</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/agences.php" class="sb-nav__item <?= sb_active('agences.php') ?>"
                data-tooltip="Agences">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <rect x="2" y="7" width="20" height="14" rx="2" />
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                    </svg>
                </span>
                <span class="sb-nav__text">Agences</span>
            </a>            

            <a href="<?= $_sb_prefix ?>pages/laboratoires.php" class="sb-nav__item <?= sb_active('laboratoires.php') ?>"
                data-tooltip="Laboratoires">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v11m0 0l-4 7h14l-4-7" />
                    </svg>
                </span>
                <span class="sb-nav__text">Laboratoires</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/medicaments.php" class="sb-nav__item <?= sb_active('medicaments.php') ?>"
                data-tooltip="Médicaments">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z" />
                        <path d="M7.5 12h9M12 7.5v9" />
                    </svg>
                </span>
                <span class="sb-nav__text">Médicaments</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/fosa.php" class="sb-nav__item <?= sb_active('fosa.php') ?>"
                data-tooltip="Formations sanitaires">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <rect x="4" y="4" width="16" height="16" rx="2" />
                        <path d="M9 12h6M12 9v6" />
                    </svg>
                </span>
                <span class="sb-nav__text">Formations sanitaires</span>
            </a>

            <a href="<?= $_sb_prefix ?>pages/professionnels.php" class="sb-nav__item <?= sb_active('professionnels.php') ?>"
                data-tooltip="Professionnels">
                <span class="sb-nav__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M5 20c0-3.866 3.134-7 7-7s7 3.134 7 7" />
                        <path d="M15 11l3 3 4-4" />
                    </svg>
                </span>
                <span class="sb-nav__text">Professionnels</span>
            </a>
            
        </div>

        <!-- ADMINISTRATION (figée) -->
        <?php if (in_array($_sb_role, ['super_admin', 'admin'])): ?>
            <div class="sb-nav__section sb-nav__section--fixed">
                <span class="sb-nav__label">Administration</span>

                <a href="<?= $_sb_prefix ?>pages/agents.php" class="sb-nav__item <?= sb_active('agents.php') ?>"
                    data-tooltip="Agents DPM">
                    <span class="sb-nav__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </span>
                    <span class="sb-nav__text">Agents DPM</span>
                </a>

                <a href="<?= $_sb_prefix ?>pages/settings.php" class="sb-nav__item <?= sb_active('settings.php') ?>"
                    data-tooltip="Utilisateurs">
                    <span class="sb-nav__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
                            <circle cx="12" cy="12" r="3" />
                            <path
                                d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                        </svg>
                    </span>
                    <span class="sb-nav__text">Utilisateurs</span>
                </a>
            </div>
        <?php endif; ?>

    </nav>

    <!-- FOOTER USER -->
    <div class="sb-footer">
        <div class="sb-user" data-tooltip="<?= htmlspecialchars($_sb_username) ?>">
            <div class="sb-user__avatar">
                <?= strtoupper(substr($_sb_username, 0, 2)) ?>
            </div>
            <div class="sb-user__info">
                <span class="sb-user__name"><?= htmlspecialchars($_sb_username) ?></span>
                <span class="sb-user__role"><?= $_sb_roleLabel ?></span>
            </div>
            <a href="<?= $_sb_prefix ?>logout.php" class="sb-user__logout" title="Se déconnecter"
                onclick="return confirm('Voulez-vous vous déconnecter ?')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                    <polyline points="16 17 21 12 16 7" />
                    <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
            </a>
        </div>
    </div>

</aside>

<div class="sb-overlay" id="sidebarOverlay"></div>