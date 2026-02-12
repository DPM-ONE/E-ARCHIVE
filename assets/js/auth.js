 
/**
 * AUTHENTICATION
 * Gestion Login & Register
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== PASSWORD TOGGLE =====
    const passwordToggles = document.querySelectorAll('.password-toggle');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.dataset.target || 'password';
            const input = document.getElementById(targetId);
            const eyeOpen = this.querySelector('.eye-open');
            
            if (input.type === 'password') {
                input.type = 'text';
                if (eyeOpen) {
                    eyeOpen.style.opacity = '0.5';
                }
            } else {
                input.type = 'password';
                if (eyeOpen) {
                    eyeOpen.style.opacity = '1';
                }
            }
        });
    });
    
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
 * Gestion connexion
 */
function handleLogin(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const alertContainer = document.getElementById('alertContainer');
    
    // Afficher loader
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="preloader__spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>';
    
    // Envoi AJAX
    fetch('api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Connexion réussie ! Redirection...');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1000);
        } else {
            showAlert('error', data.message || 'Identifiants incorrects');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        showAlert('error', 'Erreur de connexion au serveur');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

/**
 * Gestion inscription
 */
function handleRegister(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const password = formData.get('password');
    const passwordConfirm = formData.get('password_confirm');
    
    // Validation mot de passe
    if (password !== passwordConfirm) {
        showAlert('error', 'Les mots de passe ne correspondent pas');
        return;
    }
    
    if (password.length < 8) {
        showAlert('error', 'Le mot de passe doit contenir au moins 8 caractères');
        return;
    }
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<div class="preloader__spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>';
    
    // Envoi AJAX
    fetch('api/register.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', 'Compte créé avec succès ! Redirection...');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 2000);
        } else {
            showAlert('error', data.message || 'Erreur lors de l\'inscription');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        showAlert('error', 'Erreur de connexion au serveur');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}

/**
 * Afficher alerte
 */
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    
    alertContainer.innerHTML = `
        <div class="alert ${alertClass} fade-in">
            ${message}
        </div>
    `;
    
    // Retirer après 5 secondes
    setTimeout(() => {
        const alert = alertContainer.querySelector('.alert');
        if (alert) {
            alert.classList.add('fade-out');
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 250);
        }
    }, 5000);
}