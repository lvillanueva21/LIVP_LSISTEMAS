<?php
require_once __DIR__ . '/../includes/seguridad_login_admin.php';

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = slg_admin_guard_request('POST', $csrfToken);
if (empty($guard['ok'])) {
    slg_admin_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => slg_admin_new_csrf_token(),
    ]);
}

$payload = slg_admin_build_read_payload();
slg_admin_json_response(200, [
    'ok' => true,
    'code' => 'ok',
    'message' => 'ok',
    'data' => $payload,
    'csrf_token_nuevo' => slg_admin_new_csrf_token(),
]);
