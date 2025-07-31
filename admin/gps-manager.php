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
                    // NOUVEAU : Mettre à jour dans Immich aussi
                    $immichClient->updateAssetLocation($assetId, $latitude, $longitude);
                    $updated++;
                }
            }

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
                    // NOUVEAU : Supprimer aussi dans Immich (mettre null)
                    $immichClient->updateAssetLocation($assetId, null, null);
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

// Limiter le nombre d'appels API pour éviter le timeout
$maxApiCalls = 10; // Traiter seulement les 10 premières images sans GPS
$apiCallCount = 0;

// Pour chaque image, récupérer les infos depuis Immich si pas de GPS en base
foreach ($images as &$image) {
    if (($image['latitude'] === null || $image['longitude'] === null) && $apiCallCount < $maxApiCalls) {
        try {
            $assetInfo = $immichClient->getAssetInfo($image['immich_asset_id']);
            if ($assetInfo && isset($assetInfo['exifInfo'])) {
                $image['latitude'] = $assetInfo['exifInfo']['latitude'] ?? null;
                $image['longitude'] = $assetInfo['exifInfo']['longitude'] ?? null;

                if ($image['latitude'] !== null && $image['longitude'] !== null) {
                    $stmt = $db->getPDO()->prepare("
                        UPDATE gallery_images 
                        SET latitude = ?, longitude = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$image['latitude'], $image['longitude'], $image['id']]);
                }
            }
            $apiCallCount++;
        } catch (Exception $e) {
            // Ignorer les erreurs d'API et continuer
            error_log("Erreur API Immich pour asset {$image['immich_asset_id']}: " . $e->getMessage());
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
    <link rel="stylesheet" href="../public/assets/css/gallery.css">
    <link rel="stylesheet" href="../public/assets/css/gps-manager.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        /* Modal pour la carte */
        .map-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            padding: 20px;
        }

        .map-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-container {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            height: 80vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .map-header {
            padding: 16px 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-header h3 {
            margin: 0;
            font-size: 18px;
        }

        #map {
            flex: 1;
            width: 100%;
        }

        .map-footer {
            padding: 12px 20px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f5f5f5;
        }

        .map-coords {
            font-family: monospace;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content" style="flex-wrap: wrap; gap: 15px;">
            <div style="display: flex; align-items: center; gap: 20px; flex: 1;">
                <h1>Gestionnaire GPS - <?= htmlspecialchars($gallery['name']) ?></h1>

                <!-- Filtres dans le header -->
                <div class="filter-pills">
                    <button class="filter-pill active" data-filter="all">
                        Toutes <span class="pill-count"><?= $totalImages ?></span>
                    </button>
                    <button class="filter-pill" data-filter="with-gps">
                        Avec GPS <span class="pill-count success"><?= $imagesWithGPS ?></span>
                    </button>
                    <button class="filter-pill" data-filter="without-gps">
                        Sans GPS <span class="pill-count warning"><?= $imagesWithoutGPS ?></span>
                    </button>
                    <button class="filter-pill" data-filter="same-day" style="display: none;">
                        Même jour <span class="pill-count">0</span>
                    </button>
                </div>
            </div>

            <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div class="selection-info" style="font-weight: 500; color: #666;">
                    <span id="selectionCount">0</span> sélectionnée(s)
                </div>

                <button id="btnSelectAll" class="btn btn-secondary">
                    ☑️ Tout sélectionner
                </button>

                <button id="btnCopyGPS" class="btn btn-primary" disabled>
                    📍 Copier GPS
                </button>
                <button id="btnPasteGPS" class="btn btn-primary" disabled>
                    📋 Coller GPS
                </button>
                <button id="btnSameDay" class="btn btn-secondary" disabled>
                    📅 Même jour
                </button>
                <button id="btnMapSelect" class="btn btn-primary" disabled>
                    🗺️ Carte
                </button>
                <button id="btnRemoveGPS" class="btn btn-danger" disabled>
                    🗑️ Supprimer GPS
                </button>

                <div class="clipboard-info" id="clipboardInfo" style="display: none; font-size: 12px; align-items: center; gap: 8px;">
                    <img id="clipboardThumb" src="" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; display: none;">
                    📋 <span id="clipboardCoords"></span>
                </div>

                <a href="galleries.php" class="btn btn-secondary">← Retour</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Grille de photos directement -->
        <div class="photo-grid" id="imagesGrid">
            <?php foreach ($images as $index => $image):
                $hasGPS = $image['latitude'] !== null && $image['longitude'] !== null;
                $thumbnailUrl = "../public/image-proxy.php?id={$image['immich_asset_id']}&type=thumbnail";
            ?>
                <div class="photo-item gps-item <?= $hasGPS ? 'has-gps' : 'no-gps' ?>"
                    data-asset-id="<?= htmlspecialchars($image['immich_asset_id']) ?>"
                    data-latitude="<?= $image['latitude'] ?>"
                    data-longitude="<?= $image['longitude'] ?>"
                    data-date="<?= date('Y-m-d', strtotime($image['created_at'] ?? $assetInfo['fileCreatedAt'] ?? 'now')) ?>">

                    <img src="<?= $thumbnailUrl ?>"
                        alt="<?= htmlspecialchars($image['caption'] ?: 'Photo ' . ($index + 1)) ?>"
                        loading="lazy">

                    <!-- Checkbox de sélection -->
                    <div class="selection-checkbox">
                        <input type="checkbox" class="photo-select" id="select-<?= $index ?>">
                        <label for="select-<?= $index ?>"></label>
                    </div>

                    <!-- Indicateur GPS -->
                    <?php if ($hasGPS): ?>
                        <div class="gps-badge success" title="<?= number_format($image['latitude'], 6) ?>, <?= number_format($image['longitude'], 6) ?>">
                            📍 GPS
                        </div>
                    <?php else: ?>
                        <div class="gps-badge warning">
                            ❓ Pas de GPS
                        </div>
                    <?php endif; ?>

                    <!-- Légende au survol -->
                    <div class="photo-caption">
                        <div class="photo-caption-text">
                            <?= htmlspecialchars($image['caption'] ?: 'Photo ' . ($index + 1)) ?>
                            <?php if ($hasGPS): ?>
                                <br><small>📍 <?= number_format($image['latitude'], 4) ?>, <?= number_format($image['longitude'], 4) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toast notifications -->
    <div id="toast" class="toast"></div>
    <!-- Modal de carte -->
    <div id="mapModal" class="map-modal">
        <div class="map-container">
            <div class="map-header">
                <h3>Sélectionner une position GPS</h3>
                <button class="btn-close" onclick="closeMapModal()">✕</button>
            </div>
            <div id="map"></div>
            <div class="map-footer">
                <div class="map-coords">
                    Cliquez sur la carte pour sélectionner une position
                </div>
                <div>
                    <button class="btn btn-secondary" onclick="closeMapModal()">Annuler</button>
                    <button id="btnConfirmLocation" class="btn btn-primary" disabled onclick="confirmMapLocation()">
                        Valider cette position
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Hidden data -->
    <input type="hidden" id="galleryId" value="<?= $galleryId ?>">

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../public//assets/js/modules/MapManager.js"></script>
    <script src="../public/assets/js/gps-manager.js"></script>
</body>

</html>