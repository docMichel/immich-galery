<?php
// debug.php - Script de débogage pour la galerie

// Activer l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Debug Galerie Immich</h1>";
echo "<pre>";

// 1. Vérifier les chemins
echo "=== CHEMINS ===\n";
echo "Script actuel: " . __FILE__ . "\n";
echo "Dossier actuel: " . __DIR__ . "\n";
echo "Dossier racine: " . dirname(__DIR__) . "\n\n";

// 2. Vérifier les fichiers requis
echo "=== FICHIERS REQUIS ===\n";
$requiredFiles = [
    '../src/Api/ImmichClient.php',
    '../src/Services/Database.php',
    '../config/config.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    echo "$file: ";
    if (file_exists($fullPath)) {
        echo "✅ Existe\n";
    } else {
        echo "❌ MANQUANT (cherché dans: $fullPath)\n";
    }
}

// 3. Tester le chargement de la config
echo "\n=== CONFIG ===\n";
try {
    $config = include(__DIR__ . '/../config/config.php');
    echo "Config chargée: ✅\n";
    echo "URL Immich: " . ($config['immich']['api_url'] ?? 'NON DÉFINIE') . "\n";
    echo "Clé API: " . (isset($config['immich']['api_key']) && !empty($config['immich']['api_key']) ? '✅ Définie' : '❌ MANQUANTE') . "\n";
} catch (Exception $e) {
    echo "Erreur config: " . $e->getMessage() . "\n";
}

// 4. Tester le chargement d'ImmichClient
echo "\n=== IMMICH CLIENT ===\n";
try {
    require_once __DIR__ . '/../src/Api/ImmichClient.php';
    echo "ImmichClient chargé: ✅\n";

    $client = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
    echo "Client créé: ✅\n";

    // Tester la connexion
    echo "Test connexion: ";
    if ($client->testConnection()) {
        echo "✅\n";

        // Récupérer les albums
        $albums = $client->getAllAlbums();
        echo "Albums récupérés: " . count($albums) . "\n";

        if (count($albums) > 0) {
            echo "\nPremiers albums:\n";
            foreach (array_slice($albums, 0, 3) as $album) {
                echo "- {$album['albumName']} ({$album['assetCount']} photos)\n";
            }
        }
    } else {
        echo "❌ Échec\n";
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

// 5. Logs d'erreur PHP
echo "\n=== LOGS D'ERREUR ===\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "log_errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "\n";
echo "display_errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";

// Sur macOS, les logs sont souvent dans :
$possibleLogPaths = [
    '/var/log/apache2/error_log',
    '/usr/local/var/log/httpd/error_log',
    '/opt/homebrew/var/log/httpd/error_log',
    '/tmp/php-error.log',
    sys_get_temp_dir() . '/php-error.log'
];

echo "\nEmplacements possibles des logs:\n";
foreach ($possibleLogPaths as $path) {
    echo "- $path: ";
    if (file_exists($path)) {
        echo "✅ Existe";
        if (is_readable($path)) {
            echo " (lisible)";
            // Afficher les dernières lignes
            $lines = array_slice(file($path), -5);
            if (!empty($lines)) {
                echo "\n  Dernières lignes:\n";
                foreach ($lines as $line) {
                    echo "  " . trim($line) . "\n";
                }
            }
        } else {
            echo " (non lisible)";
        }
    } else {
        echo "❌";
    }
    echo "\n";
}

echo "</pre>";

// Créer un bouton pour tester la galerie
echo '<hr>';
echo '<h2>Test de la galerie</h2>';
echo '<a href="index.php" style="padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; display: inline-block;">Ouvrir la galerie</a>';
