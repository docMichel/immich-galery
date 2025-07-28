<?php
// src/Api/ImmichClient.php - Client pour l'API Immich

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
        // Les options de taille dans Immich: thumbnail, preview, original
        return "{$this->apiUrl}/api/assets/{$assetId}/thumbnail?size={$size}";
    }

    /**
     * Obtenir l'URL de l'image complète
     */
    public function getImageUrl($assetId): string
    {
        return "{$this->apiUrl}/api/assets/{$assetId}/original";
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
            // Enrichir avec les métadonnées complètes si nécessaire
            $assetInfo = $this->getAssetInfo($asset['id']);
            if ($assetInfo) {
                $assets[] = array_merge($asset, [
                    'thumbnailUrl' => $this->getThumbnailUrl($asset['id']),
                    'fullUrl' => $this->getImageUrl($asset['id']),
                    'width' => $assetInfo['exifInfo']['ImageWidth'] ?? 2000,
                    'height' => $assetInfo['exifInfo']['ImageHeight'] ?? 1500,
                    'latitude' => $assetInfo['exifInfo']['latitude'] ?? null,
                    'longitude' => $assetInfo['exifInfo']['longitude'] ?? null,
                    'dateTaken' => $assetInfo['fileCreatedAt'] ?? $assetInfo['fileModifiedAt']
                ]);
            }
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
     * Tester la connexion à l'API
     */
    public function testConnection(): bool
    {
        $response = $this->makeRequest('/api/server-info/ping');
        return $response !== null && isset($response['res']) && $response['res'] === 'pong';
    }

    /**
     * Récupérer les informations du serveur
     */
    public function getServerInfo(): ?array
    {
        return $this->makeRequest('/api/server-info');
    }
}
