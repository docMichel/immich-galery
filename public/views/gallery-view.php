<?php
// public/views/gallery-view.php
// Variables disponibles: $galleryModel, $userRole, $auth, $gallerySlug, $albumId, $immichClient, $db

// Fonction pour générer une légende
function generateCaption($photo, $galleryId, $db)
{
    // 1. Chercher en base
    $stmt = $db->getPDO()->prepare("
        SELECT caption FROM gallery_images 
        WHERE gallery_id = ? AND immich_asset_id = ?
    ");
    $stmt->execute([$galleryId, $photo['id']]);
    $savedCaption = $stmt->fetchColumn();

    if (!empty($savedCaption)) {
        return $savedCaption;
    }

    // 2. EXIF Description
    if (!empty($photo['exifInfo']['ImageDescription'])) {
        return $photo['exifInfo']['ImageDescription'];
    }

    // 3. Nom du fichier
    if (!empty($photo['originalFileName'])) {
        return pathinfo($photo['originalFileName'], PATHINFO_FILENAME);
    }

    return 'Photo';
}

// Récupérer la galerie
$selectedGallery = $galleryModel->getGalleryBySlug($gallerySlug, $userRole);

if (!$selectedGallery) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès refusé à cette galerie');
}

// Si la galerie n'a qu'un seul album, rediriger directement
if (isset($selectedGallery['albums']) && count($selectedGallery['albums']) === 1 && !$albumId) {
    $singleAlbum = $selectedGallery['albums'][0];
    header('Location: ?gallery=' . $gallerySlug . '&album=' . $singleAlbum['id']);
    exit;
}

$photos = [];
$albums = [];
$currentAlbum = null;

// Si un album est sélectionné
if ($albumId) {
    // Vérifier que l'album appartient à la galerie
    $albumBelongsToGallery = false;
    foreach ($selectedGallery['albums'] as $album) {
        if ($album['id'] === $albumId) {
            $albumBelongsToGallery = true;
            break;
        }
    }

    if (!$albumBelongsToGallery) {
        header('HTTP/1.1 403 Forbidden');
        die('Album non autorisé');
    }

    $currentAlbum = $immichClient->getAlbum($albumId);
    if ($currentAlbum && isset($currentAlbum['assets'])) {
        $photos = $currentAlbum['assets'];
    }
} else {
    // Liste des albums
    $albums = $selectedGallery['albums'];
}
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="?">Galeries</a>
    <span> › </span>
    <a href="?gallery=<?= urlencode($gallerySlug) ?>"><?= htmlspecialchars($selectedGallery['name']) ?></a>
    <?php if ($albumId && $currentAlbum): ?>
        <span> › </span>
        <span><?= htmlspecialchars($currentAlbum['albumName']) ?></span>
    <?php endif; ?>
</div>

<?php if ($albumId && !empty($photos)): ?>
    <!-- Grille de photos -->
    <div class="photo-grid pswp-gallery" id="gallery">
        <?php foreach ($photos as $index => $photo):
            $thumbnailUrl = "image-proxy.php?id={$photo['id']}&type=thumbnail";
            $fullUrl = "image-proxy.php?id={$photo['id']}&type=original";
            $caption = generateCaption($photo, $selectedGallery['id'], $db);
        ?>
            <a href="<?= htmlspecialchars($fullUrl) ?>"
                class="photo-item"
                data-pswp-width="<?= $photo['exifInfo']['ImageWidth'] ?? 2000 ?>"
                data-pswp-height="<?= $photo['exifInfo']['ImageHeight'] ?? 1500 ?>"
                data-caption="<?= htmlspecialchars($caption) ?>">
                <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                    alt="<?= htmlspecialchars($caption) ?>"
                    class="loading"
                    loading="lazy"
                    onload="this.classList.remove('loading')">
                <div class="photo-caption">
                    <div class="photo-caption-text"><?= htmlspecialchars($caption) ?></div>
                </div>
                <?php if ($auth->isAdmin()): ?>
                    <button class="caption-edit-btn"
                        onclick="editCaption(event, '<?= $photo['id'] ?>')">
                        Éditer légende
                    </button>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- PhotoSwipe JS pour les photos -->
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.3/dist/photoswipe-lightbox.esm.js';

        const lightbox = new PhotoSwipeLightbox({
            gallery: '#gallery',
            children: 'a',
            pswpModule: () => import('https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.esm.js'),
            showHideAnimationType: 'zoom',
            bgOpacity: 0.9
        });

        lightbox.init();
    </script>

<?php elseif (!empty($albums)): ?>
    <!-- Liste des albums -->
    <div class="cards-grid">
        <?php foreach ($albums as $album):
            $albumDetails = $immichClient->getAlbum($album['id']);
            $thumbnailUrl = $albumDetails['albumThumbnailAssetId']
                ? "image-proxy.php?id={$albumDetails['albumThumbnailAssetId']}&type=thumbnail"
                : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 220"%3E%3Crect fill="%23e0e0e0" width="400" height="220"/%3E%3C/svg%3E';
        ?>
            <a href="?gallery=<?= urlencode($gallerySlug) ?>&album=<?= urlencode($album['id']) ?>" class="card">
                <img src="<?= htmlspecialchars($thumbnailUrl) ?>"
                    alt="<?= htmlspecialchars($album['albumName']) ?>"
                    class="card-image loading"
                    loading="lazy"
                    onload="this.classList.remove('loading')">

                <div class="card-content">
                    <h3 class="card-title"><?= htmlspecialchars($album['albumName']) ?></h3>
                    <div class="card-meta">
                        <span><?= $album['assetCount'] ?> photos</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>