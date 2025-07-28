<?php
// Augmenter la limite de mémoire temporairement
ini_set('memory_limit', '512M');

// Activer le rapport d'erreurs en mode développement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Créer un fichier de log local
ini_set('error_log', __DIR__ . '/errors.log');

require_once dirname(__DIR__) . '/src/Api/ImmichClient.php';

$config = include(dirname(__DIR__) . '/config/config.php');
$client = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$photosPerPage = 100;

// Récupérer l'album demandé ou la liste
$albumId = $_GET['album'] ?? null;
$currentAlbum = null;
$albums = [];
$photos = [];
$totalPhotos = 0;

try {
    if ($albumId) {
        $currentAlbum = $client->getAlbum($albumId);
        if ($currentAlbum && isset($currentAlbum['assets'])) {
            $totalPhotos = count($currentAlbum['assets']);

            // Pagination des photos
            $offset = ($page - 1) * $photosPerPage;
            $photos = array_slice($currentAlbum['assets'], $offset, $photosPerPage);

            // Libérer la mémoire des assets complets
            $currentAlbum['assets'] = null;
        }
    } else {
        $albums = $client->getAllAlbums();
    }
} catch (Exception $e) {
    error_log("Erreur Immich Gallery: " . $e->getMessage());
    die("<div style='padding: 20px; background: #fee; color: #c00;'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>");
}

