<?php
// admin/gps-manager.php
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';
require_once '../src/Models/Gallery.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();

$config = include('../config/config.php');
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$db = new Database($config['database']);
$galleryModel = new Gallery();

// Récupérer la galerie demandée
$galleryId = $_GET['gallery'] ?? null;
if (!$galleryId) {
    header('Location: galleries.php');
    exit;
}

// Récupérer les infos de la galerie
$stmt = $db->getPDO()->prepare("SELECT * FROM galleries WHERE id = ?");
$stmt->execute([$galleryId]);
$gallery = $stmt->fetch();

if (!$gallery) {
    die('Galerie non trouvée');
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'update_gps':
            $assetIds = json_decode($_POST['asset_ids'], true);
            $latitude = floatval($_POST['latitude']);
            $longitude = floatval($_POST['longitude']);

            $updated = 0;
            foreach ($assetIds as $assetId) {
                // Mettre à jour dans la base locale
                $stmt = $db->getPDO()->prepare("
                    UPDATE gallery_images 
                    SET latitude = ?, longitude = ?, location_name = NULL
                    WHERE gallery_id = ? AND immich_asset_id = ?
                ");
                if ($stmt->execute([$latitude, $longitude, $galleryId, $assetId])) {
                    $updated++;
                }
            }

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'message' => "$updated photos mises à jour"
            ]);
            exit;

        case 'remove_gps':
            $assetIds = json_decode($_POST['asset_ids'], true);

            $updated = 0;
            foreach ($assetIds as $assetId) {
                $stmt = $db->getPDO()->prepare("
                    UPDATE gallery_images 
                    SET latitude = NULL, longitude = NULL, location_name = NULL
                    WHERE gallery_id = ? AND immich_asset_id = ?
                ");
                if ($stmt->execute([$galleryId, $assetId])) {
                    $updated++;
                }
            }

            echo json_encode([
                'success' => true,
                'updated' => $updated,
                'message' => "GPS supprimé de $updated photos"
            ]);
            exit;
    }
}

// Récupérer toutes les images de la galerie
$stmt = $db->getPDO()->prepare("
    SELECT gi.*, gia.immich_album_id
    FROM gallery_images gi
    LEFT JOIN gallery_immich_albums gia ON gi.gallery_id = gia.gallery_id
    WHERE gi.gallery_id = ?
    ORDER BY gi.created_at ASC
");
$stmt->execute([$galleryId]);
$images = $stmt->fetchAll();

// Pour chaque image, récupérer les infos depuis Immich si pas de GPS en base
foreach ($images as &$image) {
    if ($image['latitude'] === null || $image['longitude'] === null) {
        $assetInfo = $immichClient->getAssetInfo($image['immich_asset_id']);
        if ($assetInfo && isset($assetInfo['exifInfo'])) {
            $image['latitude'] = $assetInfo['exifInfo']['latitude'] ?? null;
            $image['longitude'] = $assetInfo['exifInfo']['longitude'] ?? null;

            // Mettre à jour en base si on a trouvé des coordonnées
            if ($image['latitude'] !== null && $image['longitude'] !== null) {
                $stmt = $db->getPDO()->prepare("
                    UPDATE gallery_images 
                    SET latitude = ?, longitude = ?
                    WHERE id = ?
                ");
                $stmt->execute([$image['latitude'], $image['longitude'], $image['id']]);
            }
        }
    }
}

// Statistiques
$totalImages = count($images);
$imagesWithGPS = count(array_filter($images, fn($img) => $img['latitude'] !== null));
$imagesWithoutGPS = $totalImages - $imagesWithGPS;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire GPS - <?= htmlspecialchars($gallery['name']) ?></title>
    <link rel="stylesheet" href="../public/assets/css/gps-manager.css">
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestionnaire GPS</h1>
                <p class="subtitle"><?= htmlspecialchars($gallery['name']) ?></p>
            </div>
            <div class="header-right">
                <a href="galleries.php" class="btn-back">← Retour</a>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">
                    Toutes
                    <span class="badge"><?= $totalImages ?></span>
                </button>
                <button class="filter-btn" data-filter="with-gps">
                    Avec GPS
                    <span class="badge"><?= $imagesWithGPS ?></span>
                </button>
                <button class="filter-btn" data-filter="without-gps">
                    Sans GPS
                    <span class="badge"><?= $imagesWithoutGPS ?></span>
                </button>
            </div>

            <div class="action-buttons">
                <button id="btnSelectAll" class="btn btn-secondary">
                    Tout sélectionner
                </button>
                <button id="btnCopyGPS" class="btn btn-primary" disabled>
                    📍 Copier GPS
                </button>
                <button id="btnPasteGPS" class="btn btn-primary" disabled>
                    📋 Coller GPS
                </button>
                <button id="btnRemoveGPS" class="btn btn-danger" disabled>
                    🗑️ Supprimer GPS
                </button>
            </div>
        </div>

        <!-- Status bar -->
        <div class="status-bar">
            <div class="selection-info">
                <span id="selectionCount">0</span> photo(s) sélectionnée(s)
            </div>
            <div class="clipboard-info" id="clipboardInfo" style="display: none;">
                📋 GPS copié: <span id="clipboardCoords"></span>
            </div>
        </div>

        <!-- Images grid -->
        <div class="images-grid" id="imagesGrid">
            <?php foreach ($images as $index => $image):
                $hasGPS = $image['latitude'] !== null && $image['longitude'] !== null;
                $thumbnailUrl = "../public/image-proxy.php?id={$image['immich_asset_id']}&type=thumbnail";
            ?>
                <div class="image-item <?= $hasGPS ? 'has-gps' : 'no-gps' ?>"
                    data-index="<?= $index ?>"
                    data-asset-id="<?= htmlspecialchars($image['immich_asset_id']) ?>"
                    data-latitude="<?= $image['latitude'] ?>"
                    data-longitude="<?= $image['longitude'] ?>">

                    <div class="image-wrapper">
                        <img src="<?= $thumbnailUrl ?>"
                            alt="Photo <?= $index + 1 ?>"
                            loading="lazy">

                        <?php if ($hasGPS): ?>
                            <div class="gps-indicator" title="GPS: <?= number_format($image['latitude'], 6) ?>, <?= number_format($image['longitude'], 6) ?>">
                                📍
                            </div>
                        <?php endif; ?>

                        <div class="selection-overlay">
                            <div class="selection-check">✓</div>
                        </div>
                    </div>

                    <div class="image-info">
                        <div class="image-name" title="<?= htmlspecialchars($image['caption'] ?: 'Photo ' . ($index + 1)) ?>">
                            <?= htmlspecialchars(substr($image['caption'] ?: 'Photo ' . ($index + 1), 0, 30)) ?>
                        </div>
                        <?php if ($hasGPS): ?>
                            <div class="gps-coords">
                                <?= number_format($image['latitude'], 4) ?>, <?= number_format($image['longitude'], 4) ?>
                            </div>
                        <?php else: ?>
                            <div class="no-gps-text">Pas de GPS</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Hidden form for data -->
    <input type="hidden" id="galleryId" value="<?= $galleryId ?>">

    <!-- Toast notifications -->
    <div id="toast" class="toast"></div>

    <script src="../public/assets/js/gps-manager.js"></script>
</body>

</html>