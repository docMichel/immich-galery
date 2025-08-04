    <?php
    // admin/api/duplicates.php - API pour la détection de doublons

    require_once '../../src/Auth/AdminAuth.php';
    require_once '../../src/Services/Database.php';

    $adminAuth = new AdminAuth();
    $adminAuth->requireAdmin();

    $config = include('../../config/config.php');
    $flaskUrl = $config['immich']['FLASK_API_URL'] ?? 'http://192.168.1.110:5001';

    header('Content-Type: application/json');

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    try {
        switch ($method) {
            case 'POST':
                if ($action === 'detect') {
                    // Lancer la détection
                    $data = json_decode(file_get_contents('php://input'), true);

                    $response = callFlaskAPI('POST', '/api/duplicates/find-similar-async', [
                        'request_id' => $data['request_id'],
                        'selected_asset_ids' => $data['asset_ids'],
                        'threshold' => $data['threshold'] ?? 0.85,
                        'group_by_time' => $data['group_by_time'] ?? true,
                        'time_window_hours' => $data['time_window_hours'] ?? 24
                    ]);

                    echo json_encode($response);
                }
                break;

            case 'GET':
                if (strpos($action, 'stream/') === 0) {
                    // Proxy SSE
                    $requestId = substr($action, 7);
                    proxySSE($requestId);
                }
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }

    function callFlaskAPI($method, $endpoint, $data = null)
    {
        global $flaskUrl;

        $ch = curl_init($flaskUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    function proxySSE($requestId)
    {
        global $flaskUrl;

        // Headers SSE corrects
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Pour Nginx

        // Désactiver la mise en buffer PHP
        @ini_set('output_buffering', 'Off');
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
        @ob_end_clean();

        $url = $flaskUrl . '/api/duplicates/find-similar-stream/' . $requestId;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
            echo $data;
            if (ob_get_level() > 0) ob_flush();
            flush();
            return strlen($data);
        });
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "event: error\n";
            echo "data: {\"error\": \"HTTP $httpCode from Flask\"}\n\n";
            flush();
        }
    }
