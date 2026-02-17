<?php
/**
 * includes/navbar.php
 * Topbar - inclure dans toutes les pages après sidebar.php
 */

$_nb_current = $pageTitle ?? 'DPM Archive';
$_nb_prefix = ($_sb_dir ?? basename(dirname($_SERVER['PHP_SELF']))) === 'pages' ? '../' : '';
?>

<header class="sb-topbar">

    <!-- Bouton hamburger (mobile) -->
    <button class="sb-topbar__hamburger" onclick="window.sidebarToggleMobile && window.sidebarToggleMobile()"
        aria-label="Menu">
        <span></span><span></span><span></span>
    </button>

    <h1 class="sb-topbar__title"><?= htmlspecialchars($_nb_current) ?></h1>

    <div class="sb-topbar__right">
        <span class="sb-topbar__date">
            <?= date('d/m/Y') ?>
        </span>
        <div class="sb-topbar__user">
            <div class="sb-user__avatar" style="width:32px;height:32px;font-size:0.72rem;border-radius:8px;">
                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 2)) ?>
            </div>
        </div>
    </div>

</header>

<style>
    .sb-topbar {
        height: 58px;
        background: #ffffff;
        border-bottom: 1px solid #E5E7EB;
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 0 24px;
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .sb-topbar__hamburger {
        display: none;
        flex-direction: column;
        gap: 4px;
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
    }

    .sb-topbar__hamburger span {
        display: block;
        width: 20px;
        height: 2px;
        background: #374151;
        border-radius: 2px;
    }

    .sb-topbar__title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #111827;
        flex: 1;
    }

    .sb-topbar__right {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .sb-topbar__date {
        font-size: 0.8125rem;
        color: #9CA3AF;
    }

    @media (max-width: 900px) {
        .sb-topbar__hamburger {
            display: flex;
        }
    }
</style>