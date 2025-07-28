<?php
// download_data.php - Script pour télécharger et importer les données géographiques

require_once 'src/Services/Database.php';
require_once 'src/Services/Geolocation.php';

$config = include('config/config.php');
$db = new Database($config['database']);
$geo = new Geolocation($db->getPDO());

echo "🌍 Téléchargement des données géographiques...\n\n";

// 1. Créer des données UNESCO d'exemple
echo "📋 Création des données UNESCO...\n";
$unescoFile = 'data/unesco_sites.csv';
$geo->createSampleUnescoData($unescoFile);

// Compléter avec plus de sites français
$additionalUnescoData = [
    ['Château et parc de Versailles', 'Château royal et jardins', 48.8048649, 2.1203554, 'France', 'Culturel'],
    ['Mont-Saint-Michel', 'Abbaye médiévale sur îlot rocheux', 48.636063, -1.511457, 'France', 'Culturel'],
    ['Château de Fontainebleau', 'Résidence royale historique', 48.4020368, 2.6969279, 'France', 'Culturel'],
    ['Sainte-Chapelle', 'Chapelle gothique royale', 48.8554004, 2.3448071, 'France', 'Culturel'],
    ['Château de Chambord', 'Château Renaissance', 47.6161742, 1.5170043, 'France', 'Culturel'],
    ['Cité de Carcassonne', 'Ville fortifiée médiévale', 43.2081, 2.3508, 'France', 'Culturel'],
    ['Pont du Gard', 'Aqueduc romain', 43.9475, 4.5358, 'France', 'Culturel'],
    ['Palais des Papes', 'Palais pontifical gothique', 43.9509, 4.8075, 'France', 'Culturel'],
    ['Abbaye de Fontenay', 'Abbaye cistercienne', 47.6383, 4.3889, 'France', 'Culturel'],
    ['Château de Chantilly', 'Château et musée', 49.1936, 2.4853, 'France', 'Culturel']
];

$fp = fopen($unescoFile, 'a');
foreach ($additionalUnescoData as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

echo "✅ Fichier UNESCO créé: $unescoFile\n";

// 2. Créer des données de lieux d'intérêt
echo "📋 Création des données de lieux d'intérêt...\n";
$placesFile = 'data/places.csv';

$placesData = [
    ['name', 'type', 'latitude', 'longitude', 'address', 'rating'],
    // Hôtels de luxe parisiens
    ['Hôtel Plaza Athénée', 'hotel', 48.8662, 2.3048, '25 Avenue Montaigne, Paris', 4.5],
    ['Le Bristol Paris', 'hotel', 48.8719, 2.3162, '112 Rue du Faubourg Saint-Honoré, Paris', 4.6],
    ['Hôtel George V', 'hotel', 48.8689, 2.3006, '31 Avenue George V, Paris', 4.4],
    ['Le Meurice', 'hotel', 48.8656, 2.3284, '228 Rue de Rivoli, Paris', 4.3],

    // Restaurants étoilés
    ['Guy Savoy', 'restaurant', 48.8636, 2.3266, '18 Rue Troyon, Paris', 4.8],
    ['Le Cinq', 'restaurant', 48.8689, 2.3006, '31 Avenue George V, Paris', 4.7],
    ['Epicure', 'restaurant', 48.8719, 2.3162, '112 Rue du Faubourg Saint-Honoré, Paris', 4.6],

    // Monuments et attractions
    ['Tour Eiffel', 'attraction', 48.8584, 2.2945, 'Champ de Mars, Paris', 4.2],
    ['Musée du Louvre', 'attraction', 48.8606, 2.3376, 'Rue de Rivoli, Paris', 4.5],
    ['Notre-Dame de Paris', 'attraction', 48.8530, 2.3499, '6 Parvis Notre-Dame, Paris', 4.3],
    ['Arc de Triomphe', 'attraction', 48.8738, 2.2950, 'Place Charles de Gaulle, Paris', 4.1],
    ['Sacré-Cœur', 'attraction', 48.8867, 2.3431, '35 Rue du Chevalier de la Barre, Paris', 4.4],

    // Châteaux Loire
    ['Hôtel de France', 'hotel', 47.6175, 1.5167, 'Chambord', 3.8],
    ['Restaurant La Grenouillère', 'restaurant', 47.6180, 1.5200, 'Chambord', 4.2],

    // Versailles
    ['Trianon Palace', 'hotel', 48.8008, 2.1189, '1 Boulevard de la Reine, Versailles', 4.3],
    ['Gordon Ramsay au Trianon', 'restaurant', 48.8008, 2.1189, 'Versailles', 4.5]
];

$fp = fopen($placesFile, 'w');
foreach ($placesData as $row) {
    fputcsv($fp, $row);
}
fclose($fp);

echo "✅ Fichier lieux créé: $placesFile\n";

// 3. Importer en base de données
echo "\n📥 Import en base de données...\n";

try {
    $unescoCount = $geo->importUnescoData($unescoFile);
    echo "✅ $unescoCount sites UNESCO importés\n";

    $placesCount = $geo->importPlacesData($placesFile);
    echo "✅ $placesCount lieux d'intérêt importés\n";
} catch (Exception $e) {
    echo "❌ Erreur lors de l'import: " . $e->getMessage() . "\n";
    echo "💡 Assurez-vous que la base de données est configurée et accessible.\n";
}

// 4. Télécharger des données supplémentaires (optionnel)
echo "\n🌐 Sources de données supplémentaires disponibles:\n";
echo "- UNESCO: https://whc.unesco.org/en/list/\n";
echo "- OpenStreetMap: https://overpass-api.de/\n";
echo "- Geonames: http://www.geonames.org/\n";

echo "\n✅ Configuration terminée !\n";
echo "🚀 Vous pouvez maintenant accéder à votre galerie.\n";
