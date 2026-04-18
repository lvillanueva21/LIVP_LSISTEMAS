<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

return [
    'db' => [
        'host'    => getenv('LSIS_DB_HOST') ?: 'localhost',
        'name'    => getenv('LSIS_DB_NAME') ?: 'u517204426_lv1g1S1sT3M4z',
        'user'    => getenv('LSIS_DB_USER') ?: 'u517204426_lv1g12026',
        'pass'    => getenv('LSIS_DB_PASS') ?: 'yE6zW=U5>a@4',
        'port'    => (int) (getenv('LSIS_DB_PORT') ?: 3306),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'America/Lima',
        'session_name' => 'lsis_sess',
        'install_key'  => getenv('LSIS_INSTALL_KEY') ?: 'LSistemas@@2026',
    ],
];
