<?php
require_once __DIR__ . '/../includes/usuarios_admin.php';

$permissions = uadm_user_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = uadm_guard_request('POST', $csrfToken, $permissions['reset_password']);
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
        'message' => 'No se pudo resetear la contrasena.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$unexpected = uadm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_usuario',
    'clave_nueva',
    'clave_confirmar',
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

list($okPass, $passwordHash, $errPass) = uadm_validate_password_pair(
    $_POST['clave_nueva'] ?? '',
    $_POST['clave_confirmar'] ?? ''
);
if (!$okPass) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa la contrasena.',
        'errors' => ['clave_nueva' => $errPass],
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

    $st = $pdo->prepare("
        UPDATE lsis_usuarios
        SET clave = ?,
            actualizado_en = NOW()
        WHERE id = ?
    ");
    $st->execute([$passwordHash, $idUsuario]);

    if ($ownTx) {
        $pdo->commit();
    }

    uadm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Contrasena reseteada correctamente.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_reset';
    $message = 'No se pudo resetear la contrasena.';
    if ($e instanceof RuntimeException && $e->getMessage() === 'usuario_no_encontrado') {
        $code = 'usuario_no_encontrado';
        $message = 'Usuario no encontrado.';
    }

    uadm_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
