<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPM Archive - Chargement</title>
    <link rel="icon" type="image/x-icon" href="assets/img/icons/favicon.ico">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/splash.css">
</head>

<body>
    <div class="splash-screen" id="splashScreen">
        <!-- Logo SVG -->
        <img src="assets/img/logo-earchive-app-450.png" class="splash-logo" alt="Logo de l'application e-Archive">
        <h1 class="splash-title">e-Archive</h1>
        <p class="splash-subtitle">SYSTEME D'ARCHIVAGE DE LA DIRECTION DE LA PHARMACIE ET DU MEDICAMENT</p>
        <div class="splash-loader">
            <div class="splash-loader__bar"></div>
        </div>
    </div>

    <script>
        // Redirection automatique après 3 secondes
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>

</html>