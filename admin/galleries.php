<?php
// admin/galleries.php - Interface d'administration pour gérer les galeries

$config = include('../config/config.php');
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Models/Gallery.php';
require_once '../src/Services/Database.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();

$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
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
                // TODO: Implémenter
            }
            break;

        case 'update_gallery':
            $galleryModel->updateGallery($_POST['gallery_id'], [
                'name' => $_POST['gallery_name'],
                'description' => $_POST['gallery_description'],
                'is_public' => isset($_POST['is_public']),
                'requires_auth' => isset($_POST['requires_auth']),
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Gestion des Galeries</h1>
            <p class="text-gray-600">Créez et gérez vos galeries à partir des albums Immich</p>
        </header>

        <!-- Bouton créer nouvelle galerie -->
        <div class="mb-6">
            <button id="btnNewGallery" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
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
                                <span>📸 <?= $gallery['image_count'] ?> photos</span>
                                <span>👀 <?= $gallery['view_count'] ?> vues</span>
                                <span class="<?= $gallery['is_public'] ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $gallery['is_public'] ? '🌐 Public' : '🔒 Privé' ?>
                                </span>
                            </div>

                            <div class="mt-2">
                                <a href="../public/?gallery=<?= $gallery['slug'] ?>" target="_blank"
                                    class="text-blue-500 hover:text-blue-600 text-sm">
                                    🔗 Voir la galerie
                                </a>
                            </div>
                        </div>

                        <div class="flex space-x-2">
                            <button onclick="editGallery(<?= htmlspecialchars(json_encode($gallery)) ?>)"
                                class="text-blue-500 hover:text-blue-600 px-3 py-1 border border-blue-300 rounded">
                                Modifier
                            </button>
                            <button onclick="openPhotoEdit(<?= $gallery['id'] ?>)"
                                class="text-green-500 hover:text-green-600 px-3 py-1 border border-green-300 rounded">
                                📸 PhotoEdit
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
                            <?php if (isset($gallery['albums']) && is_array($gallery['albums'])): ?>
                                <?php foreach ($gallery['albums'] as $album): ?>
                                    <span class="inline-block bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-sm">
                                        <?= htmlspecialchars($album['albumName']) ?> (<?= $album['assetCount'] ?> photos)
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-gray-500 italic">Aucun album associé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Modal de création/édition -->
        <div id="modalGallery" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg w-full max-w-4xl h-[90vh] flex flex-col">
                <!-- Header sticky -->
                <div class="px-6 py-4 border-b bg-white sticky top-0 z-10">
                    <h2 class="text-2xl font-bold" id="modalTitle">Créer une nouvelle galerie</h2>
                </div>

                <!-- Contenu scrollable -->
                <div class="flex-1 overflow-y-auto px-6 py-4">
                    <form method="POST" id="galleryForm">
                        <input type="hidden" name="action" id="formAction" value="create_gallery">
                        <input type="hidden" name="gallery_id" id="galleryId">

                        <!-- Informations de base -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la galerie</label>
                                <input type="text" name="gallery_name" id="galleryName" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Slug URL</label>
                                <input type="text" name="gallery_slug" id="gallerySlug" required placeholder="ma-galerie"
                                    pattern="[a-z0-9-]+"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="gallery_description" id="galleryDescription" rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"></textarea>
                        </div>

                        <!-- Options et permissions sur une ligne -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Options</h4>
                                <div class="space-y-1">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="is_public" id="isPublic" class="mr-2">
                                        Galerie publique
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="requires_auth" id="requiresAuth" class="mr-2">
                                        Authentification requise
                                    </label>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Permissions</h4>
                                <div class="space-y-1">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="permissions[]" value="admin" checked class="mr-2">
                                        Admin
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="permissions[]" value="family" class="mr-2">
                                        Famille
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="permissions[]" value="friends" class="mr-2">
                                        Amis
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Albums plus compacts -->
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Albums Immich</h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-1 h-48 overflow-y-auto border border-gray-200 rounded p-2" id="albumsList">
                                <?php foreach ($immichAlbums as $album): ?>
                                    <label class="flex items-start p-2 border border-gray-200 rounded hover:bg-gray-50 cursor-pointer text-sm">
                                        <input type="checkbox" name="selected_albums[]" value="<?= $album['id'] ?>"
                                            data-album-id="<?= $album['id'] ?>" class="mr-2 mt-0.5 album-checkbox">
                                        <div class="min-w-0">
                                            <div class="font-medium truncate"><?= htmlspecialchars($album['albumName']) ?></div>
                                            <div class="text-xs text-gray-500"><?= $album['assetCount'] ?> photos</div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer sticky avec boutons -->
                <div class="px-6 py-4 border-t bg-gray-50 sticky bottom-0">
                    <div class="flex justify-end space-x-3">
                        <button type="button" id="btnCancel"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-100">
                            Annuler
                        </button>
                        <button type="submit" form="galleryForm"
                            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            <span id="btnSubmitText">Créer la galerie</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Ouvrir le modal pour création
            $('#btnNewGallery').click(function() {
                resetForm();
                $('#modalTitle').text('Créer une nouvelle galerie');
                $('#formAction').val('create_gallery');
                $('#btnSubmitText').text('Créer la galerie');
                $('#modalGallery').removeClass('hidden').addClass('flex');
            });

            // Fermer le modal
            $('#btnCancel').click(function() {
                $('#modalGallery').removeClass('flex').addClass('hidden');
            });

            // Fermer en cliquant en dehors
            $('#modalGallery').click(function(e) {
                if (e.target === this) {
                    $(this).removeClass('flex').addClass('hidden');
                }
            });
        });

        // Réinitialiser le formulaire
        function resetForm() {
            $('#galleryForm')[0].reset();
            $('#galleryId').val('');
            $('.album-checkbox').prop('checked', false);
        }

        // Éditer une galerie
        function editGallery(gallery) {
            resetForm();

            // Remplir le formulaire avec les données existantes
            $('#modalTitle').text('Modifier la galerie');
            $('#formAction').val('update_gallery');
            $('#btnSubmitText').text('Enregistrer les modifications');
            $('#galleryId').val(gallery.id);
            $('#galleryName').val(gallery.name);
            $('#gallerySlug').val(gallery.slug).prop('readonly', true); // Slug non modifiable
            $('#galleryDescription').val(gallery.description);
            $('#isPublic').prop('checked', gallery.is_public == 1);
            $('#requiresAuth').prop('checked', gallery.requires_auth == 1);

            // Cocher les albums associés
            if (gallery.albums && Array.isArray(gallery.albums)) {
                gallery.albums.forEach(function(album) {
                    $(`input[data-album-id="${album.id}"]`).prop('checked', true);
                });
            }

            $('#modalGallery').removeClass('hidden').addClass('flex');
        }

        // Ouvrir PhotoEdit
        function openPhotoEdit(galleryId) {
            window.location.href = `edit-photos.php?gallery=${galleryId}`;
        }

        // Supprimer une galerie
        function deleteGallery(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette galerie ?')) {
                $('<form>', {
                    method: 'POST',
                    html: '<input type="hidden" name="action" value="delete_gallery">' +
                        '<input type="hidden" name="gallery_id" value="' + id + '">'
                }).appendTo('body').submit();
            }
        }
    </script>
</body>

</html>