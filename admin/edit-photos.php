<?php
// admin/edit-photos.php
require_once '../src/Auth/AdminAuth.php';
require_once '../src/Api/ImmichClient.php';
require_once '../src/Services/Database.php';

$adminAuth = new AdminAuth();
$adminAuth->requireAdmin();

$config = include('../config/config.php');
$immichClient = new ImmichClient($config['immich']['api_url'], $config['immich']['api_key']);
$db = new Database($config['database']);

// Récupérer la galerie
$galleryId = $_GET['gallery'] ?? null;
if (!$galleryId) {
    header('Location: galleries.php');
    exit;
}

// Récupérer les infos de la galerie
$stmt = $db->getPDO()->prepare("SELECT * FROM galleries WHERE id = ?");
$stmt->execute([$galleryId]);
$gallery = $stmt->fetch();

if (!$gallery) {
    die('Galerie non trouvée');
}

// Inclure les helpers
require_once 'edit-photos-data.php';
require_once 'edit-photos-ajax.php';

// Traiter les requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    handleAjaxRequest($_POST['action'], $_POST, $galleryId, $db, $immichClient);
    exit;
}

// Récupérer les données
$photosData = getPhotosData($galleryId, $db, $immichClient);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Édition Photos - <?= htmlspecialchars($gallery['name']) ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/css/gallery.css">
    <link rel="stylesheet" href="../public/assets/css/edit-photos.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid #ddd;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>Édition - <?= htmlspecialchars($gallery['name']) ?></h1>
            <div class="header-actions">
                <a href="galleries.php" class="btn btn-secondary">← Retour</a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="view-toggle">
                <button class="view-btn active" data-view="grid">Grille</button>
                <button class="view-btn" data-view="map">Carte</button>
                <button class="view-btn" data-view="duplicates">Doublons</button>
                <button class="view-btn" data-view="clipboard">Presse-papier</button>
            </div>

            <!-- Dans edit-photos.php, modifier la section toolbar-actions -->
            <div class="toolbar-actions">
                <div class="selection-info">
                    <span id="selectionCount">0</span> photo(s) sélectionnée(s)
                </div>

                <div class="toolbar-group">
                    <button id="btnSelectAll" class="btn btn-secondary">☑️ Tout sélectionner</button>
                </div>

                <div class="toolbar-group">
                    <button id="btnRotateLeft" class="btn btn-secondary" disabled>↺</button>
                    <button id="btnRotateRight" class="btn btn-secondary" disabled>↻</button>
                </div>

                <div class="toolbar-group">
                    <button id="btnCopyGPS" class="btn btn-primary" disabled>📍 Copier GPS</button>
                    <button id="btnPasteGPS" class="btn btn-primary" disabled>📋 Coller GPS</button>
                    <button id="btnMapSelect" class="btn btn-primary" disabled>🗺️ Carte</button>
                    <button id="btnRemoveGPS" class="btn btn-danger" disabled>🗑️ Supprimer GPS</button>
                </div>

                <!-- NOUVEAU : Groupe doublons -->
                <div class="toolbar-group">
                    <button id="btnFindDuplicatesSelection" class="btn btn-warning" disabled>
                        🔍 Doublons sélection
                    </button>
                    <button id="btnFindDuplicatesAll" class="btn btn-warning">
                        🔍 Doublons galerie
                    </button>
                </div>
            </div>
        </div>


        <!-- Vue Grille -->
        <div id="viewGrid" class="view-container active">
            <div class="photo-grid" id="photoGrid">
                <?php foreach ($photosData['imagesByDate'] as $date => $dayPhotos): ?>
                    <div class="date-separator" data-date="<?= $date ?>">
                        <label class="date-label">
                            <input type="checkbox" class="date-select" data-date="<?= $date ?>">
                            <span class="date-text">
                                <?= date('d/m/Y', strtotime($date)) ?>
                                <span class="date-count">(<?= count($dayPhotos) ?> photos)</span>
                            </span>
                        </label>
                    </div>

                    <?php foreach ($dayPhotos as $photo):
                        include 'views/photo-item.php';
                    endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Vue Carte -->
        <div id="viewMap" class="view-container">
            <div id="mainMap" style="height: 600px;"></div>
        </div>

        <!-- Vue Doublons -->
        <div id="viewDuplicates" class="view-container">
            <div class="duplicates-controls">
                <label>
                    Seuil de similarité:
                    <input type="range" id="duplicateThreshold" min="0.7" max="0.95" step="0.05" value="0.85">
                    <span id="thresholdValue">0.85</span>
                </label>
                <button id="btnAnalyzeDuplicates" class="btn btn-primary">Analyser les doublons</button>
            </div>
            <div id="duplicatesResults"></div>
        </div>

        <!-- Vue Presse-papier -->
        <div id="viewClipboard" class="view-container">
            <div id="clipboardContent">
                <p>Presse-papier vide</p>
            </div>
        </div>
    </div>

    <!-- Modal Caption 
    <div id="captionModal" class="modal">
        <div class="modal-content">
            <h2>Éditer la légende</h2>
            <textarea id="captionText" rows="4"></textarea>
            <div class="modal-actions">
                <button onclick="saveCaption()">Enregistrer</button>
                <button onclick="closeCaptionModal()">Annuler</button>
            </div>
        </div>
    </div>
-->


    <!-- MapModal -->
    <?php include 'views/map-modal.php'; ?>

    <!-- Toast -->
    <div id="toast" class="toast"></div>
    <!-- Configuration -->
    <script>
        window.editPhotosConfig = {
            galleryId: <?= $galleryId ?>,
            flaskApiUrl: '<?= $config['immich']['FLASK_API_URL'] ?? 'http://localhost:5001' ?>',
            immichConfig: {
                apiUrl: '<?= $config['immich']['api_url'] ?>',
                apiKey: '<?= $config['immich']['api_key'] ?>'
            }
        };
    </script>

    <!-- JS -->
    <script src="../public/assets/js/modules/SSEManager.js"></script>

    <!-- script type="module">
        import DuplicateManager from '../public/assets/js/edit-photos/modules/DuplicateManager.js';

        window.duplicateManager = new DuplicateManager({
            galleryId: <?= $galleryId ?>,
            flaskUrl: '<?= $config['immich']['FLASK_API_URL'] ?>'
        });
    </script -->
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script src="../public/assets/js/modules/MapManager.js"></script>

    <script src="../public/assets/js/edit-photos/main.js" type="module"></script>

</body>

</html>