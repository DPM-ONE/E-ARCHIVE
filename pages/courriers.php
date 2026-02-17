<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$pageTitle = 'Courriers'; // titre affiché dans la topbar
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="icon" href="../assets/img/icons/favicon.ico">
</head>

<body class="sb-layout">

    <?php include '../includes/sidebar.php'; ?>

    <div class="sb-main">

        <?php include '../includes/navbar.php'; ?>

        <main class="main-content">
            <!-- contenu de la page -->
        </main>

    </div>

    <?php include '../includes/footer.php'; ?>

</body>

</html>