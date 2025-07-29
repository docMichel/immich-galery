<?php
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

// Récupérer les infos de l'image
$stmt = $db->getPDO()->prepare("
    SELECT * FROM gallery_images 
    WHERE gallery_id = ? AND immich_asset_id = ?
");
$stmt->execute([$galleryId, $assetId]);
$imageData = $stmt->fetch();

// Si l'image n'existe pas dans la DB, la créer
if (!$imageData) {
    $stmt = $db->getPDO()->prepare("
        INSERT INTO gallery_images (gallery_id, immich_asset_id, caption, created_at) 
        VALUES (?, ?, '', NOW())
    ");
    $stmt->execute([$galleryId, $assetId]);
    $imageData = ['id' => $db->lastInsertId(), 'caption' => ''];
}

// Traitement du formulaire
$message = '';
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save':
                $stmt = $db->getPDO()->prepare("
                    UPDATE gallery_images 
                    SET caption = ?, caption_source = 'manual'
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['caption'], $imageData['id']]);
                $message = 'Légende sauvegardée !';
                $imageData['caption'] = $_POST['caption'];
                break;

            case 'generate_ai':
                // TODO: Appeler votre API d'IA ici
                $aiCaption = "Description générée par IA (à implémenter)";
                $imageData['caption'] = $aiCaption;
                $message = 'Légende générée par IA';
                break;
        }
    }
}

// Récupérer les métadonnées de l'image depuis Immich
$assetInfo = $immichClient->getAssetInfo($assetId);
$existingCaption = $imageData['caption'] ?? $assetInfo['exifInfo']['ImageDescription'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Éditer la légende</title>
    <link rel="stylesheet" href="../public/assets/css/caption-editor.css">
</head>

<body>
    <div class="editor-container">
        <div class="editor-header">
            <h1>Éditer la légende</h1>
            <a href="javascript:window.close()" style="color: white; text-decoration: none;">✕ Fermer</a>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="editor-body">
            <div class="image-preview">
                <img src="../public/image-proxy.php?id=<?= $assetId ?>&type=original"
                    alt="Image à légender">

                <div class="metadata">
                    <h4>Métadonnées</h4>
                    <?php if ($assetInfo): ?>
                        <p><strong>Fichier:</strong> <?= htmlspecialchars($assetInfo['originalFileName'] ?? 'N/A') ?></p>
                        <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($assetInfo['fileCreatedAt'] ?? 'now')) ?></p>
                        <?php if (isset($assetInfo['exifInfo']['latitude'])): ?>
                            <p><strong>GPS:</strong> <?= $assetInfo['exifInfo']['latitude'] ?>, <?= $assetInfo['exifInfo']['longitude'] ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="caption-form">
                <form method="POST">
                    <div class="form-group">
                        <label for="caption">Légende de l'image</label>
                        <textarea name="caption"
                            id="caption"
                            class="caption-textarea"
                            placeholder="Entrez la légende de l'image..."><?= htmlspecialchars($existingCaption) ?></textarea>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="action" value="save" class="btn btn-primary">
                            💾 Sauvegarder
                        </button>

                        <button type="submit" name="action" value="generate_ai" class="btn btn-ai">
                            🤖 Générer avec IA
                        </button>

                        <button type="button" onclick="window.history.back()" class="btn btn-secondary">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>