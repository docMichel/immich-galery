<?php
// gallery/index.php - Interface utilisateur avec authentification et interactions
require_once '../src/Auth/SocialAuth.php';
require_once '../src/Models/Gallery.php';
require_once '../src/Models/Comment.php';
require_once '../src/Models/User.php';

$galleryModel = new Gallery();
$commentModel = new Comment();
$userModel = new User();
$socialAuth = new SocialAuth();

// Récupérer la galerie demandée (slug ou ID)
$gallerySlug = $_GET['gallery'] ?? $_GET['g'] ?? null;
$gallery = null;

if ($gallerySlug) {
    $gallery = $galleryModel->getGalleryBySlug($gallerySlug);
} else {
    // Afficher la liste des galeries publiques
    $galleries = $galleryModel->getPublicGalleries();
}

// Gestion de l'authentification
$user = null;
if (isset($_SESSION['user_id'])) {
    $user = $userModel->getUserById($_SESSION['user_id']);
}

// Traitement des actions utilisateur
if ($_POST && $user) {
    switch ($_POST['action']) {
        case 'add_comment':
            $commentModel->addComment([
                'gallery_id' => $_POST['gallery_id'],
                'image_id' => $_POST['image_id'] ?? null,
                'user_id' => $user['id'],
                'content' => $_POST['comment_content'],
                'parent_id' => $_POST['parent_id'] ?? null
            ]);
            break;

        case 'request_deletion':
            $galleryModel->addDeletionRequest([
                'gallery_id' => $_POST['gallery_id'],
                'image_id' => $_POST['image_id'] ?? null,
                'user_id' => $user['id'],
                'reason' => $_POST['reason']
            ]);
            break;

        case 'upload_photo':
            handlePhotoUpload($_POST['gallery_id'], $user['id'], $_FILES['photo']);
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $gallery ? htmlspecialchars($gallery['name']) : 'Galeries Photos' ?></title>

    <!-- PhotoSwipe 5 + Tailwind CSS -->
    <link rel="stylesheet" href="https://unpkg.com/photoswipe@5.4.2/dist/photoswipe.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-50">
    <!-- Header avec authentification -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div>
                    <?php if ($gallery): ?>
                        <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($gallery['name']) ?></h1>
                        <p class="text-gray-600"><?= htmlspecialchars($gallery['description']) ?></p>
                    <?php else: ?>
                        <h1 class="text-2xl font-bold text-gray-900">Galeries Photos</h1>
                    <?php endif; ?>
                </div>

                <!-- Zone d'authentification -->
                <div x-data="{ showAuthModal: false }">
                    <?php if ($user): ?>
                        <div class="flex items-center space-x-4">
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>"
                                alt="<?= htmlspecialchars($user['name']) ?>"
                                class="w-8 h-8 rounded-full">
                            <span class="text-gray-700"><?= htmlspecialchars($user['name']) ?></span>
                            <a href="/logout" class="text-gray-500 hover:text-gray-700">Déconnexion</a>
                        </div>
                    <?php else: ?>
                        <button @click="showAuthModal = true"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                            Se connecter
                        </button>
                    <?php endif; ?>

                    <!-- Modal d'authentification -->
                    <div x-show="showAuthModal" x-cloak
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                            <h3 class="text-lg font-semibold mb-4">Se connecter</h3>
                            <div class="space-y-3">
                                <a href="<?= $socialAuth->getFacebookLoginUrl() ?>"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg flex items-center justify-center">
                                    📘 Continuer avec Facebook
                                </a>
                                <a href="<?= $socialAuth->getGoogleLoginUrl() ?>"
                                    class="w-full bg-red-500 hover:bg-red-600 text-white px-4 py-3 rounded-lg flex items-center justify-center">
                                    🔍 Continuer avec Google
                                </a>
                                <a href="<?= $socialAuth->getAppleLoginUrl() ?>"
                                    class="w-full bg-black hover:bg-gray-800 text-white px-4 py-3 rounded-lg flex items-center justify-center">
                                    🍎 Continuer avec Apple
                                </a>
                            </div>
                            <button @click="showAuthModal = false"
                                class="w-full mt-4 text-gray-500 hover:text-gray-700">
                                Annuler
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($gallery): ?>
            <!-- Vue galerie unique -->
            <div x-data="galleryData()" class="space-y-8">

                <!-- Actions utilisateur si connecté -->
                <?php if ($user): ?>
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <div class="flex justify-between items-center">
                            <h3 class="font-medium text-gray-900">Actions</h3>
                            <div class="flex space-x-3">
                                <button @click="showUploadModal = true"
                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                    📤 Ajouter une photo
                                </button>
                                <button @click="showCommentsPanel = !showCommentsPanel"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                    💬 Commentaires
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Panneau de commentaires -->
                <div x-show="showCommentsPanel" x-cloak class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold mb-4">Commentaires de la galerie</h3>

                    <!-- Formulaire de nouveau commentaire -->
                    <?php if ($user): ?>
                        <form method="POST" class="mb-6">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="gallery_id" value="<?= $gallery['id'] ?>">
                            <div class="flex space-x-3">
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>"
                                    class="w-8 h-8 rounded-full">
                                <div class="flex-1">
                                    <textarea name="comment_content" rows="2" placeholder="Laissez un commentaire..."
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md resize-none"></textarea>
                                    <button type="submit"
                                        class="mt-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                        Publier
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- Liste des commentaires -->
                    <div class="space-y-4">
                        <?php
                        $comments = $commentModel->getGalleryComments($gallery['id']);
                        foreach ($comments as $comment):
                        ?>
                            <div class="flex space-x-3">
                                <img src="<?= htmlspecialchars($comment['user_avatar']) ?>"
                                    class="w-8 h-8 rounded-full">
                                <div class="flex-1">
                                    <div class="bg-gray-100 rounded-lg p-3">
                                        <div class="font-medium text-sm text-gray-900">
                                            <?= htmlspecialchars($comment['user_name']) ?>
                                        </div>
                                        <div class="text-gray-700 text-sm">
                                            <?= htmlspecialchars($comment['content']) ?>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Galerie PhotoSwipe -->
                <div class="pswp-gallery grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"
                    id="gallery-<?= $gallery['id'] ?>">
                    <?php foreach ($gallery['images'] as $image): ?>
                        <div class="relative group">
                            <a href="<?= $image['full_url'] ?>"
                                data-pswp-width="<?= $image['width'] ?>"
                                data-pswp-height="<?= $image['height'] ?>"
                                data-pswp-caption="<?= htmlspecialchars($image['caption']) ?>"
                                data-image-id="<?= $image['id'] ?>"
                                target="_blank">
                                <img src="<?= $image['thumbnail_url'] ?>"
                                    alt="<?= htmlspecialchars($image['caption']) ?>"
                                    class="w-full h-48 object-cover rounded-lg">
                            </a>

                            <!-- Actions sur l'image si connecté -->
                            <?php if ($user): ?>
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <div class="flex space-x-1">
                                        <button @click="commentOnImage('<?= $image['id'] ?>')"
                                            class="bg-blue-500 text-white p-1 rounded text-xs">💬</button>
                                        <button @click="requestDeletion('<?= $image['id'] ?>')"
                                            class="bg-red-500 text-white p-1 rounded text-xs">🗑️</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Modal upload de photo -->
                <?php if ($user): ?>
                    <div x-show="showUploadModal" x-cloak
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                            <h3 class="text-lg font-semibold mb-4">Ajouter une photo</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="hidden" name="gallery_id" value="<?= $gallery['id'] ?>">

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
                                    <input type="file" name="photo" accept="image/*" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>

                                <div class="flex justify-end space-x-3">
                                    <button type="button" @click="showUploadModal = false"
                                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700">
                                        Annuler
                                    </button>
                                    <button type="submit"
                                        class="px-4 py-2 bg-blue-500 text-white rounded-md">
                                        Ajouter
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Liste des galeries -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($galleries as $galleryItem): ?>
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        <div class="aspect-w-16 aspect-h-9">
                            <img src="<?= $galleryItem['cover_image_url'] ?>"
                                alt="<?= htmlspecialchars($galleryItem['name']) ?>"
                                class="w-full h-48 object-cover">
                        </div>
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <?= htmlspecialchars($galleryItem['name']) ?>
                            </h3>
                            <p class="text-gray-600 text-sm mb-3">
                                <?= htmlspecialchars(substr($galleryItem['description'], 0, 120)) ?>...
                            </p>
                            <div class="flex justify-between items-center text-sm text-gray-500">
                                <span><?= $galleryItem['image_count'] ?> photos</span>
                                <a href="?gallery=<?= $galleryItem['slug'] ?>"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                                    Voir
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- PhotoSwipe 5 JavaScript -->
    <script type="module">
        import PhotoSwipeLightbox from 'https://unpkg.com/photoswipe@5.4.2/dist/photoswipe-lightbox.esm.js';

        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: 'a',
            pswpModule: () => import('https://unpkg.com/photoswipe@5.4.2/dist/photoswipe.esm.js'),

            paddingFn: (viewportSize) => ({
                top: 30,
                bottom: 30,
                left: 70,
                right: 70
            })
        });

        // Personnalisation pour les commentaires sur les images
        lightbox.on('uiRegister', function() {
            lightbox.pswp.ui.registerElement({
                name: 'image-actions',
                order: 9,
                isButton: false,
                appendTo: 'bar',
                html: '<div class="pswp__image-actions"></div>',
                onInit: (el, pswp) => {
                    lightbox.pswp.on('change', () => {
                        const currSlide = lightbox.pswp.currSlide;
                        if (currSlide?.data?.element) {
                            const imageId = currSlide.data.element.dataset.imageId;
                            const caption = currSlide.data.element.dataset.pswpCaption;

                            el.innerHTML = `
                                <div class="pswp__caption-container">
                                    <div class="pswp__caption">${caption}</div>
                                    <?php if ($user): ?>
                                    <div class="pswp__image-actions-buttons">
                                        <button onclick="commentOnImage('${imageId}')" class="pswp__button">💬</button>
                                        <button onclick="requestDeletion('${imageId}')" class="pswp__button">🗑️</button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            `;
                        }
                    });
                }
            });
        });

        lightbox.init();
    </script>

    <script>
        function galleryData() {
            return {
                showCommentsPanel: false,
                showUploadModal: false,

                commentOnImage(imageId) {
                    // Implémenter commentaire sur image
                    console.log('Comment on image:', imageId);
                },

                requestDeletion(imageId) {
                    if (confirm('Demander la suppression de cette image ?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="request_deletion">
                            <input type="hidden" name="image_id" value="${imageId}">
                            <input type="hidden" name="gallery_id" value="<?= $gallery['id'] ?? '' ?>">
                            <input type="hidden" name="reason" value="Demande utilisateur">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] {
            display: none;
        }

        .pswp__caption-container {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            border-radius: 8px;
            padding: 1rem;
            color: white;
        }

        .pswp__caption {
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }

        .pswp__image-actions-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .pswp__image-actions-buttons .pswp__button {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            color: white;
            cursor: pointer;
        }
    </style>
</body>

</html>

<?php
function handlePhotoUpload($galleryId, $userId, $file)
{
    // Gérer l'upload de photo utilisateur
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/user_photos/';
        $filename = uniqid() . '_' . basename($file['name']);
        $uploadPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Ajouter à la base de données avec statut "en attente de modération"
            $imageModel = new Image();
            $imageModel->addUserImage([
                'gallery_id' => $galleryId,
                'user_id' => $userId,
                'filename' => $filename,
                'status' => 'pending_moderation'
            ]);
        }
    }
}
?>