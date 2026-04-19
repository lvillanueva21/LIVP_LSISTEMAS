<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'TU_BASE_DE_DATOS',
        'user'    => 'TU_USUARIO',
        'pass'    => 'TU_PASSWORD',
        'port'    => 3306,
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'America/Lima',
        'session_name' => 'lsis_sess',
        'install_key'  => 'TU_CLAVE_DE_INSTALACION',
    ],
];
