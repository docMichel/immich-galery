<?php
// diagnose-immich.php - Diagnostic complet de la connexion Immich (CORRIGÉ)

require_once 'src/Api/ImmichClient.php';

$config = include('config/config.php');

echo "=== DIAGNOSTIC IMMICH ===\n\n";

// 1. Vérifier la configuration
echo "1. Configuration:\n";
echo "   - URL: " . $config['immich']['api_url'] . "\n";
echo "   - Clé API: " . (!empty($config['immich']['api_key']) ? '✅ Définie (' . substr($config['immich']['api_key'], 0, 8) . '...)' : '❌ MANQUANTE') . "\n\n";

if (empty($config['immich']['api_key'])) {
    echo "⚠️  ATTENTION: La clé API n'est pas configurée!\n";
    echo "   Suivez ces étapes:\n";
    echo "   1. Connectez-vous à Immich: " . $config['immich']['api_url'] . "\n";
    echo "   2. Allez dans Account Settings > API Keys\n";
    echo "   3. Créez une nouvelle clé\n";
    echo "   4. Ajoutez-la dans config/config.php\n";
    exit(1);
}

// 2. Test de connectivité réseau
echo "2. Test de connectivité:\n";
$parsed = parse_url($config['immich']['api_url']);
$host = $parsed['host'];
$port = $parsed['port'] ?? 2283;

$socket = @fsockopen($host, $port, $errno, $errstr, 5);
if ($socket) {
    echo "   ✅ Serveur accessible sur {$host}:{$port}\n";
    fclose($socket);
} else {
    echo "   ❌ Impossible de se connecter à {$host}:{$port}\n";
    echo "   Erreur: {$errstr} ({$errno})\n";
    exit(1);
}

// 3. Test avec cURL direct sur /server-info
echo "\n3. Test API direct avec cURL:\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $config['immich']['api_url'] . '/server-info',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'x-api-key: ' . $config['immich']['api_key'],
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "   ❌ Erreur cURL: {$error}\n";
} else {
    echo "   HTTP Code: {$httpCode}\n";
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "   ✅ Serveur Immich détecté\n";
        if (isset($data['version'])) {
            echo "   Version: {$data['version']}\n";
        }
    } else {
        echo "   ❌ Réponse: {$response}\n";
    }
}

// 4. Test avec le client
echo "\n4. Test avec ImmichClient:\n";
$client = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

echo "   - Test de connexion: ";
if ($client->testConnection()) {
    echo "✅ OK\n";

    // Info serveur
    $serverInfo = $client->getServerInfo();
    if ($serverInfo) {
        echo "   - Version: " . ($serverInfo['version'] ?? 'inconnue') . "\n";
        echo "   - Disk info: " . ($serverInfo['diskUse'] ?? 'N/A') . "/" . ($serverInfo['diskSize'] ?? 'N/A') . "\n";
    }

    // 5. Récupérer les albums
    echo "\n5. Récupération des albums:\n";
    $albums = $client->getAllAlbums();

    if (is_array($albums)) {
        echo "   ✅ " . count($albums) . " albums trouvés\n";

        if (empty($albums)) {
            echo "   ℹ️  Aucun album dans votre bibliothèque Immich\n";
            echo "   Créez d'abord des albums dans Immich avant d'utiliser cette galerie\n";
        } else {
            // Afficher les 3 premiers
            echo "\n   Albums disponibles:\n";
            foreach (array_slice($albums, 0, 5) as $i => $album) {
                echo "   " . ($i + 1) . ". {$album['albumName']} ";
                echo "({$album['assetCount']} photos, ID: {$album['id']})\n";
            }

            // 6. Test d'un album spécifique
            if (!empty($albums)) {
                echo "\n6. Test d'accès à un album:\n";
                $testAlbum = $albums[0];
                $albumDetails = $client->getAlbum($testAlbum['id']);

                if ($albumDetails && isset($albumDetails['assets'])) {
                    echo "   ✅ Album '{$testAlbum['albumName']}' accessible\n";
                    echo "   - Assets: " . count($albumDetails['assets']) . "\n";

                    if (!empty($albumDetails['assets'])) {
                        $asset = $albumDetails['assets'][0];
                        echo "\n7. URLs des images (premier asset):\n";
                        echo "   - Asset ID: {$asset['id']}\n";
                        echo "   - Thumbnail: " . $client->getThumbnailUrl($asset['id']) . "\n";
                        echo "   - Original: " . $client->getImageUrl($asset['id']) . "\n";

                        // Test direct de l'API thumbnail
                        echo "\n8. Test direct de l'endpoint thumbnail:\n";
                        $thumbnailUrl = $config['immich']['api_url'] . "/assets/{$asset['id']}/thumbnail";
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $thumbnailUrl,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => ['x-api-key: ' . $config['immich']['api_key']],
                            CURLOPT_NOBODY => true
                        ]);
                        curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        echo "   - HTTP Code: {$httpCode} ";
                        echo ($httpCode === 200) ? "✅" : "❌";
                        echo "\n";
                    }
                } else {
                    echo "   ❌ Impossible d'accéder aux détails de l'album\n";
                }
            }
        }
    } else {
        echo "   ❌ Erreur lors de la récupération des albums\n";
    }
} else {
    echo "❌ Échec\n";
    echo "   Vérifiez que:\n";
    echo "   - Immich est bien démarré\n";
    echo "   - La clé API est valide\n";
    echo "   - L'URL est correcte (pas de /api à la fin)\n";
}

// 9. Statistiques serveur
echo "\n9. Statistiques serveur:\n";
$stats = $client->getServerStats();
if ($stats) {
    echo "   - Photos: " . ($stats['photos'] ?? 0) . "\n";
    echo "   - Vidéos: " . ($stats['videos'] ?? 0) . "\n";
    echo "   - Utilisation: " . ($stats['usage'] ?? 'N/A') . "\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
