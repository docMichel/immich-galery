<?php
// Activer TOUS les rapports d'erreur
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('error_log', __DIR__ . '/errors.log');

// Debug info
$debug = isset($_GET['debug']);

if ($debug) {
    echo "<pre>DEBUG MODE ACTIVÉ\n\n";
    echo "PHP Version: " . phpversion() . "\n";
    echo "Script: " . __FILE__ . "\n\n";
}

// Charger les fichiers requis
try {
    $immichClientPath = dirname(__DIR__) . '/src/Api/ImmichClient.php';
    $configPath = dirname(__DIR__) . '/config/config.php';

    if ($debug) {
        echo "Chargement ImmichClient depuis: $immichClientPath\n";
        echo "Existe: " . (file_exists($immichClientPath) ? 'OUI' : 'NON') . "\n\n";
    }

    if (!file_exists($immichClientPath)) {
        throw new Exception("ImmichClient.php non trouvé dans: $immichClientPath");
    }

    require_once $immichClientPath;

    if ($debug) {
        echo "ImmichClient chargé ✅\n\n";
        echo "Chargement config depuis: $configPath\n";
    }

    $config = include($configPath);

    if ($debug) {
        echo "Config chargée ✅\n";
        echo "URL Immich: " . $config['immich']['api_url'] . "\n";
        echo "Clé API: " . substr($config['immich']['api_key'], 0, 10) . "...\n\n";
    }

    $client = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

    if ($debug) {
        echo "Client créé ✅\n\n";
    }

    // Récupérer l'album demandé ou la liste
    $albumId = $_GET['album'] ?? null;
    $currentAlbum = null;
    $albums = [];

    if ($albumId) {
        if ($debug) echo "Chargement de l'album ID: $albumId\n";
        $currentAlbum = $client->getAlbum($albumId);
        if ($debug) {
            echo "Album chargé: " . ($currentAlbum ? 'OUI' : 'NON') . "\n";
            if ($currentAlbum) {
                echo "Nom: " . $currentAlbum['albumName'] . "\n";
                echo "Photos: " . count($currentAlbum['assets']) . "\n";
            }
        }
    } else {
        if ($debug) echo "Chargement de la liste des albums...\n";
        $albums = $client->getAllAlbums();
        if ($debug) {
            echo "Albums chargés: " . count($albums) . "\n\n";
            echo "</pre><hr>\n\n";
        }
    }
} catch (Exception $e) {
    echo "<div style='background: #fee; border: 1px solid #f88; padding: 20px; margin: 20px; border-radius: 5px;'>";
    echo "<h2>Erreur</h2>";
    echo "<p><strong>" . $e->getMessage() . "</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentAlbum ? htmlspecialchars($currentAlbum['albumName']) : 'Galeries Immich' ?></title>

    <!-- PhotoSwipe CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            margin-bottom: 30px;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .back-link {
            color: #0066cc;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .album-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .album-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .album-thumb {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e0e0e0;
        }

        .album-info {
            padding: 15px;
        }

        .album-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .album-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .photo-item {
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: 4px;
            background: #e0e0e0;
            cursor: pointer;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }

        .debug-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #ff6600;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1><?= $currentAlbum ? htmlspecialchars($currentAlbum['albumName']) : 'Mes Albums Immich' ?></h1>

            <?php if ($currentAlbum): ?>
                <div>
                    <a href="?" class="back-link">← Retour aux albums</a>
                    <span style="margin: 0 10px;">•</span>
                    <span><?= count($currentAlbum['assets']) ?> photos</span>
                </div>
            <?php else: ?>
                <p style="color: #666;"><?= count($albums) ?> albums disponibles</p>
            <?php endif; ?>
        </header>

        <?php if ($currentAlbum): ?>
            <!-- Galerie de photos -->
            <div class="photo-grid pswp-gallery" id="gallery">
                <?php
                $assetsToShow = array_slice($currentAlbum['assets'], 0, 100); // Limiter pour le test
                foreach ($assetsToShow as $index => $asset):
                    $thumbnailUrl = $config['immich']['api_url'] . "/api/assets/{$asset['id']}/thumbnail?key={$config['immich']['api_key']}";
                    $originalUrl = $config['immich']['api_url'] . "/api/assets/{$asset['id']}/original?key={$config['immich']['api_key']}";
                    $width = $asset['exifInfo']['ImageWidth'] ?? 2000;
                    $height = $asset['exifInfo']['ImageHeight'] ?? 1500;
                ?>
                    <a href="<?= htmlspecialchars($originalUrl) ?>"
                        class="photo-item"
                        data-pswp-width="<?= $width ?>"
                        data-pswp-height="<?= $height ?>">
                        <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                            alt="Photo <?= $index + 1 ?>"
                            loading="lazy"
                            onerror="console.error('Erreur chargement image:', this.src); this.style.background='#f88';">
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (count($currentAlbum['assets']) > 100): ?>
                <p style="text-align: center; margin-top: 20px; color: #666;">
                    Affichage limité aux 100 premières photos (sur <?= count($currentAlbum['assets']) ?>)
                </p>
            <?php endif; ?>

        <?php else: ?>
            <!-- Liste des albums -->
            <div class="album-grid">
                <?php foreach ($albums as $album):
                    $thumbnailUrl = $album['albumThumbnailAssetId']
                        ? $config['immich']['api_url'] . "/api/assets/{$album['albumThumbnailAssetId']}/thumbnail?key={$config['immich']['api_key']}"
                        : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 200"%3E%3Crect fill="%23ddd" width="400" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-family="sans-serif" font-size="20"%3ENo Image%3C/text%3E%3C/svg%3E';
                ?>
                    <a href="?album=<?= urlencode($album['id']) ?>" class="album-card">
                        <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                            alt="<?= htmlspecialchars($album['albumName']) ?>"
                            class="album-thumb"
                            loading="lazy"
                            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 200%22%3E%3Crect fill=%22%23f88%22 width=%22400%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23fff%22 font-family=%22sans-serif%22 font-size=%2220%22%3EErreur%3C/text%3E%3C/svg%3E'">
                        <div class="album-info">
                            <div class="album-title"><?= htmlspecialchars($album['albumName']) ?></div>
                            <div class="album-meta"><?= $album['assetCount'] ?> photos</div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$debug): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['debug' => 1])) ?>" class="debug-btn">Debug</a>
    <?php endif; ?>

    <!-- PhotoSwipe -->
    <?php if ($currentAlbum): ?>
        <script type="module">
            import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';

            const lightbox = new PhotoSwipeLightbox({
                gallery: '#gallery',
                children: 'a',
                pswpModule: () => import('https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js')
            });

            lightbox.init();

            console.log('PhotoSwipe initialisé pour', document.querySelectorAll('#gallery a').length, 'photos');
        </script>
    <?php endif; ?>

    <script>
        // Logger les erreurs de chargement d'images
        document.addEventListener('error', function(e) {
            if (e.target.tagName === 'IMG') {
                console.error('Erreur de chargement image:', e.target.src);
            }
        }, true);

        console.log('Galerie Immich chargée');
        <?php if ($currentAlbum): ?>
            console.log('Album:', <?= json_encode($currentAlbum['albumName']) ?>);
            console.log('Photos:', <?= count($currentAlbum['assets']) ?>);
        <?php else: ?>
            console.log('Albums:', <?= count($albums) ?>);
        <?php endif; ?>
    </script>
</body>

</html>