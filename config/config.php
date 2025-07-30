<?php
return [
    'immich' => [
        'api_url' => 'http://192.168.1.110:3001',
        'api_key' => '3FIKnjqJp4cMWH6NVDFxjdm2wnKHp8DiKuOppMlj6w', // À configurer

        'FLASK_API_URL' => "http://192.168.1.110:5001"
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'immich_gallery',
        'user' => 'root',
        'password' => 'mysqlroot',
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
