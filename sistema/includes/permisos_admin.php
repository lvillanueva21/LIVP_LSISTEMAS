<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_access.php';
require_once __DIR__ . '/roles_admin.php';

function prm_csrf_form_key()
{
    return 'permisos_form';
}

function prm_new_csrf_token()
{
    return lsis_csrf_get_token(prm_csrf_form_key());
}

function prm_json_response($httpStatus, array $payload)
{
    http_response_code((int) $httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function prm_permission_codes()
{
    return [
        'view' => 'permisos.view',
        'assign' => 'permisos.assign',
    ];
}

function prm_guard_request($expectedMethod, $csrfToken, $permissionCode)
{
    $expectedMethod = strtoupper(trim((string) $expectedMethod));
    $currentMethod = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? '')));
    $permissionCode = trim((string) $permissionCode);

    if ($currentMethod !== $expectedMethod) {
        return [
            'ok' => false,
            'http_status' => 405,
            'code' => 'metodo_no_permitido',
            'message' => 'Metodo no permitido.',
        ];
    }

    $sessionGuard = lsis_auth_guard_active_session([
        'touch_activity' => true,
        'enforce_timeout' => true,
        'logout_on_fail' => true,
    ]);
    if (empty($sessionGuard['ok'])) {
        return [
            'ok' => false,
            'http_status' => (int) ($sessionGuard['http_status'] ?? 401),
            'code' => (string) ($sessionGuard['code'] ?? 'sesion_requerida'),
            'message' => (string) ($sessionGuard['message'] ?? 'Sesion no valida.'),
        ];
    }

    if ($permissionCode !== '' && !pag_user_has_permission_code($permissionCode)) {
        return [
            'ok' => false,
            'http_status' => 403,
            'code' => 'permiso_denegado',
            'message' => 'No tienes permiso para esta accion.',
        ];
    }

    if (!lsis_csrf_validate_token(prm_csrf_form_key(), (string) $csrfToken)) {
        return [
            'ok' => false,
            'http_status' => 419,
            'code' => 'csrf_invalido',
            'message' => 'Token CSRF invalido.',
        ];
    }

    return [
        'ok' => true,
        'http_status' => 200,
        'code' => 'ok',
        'message' => 'ok',
    ];
}

function prm_reject_unexpected_fields(array $input, array $allowedKeys)
{
    $allowed = array_fill_keys($allowedKeys, true);
    $unexpected = [];

    foreach ($input as $key => $value) {
        if (!isset($allowed[$key])) {
            $unexpected[] = (string) $key;
        }
    }

    return $unexpected;
}

function prm_required_tables_exist()
{
    $required = ['lsis_roles', 'pag_permisos', 'pag_roles_permisos'];
    foreach ($required as $tableName) {
        if (!lsis_table_exists_cached($tableName)) {
            return false;
        }
    }
    return rls_roles_has_descripcion_column();
}

function prm_parse_role_id($rawRoleId)
{
    $rawRoleId = trim((string) $rawRoleId);
    if ($rawRoleId === '' || preg_match('/^\d+$/', $rawRoleId) !== 1) {
        return [false, 0, 'Rol invalido.'];
    }

    $roleId = (int) $rawRoleId;
    if ($roleId <= 0) {
        return [false, 0, 'Rol invalido.'];
    }

    return [true, $roleId, ''];
}

function prm_parse_permission_ids($rawPermissionIds)
{
    if (!is_array($rawPermissionIds)) {
        return [false, [], 'Permisos invalidos.'];
    }

    $ids = [];
    $seen = [];

    foreach ($rawPermissionIds as $rawId) {
        $rawId = trim((string) $rawId);
        if ($rawId === '' || preg_match('/^\d+$/', $rawId) !== 1) {
            return [false, [], 'Permisos invalidos.'];
        }

        $id = (int) $rawId;
        if ($id <= 0) {
            return [false, [], 'Permisos invalidos.'];
        }

        if (isset($seen[$id])) {
            return [false, [], 'No se permiten permisos duplicados.'];
        }

        $seen[$id] = true;
        $ids[] = $id;
    }

    return [true, $ids, ''];
}

