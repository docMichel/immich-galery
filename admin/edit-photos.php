<?php
session_start();
#require_once 'session_check.php';
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Édition Photos - Immich Gallery</title>

    <!-- CSS -->
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="edit-photos/edit-photos.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
</head>

<body>
    <div class="container">
        <h1>Édition des Photos</h1>

        <!-- Include tous les composants -->
        <?php include 'edit-photos/edit-toolbar.php'; ?>
        <?php include 'edit-photos/edit-grid.php'; ?>
        <?php include 'edit-photos/edit-map.php'; ?>
        <?php include 'edit-photos/edit-duplicates.php'; ?>
        <?php include 'edit-photos/edit-clipboard.php'; ?>
        <?php include 'edit-photos/edit-caption-modal.php'; ?>
    </div>

    <!-- JS -->
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script>
        const CONFIG = {
            immichUrl: '<?php echo IMMICH_URL; ?>',
            apiKey: '<?php echo $_SESSION['api_key']; ?>',
            proxyUrl: 'proxy.php'
        };
    </script>

    <!-- JS modules -->
    <script src="edit-photos/edit-photos.js"></script>
    <script src="edit-photos/edit-grid.js"></script>
    <script src="edit-photos/edit-map.js"></script>
    <script src="edit-photos/edit-duplicates.js"></script>
    <script src="edit-photos/edit-clipboard.js"></script>
    <script src="edit-photos/edit-rotate.js"></script>
    <script src="edit-photos/edit-captions.js"></script>
</body>

</html>