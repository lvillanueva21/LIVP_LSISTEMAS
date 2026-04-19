<?php
require_once __DIR__ . '/../includes/roles_admin.php';

$permissions = rls_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = rls_guard_request('POST', $csrfToken, $permissions['create']);
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
        'message' => 'No se pudo crear rol por esquema incompleto.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$unexpected = rls_reject_unexpected_fields($_POST, [
    'csrf_token',
    'nombre',
    'descripcion',
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

list($okNombre, $nombre, $errNombre) = rls_validate_role_name($_POST['nombre'] ?? '');
list($okDescripcion, $descripcion, $errDescripcion) = rls_validate_role_description($_POST['descripcion'] ?? '');

$errors = [];
if (!$okNombre) {
    $errors['nombre'] = $errNombre;
}
if (!$okDescripcion) {
    $errors['descripcion'] = $errDescripcion;
}
if ($errors) {
    rls_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa los valores enviados.',
        'errors' => $errors,
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

if (rls_is_superadmin_name($nombre)) {
    rls_json_response(422, [
        'ok' => false,
        'code' => 'superadmin_reservado',
        'message' => 'No se permite crear otro rol Superadmin.',
        'errors' => ['nombre' => 'Nombre reservado para el rol base.'],
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$pdo = db();
$ownTx = false;

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $duplicated = rls_find_role_by_name_ci($nombre, 0, true);
    if ($duplicated) {
        throw new RuntimeException('nombre_duplicado');
    }

    $stInsert = $pdo->prepare("\n        INSERT INTO lsis_roles (nombre, descripcion, estado, creado_en, actualizado_en)\n        VALUES (?, ?, 1, NOW(), NOW())\n    ");
    $stInsert->execute([$nombre, $descripcion]);

    $newRoleId = (int) $pdo->lastInsertId();
    if ($newRoleId <= 0) {
        throw new RuntimeException('rol_no_creado');
    }

    if (rls_count_active_superadmin_users(true) < 1) {
        throw new RuntimeException('sin_superadmin_funcional');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    rls_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Rol creado correctamente.',
        'data' => ['id_rol' => $newRoleId],
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_creacion';
    $message = 'No se pudo crear el rol.';
    $validationErrors = [];

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'nombre_duplicado') {
            $code = 'nombre_duplicado';
            $message = 'El nombre de rol ya existe.';
            $validationErrors['nombre'] = 'El nombre de rol ya existe.';
        } elseif ($e->getMessage() === 'sin_superadmin_funcional') {
            $code = 'sin_superadmin_funcional';
            $message = 'Operacion bloqueada: no puede quedar el sistema sin Superadmin funcional.';
        }
    }

    rls_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $validationErrors,
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}