$totalPages = $albumId ? ceil($totalPhotos / $photosPerPage) : 0;
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentAlbum ? htmlspecialchars($currentAlbum['albumName']) : 'Galeries Immich' ?></title>

    <!-- PhotoSwipe CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">
    <link rel="stylesheet" href="https://unpkg.com/photoswipe-dynamic-caption-plugin@1.2.7/dist/photoswipe-dynamic-caption-plugin.css">

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
            line-height: 1.6;
        }

        .pswp__custom-caption {
            background: rgba(0, 0, 0, 0.75);
            color: white;
            padding: 10px 20px;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 4px;
            font-size: 14px;
            text-align: center;
            max-width: 90%;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #111;
        }

        .header-meta {
            color: #666;
            font-size: 0.95rem;
        }

        .back-link {
            color: #0066cc;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* Albums Grid */
        .album-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .album-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }

        .album-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .album-thumb {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #e0e0e0;
        }

        .album-info {
            padding: 20px;
        }

        .album-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #111;
        }

        .album-meta {
            color: #666;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Photos Grid */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            margin-bottom: 40px;
        }

        .photo-item {
            aspect-ratio: 1;
            overflow: hidden;
            border-radius: 8px;
            background: #e0e0e0;
            cursor: pointer;
            position: relative;
            display: block;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }

        /* Légendes des photos */
        .photo-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 70%, transparent 100%);
            color: white;
            padding: 20px 10px 8px;
            font-size: 12px;
            line-height: 1.3;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .photo-item:hover .photo-caption {
            opacity: 1;
        }

        .photo-caption-title {
            font-weight: 500;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .photo-caption-meta {
            font-size: 11px;
            opacity: 0.8;
        }

        /* Légendes des albums (toujours visibles) */
        .album-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, transparent 100%);
            color: white;
            padding: 20px 15px 10px;
        }

        .album-caption-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .album-caption-meta {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            background: white;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #f0f0f0;
            border-color: #999;
        }

        .pagination .current {
            background: #0066cc;
            color: white;
            border-color: #0066cc;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: default;
        }

        /* Footer */
        .footer {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
            font-size: 0.875rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1><?= $currentAlbum ? htmlspecialchars($currentAlbum['albumName']) : 'Mes Albums Immich' ?></h1>

            <?php if ($currentAlbum): ?>
                <div class="header-meta">
                    <a href="?" class="back-link">← Retour aux albums</a>
                    <span style="margin: 0 10px;">•</span>
                    <span><?= $totalPhotos ?> photos au total</span>
                    <?php if ($totalPages > 1): ?>
                        <span style="margin: 0 10px;">•</span>
                        <span>Page <?= $page ?> sur <?= $totalPages ?></span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="header-meta">
                    <?= count($albums) ?> albums disponibles
                </div>
            <?php endif; ?>
        </header>

        <?php if ($currentAlbum): ?>
            <!-- Galerie de photos -->
            <?php if (empty($photos)): ?>
                <div class="error-message">
                    Aucune photo trouvée dans cet album.
                </div>
            <?php else: ?>
                <div class="photo-grid pswp-gallery" id="gallery">
                    <?php foreach ($photos as $index => $asset):
                        $globalIndex = ($page - 1) * $photosPerPage + $index;

                        // URLs via le proxy
                        $thumbnailUrl = "image-proxy.php?id={$asset['id']}&type=thumbnail&size=thumbnail";
                        $previewUrl = "image-proxy.php?id={$asset['id']}&type=thumbnail&size=preview";
                        $originalUrl = "image-proxy.php?id={$asset['id']}&type=original";

                        // Construire la légende
                        $caption = '';
                        $captionParts = [];
                        // description
                        if (isset($asset['exifInfo']['ImageDescription']) && !empty($asset['exifInfo']['ImageDescription'])) {
                            $captionParts['description'] = $asset['exifInfo']['ImageDescription'];
                        }
                        // Nom du fichier
                        $fileName = $asset['originalFileName'] ?? 'Photo ' . ($globalIndex + 1);
                        $captionParts['title'] = pathinfo($fileName, PATHINFO_FILENAME);

                        // Date de prise de vue
                        if (isset($asset['exifInfo']['DateTimeOriginal'])) {
                            $date = date('d/m/Y à H:i', strtotime($asset['exifInfo']['DateTimeOriginal']));
                            $captionParts['date'] = $date;
                        } elseif (isset($asset['fileCreatedAt'])) {
                            $date = date('d/m/Y', strtotime($asset['fileCreatedAt']));
                            $captionParts['date'] = $date;
                        }

                        // Lieu
                        if (isset($asset['exifInfo']['city']) || isset($asset['exifInfo']['state'])) {
                            $location = [];
                            if (isset($asset['exifInfo']['city'])) $location[] = $asset['exifInfo']['city'];
                            if (isset($asset['exifInfo']['state'])) $location[] = $asset['exifInfo']['state'];
                            if (isset($asset['exifInfo']['country'])) $location[] = $asset['exifInfo']['country'];
                            $captionParts['location'] = implode(', ', $location);
                        }

                        // Légende complète pour PhotoSwipe
                        $fullCaption = $captionParts['title'];
                        if (isset($captionParts['description'])) {
                            $fullCaption = $captionParts['description']; // Priorité à la description
                        }
                        if (isset($captionParts['date'])) $fullCaption .= ' - ' . $captionParts['date'];
                        if (isset($captionParts['location'])) $fullCaption .= ' - ' . $captionParts['location'];

                        // Dimensions
                        $width = 2000;
                        $height = 1500;
                        if (isset($asset['exifInfo'])) {
                            $width = $asset['exifInfo']['ImageWidth'] ?? $asset['exifInfo']['ExifImageWidth'] ?? 2000;
                            $height = $asset['exifInfo']['ImageHeight'] ?? $asset['exifInfo']['ExifImageHeight'] ?? 1500;
                        }
                    ?>
                        <a href="<?= htmlspecialchars($previewUrl) ?>"
                            class="photo-item"
                            data-pswp-width="<?= $width ?>"
                            data-pswp-height="<?= $height ?>"
                            data-full-url="<?= htmlspecialchars($originalUrl) ?>"
                            data-caption="<?= htmlspecialchars($fullCaption) ?>">
                            <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                                alt="<?= htmlspecialchars($captionParts['title']) ?>"
                                class="loading"
                                loading="lazy"
                                onload="this.classList.remove('loading')"
                                onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22400%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-family=%22sans-serif%22 font-size=%2218%22%3EErreur%3C/text%3E%3C/svg%3E'">
                            <div class="photo-caption">
                                <div class="photo-caption-title"><?= htmlspecialchars($captionParts['title']) ?></div>
                                <?php if (isset($captionParts['date']) || isset($captionParts['location'])): ?>
                                    <div class="photo-caption-meta">
                                        <?php if (isset($captionParts['date'])): ?>
                                            <?= htmlspecialchars($captionParts['date']) ?>
                                        <?php endif; ?>
                                        <?php if (isset($captionParts['date']) && isset($captionParts['location'])): ?>
                                            •
                                        <?php endif; ?>
                                        <?php if (isset($captionParts['location'])): ?>
                                            <?= htmlspecialchars($captionParts['location']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?album=<?= urlencode($albumId) ?>&page=1">Début</a>
                            <a href="?album=<?= urlencode($albumId) ?>&page=<?= $page - 1 ?>">← Précédent</a>
                        <?php else: ?>
                            <span class="disabled">Début</span>
                            <span class="disabled">← Précédent</span>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        if ($start > 1) echo '<span>...</span>';

                        for ($i = $start; $i <= $end; $i++):
                            if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?album=<?= urlencode($albumId) ?>&page=<?= $i ?>"><?= $i ?></a>
                        <?php endif;
                        endfor;

                        if ($end < $totalPages) echo '<span>...</span>';
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?album=<?= urlencode($albumId) ?>&page=<?= $page + 1 ?>">Suivant →</a>
                            <a href="?album=<?= urlencode($albumId) ?>&page=<?= $totalPages ?>">Fin</a>
                        <?php else: ?>
                            <span class="disabled">Suivant →</span>
                            <span class="disabled">Fin</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <!-- Liste des albums -->
            <?php if (empty($albums)): ?>
                <div class="error-message">
                    Aucun album trouvé. Créez des albums dans Immich pour les voir ici.
                </div>
            <?php else: ?>
                <div class="album-grid">
                    <?php foreach ($albums as $album):
                        $thumbnailUrl = $album['albumThumbnailAssetId']
                            ? "image-proxy.php?id={$album['albumThumbnailAssetId']}&type=thumbnail&size=thumbnail"
                            : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"%3E%3Crect fill="%23e0e0e0" width="400" height="300"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-family="sans-serif" font-size="20"%3EPas d\'image%3C/text%3E%3C/svg%3E';

                        $dateRange = '';
                        if ($album['startDate']) {
                            $start = date('M Y', strtotime($album['startDate']));
                            $end = date('M Y', strtotime($album['endDate']));
                            $dateRange = $start === $end ? $start : $start . ' - ' . $end;
                        }
                    ?>
                        <a href="?album=<?= urlencode($album['id']) ?>" class="album-card">
                            <div style="position: relative;">
                                <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                                    alt="<?= htmlspecialchars($album['albumName']) ?>"
                                    class="album-thumb loading"
                                    loading="lazy"
                                    onload="this.classList.remove('loading')"
                                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 300%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-family=%22sans-serif%22 font-size=%2220%22%3EErreur%3C/text%3E%3C/svg%3E'">
                                <div class="album-caption">
                                    <div class="album-caption-title"><?= htmlspecialchars($album['albumName']) ?></div>
                                    <div class="album-caption-meta">
                                        <?= number_format($album['assetCount']) ?> photos
                                        <?php if ($dateRange): ?>
                                            • <?= $dateRange ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <footer class="footer">
            <p>Galerie Immich • <?= count($albums) ?> albums •
                <a href="debug.php" style="color: inherit;">Debug</a>
            </p>
        </footer>
    </div>

    <!-- PhotoSwipe avec plugin Caption -->
    <?php if ($currentAlbum && !empty($photos)): ?>
        <!-- script type="module">
            import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';
            import PhotoSwipeDynamicCaption from 'https://unpkg.com/photoswipe-dynamic-caption-plugin@1.2.7/dist/photoswipe-dynamic-caption-plugin.esm.js';

            const lightbox = new PhotoSwipeLightbox({
                gallery: '#gallery',
                children: 'a',
                pswpModule: () => import('https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js'),

                // Options
                showHideAnimationType: 'zoom',
                bgOpacity: 0.9,

                // Zoom
                secondaryZoomLevel: 2,
                maxZoomLevel: 4,
            });

            // Plugin pour les légendes
            const captionPlugin = new PhotoSwipeDynamicCaption(lightbox, {
                type: 'below',
                captionContent: (slide) => {
                    return slide.data.element.getAttribute('data-caption');
                }
            });

            // Charger l'image originale en haute qualité après l'ouverture
            lightbox.on('contentLoadImage', (e) => {
                const {
                    content,
                    isLazy
                } = e;

                if (content.data.element) {
                    const fullUrl = content.data.element.dataset.fullUrl;
                    if (fullUrl && content.pictureElement) {
                        // Précharger l'image HD
                        const img = new Image();
                        img.onload = () => {
                            // Remplacer par l'image HD une fois chargée
                            if (content.element && content.element.src !== fullUrl) {
                                content.element.src = fullUrl;
                            }
                        };
                        img.src = fullUrl;
                    }
                }
            });

            lightbox.init();

            console.log('PhotoSwipe initialisé pour <?= count($photos) ?> photos (page <?= $page ?>/<?= $totalPages ?>)');
        </script -->
        <script type="module">
            import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';

            const lightbox = new PhotoSwipeLightbox({
                gallery: '#gallery',
                children: 'a',
                pswpModule: () => import('https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js'),

                showHideAnimationType: 'zoom',
                bgOpacity: 0.9,
                secondaryZoomLevel: 2,
                maxZoomLevel: 4,
            });

            // Ajouter les légendes manuellement
            lightbox.on('uiRegister', function() {
                lightbox.pswp.ui.registerElement({
                    name: 'custom-caption',
                    order: 9,
                    isButton: false,
                    appendTo: 'root',
                    html: 'Caption text',
                    onInit: (el, pswp) => {
                        lightbox.pswp.on('change', () => {
                            const currSlideElement = lightbox.pswp.currSlide.data.element;
                            el.innerHTML = currSlideElement.getAttribute('data-caption') || '';
                        });
                    }
                });
            });

            lightbox.init();
        </script>
    <?php endif; ?>
</body>

</html>