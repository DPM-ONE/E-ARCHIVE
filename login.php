<?php
session_start();

// Si déjà connecté, rediriger vers dashboard
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
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link rel="icon" type="image/x-icon" href="assets/img/icons/favicon.ico">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>

<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader__spinner"></div>
        <p class="preloader__text">Chargement...</p>
    </div>

    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <!-- Header -->
                <div class="auth-header">
                    <!-- Logo PNG -->
                    <img src="assets\img\logo-poster-192x192.png" class="auth-logo" alt="Logo de l'application e-Archive">
                    <h1 class="auth-title">Connexion</h1>
                    <p class="auth-subtitle">Accédez à votre espace DPM Archive</p>
                </div>

                <!-- Alert si erreur -->
                <div id="alertContainer"></div>

                <!-- Form -->
                <form class="auth-form" id="loginForm">
                    <!-- Email/Username -->
                    <div class="form-group">
                        <label for="username" class="form-label form-label--required">Identifiant</label>
                        <div class="input-container">
                            <!-- Icon User SVG -->
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <input type="text" id="username" name="username" class="form-input"
                                placeholder="Votre nom d'utilisateur" required autocomplete="username">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label form-label--required">Mot de passe</label>
                        <div class="input-container">
                            <!-- Icon Lock SVG -->
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Votre mot de passe" required autocomplete="current-password">
                            <!-- Icon Eye (toggle) SVG -->
                            <svg class="input-icon input-icon--right password-toggle" id="togglePassword"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path id="eyeOpen" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                                <path id="eyeClosed"
                                    d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"
                                    style="display: none;" />
                                <line x1="1" y1="1" x2="23" y2="23" id="eyeSlash" style="display: none;" />
                            </svg>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Se souvenir de moi</span>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary btn-full">
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

    <!-- JavaScript -->
    <script src="assets/js/preloader.js"></script>
    <script src="assets/js/auth.js"></script>
</body>

</html>