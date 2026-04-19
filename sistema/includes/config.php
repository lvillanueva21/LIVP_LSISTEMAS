<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

function lsis_config_merge(array $base, array $extra)
{
    foreach ($extra as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = lsis_config_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }

    return $base;
}

$config = [
    'db' => [
        'host'    => '',
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'port'    => 3306,
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone'     => 'America/Lima',
        'session_name' => 'lsis_sess',
        'install_key'  => '',
    ],
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = lsis_config_merge($config, $localConfig);
    }
}

return $config;
