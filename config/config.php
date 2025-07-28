<?php
return [
    'immich' => [
        'api_url' => 'http://localhost:2283',
        'api_key' => '', // À configurer
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'immich_gallery',
        'user' => 'root',
        'password' => '',
    ],
    'geolocation' => [
        'enable_unesco' => true,
        'enable_places' => true,
        'search_radius_km' => 50,
    ],
    'captions' => [
        'auto_generate' => true,
        'python_script_path' => '../python/generate_captions.py',
    ]
];
