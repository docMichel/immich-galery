<?php
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Models/Gallery.php';
require_once '../src/Services/Database.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$galleryId = $data['gallery_id'] ?? null;
$action = $data['action'] ?? '';

#$galleryId = $_POST['gallery_id'] ?? null;
#$action = $_POST['action'] ?? '';

if ($action === 'refresh_exif' && $galleryId) {
    $config = include('../config/config.php');
    $immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
    $db = new Database($config['database']);

    // Récupérer toutes les images de la galerie
    $stmt = $db->getPDO()->prepare("
        SELECT immich_asset_id FROM gallery_images WHERE gallery_id = ?
    ");
    $stmt->execute([$galleryId]);
    $assets = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $updated = 0;
    foreach ($assets as $assetId) {
        try {
            // Récupérer les infos complètes depuis Immich
            $assetInfo = $immichClient->getAssetInfo($assetId);

            // Mettre à jour en base
            $stmt = $db->getPDO()->prepare("
                UPDATE gallery_images SET
                    exif_data = ?,
                    immich_metadata = ?,
                    created_at = ?,
                    -- original_filename = ?,
                    -- file_size = ?,
                    latitude = ?,
                    longitude = ?
                WHERE immich_asset_id = ? AND gallery_id = ?
            ");

            $stmt->execute([
                json_encode($assetInfo['exifInfo'] ?? []),
                json_encode($assetInfo),
                $assetInfo['fileCreatedAt'] ?? null,
                //$assetInfo['originalFileName'] ?? null,
                //$assetInfo['exifInfo']['FileSizeInBytes'] ?? null,
                $assetInfo['exifInfo']['latitude'] ?? null,
                $assetInfo['exifInfo']['longitude'] ?? null,
                $assetId,
                $galleryId
            ]);

            $updated++;
        } catch (Exception $e) {
            error_log("Erreur refresh asset $assetId: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'updated' => $updated]);
    exit;
}
