<?php
// admin/galleries.php - Interface d'administration pour gérer les galeries
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Models/Gallery.php';

AdminAuth::requireAdmin();

$immichClient = new ImmichClient($_ENV['IMMICH_API_URL'], $_ENV['IMMICH_API_KEY']);
$galleryModel = new Gallery();

// Traitement des actions
if ($_POST) {
    switch ($_POST['action']) {
        case 'create_gallery':
            $galleryId = $galleryModel->createGallery([
                'name' => $_POST['gallery_name'],
                'description' => $_POST['gallery_description'],
                'slug' => $_POST['gallery_slug'],
                'is_public' => isset($_POST['is_public']),
                'requires_auth' => isset($_POST['requires_auth']),
                'immich_album_ids' => $_POST['selected_albums'] ?? []
            ]);

            // Générer les légendes automatiquement si demandé
            if (isset($_POST['auto_generate_captions'])) {
                $captionGenerator = new CaptionGenerator();
                $captionGenerator->generateForGallery($galleryId);
            }
            break;

        case 'update_gallery':
            $galleryModel->updateGallery($_POST['gallery_id'], [
                'name' => $_POST['gallery_name'],
                'description' => $_POST['gallery_description'],
                'immich_album_ids' => $_POST['selected_albums'] ?? []
            ]);
            break;

        case 'delete_gallery':
            $galleryModel->deleteGallery($_POST['gallery_id']);
            break;
    }
}

$galleries = $galleryModel->getAllGalleries();
$immichAlbums = $immichClient->getAllAlbums();
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gestion des Galeries</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Gestion des Galeries</h1>
            <p class="text-gray-600">Créez et gérez vos galeries à partir des albums Immich</p>
        </header>

        <!-- Bouton créer nouvelle galerie -->
        <div class="mb-6">
            <button @click="showCreateForm = true"
                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                + Nouvelle Galerie
            </button>
        </div>

        <!-- Liste des galeries existantes -->
        <div class="grid gap-6 mb-8">
            <?php foreach ($galleries as $gallery): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($gallery['name']) ?></h3>
                            <p class="text-gray-600 mt-1"><?= htmlspecialchars($gallery['description']) ?></p>

                            <div class="mt-3 flex items-center space-x-4 text-sm text-gray-500">
                                <span>📸 <?= count($gallery['immich_album_ids']) ?> albums</span>
                                <span>👀 <?= $gallery['view_count'] ?> vues</span>
                                <span class="<?= $gallery['is_public'] ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $gallery['is_public'] ? '🌐 Public' : '🔒 Privé' ?>
                                </span>
                            </div>

                            <div class="mt-2">
                                <a href="../gallery/<?= $gallery['slug'] ?>" target="_blank"
                                    class="text-blue-500 hover:text-blue-600 text-sm">
                                    🔗 Voir la galerie
                                </a>
                            </div>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="editGallery(<?= $gallery['id'] ?>)"
                                class="text-blue-500 hover:text-blue-600 px-3 py-1 border border-blue-300 rounded">
                                Modifier
                            </button>
                            <button onclick="deleteGallery(<?= $gallery['id'] ?>)"
                                class="text-red-500 hover:text-red-600 px-3 py-1 border border-red-300 rounded">
                                Supprimer
                            </button>
                        </div>
                    </div>

                    <!-- Albums Immich associés -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h4 class="font-medium text-gray-700 mb-2">Albums Immich associés:</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($gallery['albums'] as $album): ?>
                                <span class="inline-block bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                    <?= htmlspecialchars($album['albumName']) ?> (<?= $album['assetCount'] ?> photos)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal de création/édition -->
        <div x-data="{ showCreateForm: false, selectedAlbums: [] }"
            x-show="showCreateForm"
            x-cloak
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">

            <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="create_gallery">

                    <h2 class="text-2xl font-bold mb-6">Créer une nouvelle galerie</h2>

                    <!-- Informations de base -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la galerie</label>
                            <input type="text" name="gallery_name" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Slug URL</label>
                            <input type="text" name="gallery_slug" required
                                placeholder="ma-galerie-vacances"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="gallery_description" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Options -->
                    <div class="mb-6 space-y-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_public" class="mr-2">
                            Galerie publique (visible sans connexion)
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="requires_auth" class="mr-2">
                            Nécessite une authentification (Facebook, Google...)
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="auto_generate_captions" class="mr-2">
                            Générer automatiquement les légendes IA
                        </label>
                    </div>

                    <!-- Sélection des albums Immich -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Sélectionner les albums Immich</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-96 overflow-y-auto border border-gray-200 rounded-lg p-4">
                            <?php foreach ($immichAlbums as $album): ?>
                                <label class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="selected_albums[]" value="<?= $album['id'] ?>"
                                        class="mt-1">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($album['albumName']) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= $album['assetCount'] ?> photos
                                            <?php if (!empty($album['description'])): ?>
                                                <br><?= htmlspecialchars(substr($album['description'], 0, 100)) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Boutons -->
                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="showCreateForm = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            Créer la galerie
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editGallery(id) {
            // Implémenter l'édition
            window.location.href = `edit-gallery.php?id=${id}`;
        }

        function deleteGallery(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette galerie ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_gallery">
                    <input type="hidden" name="gallery_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <style>
        [x-cloak] {
            display: none;
        }
    </style>
</body>

</html>