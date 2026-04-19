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

function lsis_security_login_dependencies_ok(&$meta = null)
{
    $meta = [
        'ok' => true,
        'reason' => '',
    ];

    if (!lsis_security_table_exists('lsis_configuracion_seguridad')) {
        $meta['ok'] = false;
        $meta['reason'] = 'falta_lsis_configuracion_seguridad';
        return false;
    }

    $policy = lsis_get_security_policy();
    if ((int) $policy['limitador_login_activo'] !== 1) {
        return true;
    }

    $requiredTables = ['lsis_intentos_acceso', 'lsis_bloqueos_login'];
    foreach ($requiredTables as $tableName) {
        if (!lsis_security_table_exists($tableName)) {
            $meta['ok'] = false;
            $meta['reason'] = 'falta_' . $tableName;
            return false;
        }
    }

    return true;
}

function lsis_security_setup_dependencies_ok(&$meta = null)
{
    $meta = [
        'ok' => true,
        'reason' => '',
    ];

    if (!lsis_security_table_exists('lsis_configuracion_seguridad')) {
        $meta['ok'] = false;
        $meta['reason'] = 'falta_lsis_configuracion_seguridad';
        return false;
    }

    $policy = lsis_get_security_policy();
    if ((int) $policy['control_abuso_setup_activo'] !== 1) {
        return true;
    }

    if (!lsis_security_table_exists('lsis_intentos_acceso')) {
        $meta['ok'] = false;
        $meta['reason'] = 'falta_lsis_intentos_acceso';
        return false;
    }

    return true;
}

