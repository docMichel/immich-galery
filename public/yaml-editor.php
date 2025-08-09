<?php
// public/yaml-editor.php
session_start();

// Configuration
$BASE_PATH = realpath(__DIR__ . '/../'); // Racine du projet
$ALLOWED_EXTENSIONS = ['yaml', 'yml'];
$EXCLUDED_DIRS = ['vendor', 'node_modules', '.git', 'cache', 'logs'];

// Fonction pour vérifier si un chemin est sûr
function isPathSafe($path, $basePath)
{
    $realPath = realpath($path);
    $realBase = realpath($basePath);
    return $realPath && strpos($realPath, $realBase) === 0;
}

// Router
$action = $_GET['action'] ?? 'browse';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'browse':
            // Parcourir les dossiers
            $path = $_GET['path'] ?? '';
            $fullPath = $BASE_PATH;

            if ($path) {
                $fullPath = realpath($BASE_PATH . '/' . $path);
                if (!isPathSafe($fullPath, $BASE_PATH)) {
                    throw new Exception('Chemin non autorisé');
                }
            }

            $items = [];

            // Dossier parent
            if ($path) {
                $parentPath = dirname($path);
                $items[] = [
                    'name' => '..',
                    'type' => 'directory',
                    'path' => $parentPath === '.' ? '' : $parentPath
                ];
            }

            // Scanner le dossier
            $files = scandir($fullPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $fullPath . '/' . $file;
                $relativePath = $path ? $path . '/' . $file : $file;

                if (is_dir($filePath)) {
                    // Exclure certains dossiers
                    if (in_array($file, $EXCLUDED_DIRS)) continue;

                    $items[] = [
                        'name' => $file,
                        'type' => 'directory',
                        'path' => $relativePath
                    ];
                } else {
                    // Vérifier l'extension
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if (in_array($ext, $ALLOWED_EXTENSIONS)) {
                        $items[] = [
                            'name' => $file,
                            'type' => 'file',
                            'path' => $relativePath,
                            'size' => filesize($filePath),
                            'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                            'writable' => is_writable($filePath)
                        ];
                    }
                }
            }

            // Trier : dossiers d'abord, puis fichiers
            usort($items, function ($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'directory' ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });

            echo json_encode([
                'success' => true,
                'path' => $path,
                'items' => $items
            ]);
            break;

        case 'load':
            // Charger un fichier
            $filepath = $_GET['file'] ?? '';
            $fullPath = realpath($BASE_PATH . '/' . $filepath);

            if (!isPathSafe($fullPath, $BASE_PATH)) {
                throw new Exception('Chemin non autorisé');
            }

            if (!file_exists($fullPath)) {
                throw new Exception('Fichier non trouvé');
            }

            $content = file_get_contents($fullPath);

            // Créer le dossier de backup pour ce fichier
            $backupDir = dirname($fullPath) . '/.backups';
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Compter les backups
            $filename = basename($filepath);
            $backups = glob($backupDir . '/' . $filename . '.*.bak');

            echo json_encode([
                'success' => true,
                'content' => $content,
                'filepath' => $filepath,
                'filename' => $filename,
                'backups' => count($backups),
                'writable' => is_writable($fullPath)
            ]);
            break;

        case 'save':
            // Sauvegarder un fichier
            $data = json_decode(file_get_contents('php://input'), true);
            $filepath = $data['filepath'] ?? '';
            $content = $data['content'] ?? '';

            $fullPath = realpath($BASE_PATH . '/' . $filepath);

            if (!isPathSafe($fullPath, $BASE_PATH)) {
                throw new Exception('Chemin non autorisé');
            }

            if (!is_writable($fullPath)) {
                throw new Exception('Fichier non modifiable');
            }

            // Créer un backup
            $backupDir = dirname($fullPath) . '/.backups';
            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = basename($filepath);
            $backupName = $filename . '.' . date('Ymd_His') . '.bak';
            copy($fullPath, $backupDir . '/' . $backupName);

            // Sauvegarder
            if (file_put_contents($fullPath, $content) === false) {
                throw new Exception('Erreur lors de la sauvegarde');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Fichier sauvegardé avec succès'
            ]);
            break;

        case 'recent':
            // Obtenir les fichiers récents depuis la session
            $recent = $_SESSION['recent_files'] ?? [];

            // Vérifier que les fichiers existent encore
            $validRecent = [];
            foreach ($recent as $file) {
                $fullPath = $BASE_PATH . '/' . $file;
                if (file_exists($fullPath) && isPathSafe($fullPath, $BASE_PATH)) {
                    $validRecent[] = [
                        'path' => $file,
                        'name' => basename($file),
                        'dir' => dirname($file)
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'files' => array_slice($validRecent, 0, 10)
            ]);
            break;

        case 'add_recent':
            // Ajouter aux fichiers récents
            $filepath = $_GET['file'] ?? '';

            if (!isset($_SESSION['recent_files'])) {
                $_SESSION['recent_files'] = [];
            }

            // Retirer si déjà présent
            $_SESSION['recent_files'] = array_diff($_SESSION['recent_files'], [$filepath]);

            // Ajouter au début
            array_unshift($_SESSION['recent_files'], $filepath);

            // Garder seulement les 10 derniers
            $_SESSION['recent_files'] = array_slice($_SESSION['recent_files'], 0, 10);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Action inconnue');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
