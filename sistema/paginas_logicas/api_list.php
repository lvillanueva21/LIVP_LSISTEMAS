<?php
require_once __DIR__ . '/../includes/paginas_logicas_admin.php';

$permissions = pgl_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = pgl_guard_request('POST', $csrfToken, $permissions['view']);
if (empty($guard['ok'])) {
    pgl_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

if (!pgl_schema_ready()) {
    pgl_json_response(500, [
        'ok' => false,
        'code' => 'schema_no_disponible',
        'message' => 'No se pudo cargar paginas logicas por esquema incompleto.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$unexpected = pgl_reject_unexpected_fields($_POST, [
    'csrf_token',
    'page',
    'per_page',
    'search',
    'estado',
    'tipo',
]);
if ($unexpected) {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'payload_invalido',
        'message' => 'Se detectaron campos no permitidos.',
        'errors' => ['unexpected_fields' => $unexpected],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$perPage = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 10;
$search = (string) ($_POST['search'] ?? '');
$estado = (string) ($_POST['estado'] ?? '');
$tipo = (string) ($_POST['tipo'] ?? '');

try {
    $list = pgl_fetch_pages_list($page, $perPage, $search, $estado, $tipo);
    $modulesCatalog = pgl_scan_modules_catalog();
    $permissionsCatalog = pgl_fetch_active_permissions_catalog();
    $parentCatalog = pgl_fetch_parent_containers_catalog(0);

    $allowedActions = [
        'view' => true,
        'create' => pag_user_has_permission_code($permissions['create']),
        'edit' => pag_user_has_permission_code($permissions['edit']),
        'toggle_state' => pag_user_has_permission_code($permissions['toggle_state']),
    ];

    pgl_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'ok',
        'data' => [
            'list' => $list,
            'modules_catalog' => $modulesCatalog,
            'permissions_catalog' => $permissionsCatalog,
            'parent_catalog' => $parentCatalog,
            'allowed_actions' => $allowedActions,
            'fixed_editable_fields' => [
                'titulo_menu',
                'titulo_pagina',
                'descripcion_pagina',
                'icono',
                'orden_menu',
                'visible_menu',
            ],
            'fixed_locked_fields' => [
                'slug_pagina',
                'modulo_codigo',
                'archivo_section',
                'es_fija',
                'es_contenedor',
                'id_permiso_requerido',
                'id_padre',
                'estado',
            ],
        ],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    pgl_json_response(500, [
        'ok' => false,
        'code' => 'error_listado',
        'message' => 'No se pudo cargar paginas logicas.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}
