<?php
session_start();
require_once '../src/Auth/Auth.php';
require_once '../src/Models/Gallery.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';

$auth = new Auth();

// Si pas connecté, rediriger vers login
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Déconnexion
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// Configuration
$config = include('../config/config.php');
$db = new Database($config['database']);
$galleryModel = new Gallery();
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$userRole = $auth->getUserRole();

// Déterminer quelle vue afficher
$gallerySlug = $_GET['gallery'] ?? null;
$albumId = $_GET['album'] ?? null;

// Augmenter la limite de mémoire si nécessaire
ini_set('memory_limit', '512M');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $gallerySlug ? 'Galerie' : 'Mes Galeries' ?></title>

    <!-- PhotoSwipe CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">

    <!-- Gallery CSS -->
    <link rel="stylesheet" href="assets/css/gallery.css">
</head>

<body>
    <?php
    // Inclure le header
    include 'views/header.php';
    ?>

    <div class="container">
        <?php
        if ($gallerySlug) {
            // Afficher une galerie spécifique
            include 'views/gallery-view.php';
        } else {
            // Afficher la liste des galeries
            include 'views/galleries-list.php';
        }
        ?>
    </div>

    <?php
    // Scripts communs
    if ($auth->isAdmin()): ?>
        <script>
            function openGPSManager(event, galleryId) {
                event.preventDefault();
                event.stopPropagation();
                window.open(`../admin/gps-manager.php?gallery=${galleryId}`,
                    'gps-manager',
                    'width=1200,height=800');
            }

            function editCaption(event, assetId) {
                event.preventDefault();
                event.stopPropagation();
                const galleryId = <?= isset($selectedGallery) ? $selectedGallery['id'] : 0 ?>;
                window.open(`../admin/edit-caption.php?asset=${assetId}&gallery=${galleryId}`,
                    'caption-editor',
                    'width=1000,height=700');
            }
        </script>
    <?php endif; ?>
</body>

</html>