<?php
require_once __DIR__ . '/../includes/usuarios_admin.php';

$permissions = uadm_user_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = uadm_guard_request('POST', $csrfToken, $permissions['view']);
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
        'message' => 'No se pudo cargar usuarios.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$perPage = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 10;
$search = (string) ($_POST['search'] ?? '');
$estado = (string) ($_POST['estado'] ?? '');

try {
    $list = uadm_list_users($page, $perPage, $search, $estado);
    $rolesCatalog = uadm_fetch_active_roles_catalog();
    $allowedActions = [
        'view' => true,
        'create' => pag_user_has_permission_code($permissions['create']),
        'edit' => pag_user_has_permission_code($permissions['edit']),
        'toggle_state' => pag_user_has_permission_code($permissions['toggle_state']),
        'reset_password' => pag_user_has_permission_code($permissions['reset_password']),
    ];

    uadm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'ok',
        'data' => [
            'list' => $list,
            'roles_catalog' => $rolesCatalog,
            'allowed_actions' => $allowedActions,
        ],
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    uadm_json_response(500, [
        'ok' => false,
        'code' => 'error_listado',
        'message' => 'No se pudo cargar usuarios.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
