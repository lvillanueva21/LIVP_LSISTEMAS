<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

return [
    'db' => [
        'host'    => getenv('LSIS_DB_HOST') ?: 'localhost',
        'name'    => getenv('LSIS_DB_NAME') ?: 'lsis_db',
        'user'    => getenv('LSIS_DB_USER') ?: 'root',
        'pass'    => getenv('LSIS_DB_PASS') ?: '',
        'port'    => (int) (getenv('LSIS_DB_PORT') ?: 3306),
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'America/Bogota',
        'session_name' => 'lsis_sess',
    ],
];