function prm_fetch_roles_catalog()
{
    $sql = "
        SELECT id, nombre, descripcion, estado, es_sistema, es_protegido
        FROM lsis_roles
        ORDER BY LOWER(nombre) ASC, id ASC
    ";

    $rows = db()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $isProtected = rls_role_is_protected_row($row);
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'descripcion' => (string) ($row['descripcion'] ?? ''),
            'estado' => ((int) ($row['estado'] ?? 0) === 1) ? 1 : 0,
            'es_superadmin' => $isProtected ? 1 : 0,
            'es_sistema' => ((int) ($row['es_sistema'] ?? 0) === 1) ? 1 : 0,
            'es_protegido' => $isProtected ? 1 : 0,
        ];
    }

    return $out;
}

function prm_fetch_active_permissions_catalog()
{
    $sql = "
        SELECT id_permiso, permiso_codigo, nombre_permiso, descripcion
        FROM pag_permisos
        WHERE estado = 1
        ORDER BY permiso_codigo ASC, id_permiso ASC
    ";

    $rows = db()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id_permiso' => (int) ($row['id_permiso'] ?? 0),
            'permiso_codigo' => (string) ($row['permiso_codigo'] ?? ''),
            'nombre_permiso' => (string) ($row['nombre_permiso'] ?? ''),
            'descripcion' => (string) ($row['descripcion'] ?? ''),
        ];
    }

    return $out;
}

function prm_fetch_role_by_id($roleId, $forUpdate = false)
{
    return rls_fetch_role_by_id($roleId, $forUpdate);
}

function prm_fetch_assigned_active_permission_ids_by_role($roleId, $forUpdate = false)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return [];
    }

    $sql = "
        SELECT rp.id_permiso
        FROM pag_roles_permisos rp
        INNER JOIN pag_permisos p ON p.id_permiso = rp.id_permiso
        WHERE rp.id_rol = ?
          AND rp.estado = 1
          AND p.estado = 1
        ORDER BY rp.id_permiso ASC
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }
    $st = db()->prepare($sql);
    $st->execute([$roleId]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $ids = [];
    foreach ($rows as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function prm_fetch_active_permission_ids_map_by_ids(array $permissionIds, $forUpdate = false)
{
    $permissionIds = array_values(array_filter(array_map('intval', $permissionIds), function ($value) {
        return $value > 0;
    }));

    if (!$permissionIds) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($permissionIds), '?'));
    $sql = "
        SELECT id_permiso
        FROM pag_permisos
        WHERE estado = 1
          AND id_permiso IN ({$marks})
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute($permissionIds);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $map = [];
    foreach ($rows as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $map[$id] = true;
        }
    }

    return $map;
}

function prm_save_role_permissions($roleId, array $permissionIds)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return false;
    }

    $permissionIds = array_values(array_filter(array_map('intval', $permissionIds), function ($value) {
        return $value > 0;
    }));

    $stDisable = db()->prepare("\n        UPDATE pag_roles_permisos\n        SET estado = 0,\n            actualizado_en = NOW()\n        WHERE id_rol = ?\n    ");
    $stDisable->execute([$roleId]);

    if (!$permissionIds) {
        return true;
    }

    $stUpsert = db()->prepare("\n        INSERT INTO pag_roles_permisos (id_rol, id_permiso, estado, creado_en, actualizado_en)\n        VALUES (?, ?, 1, NOW(), NOW())\n        ON DUPLICATE KEY UPDATE\n            estado = 1,\n            actualizado_en = NOW()\n    ");

    foreach ($permissionIds as $permissionId) {
        $stUpsert->execute([$roleId, $permissionId]);
    }

    return true;
}
