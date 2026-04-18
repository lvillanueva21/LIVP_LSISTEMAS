<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$cfg = require __DIR__ . '/config.php';

if (!empty($cfg['app']['timezone'])) {
    date_default_timezone_set($cfg['app']['timezone']);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(
        $cfg['db']['host'],
        $cfg['db']['user'],
        $cfg['db']['pass'],
        $cfg['db']['name'],
        (int) $cfg['db']['port']
    );

    $mysqli->set_charset($cfg['db']['charset']);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error de conexion a la base de datos.');
}

function db()
{
    global $mysqli;
    return $mysqli;
}
