<?php
require_once __DIR__ . '/../includes/roles_admin.php';

$permissions = rls_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = rls_guard_request('POST', $csrfToken, $permissions['toggle_state']);
if (empty($guard['ok'])) {
    rls_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

if (!rls_schema_ready()) {
    rls_json_response(500, [
        'ok' => false,
        'code' => 'schema_roles_no_disponible',
        'message' => 'No se pudo actualizar estado por esquema incompleto.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$unexpected = rls_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_rol',
    'estado_objetivo',
]);
if ($unexpected) {
    rls_json_response(422, [
        'ok' => false,
        'code' => 'payload_invalido',
        'message' => 'Se detectaron campos no permitidos.',
        'errors' => ['unexpected_fields' => $unexpected],
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$idRol = isset($_POST['id_rol']) ? (int) $_POST['id_rol'] : 0;
$estadoRaw = (string) ($_POST['estado_objetivo'] ?? '');
if ($idRol <= 0 || ($estadoRaw !== '0' && $estadoRaw !== '1')) {
    rls_json_response(422, [
        'ok' => false,
        'code' => 'parametros_invalidos',
        'message' => 'Parametros invalidos.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
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

    $role = rls_fetch_role_by_id($idRol, true);
    if (!$role) {
        throw new RuntimeException('rol_no_encontrado');
    }

    $isProtectedRole = rls_role_is_protected_row($role);
    $estadoActual = ((int) ($role['estado'] ?? 0) === 1) ? 1 : 0;

    if ($estadoActual === $estadoObjetivo) {
        if (rls_count_active_superadmin_users(true) < 1) {
            throw new RuntimeException('sin_superadmin_funcional');
        }
        if ($ownTx) {
            $pdo->commit();
        }
        rls_json_response(200, [
            'ok' => true,
            'code' => 'sin_cambios',
            'message' => 'No hubo cambios en el estado.',
            'csrf_token_nuevo' => rls_new_csrf_token(),
        ]);
    }

    if ($isProtectedRole && $estadoObjetivo === 0) {
        throw new RuntimeException('rol_protegido_no_inactivable');
    }

    $affectedUserIds = rls_fetch_active_user_ids_by_role($idRol, true);

    if ($estadoObjetivo === 0) {
        $affectedWithoutAlternative = rls_fetch_users_without_alternative_role_when_disabling($idRol);
        if ($affectedWithoutAlternative) {
            throw new RuntimeException('usuarios_quedan_sin_rol_activo');
        }
    }

    $st = $pdo->prepare("\n        UPDATE lsis_roles\n        SET estado = ?,\n            actualizado_en = NOW()\n        WHERE id = ?\n    ");
    $st->execute([$estadoObjetivo, $idRol]);

    if (rls_count_active_superadmin_users(true) < 1) {
        throw new RuntimeException('sin_superadmin_funcional');
    }

    if ($affectedUserIds) {
        lsis_close_active_sessions_by_user_ids($affectedUserIds, 'actualizacion_acceso');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    rls_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Estado del rol actualizado correctamente.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_estado';
    $message = 'No se pudo actualizar estado del rol.';

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'rol_no_encontrado') {
            $code = 'rol_no_encontrado';
            $message = 'Rol no encontrado.';
        } elseif ($e->getMessage() === 'rol_protegido_no_inactivable') {
            $code = 'rol_protegido_no_inactivable';
            $message = 'No se permite inactivar un rol protegido.';
        } elseif ($e->getMessage() === 'usuarios_quedan_sin_rol_activo') {
            $code = 'usuarios_quedan_sin_rol_activo';
            $message = 'No se puede inactivar: usuarios activos quedarian sin rol activo alternativo.';
        } elseif ($e->getMessage() === 'sin_superadmin_funcional') {
            $code = 'sin_superadmin_funcional';
            $message = 'Operacion bloqueada: no puede quedar el sistema sin Superadmin funcional.';
        }
    }

    rls_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}
