<?php
require_once '../config/config.php';
$config = include('../config/config.php');

$assetId = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'thumbnail';
$size = $_GET['size'] ?? null;

if (!$assetId) {
    http_response_code(400);
    die('ID manquant');
}

$url = $config['immich']['api_url'] . "/api/assets/{$assetId}/thumbnail";
if ($type === 'original') {
    $url = $config['immich']['api_url'] . "/api/assets/{$assetId}/original";
}
if ($size) {
    $url .= "?size={$size}";
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['x-api-key: ' . $config['immich']['api_key']],
    CURLOPT_FOLLOWLOCATION => true
]);

$data = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

header('Content-Type: ' . $contentType);
echo $data;
