<?php
// test-album.php - Test d'accès à un album spécifique

require_once 'src/Api/ImmichClient.php';

$config = include('config/config.php');
$client = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

echo "=== TEST ALBUM IMMICH ===\n\n";

// 1. Récupérer les albums
echo "1. Récupération des albums:\n";
$albums = $client->getAllAlbums();

if (empty($albums)) {
    die("Aucun album trouvé!\n");
}

echo "   ✅ " . count($albums) . " albums trouvés\n\n";

// Afficher les albums
foreach ($albums as $i => $album) {
    echo ($i + 1) . ". {$album['albumName']} ({$album['assetCount']} photos)\n";
    echo "   ID: {$album['id']}\n";
    echo "   Créé: " . date('Y-m-d', strtotime($album['createdAt'])) . "\n\n";
}

// 2. Tester l'accès au premier album
$testAlbum = $albums[0];
echo "2. Test d'accès à l'album '{$testAlbum['albumName']}':\n";

$albumDetails = $client->getAlbum($testAlbum['id']);

if (!$albumDetails) {
    die("   ❌ Impossible de récupérer l'album\n");
}

echo "   ✅ Album récupéré\n";
echo "   - Assets dans la réponse: " . (isset($albumDetails['assets']) ? count($albumDetails['assets']) : 0) . "\n";

// 3. Si on a des assets, tester l'accès aux images
if (isset($albumDetails['assets']) && !empty($albumDetails['assets'])) {
    $firstAsset = $albumDetails['assets'][0];
    echo "\n3. Test du premier asset:\n";
    echo "   - ID: {$firstAsset['id']}\n";
    echo "   - Type: " . ($firstAsset['type'] ?? 'IMAGE') . "\n";

    // Tester les URLs
    echo "\n4. URLs générées:\n";
    echo "   - Thumbnail: " . $client->getThumbnailUrl($firstAsset['id']) . "\n";
    echo "   - Original: " . $client->getImageUrl($firstAsset['id']) . "\n";

    // Tester l'accès direct à l'image
    echo "\n5. Test d'accès direct à la thumbnail:\n";
    $thumbnailUrl = $config['immich']['api_url'] . "/api/assets/{$firstAsset['id']}/thumbnail";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $thumbnailUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-api-key: ' . $config['immich']['api_key']],
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 10
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo "   - HTTP Code: $httpCode\n";
    echo "   - Content-Type: $contentType\n";

    if ($httpCode === 200) {
        echo "   ✅ Image accessible!\n";
    } else {
        echo "   ❌ Erreur d'accès à l'image\n";

        // Essayer d'autres formats d'URL
        echo "\n6. Test d'autres formats d'URL:\n";

        $urlVariants = [
            "/api/asset/thumbnail/{$firstAsset['id']}" => "Format asset singulier",
            "/assets/{$firstAsset['id']}/thumbnail" => "Sans /api",
            "/asset/thumbnail/{$firstAsset['id']}" => "Sans /api singulier"
        ];

        foreach ($urlVariants as $endpoint => $description) {
            $testUrl = $config['immich']['api_url'] . $endpoint;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $testUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['x-api-key: ' . $config['immich']['api_key']],
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 5
            ]);

            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "   - $description: $code " . ($code === 200 ? "✅" : "❌") . "\n";
        }
    }
} else {
    echo "\n⚠️  L'album ne contient pas d'assets dans la réponse\n";
    echo "   Il est possible que les assets soient chargés séparément\n";
}

echo "\n=== FIN DU TEST ===\n";
