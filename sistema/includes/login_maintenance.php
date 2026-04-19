<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';

function lsis_maintenance_defaults()
{
    return [
        'retention_days' => [
            'sesiones' => 90,
            'intentos' => 30,
            'bloqueos' => 7,
        ],
        'batch_size' => 500,
    ];
}

function lsis_maintenance_log($event, array $context = [])
{
    $event = trim((string) $event);
    if ($event === '') {
        $event = 'evento';
    }

    $parts = [];
    foreach ($context as $key => $value) {
        if (is_array($value) || is_object($value)) {
            continue;
        }
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
        if ($key === '') {
            continue;
        }
        $val = str_replace(["\r", "\n"], ' ', (string) $value);
        $parts[] = $key . '=' . $val;
    }

    $suffix = $parts ? ' ' . implode(' ', $parts) : '';
    error_log('[login_maintenance][' . $event . ']' . $suffix);
}

function lsis_maintenance_normalize_days($days, $defaultDays)
{
    $days = (int) $days;
    if ($days < 1) {
        $days = (int) $defaultDays;
    }
    if ($days < 1) {
        $days = 1;
    }

    return $days;
}

function lsis_maintenance_normalize_batch($batchSize, $defaultBatch)
{
    $batchSize = (int) $batchSize;
    if ($batchSize < 1) {
        $batchSize = (int) $defaultBatch;
    }
    if ($batchSize < 1) {
        $batchSize = 100;
    }
    if ($batchSize > 2000) {
        $batchSize = 2000;
    }

    return $batchSize;
}

function lsis_maintenance_current_session_hash()
{
    $sid = session_id();
    if ($sid === '') {
        return '';
    }

    return lsis_hash_session_id($sid);
}

