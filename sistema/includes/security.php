<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';

function lsis_security_table_exists($tableName)
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

function lsis_security_policy_defaults()
{
    return [
        'control_sesiones_activo' => 0,
        'max_dispositivos_activo' => 0,
        'max_dispositivos' => 1,
        'timeout_inactividad_activo' => 0,
        'timeout_inactividad_minutos' => 30,

        'limitador_login_activo' => 0,
        'max_intentos_fallidos' => 5,
        'ventana_intentos_minutos' => 15,
        'bloqueo_temporal_activo' => 0,
        'bloqueo_temporal_minutos' => 15,

        'control_abuso_setup_activo' => 0,
        'max_intentos_setup' => 5,
        'ventana_setup_minutos' => 15,
        'bloqueo_setup_minutos' => 15,
    ];
}

function lsis_get_security_policy()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cfg = lsis_security_policy_defaults();

    if (!lsis_security_table_exists('lsis_configuracion_seguridad')) {
        $cache = $cfg;
        return $cache;
    }

    try {
        $sql = "
            SELECT
                control_sesiones_activo,
                max_dispositivos_activo,
                max_dispositivos,
                timeout_inactividad_activo,
                timeout_inactividad_minutos,
                limitador_login_activo,
                max_intentos_fallidos,
                ventana_intentos_minutos,
                bloqueo_temporal_activo,
                bloqueo_temporal_minutos,
                control_abuso_setup_activo,
                max_intentos_setup,
                ventana_setup_minutos,
                bloqueo_setup_minutos
            FROM lsis_configuracion_seguridad
            WHERE id = 1
            LIMIT 1
        ";

        $st = db()->prepare($sql);
        $st->execute();
        $row = $st->fetch();

        if ($row) {
            foreach ($cfg as $k => $v) {
                if (array_key_exists($k, $row)) {
                    $cfg[$k] = (int) $row[$k];
                }
            }
        }
    } catch (Throwable $e) {
        $cfg = lsis_security_policy_defaults();
    }

    if ($cfg['max_dispositivos'] < 1) {
        $cfg['max_dispositivos'] = 1;
    }
    if ($cfg['timeout_inactividad_minutos'] < 1) {
        $cfg['timeout_inactividad_minutos'] = 1;
    }
    if ($cfg['max_intentos_fallidos'] < 1) {
        $cfg['max_intentos_fallidos'] = 1;
    }
    if ($cfg['ventana_intentos_minutos'] < 1) {
        $cfg['ventana_intentos_minutos'] = 1;
    }
    if ($cfg['bloqueo_temporal_minutos'] < 1) {
        $cfg['bloqueo_temporal_minutos'] = 1;
    }
    if ($cfg['max_intentos_setup'] < 1) {
        $cfg['max_intentos_setup'] = 1;
    }
    if ($cfg['ventana_setup_minutos'] < 1) {
        $cfg['ventana_setup_minutos'] = 1;
    }
    if ($cfg['bloqueo_setup_minutos'] < 1) {
        $cfg['bloqueo_setup_minutos'] = 1;
    }

    $cache = $cfg;
    return $cache;
}

function lsis_security_client_ip()
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return substr($ip, 0, 45);
}

function lsis_security_client_user_agent()
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return substr($ua, 0, 255);
}

function lsis_csrf_get_token($formKey)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $formKey = trim((string) $formKey);
    if ($formKey === '') {
        return '';
    }

    if (!isset($_SESSION['lsis_csrf']) || !is_array($_SESSION['lsis_csrf'])) {
        $_SESSION['lsis_csrf'] = [];
    }

    if (empty($_SESSION['lsis_csrf'][$formKey])) {
        $_SESSION['lsis_csrf'][$formKey] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['lsis_csrf'][$formKey];
}

function lsis_csrf_validate_token($formKey, $token, $rotate = true)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $formKey = trim((string) $formKey);
    $token = (string) $token;

    if ($formKey === '' || $token === '') {
        return false;
    }

    $stored = $_SESSION['lsis_csrf'][$formKey] ?? '';
    if (!is_string($stored) || $stored === '') {
        return false;
    }

    $ok = hash_equals($stored, $token);
    if ($ok && $rotate) {
        unset($_SESSION['lsis_csrf'][$formKey]);
    }

    return $ok;
}

