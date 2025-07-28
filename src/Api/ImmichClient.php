<?php
// src/Api/ImmichClient.php - Client pour l'API Immich CORRIGÉ

class ImmichClient
{
    private $apiUrl;
    private $apiKey;

    public function __construct($apiUrl, $apiKey)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Tester la connexion à l'API
     */
    public function testConnection(): bool
    {
        // Utiliser l'endpoint qui fonctionne pour tester
        $response = $this->makeRequest('/api/albums');
        return $response !== null && is_array($response);
    }

    /**
     * Récupérer les informations du serveur
     */
    public function getServerInfo(): ?array
    {
        // Le /server-info retourne du HTML, utilisons /server-info/version
        $ch = curl_init($this->apiUrl . '/server-info/version');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['x-api-key: ' . $this->apiKey],
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return ['version' => 'Immich Server', 'status' => 'OK'];
        }
        return null;
    }

    /**
     * Récupérer tous les albums
     */
    public function getAllAlbums(): array
    {
        $response = $this->makeRequest('/api/albums');
        return $response ?? [];
    }

    /**
     * Récupérer un album spécifique avec ses assets
     */
    public function getAlbum($albumId): ?array
    {
        return $this->makeRequest("/api/albums/{$albumId}");
    }

    /**
     * Récupérer les détails d'un asset
     */
    public function getAssetInfo($assetId): ?array
    {
        return $this->makeRequest("/api/assets/{$assetId}");
    }

    /**
     * Obtenir l'URL de thumbnail
     */
    public function getThumbnailUrl($assetId, $size = 'thumbnail'): string
    {
        // Utiliser le proxy PHP pour gérer l'authentification
        return "/image-proxy.php?id={$assetId}&type=thumbnail&size={$size}";
    }

    /**
     * Obtenir l'URL de l'image complète
     */
    public function getImageUrl($assetId): string
    {
        return "/image-proxy.php?id={$assetId}&type=original";
    }

    /**
     * Récupérer les assets d'un album
     */
    public function getAlbumAssets($albumId): array
    {
        $album = $this->getAlbum($albumId);
        if (!$album || !isset($album['assets'])) {
            return [];
        }

        $assets = [];
        foreach ($album['assets'] as $asset) {
            $assets[] = [
                'id' => $asset['id'],
                'thumbnailUrl' => $this->getThumbnailUrl($asset['id']),
                'fullUrl' => $this->getImageUrl($asset['id']),
                'width' => $asset['exifInfo']['ImageWidth'] ?? 2000,
                'height' => $asset['exifInfo']['ImageHeight'] ?? 1500,
                'latitude' => $asset['exifInfo']['latitude'] ?? null,
                'longitude' => $asset['exifInfo']['longitude'] ?? null,
                'dateTaken' => $asset['fileCreatedAt'] ?? $asset['fileModifiedAt'],
                'originalPath' => $asset['originalPath'] ?? '',
                'type' => $asset['type'] ?? 'IMAGE'
            ];
        }

        return $assets;
    }

    /**
     * Effectuer une requête API
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null): ?array
    {
        $url = $this->apiUrl . $endpoint;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Erreur cURL Immich: " . $error);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Erreur API Immich: HTTP {$httpCode} - {$response}");
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Récupérer les statistiques du serveur
     */
    public function getServerStats(): ?array
    {
        return $this->makeRequest('/api/server-info/statistics');
    }

    /**
     * Rechercher des assets
     */
    public function searchAssets($query): array
    {
        $response = $this->makeRequest('/api/search', 'POST', [
            'q' => $query,
            'type' => 'IMAGE'
        ]);
        return $response['assets']['items'] ?? [];
    }
}
