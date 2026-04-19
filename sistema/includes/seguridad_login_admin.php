<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pag_access.php';

function slg_admin_permission_code()
{
    return 'seguridad_login.manage';
}

function slg_admin_csrf_form_key()
{
    return 'seguridad_login_form';
}

function slg_admin_new_csrf_token()
{
    return lsis_csrf_get_token(slg_admin_csrf_form_key());
}

function slg_admin_json_response($httpStatus, array $payload)
{
    http_response_code((int) $httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function slg_admin_guard_request($expectedMethod, $csrfToken)
{
    $expectedMethod = strtoupper(trim((string) $expectedMethod));
    $currentMethod = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? '')));

    if ($currentMethod !== $expectedMethod) {
        return [
            'ok' => false,
            'http_status' => 405,
            'code' => 'metodo_no_permitido',
            'message' => 'Metodo no permitido.',
        ];
    }

    if (!isAuthenticated()) {
        return [
            'ok' => false,
            'http_status' => 401,
            'code' => 'sesion_requerida',
            'message' => 'Sesion no valida.',
        ];
    }

    if (!pag_user_has_permission_code(slg_admin_permission_code())) {
        return [
            'ok' => false,
            'http_status' => 403,
            'code' => 'permiso_denegado',
            'message' => 'No tienes permiso para esta accion.',
        ];
    }

    if (!lsis_csrf_validate_token(slg_admin_csrf_form_key(), (string) $csrfToken)) {
        return [
            'ok' => false,
            'http_status' => 419,
            'code' => 'csrf_invalido',
            'message' => 'Token CSRF invalido.',
        ];
    }

    return [
        'ok' => true,
        'http_status' => 200,
        'code' => 'ok',
        'message' => 'ok',
    ];
}

function slg_admin_policy_rules()
{
    return [
        'control_sesiones_activo' => ['type' => 'bool'],
        'max_dispositivos_activo' => ['type' => 'bool'],
        'max_dispositivos' => ['type' => 'int', 'min' => 1, 'max' => 10],
        'timeout_inactividad_activo' => ['type' => 'bool'],
        'timeout_inactividad_minutos' => ['type' => 'int', 'min' => 1, 'max' => 480],
        'limitador_login_activo' => ['type' => 'bool'],
        'max_intentos_fallidos' => ['type' => 'int', 'min' => 1, 'max' => 20],
        'ventana_intentos_minutos' => ['type' => 'int', 'min' => 1, 'max' => 120],
        'bloqueo_temporal_activo' => ['type' => 'bool'],
        'bloqueo_temporal_minutos' => ['type' => 'int', 'min' => 1, 'max' => 240],
        'control_abuso_setup_activo' => ['type' => 'bool'],
        'max_intentos_setup' => ['type' => 'int', 'min' => 1, 'max' => 20],
        'ventana_setup_minutos' => ['type' => 'int', 'min' => 1, 'max' => 120],
        'bloqueo_setup_minutos' => ['type' => 'int', 'min' => 1, 'max' => 240],
    ];
}

function slg_admin_parse_bool_strict($rawValue, &$ok)
{
    $ok = true;

    if ($rawValue === 0 || $rawValue === '0') {
        return 0;
    }
    if ($rawValue === 1 || $rawValue === '1') {
        return 1;
    }

    $ok = false;
    return 0;
}

function slg_admin_parse_int_strict($rawValue, $min, $max, &$ok)
{
    $ok = true;
    $str = trim((string) $rawValue);
    if ($str === '' || preg_match('/^\d+$/', $str) !== 1) {
        $ok = false;
        return 0;
    }

    $value = (int) $str;
    if ($value < (int) $min || $value > (int) $max) {
        $ok = false;
        return 0;
    }

    return $value;
}

