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
    <title>Inscription - DPM Archive</title>
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
                    <h1 class="auth-title">Inscription</h1>
                    <p class="auth-subtitle">Créez votre compte DPM Archive</p>
                </div>

                <!-- Alert -->
                <div id="alertContainer"></div>

                <!-- Form -->
                <form class="auth-form" id="registerForm">
                    <!-- Username -->
                    <div class="form-group">
                        <label for="username" class="form-label form-label--required">Nom d'utilisateur</label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input" 
                                placeholder="ex: jp.moukoko"
                                required
                            >
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email" class="form-label form-label--required">Email professionnel</label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-input" 
                                placeholder="prenom.nom@dpm.cg"
                                required
                            >
                        </div>
                    </div>

                    <!-- Matricule -->
                    <div class="form-group">
                        <label for="matricule" class="form-label form-label--required">Matricule</label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="16" rx="2" ry="2"/>
                                <line x1="7" y1="9" x2="17" y2="9"/>
                                <line x1="7" y1="13" x2="13" y2="13"/>
                            </svg>
                            <input 
                                type="text" 
                                id="matricule" 
                                name="matricule" 
                                class="form-input" 
                                placeholder="ex: DPM2025001"
                                required
                            >
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="form-group">
                        <label for="password" class="form-label form-label--required">Mot de passe</label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Min. 8 caractères"
                                required
                                minlength="8"
                            >
                            <svg class="input-icon input-icon--right password-toggle" data-target="password" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path class="eye-open" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-group">
                        <label for="password_confirm" class="form-label form-label--required">Confirmer le mot de passe</label>
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <input 
                                type="password" 
                                id="password_confirm" 
                                name="password_confirm" 
                                class="form-input" 
                                placeholder="Confirmez votre mot de passe"
                                required
                            >
                            <svg class="input-icon input-icon--right password-toggle" data-target="password_confirm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path class="eye-open" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn btn-primary btn-full">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Créer mon compte
                    </button>
                </form>

                <!-- Footer -->
                <div class="auth-footer">
                    <p class="auth-footer__text">
                        Vous avez déjà un compte ? 
                        <a href="login.php" class="auth-footer__link">Se connecter</a>
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