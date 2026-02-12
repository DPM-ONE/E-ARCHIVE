/**
 * PRELOADER
 * Gestion du preloader global
 */

document.addEventListener('DOMContentLoaded', function() {
    const preloader = document.getElementById('preloader');
    
    if (preloader) {
        // Cacher le preloader après le chargement
        window.addEventListener('load', function() {
            setTimeout(() => {
                preloader.classList.add('hidden');
                
                // Supprimer du DOM après l'animation
                setTimeout(() => {
                    preloader.style.display = 'none';
                }, 250);
            }, 500);
        });
    }
});