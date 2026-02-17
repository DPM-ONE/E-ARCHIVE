<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - DPM Archive</title>
    <link rel="icon" type="image/x-icon" href="assets/img/icons/favicon.ico">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/redirect.css">
</head>

<body>

    <!-- Preloader initial -->
    <div class="preloader" id="preloader">
        <div class="preloader__spinner"></div>
        <p class="preloader__text">Chargement...</p>
    </div>

    <!-- Loader redirection (connexion réussie) -->
    <div class="redirect-loader" id="redirectLoader">
        <div class="redirect-loader__sweep"></div>
        <img src="assets/img/logo-poster-192x192.png" class="redirect-loader__logo" alt="DPM Archive">
        <div class="redirect-loader__spinner"></div>
        <p class="redirect-loader__text">Connexion réussie</p>
        <p class="redirect-loader__sub">Redirection vers le tableau de bord...</p>
        <div class="redirect-loader__dots">
            <span></span><span></span><span></span>
        </div>
    </div>

    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">

                <!-- Header -->
                <div class="auth-header">
                    <img src="assets/img/logo-poster-192x192.png" class="auth-logo" alt="Logo DPM Archive">
                    <h1 class="auth-title">Connexion</h1>
                    <p class="auth-subtitle">Accédez à votre espace DPM Archive</p>
                </div>

                <!-- Alertes -->
                <div id="alertContainer"></div>

                <!-- Formulaire -->
                <form class="auth-form" id="loginForm">

                    <!-- Identifiant -->
                    <div class="form-group">
                        <label for="username" class="form-label form-label--required">
                            Identifiant
                        </label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <input type="text" id="username" name="username" class="form-input"
                                placeholder="Nom d'utilisateur ou email" required autocomplete="username">
                        </div>
                    </div>

                    <!-- Mot de passe -->
                    <div class="form-group">
                        <label for="password" class="form-label form-label--required">
                            Mot de passe
                        </label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Votre mot de passe" required autocomplete="current-password">
                            <span class="input-icon input-icon--right password-toggle" data-target="password"
                                title="Afficher le mot de passe">
                            </span>
                        </div>
                    </div>

                    <!-- Se souvenir de moi -->
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Se souvenir de moi</span>
                        </label>
                    </div>

                    <!-- Bouton -->
                    <button type="submit" class="btn btn-primary btn-full" id="submitBtn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                            <polyline points="10 17 15 12 10 7" />
                            <line x1="15" y1="12" x2="3" y2="12" />
                        </svg>
                        Se connecter
                    </button>

                </form>

                <!-- Footer -->
                <div class="auth-footer">
                    <p class="auth-footer__text">
                        Pas encore de compte ?
                        <a href="register.php" class="auth-footer__link">Créer un compte</a>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <script src="assets/js/preloader.js"></script>
    <script src="assets/js/auth.js"></script>
    <script>
        // Override handleLogin pour ajouter le redirect loader
        document.getElementById('loginForm').addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            const submitBtn = document.getElementById('submitBtn');
            const originalBtn = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="preloader__spinner" style="width:20px;height:20px;border-width:2px;"></div>';

            fetch('api/auth.php', {
                method: 'POST',
                body: new FormData(this)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showRedirectLoader();
                    } else {
                        showAlert('error', data.message || 'Identifiants incorrects');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtn;
                    }
                })
                .catch(() => {
                    showAlert('error', 'Erreur de connexion au serveur');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtn;
                });
        }, true); // capture phase pour override auth.js

        function showRedirectLoader() {
            const loader = document.getElementById('redirectLoader');
            loader.classList.add('visible');
            setTimeout(() => {
                window.location.href = 'dashboard.php';
            }, 1800);
        }
    </script>

</body>

</html>