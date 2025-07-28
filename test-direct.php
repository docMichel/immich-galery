<?php
// test-direct.php - Test direct des différents endpoints Immich

$config = include('config/config.php');
$baseUrl = $config['immich']['api_url'];
$apiKey = $config['immich']['api_key'];

echo "=== TEST DIRECT DES ENDPOINTS IMMICH ===\n\n";
echo "URL: $baseUrl\n";
echo "Clé: " . substr($apiKey, 0, 10) . "...\n\n";

// Fonction pour tester un endpoint
function testEndpoint($url, $apiKey, $description)
{
    echo "Test: $description\n";
    echo "URL: $url\n";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP: $httpCode\n";

    if ($error) {
        echo "Erreur: $error\n";
    } elseif ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ Succès\n";
            echo "Données: " . json_encode(array_slice($data, 0, 3), JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✅ Succès (réponse non-JSON)\n";
        }
    } else {
        echo "❌ Échec\n";
        echo "Réponse: " . substr($response, 0, 200) . "\n";
    }
    echo str_repeat("-", 50) . "\n\n";
}

// Tester différents endpoints possibles
$endpoints = [
    '/server-info' => 'Server Info (ancien)',
    '/api/server-info' => 'Server Info (avec /api)',
    '/server-info/ping' => 'Ping',
    '/server-info/version' => 'Version',
    '/server-info/stats' => 'Stats',
    '/albums' => 'Albums (sans /api)',
    '/api/albums' => 'Albums (avec /api)',
    '/album' => 'Album (singulier)',
    '/api/album' => 'Album API',
];

foreach ($endpoints as $endpoint => $description) {
    testEndpoint($baseUrl . $endpoint, $apiKey, $description);
}

// Test spécial : obtenir la liste des endpoints disponibles
echo "\n=== RECHERCHE DES ENDPOINTS DISPONIBLES ===\n";

// Essayer l'endpoint OpenAPI/Swagger
$swaggerEndpoints = [
    '/api-docs',
    '/swagger',
    '/openapi',
    '/api/openapi.json',
    '/docs',
    '/api/docs'
];

foreach ($swaggerEndpoints as $endpoint) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['x-api-key: ' . $apiKey],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_NOBODY => true
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "✅ Documentation trouvée à: $endpoint\n";
    }
}

echo "\n=== FIN DES TESTS ===\n";
