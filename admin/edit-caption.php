<?php
// admin/edit-caption.php
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();

$config = include('../config/config.php');
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$db = new Database($config['database']);

$assetId = $_GET['asset'] ?? null;
$galleryId = $_GET['gallery'] ?? null;

if (!$assetId || !$galleryId) {
    die('Paramètres manquants');
}

// Configuration du serveur Flask
$FLASK_API_URL = "http://localhost:5000";

// Récupérer les infos de l'image
$stmt = $db->getPDO()->prepare("
    SELECT * FROM gallery_images 
    WHERE gallery_id = ? AND immich_asset_id = ?
");
$stmt->execute([$galleryId, $assetId]);
$imageData = $stmt->fetch();

if (!$imageData) {
    $stmt = $db->getPDO()->prepare("
        INSERT INTO gallery_images (gallery_id, immich_asset_id, caption, created_at) 
        VALUES (?, ?, '', NOW())
    ");
    $stmt->execute([$galleryId, $assetId]);
    $imageData = ['id' => $db->getPDO()->lastInsertId(), 'caption' => ''];
}

// Récupérer les métadonnées depuis Immich
$assetInfo = $immichClient->getAssetInfo($assetId);
$existingCaption = $imageData['caption'] ?? '';

// Extraire les coordonnées GPS
$latitude = null;
$longitude = null;
if (isset($assetInfo['exifInfo'])) {
    $latitude = $assetInfo['exifInfo']['latitude'] ?? null;
    $longitude = $assetInfo['exifInfo']['longitude'] ?? null;
}

// Sauvegarder si POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $stmt = $db->getPDO()->prepare("
        UPDATE gallery_images 
        SET caption = ?, caption_source = 'ai'
        WHERE id = ?
    ");
    $stmt->execute([$_POST['caption'], $imageData['id']]);

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer la légende avec IA</title>
    <link rel="stylesheet" href="../public/assets/css/caption-editor-ai.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Éditer la légende avec IA</h1>
            <button onclick="window.close()" class="btn-close">✕ Fermer</button>
        </div>

        <div class="main-grid">
            <!-- Colonne gauche : Image -->
            <div class="panel image-panel">
                <div class="image-container">
                    <img src="../public/image-proxy.php?id=<?= $assetId ?>&type=original"
                        alt="Image à légender"
                        id="mainImage">
                </div>

                <div class="metadata-box">
                    <h4>Métadonnées</h4>
                    <?php if ($assetInfo): ?>
                        <p><strong>Fichier:</strong> <?= htmlspecialchars($assetInfo['originalFileName'] ?? 'N/A') ?></p>
                        <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($assetInfo['fileCreatedAt'] ?? 'now')) ?></p>
                        <?php if ($latitude !== null && $longitude !== null): ?>
                            <p><strong>GPS:</strong> <?= number_format($latitude, 6) ?>, <?= number_format($longitude, 6) ?></p>
                        <?php else: ?>
                            <p><strong>GPS:</strong> <em>Aucune donnée de localisation</em></p>
                        <?php endif; ?>

                        <?php if (defined('DEBUG') || isset($_GET['debug'])): ?>
                            <details>
                                <summary>Debug EXIF</summary>
                                <pre style="font-size: 11px; overflow: auto; max-height: 200px;">
<?= htmlspecialchars(json_encode($assetInfo['exifInfo'] ?? [], JSON_PRETTY_PRINT)) ?>
                                </pre>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Colonne droite : Formulaires IA -->
            <div class="panel form-panel">
                <!-- Progress bar -->
                <div id="progressContainer" class="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Initialisation...</div>
                </div>

                <!-- Étapes de génération -->
                <div class="generation-steps">
                    <div class="form-group">
                        <label>Description de l'image</label>
                        <textarea id="imageDescription" rows="3" placeholder="Description générée par l'IA..."><?= htmlspecialchars($imageData['image_description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Contexte géographique</label>
                        <textarea id="geoContext" rows="2" placeholder="Localisation et contexte géographique..."><?= htmlspecialchars($imageData['geo_context'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Enrichissement culturel</label>
                        <textarea id="culturalEnrichment" rows="3" placeholder="Informations culturelles et historiques..."><?= htmlspecialchars($imageData['cultural_enrichment'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Légende finale</label>
                        <textarea id="finalCaption" rows="4" placeholder="Légende finale générée..."><?= htmlspecialchars($existingCaption) ?></textarea>
                    </div>
                </div>

                <!-- Options de génération -->
                <div class="generation-options">
                    <div class="option-group">
                        <label>Langue</label>
                        <select id="language">
                            <option value="français">Français</option>
                            <option value="english">English</option>
                        </select>
                    </div>

                    <div class="option-group">
                        <label>Style</label>
                        <select id="style">
                            <option value="descriptive">Descriptif</option>
                            <option value="creative">Créatif</option>
                            <option value="minimal">Minimaliste</option>
                            <option value="technical">Technique</option>
                        </select>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="action-buttons">
                    <button id="btnGenerate" class="btn btn-primary">
                        🤖 Générer avec IA
                    </button>

                    <button id="btnRegenerate" class="btn btn-secondary" disabled>
                        🔄 Régénérer la légende finale
                    </button>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="caption" id="captionToSave">
                        <button type="submit" class="btn btn-success" onclick="document.getElementById('captionToSave').value = document.getElementById('finalCaption').value">
                            💾 Sauvegarder
                        </button>
                    </form>
                </div>

                <!-- Messages -->
                <div id="messageBox" class="message-box" style="display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Configuration pour JavaScript -->
    <script>
        window.captionEditorConfig = {
            flaskApiUrl: '<?= $FLASK_API_URL ?>',
            assetId: '<?= $assetId ?>',
            galleryId: '<?= $galleryId ?>',
            latitude: <?= $latitude !== null ? $latitude : 'null' ?>,
            longitude: <?= $longitude !== null ? $longitude : 'null' ?>
        };

        // Debug
        console.log('Caption Editor Config:', window.captionEditorConfig);
        console.log('Asset Info:', <?= json_encode($assetInfo ?? null) ?>);
    </script>
    <script src="../public/assets/js/caption-editor-ai.js"></script>
</body>

</html>