function slg_admin_validate_policy_input(array $input)
{
    $rules = slg_admin_policy_rules();
    $errors = [];
    $clean = [];

    foreach ($rules as $field => $rule) {
        if (!array_key_exists($field, $input)) {
            $errors[$field] = 'Campo requerido.';
            continue;
        }

        $raw = $input[$field];
        if ($rule['type'] === 'bool') {
            $ok = false;
            $clean[$field] = slg_admin_parse_bool_strict($raw, $ok);
            if (!$ok) {
                $errors[$field] = 'Valor booleano invalido.';
            }
            continue;
        }

        if ($rule['type'] === 'int') {
            $ok = false;
            $clean[$field] = slg_admin_parse_int_strict($raw, $rule['min'], $rule['max'], $ok);
            if (!$ok) {
                $errors[$field] = 'Valor fuera de rango.';
            }
            continue;
        }
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'data' => $clean,
    ];
}

function slg_admin_table_exists($tableName)
{
    return lsis_table_exists_cached($tableName);
}

function slg_admin_apply_policy_bounds(array $policy)
{
    $rules = slg_admin_policy_rules();
    foreach ($rules as $field => $rule) {
        if (!array_key_exists($field, $policy)) {
            continue;
        }

        if ($rule['type'] === 'bool') {
            $policy[$field] = ((int) $policy[$field] === 1) ? 1 : 0;
            continue;
        }

        $value = (int) $policy[$field];
        if ($value < (int) $rule['min']) {
            $value = (int) $rule['min'];
        }
        if ($value > (int) $rule['max']) {
            $value = (int) $rule['max'];
        }
        $policy[$field] = $value;
    }

    return $policy;
}

function slg_admin_get_policy_read_model()
{
    $policy = lsis_get_security_policy();
    $policy = slg_admin_apply_policy_bounds($policy);
    $actualizadoEn = null;

    if (slg_admin_table_exists('lsis_configuracion_seguridad')) {
        try {
            $st = db()->prepare("SELECT actualizado_en FROM lsis_configuracion_seguridad WHERE id = 1 LIMIT 1");
            $st->execute();
            $row = $st->fetch();
            if (!empty($row['actualizado_en'])) {
                $actualizadoEn = (string) $row['actualizado_en'];
            }
        } catch (Throwable $e) {
            $actualizadoEn = null;
        }
    }

    return [
        'policy' => $policy,
        'actualizado_en' => $actualizadoEn,
    ];
}

function slg_admin_mask_user_value($userValue)
{
    $userValue = trim((string) $userValue);
    if ($userValue === '') {
        return '';
    }

    $len = strlen($userValue);
    if ($len <= 2) {
        return str_repeat('*', $len);
    }
    if ($len <= 4) {
        return substr($userValue, 0, 1) . str_repeat('*', $len - 2) . substr($userValue, -1);
    }

    return substr($userValue, 0, 2) . str_repeat('*', $len - 4) . substr($userValue, -2);
}

function slg_admin_mask_ip_value($ip)
{
    $ip = trim((string) $ip);
    if ($ip === '') {
        return '';
    }

    if (strpos($ip, '.') !== false) {
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.*.*';
        }
    }

    if (strpos($ip, ':') !== false) {
        return substr($ip, 0, 4) . ':****';
    }

    return '***';
}

