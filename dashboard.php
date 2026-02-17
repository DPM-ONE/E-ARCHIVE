<?php
/**
 * dashboard.php
 */

require_once 'config/database.php';
require_once 'config/session.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Utilisateur';
$role = $_SESSION['role'] ?? 'lecteur';

$roleLabel = match ($role) {
    'super_admin' => 'Super Administrateur',
    'admin' => 'Administrateur',
    'archiviste' => 'Archiviste',
    default => 'Lecteur'
};

// Statistiques rapides
try {
    $pdo = getPDO();

    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

} catch (Exception $e) {
    $totalUsers = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - DPM Archive</title>
    <link rel="icon" type="image/x-icon" href="assets/img/icons/favicon.ico">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #F3F4F6;
            color: #111827;
            min-height: 100vh;
            display: flex;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 260px;
            background: #111827;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            z-index: 100;
        }

        .sidebar__logo {
            padding: 24px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar__logo img {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: contain;
        }

        .sidebar__logo-text {
            color: white;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .sidebar__logo-sub {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.7rem;
            font-weight: 400;
        }

        .sidebar__nav {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav__section-title {
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 16px 8px 8px;
        }

        .nav__item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.15s;
            margin-bottom: 2px;
        }

        .nav__item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: white;
        }

        .nav__item.active {
            background: #00A859;
            color: white;
        }

        .nav__item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .sidebar__footer {
            padding: 16px 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #00A859;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            color: white;
            font-size: 0.8125rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.7rem;
        }

        .logout-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255, 255, 255, 0.4);
            padding: 4px;
            border-radius: 4px;
            transition: color 0.15s;
            display: flex;
        }

        .logout-btn:hover {
            color: #EF4444;
        }

        .logout-btn svg {
            width: 16px;
            height: 16px;
        }

        /* ===== MAIN ===== */
        .main {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ===== TOPBAR ===== */
        .topbar {
            background: white;
            padding: 0 32px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #E5E7EB;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar__title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
        }

        .topbar__right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar__date {
            font-size: 0.8125rem;
            color: #6B7280;
        }

        /* ===== CONTENT ===== */
        .content {
            padding: 32px;
            flex: 1;
        }

        /* ===== WELCOME BANNER ===== */
        .welcome-banner {
            background: linear-gradient(135deg, #00A859 0%, #047857 100%);
            border-radius: 16px;
            padding: 32px;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            right: -40px;
            top: -40px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.06);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            right: 60px;
            bottom: -60px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .welcome-sub {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-bottom: 12px;
        }

        /* ===== STATS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #E5E7EB;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stat-card__label {
            font-size: 0.8125rem;
            color: #6B7280;
            font-weight: 500;
        }

        .stat-card__value {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
            line-height: 1;
        }

        .stat-card__footer {
            font-size: 0.75rem;
            color: #9CA3AF;
        }

        /* ===== MODULES GRID ===== */
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .module-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #E5E7EB;
            text-decoration: none;
            color: #111827;
            transition: all 0.15s;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .module-card:hover {
            border-color: #00A859;
            box-shadow: 0 4px 12px rgba(0, 168, 89, 0.1);
            transform: translateY(-2px);
        }

        .module-card__icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(0, 168, 89, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #00A859;
        }

        .module-card__icon svg {
            width: 20px;
            height: 20px;
        }

        .module-card__name {
            font-size: 0.875rem;
            font-weight: 600;
        }

        .module-card__desc {
            font-size: 0.75rem;
            color: #9CA3AF;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .modules-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .modules-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>

<body>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">

        <div class="sidebar__logo">
            <img src="assets/img/logo-poster-192x192.png" alt="DPM">
            <div>
                <div class="sidebar__logo-text">DPM Archive</div>
                <div class="sidebar__logo-sub">Direction de la Pharmacie</div>
            </div>
        </div>

        <nav class="sidebar__nav">

            <div class="nav__section-title">Navigation</div>

            <a href="dashboard.php" class="nav__item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" />
                    <rect x="14" y="3" width="7" height="7" />
                    <rect x="14" y="14" width="7" height="7" />
                    <rect x="3" y="14" width="7" height="7" />
                </svg>
                Tableau de bord
            </a>

            <a href="pages/courriers.php" class="nav__item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                    <polyline points="22,6 12,13 2,6" />
                </svg>
                Courriers
            </a>

            <a href="pages/visas.php" class="nav__item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                </svg>
                Visas
            </a>

            <a href="pages/pharmacies.php" class="nav__item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                    <polyline points="9 22 9 12 15 12 15 22" />
                </svg>
                Pharmacies
            </a>

            <a href="pages/medicaments.php" class="nav__item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
                Medicaments
            </a>

            <a href="pages/rapports.php" class="nav__item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10" />
                    <line x1="12" y1="20" x2="12" y2="4" />
                    <line x1="6" y1="20" x2="6" y2="14" />
                </svg>
                Rapports
            </a>

            <?php if (in_array($role, ['super_admin', 'admin'])): ?>
                <div class="nav__section-title">Administration</div>

                <a href="pages/settings.php" class="nav__item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                    Utilisateurs
                </a>
            <?php endif; ?>

        </nav>

        <div class="sidebar__footer">
            <div class="user-card">
                <div class="user-avatar">
                    <?= strtoupper(substr($username, 0, 2)) ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($username) ?></div>
                    <div class="user-role"><?= $roleLabel ?></div>
                </div>
                <a href="logout.php" class="logout-btn" title="Se deconnecter">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </a>
            </div>
        </div>

    </aside>


    <!-- ===== MAIN ===== -->
    <div class="main">

        <!-- Topbar -->
        <header class="topbar">
            <span class="topbar__title">Tableau de bord</span>
            <div class="topbar__right">
                <span class="topbar__date">
                    <?= strftime('%A %d %B %Y') ?: date('d/m/Y') ?>
                </span>
            </div>
        </header>

        <!-- Content -->
        <div class="content">

            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-badge">DPM Archive</div>
                <div class="welcome-title">Bienvenue, <?= htmlspecialchars($username) ?></div>
                <div class="welcome-sub">
                    Vous etes connecte en tant que <?= $roleLabel ?>.
                    Bonne journee de travail.
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">

                <div class="stat-card">
                    <span class="stat-card__label">Utilisateurs</span>
                    <span class="stat-card__value"><?= $totalUsers ?></span>
                    <span class="stat-card__footer">Comptes enregistres</span>
                </div>

                <div class="stat-card">
                    <span class="stat-card__label">Modules</span>
                    <span class="stat-card__value">13</span>
                    <span class="stat-card__footer">Modules disponibles</span>
                </div>

                <div class="stat-card">
                    <span class="stat-card__label">Session</span>
                    <span class="stat-card__value">Active</span>
                    <span class="stat-card__footer">Expire dans 30 min</span>
                </div>

                <div class="stat-card">
                    <span class="stat-card__label">Role</span>
                    <span class="stat-card__value" style="font-size:1.1rem"><?= $roleLabel ?></span>
                    <span class="stat-card__footer"><?= htmlspecialchars($username) ?></span>
                </div>

            </div>

            <!-- Modules -->
            <div class="section-title">Acces rapide aux modules</div>

            <div class="modules-grid">

                <a href="pages/courriers.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg>
                    </div>
                    <div class="module-card__name">Courriers</div>
                    <div class="module-card__desc">Gestion des courriers entrants et sortants</div>
                </a>

                <a href="pages/visas.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                    </div>
                    <div class="module-card__name">Visas</div>
                    <div class="module-card__desc">Suivi des visas et autorisations</div>
                </a>

                <a href="pages/pharmacies.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                        </svg>
                    </div>
                    <div class="module-card__name">Pharmacies</div>
                    <div class="module-card__desc">Registre des pharmacies</div>
                </a>

                <a href="pages/agences.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2" />
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                        </svg>
                    </div>
                    <div class="module-card__name">Agences</div>
                    <div class="module-card__desc">Gestion des agences pharmaceutiques</div>
                </a>

                <a href="pages/depots.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                        </svg>
                    </div>
                    <div class="module-card__name">Depots</div>
                    <div class="module-card__desc">Gestion des depots de medicaments</div>
                </a>

                <a href="pages/laboratoires.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v11m0 0l-4 7h14l-4-7" />
                        </svg>
                    </div>
                    <div class="module-card__name">Laboratoires</div>
                    <div class="module-card__desc">Suivi des laboratoires analyses</div>
                </a>

                <a href="pages/medicaments.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="8" height="8" rx="1" />
                            <rect x="13" y="3" width="8" height="8" rx="1" />
                            <rect x="3" y="13" width="8" height="8" rx="1" />
                            <rect x="13" y="13" width="8" height="8" rx="1" />
                        </svg>
                    </div>
                    <div class="module-card__name">Medicaments</div>
                    <div class="module-card__desc">Registre national des medicaments</div>
                </a>

                <a href="pages/rapports.php" class="module-card">
                    <div class="module-card__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10" />
                            <line x1="12" y1="20" x2="12" y2="4" />
                            <line x1="6" y1="20" x2="6" y2="14" />
                        </svg>
                    </div>
                    <div class="module-card__name">Rapports</div>
                    <div class="module-card__desc">Generation et export de rapports</div>
                </a>

            </div>

        </div>
    </div>

    <script>
        // Deconnexion
        document.querySelectorAll('a[href="logout.php"]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                if (!confirm('Voulez-vous vous deconnecter ?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>

</html>