function lsis_security_is_login_blocked($usuario, &$meta = null)
{
    $depsMeta = [];
    if (!lsis_security_login_dependencies_ok($depsMeta)) {
        $meta = [
            'blocked' => true,
            'count' => 0,
            'blocked_until' => null,
            'fail_closed' => true,
            'reason' => (string) ($depsMeta['reason'] ?? 'dependencias_login'),
        ];
        return true;
    }

    $policy = lsis_get_security_policy();
    $usuario = trim((string) $usuario);
    $ip = lsis_security_client_ip();

    $meta = [
        'blocked' => false,
        'count' => 0,
        'blocked_until' => null,
        'fail_closed' => false,
        'reason' => '',
    ];

    if ((int) $policy['limitador_login_activo'] !== 1) {
        return false;
    }

    $maxFail = (int) $policy['max_intentos_fallidos'];
    $windowMin = (int) $policy['ventana_intentos_minutos'];
    $blockActive = ((int) $policy['bloqueo_temporal_activo'] === 1);
    $blockMin = (int) $policy['bloqueo_temporal_minutos'];

    try {
        $st = db()->prepare("
            SELECT id, intentos_fallidos, ultimo_intento_at, bloqueado_hasta
            FROM lsis_bloqueos_login
            WHERE usuario = ?
              AND ip = ?
            LIMIT 1
        ");
        $st->execute([$usuario, $ip]);
        $row = $st->fetch();

        if (!$row) {
            return false;
        }

        $rowId = (int) ($row['id'] ?? 0);
        $count = (int) ($row['intentos_fallidos'] ?? 0);
        $lastFail = (string) ($row['ultimo_intento_at'] ?? '');
        $blockedUntil = (string) ($row['bloqueado_hasta'] ?? '');
        $meta['count'] = $count;

        $nowTs = time();
        $windowEndTs = 0;
        if ($lastFail !== '') {
            $lastFailTs = strtotime($lastFail);
            if ($lastFailTs !== false) {
                $windowEndTs = $lastFailTs + ($windowMin * 60);
            }
        }

        if ($windowEndTs > 0 && $nowTs >= $windowEndTs && $rowId > 0) {
            $stReset = db()->prepare("
                UPDATE lsis_bloqueos_login
                SET intentos_fallidos = 0,
                    bloqueado_hasta = NULL,
                    actualizado_en = NOW()
                WHERE id = ?
            ");
            $stReset->execute([$rowId]);
            return false;
        }

        if ($blockActive) {
            if ($blockedUntil !== '') {
                $blockedUntilTs = strtotime($blockedUntil);
                if ($blockedUntilTs !== false) {
                    if ($nowTs < $blockedUntilTs) {
                        $meta['blocked'] = true;
                        $meta['blocked_until'] = date('Y-m-d H:i:s', $blockedUntilTs);
                        return true;
                    }

                    if ($rowId > 0) {
                        $stClear = db()->prepare("
                            UPDATE lsis_bloqueos_login
                            SET intentos_fallidos = 0,
                                bloqueado_hasta = NULL,
                                actualizado_en = NOW()
                            WHERE id = ?
                        ");
                        $stClear->execute([$rowId]);
                    }
                    return false;
                }
            }

            if ($count >= $maxFail && $windowEndTs > 0) {
                $untilTs = $nowTs + ($blockMin * 60);
                $untilStr = date('Y-m-d H:i:s', $untilTs);
                $stForce = db()->prepare("
                    UPDATE lsis_bloqueos_login
                    SET bloqueado_hasta = ?,
                        actualizado_en = NOW()
                    WHERE id = ?
                ");
                $stForce->execute([$untilStr, $rowId]);

                $meta['blocked'] = true;
                $meta['blocked_until'] = $untilStr;
                return true;
            }

            return false;
        }

        if ($count >= $maxFail && $windowEndTs > 0 && $nowTs < $windowEndTs) {
            $meta['blocked'] = true;
            $meta['blocked_until'] = date('Y-m-d H:i:s', $windowEndTs);
            return true;
        }

        return false;
    } catch (Throwable $e) {
        $meta['blocked'] = true;
        $meta['fail_closed'] = true;
        $meta['reason'] = 'error_login_block_check';
        return true;
    }
}

function lsis_security_register_credential_failure($usuario, &$meta = null)
{
    $meta = [
        'blocked' => false,
        'count' => 0,
        'blocked_until' => null,
        'fail_closed' => false,
        'reason' => '',
    ];

    $depsMeta = [];
    if (!lsis_security_login_dependencies_ok($depsMeta)) {
        $meta['blocked'] = true;
        $meta['fail_closed'] = true;
        $meta['reason'] = (string) ($depsMeta['reason'] ?? 'dependencias_login');
        return false;
    }

    $policy = lsis_get_security_policy();
    if ((int) $policy['limitador_login_activo'] !== 1) {
        return true;
    }

    $usuario = trim((string) $usuario);
    $ip = lsis_security_client_ip();
    $maxFail = (int) $policy['max_intentos_fallidos'];
    $windowMin = (int) $policy['ventana_intentos_minutos'];
    $blockActive = ((int) $policy['bloqueo_temporal_activo'] === 1);
    $blockMin = (int) $policy['bloqueo_temporal_minutos'];
    $pdo = db();
    $ownTx = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $ownTx = true;
        }

        $stSel = $pdo->prepare("
            SELECT id, intentos_fallidos, ultimo_intento_at, bloqueado_hasta
            FROM lsis_bloqueos_login
            WHERE usuario = ?
              AND ip = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stSel->execute([$usuario, $ip]);
        $row = $stSel->fetch();

        if (!$row) {
            $stIns = $pdo->prepare("
                INSERT INTO lsis_bloqueos_login
                    (usuario, ip, intentos_fallidos, ultimo_intento_at, bloqueado_hasta, creado_en, actualizado_en)
                VALUES
                    (?, ?, 0, NULL, NULL, NOW(), NOW())
            ");
            $stIns->execute([$usuario, $ip]);
            $rowId = (int) $pdo->lastInsertId();
            $row = [
                'id' => $rowId,
                'intentos_fallidos' => 0,
                'ultimo_intento_at' => null,
                'bloqueado_hasta' => null,
            ];
        }

        $rowId = (int) ($row['id'] ?? 0);
        $count = (int) ($row['intentos_fallidos'] ?? 0);
        $lastFail = (string) ($row['ultimo_intento_at'] ?? '');
        $blockedUntil = (string) ($row['bloqueado_hasta'] ?? '');
        $nowTs = time();

        if ($blockedUntil !== '') {
            $blockedUntilTs = strtotime($blockedUntil);
            if ($blockedUntilTs !== false && $nowTs < $blockedUntilTs) {
                $meta['blocked'] = true;
                $meta['count'] = $count;
                $meta['blocked_until'] = date('Y-m-d H:i:s', $blockedUntilTs);
                if ($ownTx) {
                    $pdo->commit();
                }
                return true;
            }
        }

        if ($lastFail !== '') {
            $lastFailTs = strtotime($lastFail);
            if ($lastFailTs !== false && $nowTs >= ($lastFailTs + ($windowMin * 60))) {
                $count = 0;
            }
        }

        $count++;
        $meta['count'] = $count;
        $blockedUntilValue = null;

        if ($count >= $maxFail) {
            if ($blockActive) {
                $blockedUntilTs = $nowTs + ($blockMin * 60);
                $blockedUntilValue = date('Y-m-d H:i:s', $blockedUntilTs);
                $meta['blocked'] = true;
                $meta['blocked_until'] = $blockedUntilValue;
            } else {
                $windowEndTs = $nowTs + ($windowMin * 60);
                $meta['blocked'] = true;
                $meta['blocked_until'] = date('Y-m-d H:i:s', $windowEndTs);
            }
        }

        $stUpd = $pdo->prepare("
            UPDATE lsis_bloqueos_login
            SET intentos_fallidos = ?,
                ultimo_intento_at = NOW(),
                bloqueado_hasta = ?,
                actualizado_en = NOW()
            WHERE id = ?
        ");
        $stUpd->execute([$count, $blockedUntilValue, $rowId]);

        if ($ownTx) {
            $pdo->commit();
        }

        return true;
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $meta['blocked'] = true;
        $meta['fail_closed'] = true;
        $meta['reason'] = 'error_login_block_update';
        return false;
    }
}

function lsis_security_clear_login_block_state($usuario)
{
    $depsMeta = [];
    if (!lsis_security_login_dependencies_ok($depsMeta)) {
        return false;
    }

    $policy = lsis_get_security_policy();
    if ((int) $policy['limitador_login_activo'] !== 1) {
        return true;
    }

    $usuario = trim((string) $usuario);
    $ip = lsis_security_client_ip();

    try {
        $st = db()->prepare("DELETE FROM lsis_bloqueos_login WHERE usuario = ? AND ip = ?");
        $st->execute([$usuario, $ip]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function lsis_security_is_setup_blocked(&$meta = null)
{
    $depsMeta = [];
    if (!lsis_security_setup_dependencies_ok($depsMeta)) {
        $meta = [
            'blocked' => true,
            'count' => 0,
            'blocked_until' => null,
            'fail_closed' => true,
            'reason' => (string) ($depsMeta['reason'] ?? 'dependencias_setup'),
        ];
        return true;
    }

    $policy = lsis_get_security_policy();
    $ip = lsis_security_client_ip();

    $meta = [
        'blocked' => false,
        'count' => 0,
        'blocked_until' => null,
        'fail_closed' => false,
        'reason' => '',
    ];

    if ((int) $policy['control_abuso_setup_activo'] !== 1) {
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
        $meta['blocked'] = true;
        $meta['fail_closed'] = true;
        $meta['reason'] = 'error_setup_block_check';
        return true;
    }
}
