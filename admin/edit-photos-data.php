<?php
// admin/edit-photos-data.php - Récupération et organisation des données photos

function getPhotosData($galleryId, $db, $immichClient)
{
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

    // Limiter le nombre d'appels API
    $maxApiCalls = 10;
    $apiCallCount = 0;

    // Pour chaque image, récupérer les infos depuis Immich si nécessaire
    foreach ($images as &$image) {
        if (($image['latitude'] === null || $image['longitude'] === null) && $apiCallCount < $maxApiCalls) {
            try {
                $assetInfo = $immichClient->getAssetInfo($image['immich_asset_id']);
                if ($assetInfo && isset($assetInfo['exifInfo'])) {
                    $image['latitude'] = $assetInfo['exifInfo']['latitude'] ?? null;
                    $image['longitude'] = $assetInfo['exifInfo']['longitude'] ?? null;
                    $image['fileCreatedAt'] = $assetInfo['fileCreatedAt'] ?? null;
                    $image['originalFileName'] = $assetInfo['originalFileName'] ?? null;

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
                error_log("Erreur API Immich pour asset {$image['immich_asset_id']}: " . $e->getMessage());
            }
        }

        // Extraire la date
        $dateStr = $image['created_at'] ?? $image['fileCreatedAt'] ?? 'now';
        $image['photo_date'] = date('Y-m-d', strtotime($dateStr));
    }

    // Grouper par date
    $imagesByDate = [];
    foreach ($images as $image) {
        $date = $image['photo_date'];
        if (!isset($imagesByDate[$date])) {
            $imagesByDate[$date] = [];
        }
        $imagesByDate[$date][] = $image;
    }

    // Trier les dates
    ksort($imagesByDate);

    // Statistiques
    $totalImages = count($images);
    $imagesWithGPS = count(array_filter($images, fn($img) => $img['latitude'] !== null));
    $imagesWithoutGPS = $totalImages - $imagesWithGPS;

    return [
        'images' => $images,
        'imagesByDate' => $imagesByDate,
        'total' => $totalImages,
        'withGPS' => $imagesWithGPS,
        'withoutGPS' => $imagesWithoutGPS
    ];
}
