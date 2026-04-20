<?php
require_once __DIR__ . '/../includes/permisos_admin.php';

$permissions = prm_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = prm_guard_request('POST', $csrfToken, $permissions['assign']);
if (empty($guard['ok'])) {
    prm_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

if (!prm_required_tables_exist()) {
    prm_json_response(500, [
        'ok' => false,
        'code' => 'tablas_no_disponibles',
        'message' => 'No se pudo guardar matriz de permisos.',
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

$unexpected = prm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_rol',
    'permisos_ids',
]);
if ($unexpected) {
    prm_json_response(422, [
        'ok' => false,
        'code' => 'payload_invalido',
        'message' => 'Se detectaron campos no permitidos.',
        'errors' => ['unexpected_fields' => $unexpected],
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

list($okRole, $roleId, $roleError) = prm_parse_role_id($_POST['id_rol'] ?? '');
if (!$okRole) {
    prm_json_response(422, [
        'ok' => false,
        'code' => 'rol_invalido',
        'message' => $roleError,
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

$rawPermissionIds = $_POST['permisos_ids'] ?? [];
list($okPermissions, $permissionIds, $permissionsError) = prm_parse_permission_ids($rawPermissionIds);
if (!$okPermissions) {
    prm_json_response(422, [
        'ok' => false,
        'code' => 'permisos_invalidos',
        'message' => $permissionsError,
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

$pdo = db();
$ownTx = false;

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $role = prm_fetch_role_by_id($roleId, true);
    if (!$role) {
        throw new RuntimeException('rol_no_encontrado');
    }
    $roleIsActive = ((int) ($role['estado'] ?? 0) === 1);
    $affectedUserIds = $roleIsActive ? rls_fetch_active_user_ids_by_role($roleId, true) : [];
    $beforePermissionIds = prm_fetch_assigned_active_permission_ids_by_role($roleId, true);

    if ($permissionIds) {
        $activeMap = prm_fetch_active_permission_ids_map_by_ids($permissionIds, true);
        if (count($activeMap) !== count($permissionIds)) {
            throw new RuntimeException('permisos_no_activos_o_invalidos');
        }
    }

    if (!prm_save_role_permissions($roleId, $permissionIds)) {
        throw new RuntimeException('no_se_pudo_guardar');
    }

    $afterPermissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds), function ($value) {
        return $value > 0;
    })));
    sort($afterPermissionIds);
    $beforePermissionIds = array_values(array_unique(array_filter(array_map('intval', $beforePermissionIds), function ($value) {
        return $value > 0;
    })));
    sort($beforePermissionIds);
    $permissionsChanged = ($beforePermissionIds !== $afterPermissionIds);

    if ($permissionsChanged && $affectedUserIds) {
        lsis_close_active_sessions_by_user_ids($affectedUserIds, 'actualizacion_acceso');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    prm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Matriz de permisos guardada correctamente.',
        'data' => [
            'id_rol' => $roleId,
            'permisos_ids' => array_values($permissionIds),
            'permite_editar_matriz_rol_inactivo' => true,
        ],
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_guardado';
    $message = 'No se pudo guardar matriz de permisos.';

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'rol_no_encontrado') {
            $code = 'rol_no_encontrado';
            $message = 'Rol no encontrado.';
        } elseif ($e->getMessage() === 'permisos_no_activos_o_invalidos') {
            $code = 'permisos_no_activos_o_invalidos';
            $message = 'Solo se permiten permisos existentes y activos.';
        }
    }

    prm_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}
