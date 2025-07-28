<?php
// src/Services/Geolocation.php - Service de géolocalisation aggressive

class Geolocation
{
    private $db;
    private $config;

    public function __construct($database)
    {
        $this->db = $database;
        $this->config = include(__DIR__ . '/../../config/config.php');
    }

    /**
     * Analyser une image et enrichir avec géolocalisation
     */
    public function enrichImageLocation($imageData): array
    {
        $latitude = $imageData['latitude'] ?? null;
        $longitude = $imageData['longitude'] ?? null;

        if (!$latitude || !$longitude) {
            return $imageData; // Pas de coordonnées GPS
        }

        $enriched = $imageData;

        // Rechercher sites UNESCO proches
        $unescoSites = $this->findNearbyUnescoSites($latitude, $longitude);
        $enriched['nearby_unesco'] = $unescoSites;

        // Rechercher lieux d'intérêt
        $places = $this->findNearbyPlaces($latitude, $longitude);
        $enriched['nearby_places'] = $places;

        // Géocodage inverse pour obtenir l'adresse
        $location = $this->reverseGeocode($latitude, $longitude);
        $enriched['location_info'] = $location;

        // Générer une légende enrichie
        $enriched['enhanced_caption'] = $this->generateLocationCaption($enriched);

        return $enriched;
    }

    /**
     * Trouver les sites UNESCO proches
     */
    private function findNearbyUnescoSites($lat, $lng, $radiusKm = 50): array
    {
        $sql = "SELECT *, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance 
                FROM unesco_sites 
                HAVING distance < ? 
                ORDER BY distance LIMIT 10";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lat, $lng, $lat, $radiusKm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Trouver les lieux d'intérêt proches
     */
    private function findNearbyPlaces($lat, $lng, $radiusKm = 20): array
    {
        $sql = "SELECT *, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance 
                FROM places 
                HAVING distance < ? 
                ORDER BY distance LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$lat, $lng, $lat, $radiusKm]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Géocodage inverse (obtenir adresse depuis coordonnées)
     */
    private function reverseGeocode($lat, $lng): ?array
    {
        // Utiliser Nominatim (OpenStreetMap) - gratuit
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";

        $options = [
            'http' => [
                'header' => "User-Agent: ImmichGallery/1.0\r\n",
                'timeout' => 5
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);

        if ($response) {
            $data = json_decode($response, true);
            return [
                'display_name' => $data['display_name'] ?? '',
                'city' => $data['address']['city'] ?? $data['address']['town'] ?? '',
                'country' => $data['address']['country'] ?? '',
                'region' => $data['address']['state'] ?? $data['address']['region'] ?? ''
            ];
        }

        return null;
    }

    /**
     * Générer une légende enrichie avec localisation
     */
    private function generateLocationCaption($enrichedData): string
    {
        $caption = $enrichedData['caption'] ?? '';
        $location = $enrichedData['location_info'];
        $unesco = $enrichedData['nearby_unesco'];
        $places = $enrichedData['nearby_places'];

        $locationParts = [];

        // Ajouter l'adresse
        if ($location && $location['display_name']) {
            $locationParts[] = "📍 " . $location['display_name'];
        }

        // Ajouter les sites UNESCO proches
        if (!empty($unesco)) {
            $closest = $unesco[0];
            $distance = round($closest['distance'], 1);
            $locationParts[] = "🏛️ À {$distance}km du site UNESCO : {$closest['name']}";
        }

        // Ajouter les lieux d'intérêt proches
        if (!empty($places)) {
            $hotelsAndRestaurants = array_filter($places, function ($place) {
                return in_array($place['type'], ['hotel', 'restaurant', 'attraction']);
            });

            if (!empty($hotelsAndRestaurants)) {
                $closest = $hotelsAndRestaurants[0];
                $distance = round($closest['distance'], 1);
                $locationParts[] = "🏨 À {$distance}km : {$closest['name']} ({$closest['type']})";
            }
        }

        // Combiner la légende originale avec les infos de localisation
        $enhancedCaption = $caption;
        if (!empty($locationParts)) {
            $enhancedCaption .= "\n\n" . implode("\n", $locationParts);
        }

        return trim($enhancedCaption);
    }

    /**
     * Importer les données UNESCO depuis un fichier CSV
     */
    public function importUnescoData($csvFile): int
    {
        $imported = 0;

        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle); // Ignorer l'en-tête

            while (($data = fgetcsv($handle)) !== FALSE) {
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO unesco_sites 
                    (name, description, latitude, longitude, country, category) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $data[0], // name
                    $data[1], // description
                    floatval($data[2]), // latitude
                    floatval($data[3]), // longitude
                    $data[4], // country
                    $data[5]  // category
                ]);

                $imported++;
            }
            fclose($handle);
        }

        return $imported;
    }

    /**
     * Importer les lieux d'intérêt depuis un fichier CSV
     */
    public function importPlacesData($csvFile): int
    {
        $imported = 0;

        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== FALSE) {
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO places 
                    (name, type, latitude, longitude, address, rating) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $data[0], // name
                    $data[1], // type
                    floatval($data[2]), // latitude
                    floatval($data[3]), // longitude
                    $data[4], // address
                    floatval($data[5] ?? 0) // rating
                ]);

                $imported++;
            }
            fclose($handle);
        }

        return $imported;
    }

    /**
     * Créer les fichiers CSV d'exemple pour UNESCO
     */
    public function createSampleUnescoData($outputFile): void
    {
        $sampleData = [
            ['name', 'description', 'latitude', 'longitude', 'country', 'category'],
            ['Palace of Versailles', 'Historic French palace', 48.8048649, 2.1203554, 'France', 'Cultural'],
            ['Mont-Saint-Michel', 'Medieval abbey on tidal island', 48.636063, -1.511457, 'France', 'Cultural'],
            ['Palace of Fontainebleau', 'Former royal residence', 48.4020368, 2.6969279, 'France', 'Cultural'],
            ['Sainte-Chapelle', 'Gothic royal chapel', 48.8554004, 2.3448071, 'France', 'Cultural'],
            ['Château de Chambord', 'Renaissance castle', 47.6161742, 1.5170043, 'France', 'Cultural']
        ];

        $fp = fopen($outputFile, 'w');
        foreach ($sampleData as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
}
