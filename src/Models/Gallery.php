<?php
// src/Models/Gallery.php - Modèle pour gérer les galeries

class Gallery
{
    private $db;
    private $immichClient;

    public function __construct()
    {
        $config = include(__DIR__ . '/../../config/config.php');
        $this->db = new Database($config['database']);
        $this->immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
    }

    /**
     * Créer une nouvelle galerie
     */
    public function createGallery($data): int
    {
        $stmt = $this->db->getPDO()->prepare("
            INSERT INTO galleries (name, description, slug, is_public, requires_auth, auto_generate_captions, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['slug'],
            $data['is_public'] ? 1 : 0,
            $data['requires_auth'] ? 1 : 0,
            $data['auto_generate_captions'] ?? 1
        ]);

        $galleryId = $this->db->getPDO()->lastInsertId();

        // Associer les albums Immich
        if (!empty($data['immich_album_ids'])) {
            foreach ($data['immich_album_ids'] as $albumId) {
                $this->addImmichAlbum($galleryId, $albumId);
            }
        }

        // Synchroniser les images depuis Immich
        $this->syncGalleryImages($galleryId);

        return $galleryId;
    }

    /**
     * Associer un album Immich à une galerie
     */
    private function addImmichAlbum($galleryId, $immichAlbumId): void
    {
        // Récupérer les infos de l'album depuis Immich
        $album = $this->immichClient->getAlbum($immichAlbumId);

        $stmt = $this->db->getPDO()->prepare("
            INSERT INTO gallery_immich_albums (gallery_id, immich_album_id, immich_album_name) 
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $galleryId,
            $immichAlbumId,
            $album['albumName'] ?? 'Album sans nom'
        ]);
    }

    /**
     * Synchroniser les images d'une galerie depuis Immich
     */
    public function syncGalleryImages($galleryId): void
    {
        // Récupérer tous les albums associés
        $stmt = $this->db->getPDO()->prepare("
            SELECT immich_album_id FROM gallery_immich_albums WHERE gallery_id = ?
        ");
        $stmt->execute([$galleryId]);
        $albums = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $totalImages = 0;

        foreach ($albums as $albumId) {
            $assets = $this->immichClient->getAlbumAssets($albumId);

            foreach ($assets as $asset) {
                // Insérer ou mettre à jour l'image
                $stmt = $this->db->getPDO()->prepare("
                    INSERT INTO gallery_images 
                    (gallery_id, immich_asset_id, thumbnail_url, full_url, width, height, 
                     latitude, longitude, exif_data, immich_metadata, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        thumbnail_url = VALUES(thumbnail_url),
                        full_url = VALUES(full_url),
                        width = VALUES(width),
                        height = VALUES(height)
                ");

                $stmt->execute([
                    $galleryId,
                    $asset['id'],
                    $asset['thumbnailUrl'],
                    $asset['fullUrl'],
                    $asset['width'],
                    $asset['height'],
                    $asset['latitude'],
                    $asset['longitude'],
                    json_encode($asset['exifInfo'] ?? []),
                    json_encode($asset)
                ]);

                $totalImages++;
            }
        }

        // Mettre à jour le compteur d'images
        $stmt = $this->db->getPDO()->prepare("
            UPDATE galleries SET image_count = ? WHERE id = ?
        ");
        $stmt->execute([$totalImages, $galleryId]);
    }

    /**
     * Récupérer toutes les galeries
     */
    public function getAllGalleries(): array
    {
        $stmt = $this->db->getPDO()->query("
            SELECT g.*, 
                   GROUP_CONCAT(gia.immich_album_id) as album_ids,
                   GROUP_CONCAT(gia.immich_album_name) as album_names
            FROM galleries g
            LEFT JOIN gallery_immich_albums gia ON g.id = gia.gallery_id
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");

        $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enrichir avec les infos des albums
        foreach ($galleries as &$gallery) {
            $gallery['albums'] = [];
            if ($gallery['album_ids']) {
                $albumIds = explode(',', $gallery['album_ids']);
                $albumNames = explode(',', $gallery['album_names']);

                foreach ($albumIds as $i => $albumId) {
                    $gallery['albums'][] = [
                        'id' => $albumId,
                        'albumName' => $albumNames[$i] ?? '',
                        'assetCount' => $this->getAlbumAssetCount($albumId)
                    ];
                }
            }

            // Récupérer une image de couverture
            $gallery['cover_image_url'] = $this->getGalleryCoverImage($gallery['id']);
        }

        return $galleries;
    }

    /**
     * Récupérer les galeries publiques
     */
    public function getPublicGalleries(): array
    {
        $stmt = $this->db->getPDO()->query("
            SELECT * FROM galleries 
            WHERE is_public = 1 
            ORDER BY created_at DESC
        ");

        $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($galleries as &$gallery) {
            $gallery['cover_image_url'] = $this->getGalleryCoverImage($gallery['id']);
        }

        return $galleries;
    }

    /**
     * Récupérer une galerie par son slug
     */
    public function getGalleryBySlug($slug): ?array
    {
        $stmt = $this->db->getPDO()->prepare("
            SELECT * FROM galleries WHERE slug = ?
        ");
        $stmt->execute([$slug]);
        $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gallery) {
            return null;
        }

        // Récupérer les images
        $stmt = $this->db->getPDO()->prepare("
            SELECT * FROM gallery_images 
            WHERE gallery_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$gallery['id']]);
        $gallery['images'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Incrémenter le compteur de vues
        $this->incrementViewCount($gallery['id']);

        return $gallery;
    }

    /**
     * Obtenir l'image de couverture d'une galerie
     */
    private function getGalleryCoverImage($galleryId): ?string
    {
        $stmt = $this->db->getPDO()->prepare("
            SELECT thumbnail_url FROM gallery_images 
            WHERE gallery_id = ? 
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute([$galleryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['thumbnail_url'] : '/images/default-gallery.jpg';
    }

    /**
     * Compter les assets d'un album
     */
    private function getAlbumAssetCount($albumId): int
    {
        $album = $this->immichClient->getAlbum($albumId);
        return $album ? ($album['assetCount'] ?? 0) : 0;
    }

    /**
     * Incrémenter le compteur de vues
     */
    private function incrementViewCount($galleryId): void
    {
        $stmt = $this->db->getPDO()->prepare("
            UPDATE galleries SET view_count = view_count + 1 WHERE id = ?
        ");
        $stmt->execute([$galleryId]);
    }

    /**
     * Mettre à jour une galerie
     */
    public function updateGallery($galleryId, $data): void
    {
        $stmt = $this->db->getPDO()->prepare("
            UPDATE galleries 
            SET name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['description'],
            $galleryId
        ]);

        // Mettre à jour les albums si fournis
        if (isset($data['immich_album_ids'])) {
            // Supprimer les associations existantes
            $stmt = $this->db->getPDO()->prepare("
                DELETE FROM gallery_immich_albums WHERE gallery_id = ?
            ");
            $stmt->execute([$galleryId]);

            // Ajouter les nouvelles associations
            foreach ($data['immich_album_ids'] as $albumId) {
                $this->addImmichAlbum($galleryId, $albumId);
            }

            // Resynchroniser les images
            $this->syncGalleryImages($galleryId);
        }
    }

    /**
     * Supprimer une galerie
     */
    public function deleteGallery($galleryId): void
    {
        $stmt = $this->db->getPDO()->prepare("DELETE FROM galleries WHERE id = ?");
        $stmt->execute([$galleryId]);
    }
}
