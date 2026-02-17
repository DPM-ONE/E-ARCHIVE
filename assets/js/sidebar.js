/**
 * assets/js/sidebar.js
 * Logique show/hide sidebar avec persistance localStorage
 */

'use strict';

(function () {

    const STORAGE_KEY = 'dpm_sidebar_collapsed';
    const BREAKPOINT  = 900;

    const sidebar   = document.getElementById('sidebar');
    const toggle    = document.getElementById('sidebarToggle');
    const overlay   = document.getElementById('sidebarOverlay');
    const body      = document.body;

    if (!sidebar) return;

    // ============================================================
    // INITIALISATION
    // ============================================================
    function init() {
        const isCollapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        const isMobile    = window.innerWidth <= BREAKPOINT;

        if (!isMobile && isCollapsed) {
            sidebar.classList.add('collapsed');
            updateToggleTitle(true);
        }

        // Adapter le main content au démarrage
        syncMainMargin();
    }

    // ============================================================
    // TOGGLE DESKTOP
    // ============================================================
    function toggleDesktop() {
        const willCollapse = !sidebar.classList.contains('collapsed');

        sidebar.classList.toggle('collapsed');
        updateToggleTitle(willCollapse);
        localStorage.setItem(STORAGE_KEY, willCollapse);
        syncMainMargin();
    }

    // ============================================================
    // TOGGLE MOBILE
    // ============================================================
    function toggleMobile() {
        const isOpen = sidebar.classList.contains('mobile-open');

        if (isOpen) {
            closeMobile();
        } else {
            openMobile();
        }
    }

    function openMobile() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('visible');
        body.style.overflow = 'hidden';
    }

    function closeMobile() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('visible');
        body.style.overflow = '';
    }

    // ============================================================
    // SYNC MARGIN DU MAIN CONTENT
    // ============================================================
    function syncMainMargin() {
        const main = document.querySelector('.sb-main');
        if (!main || window.innerWidth <= BREAKPOINT) return;

        const isCollapsed = sidebar.classList.contains('collapsed');
        main.style.marginLeft = isCollapsed
            ? 'var(--sb-width-collapsed)'
            : 'var(--sb-width)';
    }

    // ============================================================
    // TITRE DU BOUTON TOGGLE
    // ============================================================
    function updateToggleTitle(collapsed) {
        if (!toggle) return;
        toggle.setAttribute(
            'title',
            collapsed ? 'Afficher le menu' : 'Réduire le menu'
        );
        toggle.setAttribute(
            'aria-label',
            collapsed ? 'Afficher le menu' : 'Réduire le menu'
        );
    }

    // ============================================================
    // EVENTS
    // ============================================================

    // Bouton toggle
    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (window.innerWidth <= BREAKPOINT) {
                toggleMobile();
            } else {
                toggleDesktop();
            }
        });
    }

    // Overlay mobile
    if (overlay) {
        overlay.addEventListener('click', closeMobile);
    }

    // Resize : réinitialiser comportement mobile
    let resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth > BREAKPOINT) {
                closeMobile();
                syncMainMargin();
            }
        }, 150);
    });

    // Exposer toggleMobile pour le bouton hamburger du topbar
    window.sidebarToggleMobile = toggleMobile;

    // ============================================================
    // INIT
    // ============================================================
    document.addEventListener('DOMContentLoaded', init);

})();