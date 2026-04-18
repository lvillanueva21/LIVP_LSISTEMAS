<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$cfg = require __DIR__ . '/config.php';

if (!empty($cfg['app']['timezone'])) {
    date_default_timezone_set($cfg['app']['timezone']);
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $cfg['db']['host'],
        (int) $cfg['db']['port'],
        $cfg['db']['name'],
        $cfg['db']['charset']
    );

    $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error de conexion a la base de datos.');
}

function db()
{
    global $pdo;
    return $pdo;
}
