<?php
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();

$config = include('../config/config.php');
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$db = new Database($config['database']);

// Récupérer toutes les images avec GPS dans votre base
$stmt = $db->getPDO()->query("
    SELECT DISTINCT immich_asset_id, latitude, longitude 
    FROM gallery_images 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");
$images = $stmt->fetchAll();

$total = count($images);
$synced = 0;

echo "<h1>Synchronisation GPS vers Immich</h1>";
echo "<p>$total images à synchroniser...</p>";

foreach ($images as $image) {
    try {
        $result = $immichClient->updateAssetLocation(
            $image['immich_asset_id'],
            $image['latitude'],
            $image['longitude']
        );
        if ($result) {
            $synced++;
            echo "✅ {$image['immich_asset_id']}<br>";
        }
    } catch (Exception $e) {
        echo "❌ {$image['immich_asset_id']}: {$e->getMessage()}<br>";
    }

    // Pause pour ne pas surcharger l'API
    usleep(100000); // 0.1 seconde
}

echo "<p><strong>Terminé : $synced/$total synchronisés</strong></p>";
