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

$validation = slg_admin_validate_policy_input($_POST);
if (empty($validation['ok'])) {
    slg_admin_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa los valores enviados.',
        'errors' => $validation['errors'],
        'csrf_token_nuevo' => slg_admin_new_csrf_token(),
    ]);
}

$saveResult = slg_admin_save_policy($validation['data']);
if (empty($saveResult['ok'])) {
    slg_admin_json_response(500, [
        'ok' => false,
        'code' => (string) ($saveResult['code'] ?? 'error_guardado'),
        'message' => (string) ($saveResult['message'] ?? 'No se pudo guardar la configuracion.'),
        'csrf_token_nuevo' => slg_admin_new_csrf_token(),
    ]);
}

$payload = slg_admin_build_read_payload();
slg_admin_json_response(200, [
    'ok' => true,
    'code' => 'ok',
    'message' => (string) ($saveResult['message'] ?? 'Configuracion guardada correctamente.'),
    'data' => $payload,
    'csrf_token_nuevo' => slg_admin_new_csrf_token(),
]);
