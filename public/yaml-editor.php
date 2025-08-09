<?php
// public/yaml-editor.php
session_start();

// Configuration
$CONFIG_PATH = __DIR__ . '/../config/';
$BACKUP_PATH = __DIR__ . '/../config/backups/';
$ALLOWED_FILES = ['ai_prompts.yaml']; // Liste blanche des fichiers éditables

// Créer le dossier de backup si nécessaire
if (!file_exists($BACKUP_PATH)) {
    mkdir($BACKUP_PATH, 0755, true);
}

// Fonction pour parser YAML simple (sans extension YAML)
function parseYamlSimple($content)
{
    // Pour un vrai parser, installer: composer require symfony/yaml
    // Ici on fait un parser basique pour les prompts
    $lines = explode("\n", $content);
    $result = [];
    $currentKey = '';
    $currentValue = '';
    $inMultiline = false;

    foreach ($lines as $line) {
        // Ignorer les commentaires et lignes vides
        if (trim($line) === '' || strpos(trim($line), '#') === 0) {
            continue;
        }

        // Détecter le début d'un bloc multilignes
        if (preg_match('/^(\s*)(\w+):\s*\|/', $line, $matches)) {
            if ($currentKey) {
                $result[$currentKey] = trim($currentValue);
            }
            $currentKey = $matches[2];
            $currentValue = '';
            $inMultiline = true;
            continue;
        }

        // Ligne simple clé: valeur
        if (!$inMultiline && preg_match('/^(\s*)(\w+):\s*(.*)$/', $line, $matches)) {
            if ($currentKey) {
                $result[$currentKey] = trim($currentValue);
            }
            $currentKey = $matches[2];
            $currentValue = $matches[3];
            $inMultiline = false;
            continue;
        }

        // Contenu multilignes
        if ($inMultiline) {
            $currentValue .= $line . "\n";
        }
    }

    // Dernière entrée
    if ($currentKey) {
        $result[$currentKey] = trim($currentValue);
    }

    return $result;
}

// Router simple
$action = $_GET['action'] ?? 'list';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'list':
            // Lister les fichiers disponibles
            $files = [];
            foreach ($ALLOWED_FILES as $file) {
                $path = $CONFIG_PATH . $file;
                if (file_exists($path)) {
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($path),
                        'modified' => date('Y-m-d H:i:s', filemtime($path)),
                        'writable' => is_writable($path)
                    ];
                }
            }
            echo json_encode(['success' => true, 'files' => $files]);
            break;

        case 'load':
            // Charger un fichier
            $filename = $_GET['file'] ?? '';
            if (!in_array($filename, $ALLOWED_FILES)) {
                throw new Exception('Fichier non autorisé');
            }

            $filepath = $CONFIG_PATH . $filename;
            if (!file_exists($filepath)) {
                throw new Exception('Fichier non trouvé');
            }

            $content = file_get_contents($filepath);

            // Compter les backups
            $backups = glob($BACKUP_PATH . $filename . '.*.bak');

            echo json_encode([
                'success' => true,
                'content' => $content,
                'filename' => $filename,
                'backups' => count($backups)
            ]);
            break;

        case 'save':
            // Sauvegarder un fichier
            $data = json_decode(file_get_contents('php://input'), true);
            $filename = $data['filename'] ?? '';
            $content = $data['content'] ?? '';

            if (!in_array($filename, $ALLOWED_FILES)) {
                throw new Exception('Fichier non autorisé');
            }

            $filepath = $CONFIG_PATH . $filename;

            // Créer un backup
            if (file_exists($filepath)) {
                $backupName = $filename . '.' . date('Ymd_His') . '.bak';
                copy($filepath, $BACKUP_PATH . $backupName);
            }

            // Sauvegarder
            if (file_put_contents($filepath, $content) === false) {
                throw new Exception('Erreur lors de la sauvegarde');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Fichier sauvegardé avec succès'
            ]);
            break;

        case 'backups':
            // Lister les backups
            $filename = $_GET['file'] ?? '';
            if (!in_array($filename, $ALLOWED_FILES)) {
                throw new Exception('Fichier non autorisé');
            }

            $backups = [];
            $files = glob($BACKUP_PATH . $filename . '.*.bak');
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'size' => filesize($file)
                ];
            }

            // Trier par date décroissante
            usort($backups, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            echo json_encode(['success' => true, 'backups' => $backups]);
            break;

        case 'restore':
            // Restaurer un backup
            $data = json_decode(file_get_contents('php://input'), true);
            $backupName = $data['backup'] ?? '';

            // Vérifier que c'est bien un backup
            if (!preg_match('/^[\w\-\.]+\.yaml\.\d{8}_\d{6}\.bak$/', $backupName)) {
                throw new Exception('Nom de backup invalide');
            }

            $backupPath = $BACKUP_PATH . $backupName;
            if (!file_exists($backupPath)) {
                throw new Exception('Backup non trouvé');
            }

            // Extraire le nom du fichier original
            $originalName = preg_replace('/\.\d{8}_\d{6}\.bak$/', '', $backupName);
            if (!in_array($originalName, $ALLOWED_FILES)) {
                throw new Exception('Fichier non autorisé');
            }

            $originalPath = $CONFIG_PATH . $originalName;

            // Faire un backup du fichier actuel avant de restaurer
            if (file_exists($originalPath)) {
                $newBackupName = $originalName . '.' . date('Ymd_His') . '.before_restore.bak';
                copy($originalPath, $BACKUP_PATH . $newBackupName);
            }

            // Restaurer
            copy($backupPath, $originalPath);

            echo json_encode([
                'success' => true,
                'message' => 'Backup restauré avec succès'
            ]);
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
