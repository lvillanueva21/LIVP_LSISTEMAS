<?php
require_once __DIR__ . '/../includes/usuarios_admin.php';

$permissions = uadm_user_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = uadm_guard_request('POST', $csrfToken, $permissions['create']);
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
        'message' => 'No se pudo crear usuario.',
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}

$unexpected = uadm_reject_unexpected_fields($_POST, [
    'csrf_token',
    'usuario',
    'nombres',
    'apellidos',
    'clave',
    'clave_confirmar',
    'roles',
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

list($okUsuario, $usuario, $errUsuario) = uadm_validate_usuario_identifier($_POST['usuario'] ?? '');
list($okNombres, $nombres, $errNombres) = uadm_validate_person_name($_POST['nombres'] ?? '', 'Nombres');
list($okApellidos, $apellidos, $errApellidos) = uadm_validate_person_name($_POST['apellidos'] ?? '', 'Apellidos');
list($okPass, $passwordHash, $errPass) = uadm_validate_password_pair($_POST['clave'] ?? '', $_POST['clave_confirmar'] ?? '');
list($okRoles, $roleIds, $errRoles) = uadm_parse_role_ids($_POST['roles'] ?? []);

$errors = [];
if (!$okUsuario) {
    $errors['usuario'] = $errUsuario;
}
if (!$okNombres) {
    $errors['nombres'] = $errNombres;
}
if (!$okApellidos) {
    $errors['apellidos'] = $errApellidos;
}
if (!$okPass) {
    $errors['clave'] = $errPass;
}
if (!$okRoles) {
    $errors['roles'] = $errRoles;
}
if ($errors) {
    uadm_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa los valores enviados.',
        'errors' => $errors,
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

    $existingRoles = uadm_fetch_active_roles_by_ids($roleIds, true);
    if (count($existingRoles) !== count($roleIds)) {
        throw new RuntimeException('roles_invalidos');
    }

    $stExists = $pdo->prepare("SELECT id FROM lsis_usuarios WHERE usuario = ? LIMIT 1 FOR UPDATE");
    $stExists->execute([$usuario]);
    $already = $stExists->fetch();
    if ($already) {
        throw new RuntimeException('usuario_duplicado');
    }

    $stInsert = $pdo->prepare("
        INSERT INTO lsis_usuarios (usuario, clave, nombres, apellidos, estado, creado_en, actualizado_en)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
    ");
    $stInsert->execute([$usuario, $passwordHash, $nombres, $apellidos]);
    $newUserId = (int) $pdo->lastInsertId();
    if ($newUserId <= 0) {
        throw new RuntimeException('usuario_no_creado');
    }

    if (!uadm_sync_user_roles($newUserId, $roleIds)) {
        throw new RuntimeException('roles_no_asignados');
    }

    if (!uadm_user_has_any_active_role($newUserId, true)) {
        throw new RuntimeException('usuario_sin_rol_activo');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    uadm_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Usuario creado correctamente.',
        'data' => ['id_usuario' => $newUserId],
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_creacion';
    $message = 'No se pudo crear el usuario.';
    $validationErrors = [];

    if ($e instanceof RuntimeException) {
        if ($e->getMessage() === 'usuario_duplicado') {
            $code = 'usuario_duplicado';
            $message = 'El usuario ya existe.';
            $validationErrors['usuario'] = 'El usuario ya existe.';
        } elseif ($e->getMessage() === 'roles_invalidos') {
            $code = 'roles_invalidos';
            $message = 'Roles invalidos o inactivos.';
            $validationErrors['roles'] = 'Roles invalidos o inactivos.';
        } elseif ($e->getMessage() === 'usuario_sin_rol_activo') {
            $code = 'usuario_sin_rol';
            $message = 'No se permite crear usuario sin rol activo.';
            $validationErrors['roles'] = 'Debes seleccionar al menos un rol activo.';
        }
    }

    uadm_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $validationErrors,
        'csrf_token_nuevo' => uadm_new_csrf_token(),
    ]);
}
