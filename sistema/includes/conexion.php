<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$cfg = require __DIR__ . '/config.php';

$appTimezone = !empty($cfg['app']['timezone']) ? (string) $cfg['app']['timezone'] : 'America/Lima';
date_default_timezone_set($appTimezone);

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

    try {
        // 1) Intentar timezone nominal (America/Lima) si el servidor MySQL lo soporta.
        // 2) Si no lo soporta, usar offset fijo de Peru (UTC-5).
        $pdo->exec("SET time_zone = " . $pdo->quote($appTimezone));
    } catch (Throwable $eTz1) {
        try {
            $pdo->exec("SET time_zone = '-05:00'");
        } catch (Throwable $eTz2) {
            // Si falla, se mantiene timezone por defecto del servidor de BD.
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error de conexion a la base de datos.');
}

function db()
{
    global $pdo;
    return $pdo;
}