function lsis_admin_close_session_by_id($sessionId, $actorAdminId, $allowSelfClose = false)
{
    $defaults = lsis_maintenance_defaults();
    $sessionId = (int) $sessionId;
    $actorAdminId = (int) $actorAdminId;
    $allowSelfClose = (bool) $allowSelfClose;

    $result = [
        'ok' => false,
        'code' => 'error',
        'affected' => 0,
        'session_id' => $sessionId,
        'target_user_id' => 0,
        'actor_admin_id' => $actorAdminId,
        'blocked_self' => false,
        'message' => 'No se pudo cerrar la sesion.',
    ];

    if (!lsis_auth_table_exists('lsis_sesiones')) {
        $result['code'] = 'tabla_no_disponible';
        $result['message'] = 'Tabla de sesiones no disponible.';
        return $result;
    }

    if ($sessionId <= 0) {
        $result['code'] = 'parametro_invalido';
        $result['message'] = 'Sesion invalida.';
        return $result;
    }

    try {
        $stRow = db()->prepare("
            SELECT id, id_usuario, session_id_hash, estado
            FROM lsis_sesiones
            WHERE id = ?
            LIMIT 1
        ");
        $stRow->execute([$sessionId]);
        $row = $stRow->fetch();

        if (!$row) {
            $result['code'] = 'sesion_no_encontrada';
            $result['message'] = 'Sesion no encontrada.';
            lsis_maintenance_log('admin_close_session', [
                'actor_admin_id' => $actorAdminId,
                'session_id' => $sessionId,
                'status' => 'not_found',
            ]);
            return $result;
        }

        $targetUserId = (int) ($row['id_usuario'] ?? 0);
        $estado = (int) ($row['estado'] ?? 0);
        $targetHash = (string) ($row['session_id_hash'] ?? '');
        $result['target_user_id'] = $targetUserId;

        if ($estado !== 1) {
            $result['ok'] = true;
            $result['code'] = 'sesion_ya_cerrada';
            $result['message'] = 'La sesion ya estaba cerrada.';
            return $result;
        }

        $currentHash = lsis_maintenance_current_session_hash();
        if (!$allowSelfClose && $currentHash !== '' && hash_equals($targetHash, $currentHash)) {
            $result['code'] = 'auto_cierre_bloqueado';
            $result['blocked_self'] = true;
            $result['message'] = 'No se permite cerrar la sesion activa del admin.';
            lsis_maintenance_log('admin_close_session', [
                'actor_admin_id' => $actorAdminId,
                'session_id' => $sessionId,
                'target_user_id' => $targetUserId,
                'status' => 'blocked_self',
            ]);
            return $result;
        }

        $stClose = db()->prepare("
            UPDATE lsis_sesiones
            SET estado = 0,
                logout_at = NOW(),
                motivo_cierre = 'forzada_admin',
                actualizado_en = NOW()
            WHERE id = ?
              AND estado = 1
        ");
        $stClose->execute([$sessionId]);

        $affected = (int) $stClose->rowCount();
        $result['affected'] = $affected;
        if ($affected > 0) {
            $result['ok'] = true;
            $result['code'] = 'ok';
            $result['message'] = 'Sesion cerrada por admin.';
            lsis_maintenance_log('admin_close_session', [
                'actor_admin_id' => $actorAdminId,
                'session_id' => $sessionId,
                'target_user_id' => $targetUserId,
                'status' => 'closed',
                'affected' => $affected,
            ]);
            return $result;
        }

        $result['ok'] = true;
        $result['code'] = 'sin_cambios';
        $result['message'] = 'No hubo cambios al cerrar sesion.';
        return $result;
    } catch (Throwable $e) {
        lsis_maintenance_log('admin_close_session', [
            'actor_admin_id' => $actorAdminId,
            'session_id' => $sessionId,
            'status' => 'error',
            'error' => $e->getMessage(),
        ]);
        return $result;
    }
}

function lsis_admin_close_user_sessions($targetUserId, $actorAdminId, $allowSelfClose = false)
{
    $targetUserId = (int) $targetUserId;
    $actorAdminId = (int) $actorAdminId;
    $allowSelfClose = (bool) $allowSelfClose;

    $result = [
        'ok' => false,
        'code' => 'error',
        'affected' => 0,
        'target_user_id' => $targetUserId,
        'actor_admin_id' => $actorAdminId,
        'skipped_self' => 0,
        'message' => 'No se pudieron cerrar las sesiones del usuario.',
    ];

    if (!lsis_auth_table_exists('lsis_sesiones')) {
        $result['code'] = 'tabla_no_disponible';
        $result['message'] = 'Tabla de sesiones no disponible.';
        return $result;
    }

    if ($targetUserId <= 0) {
        $result['code'] = 'parametro_invalido';
        $result['message'] = 'Usuario invalido.';
        return $result;
    }

    try {
        $st = db()->prepare("
            SELECT id, session_id_hash
            FROM lsis_sesiones
            WHERE id_usuario = ?
              AND estado = 1
            ORDER BY id ASC
        ");
        $st->execute([$targetUserId]);
        $rows = $st->fetchAll();

        if (!$rows) {
            $result['ok'] = true;
            $result['code'] = 'sin_sesiones_activas';
            $result['message'] = 'No hay sesiones activas para cerrar.';
            return $result;
        }

        $currentHash = lsis_maintenance_current_session_hash();
        $idsToClose = [];
        $skippedSelf = 0;
        foreach ($rows as $row) {
            $sid = (int) ($row['id'] ?? 0);
            $hash = (string) ($row['session_id_hash'] ?? '');
            if ($sid <= 0) {
                continue;
            }

            if (!$allowSelfClose && $currentHash !== '' && hash_equals($hash, $currentHash)) {
                $skippedSelf++;
                continue;
            }

            $idsToClose[] = $sid;
        }

        $result['skipped_self'] = $skippedSelf;

        if (!$idsToClose) {
            $result['ok'] = true;
            $result['code'] = 'auto_cierre_bloqueado';
            $result['message'] = 'No se cerraron sesiones por bloqueo de auto-cierre.';
            lsis_maintenance_log('admin_close_user_sessions', [
                'actor_admin_id' => $actorAdminId,
                'target_user_id' => $targetUserId,
                'status' => 'blocked_self',
                'skipped_self' => $skippedSelf,
            ]);
            return $result;
        }

        lsis_close_active_sessions_by_ids($idsToClose, 'forzada_admin');

        $affected = count($idsToClose);
        $result['ok'] = true;
        $result['code'] = 'ok';
        $result['affected'] = $affected;
        $result['message'] = 'Sesiones cerradas por admin.';

        lsis_maintenance_log('admin_close_user_sessions', [
            'actor_admin_id' => $actorAdminId,
            'target_user_id' => $targetUserId,
            'status' => 'closed',
            'affected' => $affected,
            'skipped_self' => $skippedSelf,
        ]);

        return $result;
    } catch (Throwable $e) {
        lsis_maintenance_log('admin_close_user_sessions', [
            'actor_admin_id' => $actorAdminId,
            'target_user_id' => $targetUserId,
            'status' => 'error',
            'error' => $e->getMessage(),
        ]);
        return $result;
    }
}

function lsis_maintenance_delete_by_ids($tableName, array $ids)
{
    if (!$ids) {
        return 0;
    }

    $tableName = (string) $tableName;
    $allowed = ['lsis_sesiones', 'lsis_intentos_acceso', 'lsis_bloqueos_login'];
    if (!in_array($tableName, $allowed, true)) {
        return 0;
    }

    $ids = array_values(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    }));
    if (!$ids) {
        return 0;
    }

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM {$tableName} WHERE id IN ($marks)";
    $st = db()->prepare($sql);
    $st->execute($ids);

    return (int) $st->rowCount();
}

function lsis_maintenance_cleanup_closed_sessions($actorAdminId = 0, $retentionDays = 90, $batchSize = 500, $dryRun = false)
{
    $defaults = lsis_maintenance_defaults();
    $actorAdminId = (int) $actorAdminId;
    $retentionDays = lsis_maintenance_normalize_days($retentionDays, $defaults['retention_days']['sesiones']);
    $batchSize = lsis_maintenance_normalize_batch($batchSize, $defaults['batch_size']);
    $dryRun = (bool) $dryRun;

    $result = [
        'ok' => false,
        'code' => 'error',
        'table' => 'lsis_sesiones',
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'dry_run' => $dryRun,
        'would_delete' => 0,
        'deleted' => 0,
        'actor_admin_id' => $actorAdminId,
    ];

    if (!lsis_auth_table_exists('lsis_sesiones')) {
        $result['code'] = 'tabla_no_disponible';
        return $result;
    }

    $countSql = "
        SELECT COUNT(*) AS c
        FROM lsis_sesiones
        WHERE estado = 0
          AND COALESCE(logout_at, ultima_actividad_at, login_at) < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    ";
    $row = db()->query($countSql)->fetch();
    $wouldDelete = (int) ($row['c'] ?? 0);
    $result['would_delete'] = $wouldDelete;

    if ($dryRun) {
        $result['ok'] = true;
        $result['code'] = 'dry_run';
        return $result;
    }

    $deleted = 0;
    while (true) {
        $selectSql = "
            SELECT id
            FROM lsis_sesiones
            WHERE estado = 0
              AND COALESCE(logout_at, ultima_actividad_at, login_at) < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
            ORDER BY id ASC
            LIMIT {$batchSize}
        ";
        $ids = db()->query($selectSql)->fetchAll(PDO::FETCH_COLUMN);
        $ids = $ids ? array_map('intval', $ids) : [];
        if (!$ids) {
            break;
        }

        $deleted += lsis_maintenance_delete_by_ids('lsis_sesiones', $ids);

        if (count($ids) < $batchSize) {
            break;
        }
    }

    $result['ok'] = true;
    $result['code'] = 'ok';
    $result['deleted'] = $deleted;

    lsis_maintenance_log('cleanup_sesiones', [
        'actor_admin_id' => $actorAdminId,
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'would_delete' => $wouldDelete,
        'deleted' => $deleted,
    ]);

    return $result;
}

function lsis_maintenance_cleanup_old_attempts($actorAdminId = 0, $retentionDays = 30, $batchSize = 500, $dryRun = false)
{
    $defaults = lsis_maintenance_defaults();
    $actorAdminId = (int) $actorAdminId;
    $retentionDays = lsis_maintenance_normalize_days($retentionDays, $defaults['retention_days']['intentos']);
    $batchSize = lsis_maintenance_normalize_batch($batchSize, $defaults['batch_size']);
    $dryRun = (bool) $dryRun;

    $result = [
        'ok' => false,
        'code' => 'error',
        'table' => 'lsis_intentos_acceso',
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'dry_run' => $dryRun,
        'would_delete' => 0,
        'deleted' => 0,
        'actor_admin_id' => $actorAdminId,
    ];

    if (!lsis_security_table_exists('lsis_intentos_acceso')) {
        $result['code'] = 'tabla_no_disponible';
        return $result;
    }

    $countSql = "
        SELECT COUNT(*) AS c
        FROM lsis_intentos_acceso
        WHERE intento_at < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    ";
    $row = db()->query($countSql)->fetch();
    $wouldDelete = (int) ($row['c'] ?? 0);
    $result['would_delete'] = $wouldDelete;

    if ($dryRun) {
        $result['ok'] = true;
        $result['code'] = 'dry_run';
        return $result;
    }

    $deleted = 0;
    while (true) {
        $selectSql = "
            SELECT id
            FROM lsis_intentos_acceso
            WHERE intento_at < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
            ORDER BY id ASC
            LIMIT {$batchSize}
        ";
        $ids = db()->query($selectSql)->fetchAll(PDO::FETCH_COLUMN);
        $ids = $ids ? array_map('intval', $ids) : [];
        if (!$ids) {
            break;
        }

        $deleted += lsis_maintenance_delete_by_ids('lsis_intentos_acceso', $ids);

        if (count($ids) < $batchSize) {
            break;
        }
    }

    $result['ok'] = true;
    $result['code'] = 'ok';
    $result['deleted'] = $deleted;

    lsis_maintenance_log('cleanup_intentos', [
        'actor_admin_id' => $actorAdminId,
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'would_delete' => $wouldDelete,
        'deleted' => $deleted,
    ]);

    return $result;
}

function lsis_maintenance_cleanup_old_login_blocks($actorAdminId = 0, $retentionDays = 7, $batchSize = 500, $dryRun = false)
{
    $defaults = lsis_maintenance_defaults();
    $actorAdminId = (int) $actorAdminId;
    $retentionDays = lsis_maintenance_normalize_days($retentionDays, $defaults['retention_days']['bloqueos']);
    $batchSize = lsis_maintenance_normalize_batch($batchSize, $defaults['batch_size']);
    $dryRun = (bool) $dryRun;

    $result = [
        'ok' => false,
        'code' => 'error',
        'table' => 'lsis_bloqueos_login',
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'dry_run' => $dryRun,
        'would_delete' => 0,
        'deleted' => 0,
        'actor_admin_id' => $actorAdminId,
    ];

    if (!lsis_security_table_exists('lsis_bloqueos_login')) {
        $result['code'] = 'tabla_no_disponible';
        return $result;
    }

    $condition = "
        (bloqueado_hasta IS NULL OR bloqueado_hasta < NOW())
        AND COALESCE(ultimo_intento_at, bloqueado_hasta, actualizado_en, creado_en) < DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)
    ";

    $countSql = "
        SELECT COUNT(*) AS c
        FROM lsis_bloqueos_login
        WHERE {$condition}
    ";
    $row = db()->query($countSql)->fetch();
    $wouldDelete = (int) ($row['c'] ?? 0);
    $result['would_delete'] = $wouldDelete;

    if ($dryRun) {
        $result['ok'] = true;
        $result['code'] = 'dry_run';
        return $result;
    }

    $deleted = 0;
    while (true) {
        $selectSql = "
            SELECT id
            FROM lsis_bloqueos_login
            WHERE {$condition}
            ORDER BY id ASC
            LIMIT {$batchSize}
        ";
        $ids = db()->query($selectSql)->fetchAll(PDO::FETCH_COLUMN);
        $ids = $ids ? array_map('intval', $ids) : [];
        if (!$ids) {
            break;
        }

        $deleted += lsis_maintenance_delete_by_ids('lsis_bloqueos_login', $ids);

        if (count($ids) < $batchSize) {
            break;
        }
    }

    $result['ok'] = true;
    $result['code'] = 'ok';
    $result['deleted'] = $deleted;

    lsis_maintenance_log('cleanup_bloqueos', [
        'actor_admin_id' => $actorAdminId,
        'retention_days' => $retentionDays,
        'batch_size' => $batchSize,
        'would_delete' => $wouldDelete,
        'deleted' => $deleted,
    ]);

    return $result;
}

