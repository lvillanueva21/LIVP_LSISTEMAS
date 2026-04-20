<?php
require_once __DIR__ . '/../includes/roles_admin.php';

$permissions = rls_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = rls_guard_request('POST', $csrfToken, $permissions['edit']);
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
        'message' => 'No se pudo editar rol por esquema incompleto.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
}

$unexpected = rls_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_rol',
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

$idRol = isset($_POST['id_rol']) ? (int) $_POST['id_rol'] : 0;
if ($idRol <= 0) {
    rls_json_response(422, [
        'ok' => false,
        'code' => 'id_invalido',
        'message' => 'Rol invalido.',
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

$pdo = db();
$ownTx = false;

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $role = rls_fetch_role_by_id($idRol, true);
    if (!$role) {
        throw new RuntimeException('rol_no_encontrado');
    }

    $currentName = (string) ($role['nombre'] ?? '');
    $isCurrentProtected = rls_role_is_protected_row($role);

    if ($isCurrentProtected && strcasecmp($currentName, $nombre) !== 0) {
        throw new RuntimeException('rol_protegido_no_renombrable');
    }

    if (!$isCurrentProtected && rls_is_superadmin_name($nombre)) {
        throw new RuntimeException('superadmin_reservado');
    }

    $duplicated = rls_find_role_by_name_ci($nombre, $idRol, true);
    if ($duplicated) {
        throw new RuntimeException('nombre_duplicado');
    }

    $stUpdate = $pdo->prepare("\n        UPDATE lsis_roles\n        SET nombre = ?,\n            descripcion = ?,\n            actualizado_en = NOW()\n        WHERE id = ?\n    ");
    $stUpdate->execute([$nombre, $descripcion, $idRol]);

    if (rls_count_active_superadmin_users(true) < 1) {
        throw new RuntimeException('sin_superadmin_funcional');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    rls_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Rol actualizado correctamente.',
        'csrf_token_nuevo' => rls_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_actualizacion';
    $message = 'No se pudo actualizar el rol.';
    $validationErrors = [];

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'rol_no_encontrado') {
            $code = 'rol_no_encontrado';
            $message = 'Rol no encontrado.';
        } elseif ($e->getMessage() === 'rol_protegido_no_renombrable') {
            $code = 'rol_protegido_no_renombrable';
            $message = 'No se permite renombrar un rol protegido.';
            $validationErrors['nombre'] = 'Operacion bloqueada por seguridad.';
        } elseif ($e->getMessage() === 'superadmin_reservado') {
            $code = 'superadmin_reservado';
            $message = 'No se permite usar el nombre Superadmin en otro rol.';
            $validationErrors['nombre'] = 'Nombre reservado para el rol base.';
        } elseif ($e->getMessage() === 'nombre_duplicado') {
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
