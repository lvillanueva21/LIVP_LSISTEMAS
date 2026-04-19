<?php
require_once __DIR__ . '/../includes/usuarios_admin.php';

$permissions = uadm_user_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = uadm_guard_request('POST', $csrfToken, $permissions['toggle_state']);
if (empty($guard['ok'])) {
    uadm_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

if (!uadm_required_tables_exist()) {
    uadm_json_response(500, [
        'ok' => false,
        'code' => 'tablas_no_disponibles',
        'message' => 'No se pudo actualizar el estado.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$unexpected = uadm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_usuario',
    'estado_objetivo',
]);
if ($unexpected) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'payload_invalido',
        'message' => 'Se detectaron campos no permitidos.',
        'errors' => ['unexpected_fields' => $unexpected],
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$idUsuario = isset($_POST['id_usuario']) ? (int) $_POST['id_usuario'] : 0;
$estadoRaw = (string) ($_POST['estado_objetivo'] ?? '');
if ($idUsuario <= 0 || ($estadoRaw !== '0' && $estadoRaw !== '1')) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'parametros_invalidos',
        'message' => 'Parametros invalidos.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
$estadoObjetivo = (int) $estadoRaw;

$pdo = db();
$ownTx = false;
try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $userRow = uadm_fetch_user_by_id($idUsuario, true);
    if (!$userRow) {
        throw new RuntimeException('usuario_no_encontrado');
    }

    $estadoActual = ((int) ($userRow['estado'] ?? 0) === 1) ? 1 : 0;
    if ($estadoActual === $estadoObjetivo) {
        if ($ownTx) {
            $pdo->commit();
        }
        uadm_json_response(200, [
            'ok' => true,
            'code' => 'sin_cambios',
            'message' => 'No hubo cambios en el estado.',
            'csrf_token_nuevo' => uadm_new_csrf_token(),
        ]);
    }

    $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
    if ($estadoObjetivo === 0 && $currentUserId > 0 && $idUsuario === $currentUserId) {
        throw new RuntimeException('auto_inactivacion_bloqueada');
    }

    if ($estadoObjetivo === 0) {
        $isSuperadmin = uadm_user_is_active_superadmin($idUsuario, true);
        if ($isSuperadmin) {
            $activeSuperadmins = uadm_count_active_superadmins(true);
            if ($activeSuperadmins <= 1) {
                throw new RuntimeException('ultimo_superadmin');
            }
        }
    } else {
        if (!uadm_user_has_any_active_role($idUsuario, true)) {
            throw new RuntimeException('usuario_sin_rol_activo');
        }
    }

    $st = $pdo->prepare("
        UPDATE lsis_usuarios
        SET estado = ?,
            actualizado_en = NOW()
        WHERE id = ?
    ");
    $st->execute([$estadoObjetivo, $idUsuario]);

    if ($ownTx) {
        $pdo->commit();
    }

    uadm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Estado actualizado correctamente.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_estado';
    $message = 'No se pudo actualizar el estado.';

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'usuario_no_encontrado') {
            $code = 'usuario_no_encontrado';
            $message = 'Usuario no encontrado.';
        } elseif ($e->getMessage() === 'auto_inactivacion_bloqueada') {
            $code = 'auto_inactivacion_bloqueada';
            $message = 'No se permite auto-inactivacion del usuario logueado.';
        } elseif ($e->getMessage() === 'ultimo_superadmin') {
            $code = 'ultimo_superadmin';
            $message = 'No se puede inactivar al ultimo Superadmin activo.';
        } elseif ($e->getMessage() === 'usuario_sin_rol_activo') {
            $code = 'usuario_sin_rol';
            $message = 'No se puede activar un usuario sin rol activo.';
        }
    }

    uadm_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