function lsis_maintenance_run_cleanup($actorAdminId = 0, $dryRun = false, $batchSize = 500, array $retentionDays = [])
{
    $defaults = lsis_maintenance_defaults();
    $days = $defaults['retention_days'];
    foreach ($retentionDays as $k => $v) {
        if (array_key_exists($k, $days)) {
            $days[$k] = (int) $v;
        }
    }

    $resSes = lsis_maintenance_cleanup_closed_sessions($actorAdminId, $days['sesiones'], $batchSize, $dryRun);
    $resInt = lsis_maintenance_cleanup_old_attempts($actorAdminId, $days['intentos'], $batchSize, $dryRun);
    $resBlo = lsis_maintenance_cleanup_old_login_blocks($actorAdminId, $days['bloqueos'], $batchSize, $dryRun);

    $ok = !empty($resSes['ok']) && !empty($resInt['ok']) && !empty($resBlo['ok']);

    return [
        'ok' => $ok,
        'code' => $ok ? 'ok' : 'error',
        'dry_run' => (bool) $dryRun,
        'actor_admin_id' => (int) $actorAdminId,
        'retention_days' => $days,
        'batch_size' => lsis_maintenance_normalize_batch($batchSize, $defaults['batch_size']),
        'results' => [
            'sesiones' => $resSes,
            'intentos' => $resInt,
            'bloqueos' => $resBlo,
        ],
    ];
}
