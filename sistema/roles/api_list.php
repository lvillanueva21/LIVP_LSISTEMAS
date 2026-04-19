<?php
require_once __DIR__ . '/../includes/roles_admin.php';

$permissions = rls_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = rls_guard_request('POST', $csrfToken, $permissions['view']);
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
        'message' => 'No se pudo cargar roles por esquema incompleto.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$unexpected = rls_reject_unexpected_fields($_POST, [
    'csrf_token',
    'page',
    'per_page',
    'search',
    'estado',
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

$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$perPage = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 10;
$search = (string) ($_POST['search'] ?? '');
$estado = (string) ($_POST['estado'] ?? '');

try {
    $list = rls_list_roles($page, $perPage, $search, $estado);
    $allowedActions = [
        'view' => true,
        'create' => pag_user_has_permission_code($permissions['create']),
        'edit' => pag_user_has_permission_code($permissions['edit']),
        'toggle_state' => pag_user_has_permission_code($permissions['toggle_state']),
    ];

    rls_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'ok',
        'data' => [
            'list' => $list,
            'allowed_actions' => $allowedActions,
            'deuda_tecnica_superadmin_por_nombre' => true,
        ],
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    rls_json_response(500, [
        'ok' => false,
        'code' => 'error_listado',
        'message' => 'No se pudo cargar roles.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}
