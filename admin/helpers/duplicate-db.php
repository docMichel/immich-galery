<?php
// admin/helpers/duplicate-db.php - Helper pour la détection de doublons

require_once __DIR__ . '/../../src/Services/Database.php';
require_once __DIR__ . '/../../src/Api/ImmichClient.php';

/**
 * Récupérer tous les assets d'une galerie pour l'analyse de doublons
 */
function getGalleryAssetsForDuplicates($galleryId, $db, $immichClient)
{
    $stmt = $db->getPDO()->prepare("
        SELECT 
            gi.id,
            gi.immich_asset_id,
            gi.caption,
            gi.latitude,
            gi.longitude,
            gi.created_at,
            ia.originalFileName,
            ia.fileCreatedAt
        FROM gallery_images gi
        LEFT JOIN gallery_immich_albums gia ON gi.gallery_id = gia.gallery_id
        WHERE gi.gallery_id = ?
        ORDER BY gi.created_at ASC
    ");
    $stmt->execute([$galleryId]);
    $images = $stmt->fetchAll();

    $assets = [];

    foreach ($images as $image) {
        // Récupérer plus d'infos depuis Immich si nécessaire
        $assetInfo = null;
        try {
            $assetInfo = $immichClient->getAssetInfo($image['immich_asset_id']);
        } catch (Exception $e) {
            // Ignorer les erreurs
        }

        $assets[] = [
            'id' => $image['id'],
            'immich_asset_id' => $image['immich_asset_id'],
            'filename' => $assetInfo['originalFileName'] ?? $image['originalFileName'] ?? 'IMG_' . $image['immich_asset_id'] . '.jpg',
            'created_at' => $image['fileCreatedAt'] ?? $image['created_at'],
            'latitude' => $image['latitude'],
            'longitude' => $image['longitude'],
            'size' => $assetInfo['exifInfo']['FileSizeInBytes'] ?? 0,
            'width' => $assetInfo['exifInfo']['ImageWidth'] ?? 0,
            'height' => $assetInfo['exifInfo']['ImageHeight'] ?? 0
        ];
    }

    return $assets;
}

/**
 * Endpoint AJAX pour fournir les assets au serveur Flask
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_gallery_assets') {
    session_start();

    // Vérifier l'authentification admin
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Non autorisé']);
        exit;
    }

    $galleryId = $_GET['gallery_id'] ?? null;
    if (!$galleryId) {
        http_response_code(400);
        echo json_encode(['error' => 'gallery_id manquant']);
        exit;
    }

    $config = include(__DIR__ . '/../../config/config.php');
    $db = new Database($config['database']);
    $immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

    $assets = getGalleryAssetsForDuplicates($galleryId, $db, $immichClient);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'assets' => $assets,
        'total' => count($assets)
    ]);
    exit;
}
