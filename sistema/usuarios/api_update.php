<?php
require_once __DIR__ . '/../includes/usuarios_admin.php';

$permissions = uadm_user_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = uadm_guard_request('POST', $csrfToken, $permissions['edit']);
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
        'message' => 'No se pudo editar usuario.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$unexpected = uadm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_usuario',
    'nombres',
    'apellidos',
    'roles',
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
if ($idUsuario <= 0) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'id_invalido',
        'message' => 'Usuario invalido.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

list($okNombres, $nombres, $errNombres) = uadm_validate_person_name($_POST['nombres'] ?? '', 'Nombres');
list($okApellidos, $apellidos, $errApellidos) = uadm_validate_person_name($_POST['apellidos'] ?? '', 'Apellidos');
list($okRoles, $roleIds, $errRoles) = uadm_parse_role_ids($_POST['roles'] ?? []);

$errors = [];
if (!$okNombres) {
    $errors['nombres'] = $errNombres;
}
if (!$okApellidos) {
    $errors['apellidos'] = $errApellidos;
}
if (!$okRoles) {
    $errors['roles'] = $errRoles;
}
if ($errors) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa los valores enviados.',
        'errors' => $errors,
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

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
    $beforeRoleIds = uadm_fetch_active_role_ids_for_user($idUsuario, true);

    $existingRoles = uadm_fetch_active_roles_by_ids($roleIds, true);
    if (count($existingRoles) !== count($roleIds)) {
        throw new RuntimeException('roles_invalidos');
    }

    $userEstado = ((int) ($userRow['estado'] ?? 0) === 1) ? 1 : 0;
    $protectedRoleIds = lsis_auth_get_protected_system_role_ids(true, true);
    if ($protectedRoleIds) {
        $wasSuperadmin = uadm_user_is_active_superadmin($idUsuario, true);
        $willBeSuperadmin = false;
        if ($userEstado === 1) {
            foreach ($protectedRoleIds as $protectedRoleId) {
                if (in_array((int) $protectedRoleId, $roleIds, true)) {
                    $willBeSuperadmin = true;
                    break;
                }
            }
        }
        if ($wasSuperadmin && !$willBeSuperadmin) {
            $activeSuperadmins = uadm_count_active_superadmins(true);
            if ($activeSuperadmins <= 1) {
                throw new RuntimeException('ultimo_superadmin');
            }
        }
    }

    $stUpdate = $pdo->prepare("
        UPDATE lsis_usuarios
        SET nombres = ?,
            apellidos = ?,
            actualizado_en = NOW()
        WHERE id = ?
    ");
    $stUpdate->execute([$nombres, $apellidos, $idUsuario]);

    if (!uadm_sync_user_roles($idUsuario, $roleIds)) {
        throw new RuntimeException('roles_no_sincronizados');
    }

    if (!uadm_user_has_any_active_role($idUsuario, true)) {
        throw new RuntimeException('usuario_sin_rol_activo');
    }

    $afterRoleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), function ($value) {
        return $value > 0;
    })));
    sort($afterRoleIds);
    $rolesChanged = ($beforeRoleIds !== $afterRoleIds);

    if ($rolesChanged) {
        lsis_close_active_sessions_by_user_ids([$idUsuario], 'actualizacion_acceso');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    uadm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Usuario actualizado correctamente.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_actualizacion';
    $message = 'No se pudo actualizar el usuario.';
    $validationErrors = [];

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'usuario_no_encontrado') {
            $code = 'usuario_no_encontrado';
            $message = 'Usuario no encontrado.';
        } elseif ($e->getMessage() === 'roles_invalidos') {
            $code = 'roles_invalidos';
            $message = 'Roles invalidos o inactivos.';
            $validationErrors['roles'] = 'Roles invalidos o inactivos.';
        } elseif ($e->getMessage() === 'usuario_sin_rol_activo') {
            $code = 'usuario_sin_rol';
            $message = 'No se permite dejar usuario sin rol activo.';
            $validationErrors['roles'] = 'Debes seleccionar al menos un rol activo.';
        } elseif ($e->getMessage() === 'ultimo_superadmin') {
            $code = 'ultimo_superadmin';
            $message = 'No se puede quitar el ultimo rol Superadmin activo del sistema.';
            $validationErrors['roles'] = 'Operacion bloqueada por seguridad.';
        }
    }

    uadm_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $validationErrors,
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
