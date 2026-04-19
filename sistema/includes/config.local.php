<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'u517204426_lv1g1S1sT3M4z',
        'user'    => 'u517204426_lv1g12026',
        'pass'    => 'yE6zW=U5>a@4',
        'port'    => 3306,
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'America/Lima',
        'session_name' => 'lsis_sess',
        'install_key'  => 'LSistemas@@2026',
    ],
];
