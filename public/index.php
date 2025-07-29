<?php
session_start();
require_once '../src/Auth/Auth.php';
require_once '../src/Models/Gallery.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';
$config = include('../config/config.php');
$db = new Database($config['database']);

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

$auth = new Auth();

// Si pas connecté, rediriger vers login
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Déconnexion
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// Initialiser les modèles
$galleryModel = new Gallery();
$config = include(dirname(__DIR__) . '/config/config.php');
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);

// Récupérer le rôle de l'utilisateur
$userRole = $auth->getUserRole();

// Récupérer les galeries accessibles selon le rôle
$galleries = $galleryModel->getGalleriesForRole($userRole);

// Gérer la sélection de galerie
$selectedGallery = null;
$gallerySlug = $_GET['gallery'] ?? null;
$albumId = $_GET['album'] ?? null;
$photos = [];
$albums = [];
$currentAlbum = null;

if ($gallerySlug) {
    // Récupérer la galerie spécifique
    $selectedGallery = $galleryModel->getGalleryBySlug($gallerySlug, $userRole);

    if (!$selectedGallery) {
        header('HTTP/1.1 403 Forbidden');
        die('Accès refusé à cette galerie');
    }

    // Si la galerie n'a qu'un seul album, rediriger directement vers cet album
    if (isset($selectedGallery['albums']) && count($selectedGallery['albums']) === 1 && !$albumId) {
        $singleAlbum = $selectedGallery['albums'][0];
        header('Location: ?gallery=' . $gallerySlug . '&album=' . $singleAlbum['id']);
        exit;
    }

    // Si un album est sélectionné, récupérer ses photos
    if ($albumId) {
        // Vérifier que l'album appartient bien à la galerie
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
        // Afficher la liste des albums de la galerie
        $albums = $selectedGallery['albums'];
    }
}

// Augmenter la limite de mémoire si nécessaire
ini_set('memory_limit', '512M');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $selectedGallery ? htmlspecialchars($selectedGallery['name']) : 'Mes Galeries' ?></title>

    <!-- PhotoSwipe CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.3/dist/photoswipe.css">

    <!-- Gallery CSS -->
    <link rel="stylesheet" href="assets/css/gallery.css">
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1><?= $selectedGallery ? htmlspecialchars($selectedGallery['name']) : 'Mes Galeries' ?></h1>

            <div class="header-meta">
                <div class="user-info">
                    <span>Connecté en tant que:</span>
                    <span class="user-role"><?= htmlspecialchars($userRole) ?></span>
                </div>

                <?php if ($auth->isAdmin()): ?>
                    <a href="../admin/galleries.php" class="admin-link">Administration</a>
                <?php endif; ?>

                <a href="?logout=1" class="logout-link">Déconnexion</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Breadcrumb navigation -->
        <div class="breadcrumb">
            <a href="?">Galeries</a>
            <?php if ($selectedGallery): ?>
                <span> › </span>
                <a href="?gallery=<?= urlencode($gallerySlug) ?>"><?= htmlspecialchars($selectedGallery['name']) ?></a>
                <?php if ($albumId && $currentAlbum): ?>
                    <span> › </span>
                    <span><?= htmlspecialchars($currentAlbum['albumName']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if (!$selectedGallery): ?>
            <!-- Liste des galeries -->
            <?php if (empty($galleries)): ?>
                <div class="empty-state">
                    <h3>Aucune galerie disponible</h3>
                    <p>Aucune galerie n'est accessible avec votre niveau d'autorisation.</p>
                    <?php if ($auth->isAdmin()): ?>
                        <p style="margin-top: 20px;">
                            <a href="../admin/galleries.php" class="admin-link">Créer une galerie</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($galleries as $gallery): ?>
                        <a href="?gallery=<?= urlencode($gallery['slug']) ?>" class="card">
                            <img src="<?= htmlspecialchars($gallery['cover_image_url'] ?? 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 220"%3E%3Crect fill="%23e0e0e0" width="400" height="220"/%3E%3C/svg%3E') ?>"
                                alt="<?= htmlspecialchars($gallery['name']) ?>"
                                class="card-image loading"
                                onload="this.classList.remove('loading')">

                            <div class="card-content">
                                <h3 class="card-title"><?= htmlspecialchars($gallery['name']) ?></h3>

                                <?php if ($gallery['description']): ?>
                                    <p class="card-description"><?= htmlspecialchars($gallery['description']) ?></p>
                                <?php endif; ?>

                                <div class="card-meta">
                                    <span><?= $gallery['image_count'] ?> photos</span>
                                    <span><?= count($gallery['albums']) ?> album<?= count($gallery['albums']) > 1 ? 's' : '' ?></span>
                                </div>

                                <?php if (!empty($gallery['permissions'])): ?>
                                    <div class="card-permissions">
                                        <?php foreach ($gallery['permissions'] as $perm): ?>
                                            <span class="permission-badge"><?= htmlspecialchars($perm) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($albumId && !empty($photos)): ?>
            <!-- Grille de photos -->
            <div class="photo-grid pswp-gallery" id="gallery">
                <?php foreach ($photos as $index => $photo):
                    $thumbnailUrl = "image-proxy.php?id={$photo['id']}&type=thumbnail";
                    $fullUrl = "image-proxy.php?id={$photo['id']}&type=original";
                    $caption = generateCaption($photo, $selectedGallery['id'], $db);
                    # generateCaption($photo, $savedCaption);
                    // $photo['exifInfo']['ImageDescription'] ?? $photo['originalFileName'] ?? 'Photo ' . ($index + 1);
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

        <?php elseif (!empty($albums)): ?>
            <!-- Liste des albums de la galerie -->
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
    </div>

    <!-- PhotoSwipe JS -->
    <?php if ($albumId && !empty($photos)): ?>
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
    <?php endif; ?>

    <?php if ($auth->isAdmin()): ?>
        <script>
            function editCaption(event, assetId) {
                event.preventDefault();
                event.stopPropagation();

                const galleryId = <?= $selectedGallery['id'] ?? 0 ?>;
                window.open(`../admin/edit-caption.php?asset=${assetId}&gallery=${galleryId}`,
                    'caption-editor',
                    'width=1000,height=700');
            }

            function XXeditCaption(event, assetId) {
                event.preventDefault();
                event.stopPropagation();

                // À implémenter : ouvrir l'éditeur de légende
                alert('Éditeur de légende pour l\'image ' + assetId + ' (à implémenter)');
            }
        </script>
    <?php endif; ?>
</body>

</html>