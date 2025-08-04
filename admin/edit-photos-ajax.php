<?php
// admin/edit-photos-ajax.php - Gestionnaire des requêtes AJAX

function handleAjaxRequest($action, $data, $galleryId, $db, $immichClient)
{
    header('Content-Type: application/json');

    try {
        switch ($action) {
            case 'update_gps':
                $result = updateGPS($data, $galleryId, $db, $immichClient);
                break;

            case 'remove_gps':
                $result = removeGPS($data, $galleryId, $db, $immichClient);
                break;

            case 'rotate_image':
                $result = rotateImage($data, $immichClient);
                break;

            case 'update_caption':
                $result = updateCaption($data, $galleryId, $db);
                break;

            case 'find_duplicates':
                $result = LocalfindDuplicates($data, $galleryId, $db);
                break;

            default:
                $result = ['success' => false, 'error' => 'Action inconnue'];
        }

        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function updateGPS($data, $galleryId, $db, $immichClient)
{
    $assetIds = json_decode($data['asset_ids'], true);
    $latitude = floatval($data['latitude']);
    $longitude = floatval($data['longitude']);

    $updated = 0;
    foreach ($assetIds as $assetId) {
        $stmt = $db->getPDO()->prepare("
            UPDATE gallery_images 
            SET latitude = ?, longitude = ?, location_name = NULL
            WHERE gallery_id = ? AND immich_asset_id = ?
        ");

        if ($stmt->execute([$latitude, $longitude, $galleryId, $assetId])) {
            $immichClient->updateAssetLocation($assetId, $latitude, $longitude);
            $updated++;
        }
    }

    return [
        'success' => true,
        'updated' => $updated,
        'message' => "GPS appliqué à $updated photos"
    ];
}

function removeGPS($data, $galleryId, $db, $immichClient)
{
    $assetIds = json_decode($data['asset_ids'], true);
    $updated = 0;

    foreach ($assetIds as $assetId) {
        $stmt = $db->getPDO()->prepare("
            UPDATE gallery_images 
            SET latitude = NULL, longitude = NULL, location_name = NULL
            WHERE gallery_id = ? AND immich_asset_id = ?
        ");

        if ($stmt->execute([$galleryId, $assetId])) {
            $immichClient->updateAssetLocation($assetId, null, null);
            $updated++;
        }
    }

    return [
        'success' => true,
        'updated' => $updated,
        'message' => "GPS supprimé de $updated photos"
    ];
}

function rotateImage($data, $immichClient)
{
    $assetId = $data['asset_id'];
    $direction = $data['direction']; // 'left' ou 'right'

    // TODO: Implémenter la rotation via l'API Immich
    // L'API Immich ne semble pas avoir d'endpoint direct pour la rotation
    // Il faudrait peut-être passer par une modification EXIF

    return [
        'success' => false,
        'message' => "La rotation n'est pas encore implémentée"
    ];
}

function updateCaption($data, $galleryId, $db)
{
    $assetId = $data['asset_id'];
    $caption = $data['caption'];

    $stmt = $db->getPDO()->prepare("
        INSERT INTO gallery_images (gallery_id, immich_asset_id, caption, caption_source) 
        VALUES (?, ?, ?, 'manual')
        ON DUPLICATE KEY UPDATE caption = ?, caption_source = 'manual'
    ");

    $success = $stmt->execute([$galleryId, $assetId, $caption, $caption]);

    return [
        'success' => $success,
        'message' => $success ? 'Légende enregistrée' : 'Erreur lors de l\'enregistrement'
    ];
}

function LocalfindDuplicates($data, $galleryId, $db)
{
    $threshold = floatval($data['threshold'] ?? 0.85);
    $assetIds = isset($data['asset_ids']) ? json_decode($data['asset_ids'], true) : null;

    // Si des assets sont spécifiés, on cherche dans la sélection
    // Sinon on cherche dans toute la galerie

    // TODO: Appeler le serveur Flask pour l'analyse
    $flaskUrl = $GLOBALS['config']['immich']['FLASK_API_URL'] ?? 'http://localhost:5001';

    return [
        'success' => false,
        'message' => 'Analyse des doublons à implémenter avec Flask'
    ];
}