function slg_admin_get_snapshot_summary($attemptLimit = 10)
{
    $attemptLimit = (int) $attemptLimit;
    if ($attemptLimit < 1) {
        $attemptLimit = 10;
    }
    if ($attemptLimit > 20) {
        $attemptLimit = 20;
    }

    $snapshot = [
        'sesiones_activas_totales' => 0,
        'bloqueos_activos_totales' => 0,
        'bloqueos_recientes_totales' => 0,
        'ventana_bloqueos_recientes_horas' => 24,
        'ultimos_intentos_limite' => $attemptLimit,
        'ultimos_intentos' => [],
    ];

    if (slg_admin_table_exists('lsis_sesiones')) {
        try {
            $row = db()->query("SELECT COUNT(*) AS c FROM lsis_sesiones WHERE estado = 1")->fetch();
            $snapshot['sesiones_activas_totales'] = (int) ($row['c'] ?? 0);
        } catch (Throwable $e) {
            $snapshot['sesiones_activas_totales'] = 0;
        }
    }

    if (slg_admin_table_exists('lsis_bloqueos_login')) {
        try {
            $rowActivos = db()->query("
                SELECT COUNT(*) AS c
                FROM lsis_bloqueos_login
                WHERE bloqueado_hasta IS NOT NULL
                  AND bloqueado_hasta > NOW()
            ")->fetch();
            $snapshot['bloqueos_activos_totales'] = (int) ($rowActivos['c'] ?? 0);

            $rowRecientes = db()->query("
                SELECT COUNT(*) AS c
                FROM lsis_bloqueos_login
                WHERE COALESCE(ultimo_intento_at, bloqueado_hasta, actualizado_en, creado_en) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetch();
            $snapshot['bloqueos_recientes_totales'] = (int) ($rowRecientes['c'] ?? 0);
        } catch (Throwable $e) {
            $snapshot['bloqueos_activos_totales'] = 0;
            $snapshot['bloqueos_recientes_totales'] = 0;
        }
    }

    if (slg_admin_table_exists('lsis_intentos_acceso')) {
        try {
            $sql = "
                SELECT intento_at, endpoint, exito, motivo, usuario, ip
                FROM lsis_intentos_acceso
                ORDER BY id DESC
                LIMIT {$attemptLimit}
            ";
            $rows = db()->query($sql)->fetchAll();
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'intento_at' => (string) ($row['intento_at'] ?? ''),
                    'endpoint' => (string) ($row['endpoint'] ?? ''),
                    'exito' => ((int) ($row['exito'] ?? 0) === 1) ? 1 : 0,
                    'motivo' => (string) ($row['motivo'] ?? ''),
                    'usuario_ref' => slg_admin_mask_user_value((string) ($row['usuario'] ?? '')),
                    'ip_ref' => slg_admin_mask_ip_value((string) ($row['ip'] ?? '')),
                ];
            }
            $snapshot['ultimos_intentos'] = $out;
        } catch (Throwable $e) {
            $snapshot['ultimos_intentos'] = [];
        }
    }

    return $snapshot;
}

function slg_admin_build_read_payload()
{
    $cfg = slg_admin_get_policy_read_model();
    $snapshot = slg_admin_get_snapshot_summary(10);

    return [
        'policy' => $cfg['policy'],
        'actualizado_en' => $cfg['actualizado_en'],
        'snapshot' => $snapshot,
    ];
}

function slg_admin_save_policy(array $cleanPolicy)
{
    if (!slg_admin_table_exists('lsis_configuracion_seguridad')) {
        return [
            'ok' => false,
            'code' => 'tabla_no_disponible',
            'message' => 'No se encontro configuracion de seguridad.',
        ];
    }

    $fields = array_keys(slg_admin_policy_rules());
    $values = [];
    foreach ($fields as $f) {
        $values[] = (int) ($cleanPolicy[$f] ?? 0);
    }

    $insertCols = implode(', ', $fields);
    $insertMarks = implode(', ', array_fill(0, count($fields), '?'));
    $updatePairs = [];
    foreach ($fields as $f) {
        $updatePairs[] = $f . ' = VALUES(' . $f . ')';
    }
    $updateSql = implode(",\n                ", $updatePairs);

    try {
        $pdo = db();
        $ownTx = false;
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $ownTx = true;
        }

        $sql = "
            INSERT INTO lsis_configuracion_seguridad
                (id, {$insertCols}, creado_en, actualizado_en)
            VALUES
                (1, {$insertMarks}, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                {$updateSql},
                actualizado_en = NOW()
        ";
        $st = $pdo->prepare($sql);
        $st->execute($values);

        if ($ownTx) {
            $pdo->commit();
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'message' => 'Configuracion guardada correctamente.',
        ];
    } catch (Throwable $e) {
        if (isset($ownTx) && $ownTx && isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'ok' => false,
            'code' => 'error_guardado',
            'message' => 'No se pudo guardar la configuracion.',
        ];
    }
}
