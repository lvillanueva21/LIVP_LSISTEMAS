<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

function lsis_bootstrap_fail($publicMessage, $logMessage = '')
{
    if ($logMessage !== '') {
        error_log('[conexion] ' . $logMessage);
    }

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    exit($publicMessage);
}

$cfg = require __DIR__ . '/config.php';
$localConfigPath = __DIR__ . '/config.local.php';
$localConfigExists = is_file($localConfigPath);

$dbHost = trim((string) ($cfg['db']['host'] ?? ''));
$dbName = trim((string) ($cfg['db']['name'] ?? ''));
$dbUser = trim((string) ($cfg['db']['user'] ?? ''));
$dbPass = (string) ($cfg['db']['pass'] ?? '');
$dbPort = (int) ($cfg['db']['port'] ?? 3306);
$dbCharset = trim((string) ($cfg['db']['charset'] ?? 'utf8mb4'));

if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
    $extra = $localConfigExists
        ? 'Revisa host, name, user y pass en sistema/includes/config.local.php.'
        : 'No se encontro sistema/includes/config.local.php. Copia config.example.php como config.local.php y completa credenciales.';
    lsis_bootstrap_fail('Configuracion de base de datos incompleta. ' . $extra, 'Datos DB incompletos.');
}

if ($dbPort <= 0) {
    $dbPort = 3306;
}
if ($dbCharset === '') {
    $dbCharset = 'utf8mb4';
}

$appTimezone = !empty($cfg['app']['timezone']) ? (string) $cfg['app']['timezone'] : 'America/Lima';
date_default_timezone_set($appTimezone);

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $dbHost,
        $dbPort,
        $dbName,
        $dbCharset
    );

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
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
    lsis_bootstrap_fail(
        'Error de conexion a la base de datos. Verifica credenciales y acceso del servidor MySQL.',
        $e->getMessage()
    );
}

function db()
{
    global $pdo;
    return $pdo;
}

function lsis_table_exists_cached($tableName)
{
    static $cache = [];

    $tableName = trim((string) $tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $sql = "
            SELECT COUNT(*) AS c
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ";
        $st = db()->prepare($sql);
        $st->execute([$tableName]);
        $row = $st->fetch();
        $cache[$tableName] = !empty($row['c']);
    } catch (Throwable $e) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function lsis_get_config()
{
    global $cfg;
    return is_array($cfg) ? $cfg : [];
}
