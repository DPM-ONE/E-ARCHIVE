/**
 * AUTHENTICATION
 * Gestion Login & Register
 * assets/js/auth.js
 */

'use strict';

// ============================================================
// SVG ICONS - eye / eye-close
// ============================================================
const SVG_EYE = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
    <circle cx="12" cy="12" r="3"/>
</svg>`;

const SVG_EYE_CLOSE = `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round">
    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8
             a18.45 18.45 0 0 1 5.06-5.94"/>
    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8
             a18.5 18.5 0 0 1-2.16 3.19"/>
    <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
    <line x1="1" y1="1" x2="23" y2="23"/>
</svg>`;


document.addEventListener('DOMContentLoaded', function () {

    // ===== PASSWORD TOGGLE =====
    initPasswordToggles();

    // ===== LOGIN FORM =====
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // ===== REGISTER FORM =====
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
});


/**
 * Initialiser les boutons toggle password
 * Mot de passe caché  → eye       (afficher)
 * Mot de passe visible → eye-close (masquer)
 */
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(toggle => {

        // État initial : password caché → afficher eye
        toggle.innerHTML = SVG_EYE;
        toggle.setAttribute('title', 'Afficher le mot de passe');

        toggle.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const input    = document.getElementById(targetId);
            if (!input) return;

            if (input.type === 'password') {
                // Rendre visible → eye-close
                input.type     = 'text';
                this.innerHTML = SVG_EYE_CLOSE;
                this.setAttribute('title', 'Masquer le mot de passe');
            } else {
                // Masquer → eye
                input.type     = 'password';
                this.innerHTML = SVG_EYE;
                this.setAttribute('title', 'Afficher le mot de passe');
            }
        });
    });
}


/**
 * Gestion connexion
 */
function handleLogin(e) {
    e.preventDefault();

    const submitBtn   = e.target.querySelector('button[type="submit"]');
    const originalBtn = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <div class="preloader__spinner"
             style="width:20px;height:20px;border-width:2px;">
        </div>`;

    fetch('api/auth.php', {
        method : 'POST',
        body   : new FormData(e.target)
    })
    .then(res => {
        if (!res.ok) {
            // Capturer l'erreur HTTP (400, 500...)
            return res.json().then(data => { throw new Error(data.message || 'Erreur serveur ' + res.status); });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            showAlert('success', 'Connexion réussie ! Redirection...');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } else {
            showAlert('error', data.message || 'Identifiants incorrects');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtn;
        }
    })
    .catch(err => {
        showAlert('error', err.message || 'Erreur de connexion au serveur');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtn;
    });
}


/**
 * Gestion inscription
 */
function handleRegister(e) {
    e.preventDefault();

    const formData        = new FormData(e.target);
    const password        = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');

    // Validations côté client
    if (password !== passwordConfirm) {
        showAlert('error', 'Les mots de passe ne correspondent pas');
        return;
    }

    if (password.length < 8) {
        showAlert('error', 'Le mot de passe doit contenir au moins 8 caractères');
        return;
    }

    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(password)) {
        showAlert('error', 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre');
        return;
    }

    const submitBtn   = e.target.querySelector('button[type="submit"]');
    const originalBtn = submitBtn.innerHTML;

    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <div class="preloader__spinner"
             style="width:20px;height:20px;border-width:2px;">
        </div>`;

    fetch('api/register.php', {
        method : 'POST',
        body   : formData
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => { throw new Error(data.message || 'Erreur serveur ' + res.status); });
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            showAlert('success', 'Compte créé avec succès ! Redirection...');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        } else {
            showAlert('error', data.message || 'Erreur lors de l\'inscription');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtn;
        }
    })
    .catch(err => {
        showAlert('error', err.message || 'Erreur de connexion au serveur');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtn;
    });
}


/**
 * Afficher une alerte
 * @param {string} type    - 'success' | 'error' | 'warning'
 * @param {string} message - Texte à afficher
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) return;

    const alertClass = {
        success : 'alert-success',
        error   : 'alert-error',
        warning : 'alert-warning'
    }[type] || 'alert-error';

    alertContainer.innerHTML = `
        <div class="alert ${alertClass} fade-in">
            ${message}
        </div>`;

    // Retirer automatiquement après 5 secondes
    setTimeout(() => {
        const alertEl = alertContainer.querySelector('.alert');
        if (alertEl) {
            alertEl.classList.replace('fade-in', 'fade-out');
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 250);
        }
    }, 5000);
}