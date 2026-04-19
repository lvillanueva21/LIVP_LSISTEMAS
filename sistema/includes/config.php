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

$envMap = [
    'db.host' => getenv('LSIS_DB_HOST'),
    'db.name' => getenv('LSIS_DB_NAME'),
    'db.user' => getenv('LSIS_DB_USER'),
    'db.pass' => getenv('LSIS_DB_PASS'),
    'db.port' => getenv('LSIS_DB_PORT'),
    'app.install_key' => getenv('LSIS_INSTALL_KEY'),
];

foreach ($envMap as $path => $envValue) {
    if ($envValue === false || $envValue === '') {
        continue;
    }

    if ($path === 'db.port') {
        $config['db']['port'] = (int) $envValue;
        if ($config['db']['port'] <= 0) {
            $config['db']['port'] = 3306;
        }
        continue;
    }

    $parts = explode('.', $path, 2);
    if (count($parts) === 2) {
        $config[$parts[0]][$parts[1]] = $envValue;
    }
}

return $config;
