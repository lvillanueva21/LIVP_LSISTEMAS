<?php
require_once __DIR__ . '/../includes/permisos_admin.php';

$permissions = prm_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = prm_guard_request('POST', $csrfToken, $permissions['view']);
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
        'message' => 'No se pudo cargar matriz de permisos.',
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}

$unexpected = prm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_rol',
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

try {
    $rolesCatalog = prm_fetch_roles_catalog();
    $permissionsCatalog = prm_fetch_active_permissions_catalog();

    $selectedRoleId = 0;
    if (isset($_POST['id_rol']) && trim((string) $_POST['id_rol']) !== '') {
        list($okRole, $parsedRoleId, $roleError) = prm_parse_role_id($_POST['id_rol']);
        if (!$okRole) {
            prm_json_response(422, [
                'ok' => false,
                'code' => 'rol_invalido',
                'message' => $roleError,
                'csrf_token_nuevo' => prm_new_csrf_token(),
            ]);
        }
        $selectedRoleId = $parsedRoleId;
    } elseif (!empty($rolesCatalog)) {
        $selectedRoleId = (int) ($rolesCatalog[0]['id'] ?? 0);
    }

    $selectedRole = null;
    foreach ($rolesCatalog as $roleRow) {
        if ((int) ($roleRow['id'] ?? 0) === $selectedRoleId) {
            $selectedRole = $roleRow;
            break;
        }
    }

    if ($selectedRoleId > 0 && !$selectedRole) {
        prm_json_response(404, [
            'ok' => false,
            'code' => 'rol_no_encontrado',
            'message' => 'Rol no encontrado.',
            'csrf_token_nuevo' => prm_new_csrf_token(),
        ]);
    }

    $assignedPermissionIds = [];
    if ($selectedRoleId > 0) {
        $assignedPermissionIds = prm_fetch_assigned_active_permission_ids_by_role($selectedRoleId);
    }

    prm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'ok',
        'data' => [
            'roles_catalog' => $rolesCatalog,
            'permissions_catalog' => $permissionsCatalog,
            'role_selected_id' => $selectedRoleId,
            'role_selected' => $selectedRole,
            'assigned_permission_ids' => $assignedPermissionIds,
            'allowed_actions' => [
                'view' => true,
                'assign' => pag_user_has_permission_code($permissions['assign']),
            ],
            'permite_editar_matriz_rol_inactivo' => true,
        ],
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    prm_json_response(500, [
        'ok' => false,
        'code' => 'error_matriz',
        'message' => 'No se pudo cargar matriz de permisos.',
        'csrf_token_nuevo' => prm_new_csrf_token(),
    ]);
}
