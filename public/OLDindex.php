<?php
// public/index.php - Point d'entrée principal

session_start();

// Configuration et autoload simple
require_once '../src/Services/Auth.php';
require_once '../src/Services/Database.php';
require_once '../src/Services/ImmichClient.php';
require_once '../src/Services/Geolocation.php';

$auth = new Auth();
$config = include('../config/config.php');

// Gestion de la connexion
if ($_POST && isset($_POST['password'])) {
    if ($auth->login($_POST['password'])) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Mot de passe incorrect";
    }
}

// Si pas connecté, afficher le formulaire
if (!$auth->isAuthenticated()) {
    echo $auth->getLoginForm();
    if (isset($error)) {
        echo "<script>alert('$error')</script>";
    }
    exit;
}

// Utilisateur connecté - Afficher la galerie
$db = new Database($config['database']);
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$geolocation = new Geolocation($db->getPDO());

// Récupérer les galeries configurées
$stmt = $db->getPDO()->query("SELECT * FROM galleries WHERE is_public = 1 ORDER BY created_at DESC");
$galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si une galerie spécifique est demandée
$currentGallery = null;
if (isset($_GET['gallery'])) {
    $stmt = $db->getPDO()->prepare("SELECT * FROM galleries WHERE slug = ?");
    $stmt->execute([$_GET['gallery']]);
    $currentGallery = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $currentGallery ? $currentGallery['name'] : 'Galeries Photos' ?></title>

    <!-- PhotoSwipe 5 CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.2/dist/photoswipe.css">

    <!-- Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #333;
        }

        .header .user-info {
            float: right;
            color: #666;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .gallery-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .gallery-card:hover {
            transform: translateY(-2px);
        }

        .gallery-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .gallery-card .info {
            padding: 1rem;
        }

        .gallery-card h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .gallery-card p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .photo-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }

        .photo-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
            color: white;
            padding: 1rem;
            font-size: 0.8rem;
            line-height: 1.3;
            max-height: 100px;
            overflow: hidden;
        }

        .photo-author {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .logout-link {
            color: #dc3545;
            text-decoration: none;
            margin-left: 1rem;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <h1><?= $currentGallery ? $currentGallery['name'] : 'Galeries Photos' ?></h1>
        <div class="user-info">
            Connecté en tant que: <?= ucfirst($auth->getUserRole()) ?>
            <?php if ($auth->isAdmin()): ?>
                <a href="admin/">Administration</a>
            <?php endif; ?>
            <a href="?logout=1" class="logout-link">Déconnexion</a>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="container">
        <?php if ($currentGallery): ?>
            <!-- Vue galerie spécifique -->
            <a href="?" class="back-link">← Retour aux galeries</a>

            <div class="pswp-gallery photo-grid" id="gallery-<?= $currentGallery['id'] ?>">
                <?php
                // Récupérer les images de la galerie avec géolocalisation
                $stmt = $db->getPDO()->prepare("SELECT * FROM gallery_images WHERE gallery_id = ? ORDER BY created_at ASC");
                $stmt->execute([$currentGallery['id']]);
                $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($images as $image):
                    $thumbnailUrl = $immichClient->getThumbnailUrl($image['immich_asset_id']);
                    $fullUrl = $immichClient->getImageUrl($image['immich_asset_id']);

                    // Enrichir avec géolocalisation si pas déjà fait
                    if ($image['latitude'] && !$image['location_name']) {
                        $enriched = $geolocation->enrichImageLocation($image);
                        $image['caption'] = $enriched['enhanced_caption'];
                    }
                ?>
                    <div class="photo-item">
                        <a href="<?= $fullUrl ?>"
                            data-pswp-width="2000"
                            data-pswp-height="1500"
                            data-pswp-caption="<?= htmlspecialchars($image['caption']) ?>">
                            <img src="<?= $thumbnailUrl ?>" alt="">
                        </a>

                        <?php if ($image['author']): ?>
                            <div class="photo-author">📸 <?= htmlspecialchars($image['author']) ?></div>
                        <?php endif; ?>

                        <div class="photo-caption">
                            <?= nl2br(htmlspecialchars(substr($image['caption'], 0, 150))) ?>
                            <?php if (strlen($image['caption']) > 150): ?>...<?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Liste des galeries -->
            <div class="gallery-grid">
                <?php foreach ($galleries as $gallery): ?>
                    <div class="gallery-card">
                        <img src="https://picsum.photos/400/200?random=<?= $gallery['id'] ?>" alt="">
                        <div class="info">
                            <h3><a href="?gallery=<?= $gallery['slug'] ?>" style="text-decoration: none; color: inherit;">
                                    <?= htmlspecialchars($gallery['name']) ?>
                                </a></h3>
                            <p><?= htmlspecialchars($gallery['description']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PhotoSwipe 5 JavaScript -->
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.2/dist/photoswipe-lightbox.esm.js';

        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: 'a',
            pswpModule: () => import('https://unpkg.com/photoswipe@5.4.2/dist/photoswipe.esm.js'),

            paddingFn: (viewportSize) => ({
                top: 30,
                bottom: 100,
                left: 50,
                right: 50
            })
        });

        lightbox.init();
    </script>

    <?php
    // Gestion de la déconnexion
    if (isset($_GET['logout'])) {
        $auth->logout();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    ?>
</body>

</html>