function lsis_security_record_attempt($endpoint, $usuario, $exito, $motivo)
{
    if (!lsis_security_table_exists('lsis_intentos_acceso')) {
        return;
    }

    $endpoint = substr(trim((string) $endpoint), 0, 50);
    $usuario = trim((string) $usuario);
    $usuario = $usuario === '' ? null : substr($usuario, 0, 20);
    $ip = lsis_security_client_ip();
    $ua = lsis_security_client_user_agent();
    $exito = ((int) $exito === 1) ? 1 : 0;
    $motivo = trim((string) $motivo);
    $motivo = $motivo === '' ? null : substr($motivo, 0, 50);

    try {
        $sql = "
            INSERT INTO lsis_intentos_acceso
                (endpoint, usuario, ip, user_agent, exito, motivo, intento_at)
            VALUES
                (?, ?, ?, ?, ?, ?, NOW())
        ";
        $st = db()->prepare($sql);
        $st->execute([$endpoint, $usuario, $ip, $ua, $exito, $motivo]);
    } catch (Throwable $e) {
        // No romper flujo principal.
    }
}

function lsis_security_is_login_blocked($usuario, &$meta = null)
{
    $policy = lsis_get_security_policy();
    $usuario = trim((string) $usuario);
    $ip = lsis_security_client_ip();

    $meta = [
        'blocked' => false,
        'count' => 0,
        'blocked_until' => null,
    ];

    if ((int) $policy['limitador_login_activo'] !== 1) {
        return false;
    }

    if (!lsis_security_table_exists('lsis_intentos_acceso')) {
        return false;
    }

    $maxFail = (int) $policy['max_intentos_fallidos'];
    $windowMin = (int) $policy['ventana_intentos_minutos'];
    $blockActive = ((int) $policy['bloqueo_temporal_activo'] === 1);
    $blockMin = (int) $policy['bloqueo_temporal_minutos'];

    $from = date('Y-m-d H:i:s', time() - ($windowMin * 60));

    try {
        $sql = "
            SELECT COUNT(*) AS c, MAX(intento_at) AS last_fail
            FROM lsis_intentos_acceso
            WHERE endpoint = 'login'
              AND exito = 0
              AND usuario = ?
              AND ip = ?
              AND intento_at >= ?
        ";
        $st = db()->prepare($sql);
        $st->execute([$usuario, $ip, $from]);
        $row = $st->fetch();

        $count = (int) ($row['c'] ?? 0);
        $lastFail = (string) ($row['last_fail'] ?? '');
        $meta['count'] = $count;

        if ($count < $maxFail) {
            return false;
        }

        if (!$blockActive) {
            $meta['blocked'] = true;
            return true;
        }

        if ($lastFail === '') {
            $meta['blocked'] = true;
            return true;
        }

        $untilTs = strtotime($lastFail . ' +' . $blockMin . ' minutes');
        if ($untilTs === false) {
            $meta['blocked'] = true;
            return true;
        }

        $meta['blocked_until'] = date('Y-m-d H:i:s', $untilTs);
        $meta['blocked'] = (time() < $untilTs);
        return $meta['blocked'];
    } catch (Throwable $e) {
        return false;
    }
}

function lsis_security_is_setup_blocked(&$meta = null)
{
    $policy = lsis_get_security_policy();
    $ip = lsis_security_client_ip();

    $meta = [
        'blocked' => false,
        'count' => 0,
        'blocked_until' => null,
    ];

    if ((int) $policy['control_abuso_setup_activo'] !== 1) {
        return false;
    }

    if (!lsis_security_table_exists('lsis_intentos_acceso')) {
        return false;
    }

    $maxFail = (int) $policy['max_intentos_setup'];
    $windowMin = (int) $policy['ventana_setup_minutos'];
    $blockMin = (int) $policy['bloqueo_setup_minutos'];

    $from = date('Y-m-d H:i:s', time() - ($windowMin * 60));

    try {
        $sql = "
            SELECT COUNT(*) AS c, MAX(intento_at) AS last_fail
            FROM lsis_intentos_acceso
            WHERE endpoint = 'registro_inicial'
              AND exito = 0
              AND ip = ?
              AND intento_at >= ?
        ";
        $st = db()->prepare($sql);
        $st->execute([$ip, $from]);
        $row = $st->fetch();

        $count = (int) ($row['c'] ?? 0);
        $lastFail = (string) ($row['last_fail'] ?? '');
        $meta['count'] = $count;

        if ($count < $maxFail) {
            return false;
        }

        if ($lastFail === '') {
            $meta['blocked'] = true;
            return true;
        }

        $untilTs = strtotime($lastFail . ' +' . $blockMin . ' minutes');
        if ($untilTs === false) {
            $meta['blocked'] = true;
            return true;
        }

        $meta['blocked_until'] = date('Y-m-d H:i:s', $untilTs);
        $meta['blocked'] = (time() < $untilTs);
        return $meta['blocked'];
    } catch (Throwable $e) {
        return false;
    }
}
