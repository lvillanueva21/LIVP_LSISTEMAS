<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_access.php';

function rls_csrf_form_key()
{
    return 'roles_form';
}

function rls_new_csrf_token()
{
    return lsis_csrf_get_token(rls_csrf_form_key());
}

function rls_json_response($httpStatus, array $payload)
{
    http_response_code((int) $httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rls_permission_codes()
{
    return [
        'view' => 'roles.view',
        'create' => 'roles.create',
        'edit' => 'roles.edit',
        'toggle_state' => 'roles.toggle_state',
    ];
}

function rls_guard_request($expectedMethod, $csrfToken, $permissionCode)
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

    if (!lsis_csrf_validate_token(rls_csrf_form_key(), (string) $csrfToken)) {
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

function rls_reject_unexpected_fields(array $input, array $allowedKeys)
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

function rls_required_tables_exist()
{
    $required = ['lsis_roles', 'lsis_usuarios', 'lsis_usuario_roles'];
    foreach ($required as $tableName) {
        if (!lsis_table_exists_cached($tableName)) {
            return false;
        }
    }
    return true;
}

function rls_roles_has_descripcion_column()
{
    if (!lsis_table_exists_cached('lsis_roles')) {
        return false;
    }

    try {
        $sql = "
            SELECT COUNT(*) AS c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'lsis_roles'
              AND COLUMN_NAME = 'descripcion'
        ";
        $row = db()->query($sql)->fetch();
        return ((int) ($row['c'] ?? 0) > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function rls_roles_has_access_hardening_columns()
{
    if (!lsis_table_exists_cached('lsis_roles')) {
        return false;
    }

    try {
        $sql = "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'lsis_roles'
              AND COLUMN_NAME IN ('es_sistema', 'es_protegido')
        ";
        $rows = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        $rows = is_array($rows) ? $rows : [];
        $map = array_fill_keys($rows, true);
        return isset($map['es_sistema']) && isset($map['es_protegido']);
    } catch (Throwable $e) {
        return false;
    }
}

function rls_schema_ready()
{
    return rls_required_tables_exist()
        && rls_roles_has_descripcion_column()
        && rls_roles_has_access_hardening_columns();
}

function rls_is_superadmin_name($name)
{
    return strtolower(trim((string) $name)) === 'superadmin';
}

function rls_role_is_protected_row(array $roleRow)
{
    $isProtected = ((int) ($roleRow['es_protegido'] ?? 0) === 1);
    $isSystem = ((int) ($roleRow['es_sistema'] ?? 0) === 1);
    return $isProtected && $isSystem;
}

function rls_validate_role_name($name)
{
    $name = trim((string) $name);
    if ($name === '') {
        return [false, '', 'Nombre de rol requerido.'];
    }

    if (strlen($name) > 80) {
        return [false, '', 'Nombre de rol excede longitud permitida.'];
    }

    return [true, $name, ''];
}

function rls_validate_role_description($description)
{
    $description = trim((string) $description);
    if ($description === '') {
        return [true, null, ''];
    }

    if (strlen($description) > 255) {
        return [false, null, 'Descripcion excede longitud permitida.'];
    }

    return [true, $description, ''];
}

function rls_fetch_role_by_id($roleId, $forUpdate = false)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return null;
    }

    $sql = "
        SELECT id, nombre, descripcion, estado, es_sistema, es_protegido, creado_en, actualizado_en
        FROM lsis_roles
        WHERE id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute([$roleId]);
    $row = $st->fetch();
    return $row ?: null;
}

function rls_find_role_by_name_ci($name, $excludeRoleId = 0, $forUpdate = false)
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    $excludeRoleId = (int) $excludeRoleId;
    $sql = "
        SELECT id, nombre, descripcion, estado, es_sistema, es_protegido
        FROM lsis_roles
        WHERE LOWER(nombre) = LOWER(?)
    ";
    $params = [$name];
    if ($excludeRoleId > 0) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeRoleId;
    }
    $sql .= ' LIMIT 1';
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

function rls_count_active_superadmin_users($forUpdate = false)
{
    return lsis_auth_count_active_protected_admin_users((bool) $forUpdate);
}

function rls_list_roles($page, $perPage, $search, $estado)
{
    $page = (int) $page;
    if ($page < 1) {
        $page = 1;
    }

    $perPage = (int) $perPage;
    if ($perPage < 5) {
        $perPage = 10;
    }
    if ($perPage > 50) {
        $perPage = 50;
    }

    $search = trim((string) $search);
    $estado = trim((string) $estado);
    $estadoFilter = null;
    if ($estado === '0' || $estado === '1') {
        $estadoFilter = (int) $estado;
    }

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(r.nombre LIKE ? OR r.descripcion LIKE ?)';
        $needle = '%' . $search . '%';
        $params[] = $needle;
        $params[] = $needle;
    }

    if ($estadoFilter !== null) {
        $where[] = 'r.estado = ?';
        $params[] = $estadoFilter;
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "
        SELECT COUNT(*) AS c
        FROM lsis_roles r
        WHERE {$whereSql}
    ";
    $stCount = db()->prepare($sqlCount);
    $stCount->execute($params);
    $rowCount = $stCount->fetch();
    $total = (int) ($rowCount['c'] ?? 0);
    $totalPages = ($total > 0) ? (int) ceil($total / $perPage) : 1;

    if ($page > $totalPages) {
        $page = $totalPages;
    }
    if ($page < 1) {
        $page = 1;
    }

    $offset = ($page - 1) * $perPage;

    $sqlData = "
        SELECT
            r.id,
            r.nombre,
            r.descripcion,
            r.estado,
            r.es_sistema,
            r.es_protegido,
            r.creado_en,
            r.actualizado_en,
            COUNT(DISTINCT CASE WHEN ur.estado = 1 AND u.estado = 1 THEN u.id END) AS usuarios_activos_asignados
        FROM lsis_roles r
        LEFT JOIN lsis_usuario_roles ur ON ur.id_rol = r.id
        LEFT JOIN lsis_usuarios u ON u.id = ur.id_usuario
        WHERE {$whereSql}
        GROUP BY r.id, r.nombre, r.descripcion, r.estado, r.es_sistema, r.es_protegido, r.creado_en, r.actualizado_en
        ORDER BY LOWER(r.nombre) ASC, r.id ASC
        LIMIT ? OFFSET ?
    ";

    $stData = db()->prepare($sqlData);
    $idx = 1;
    foreach ($params as $param) {
        $stData->bindValue($idx, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $idx++;
    }
    $stData->bindValue($idx, (int) $perPage, PDO::PARAM_INT);
    $idx++;
    $stData->bindValue($idx, (int) $offset, PDO::PARAM_INT);
    $stData->execute();

    $rows = $stData->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $nombre = (string) ($row['nombre'] ?? '');
        $isProtected = rls_role_is_protected_row($row);
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => $nombre,
            'descripcion' => (string) ($row['descripcion'] ?? ''),
            'estado' => ((int) ($row['estado'] ?? 0) === 1) ? 1 : 0,
            'es_superadmin' => $isProtected ? 1 : 0,
            'es_sistema' => ((int) ($row['es_sistema'] ?? 0) === 1) ? 1 : 0,
            'es_protegido' => $isProtected ? 1 : 0,
            'usuarios_activos_asignados' => (int) ($row['usuarios_activos_asignados'] ?? 0),
            'creado_en' => (string) ($row['creado_en'] ?? ''),
            'actualizado_en' => (string) ($row['actualizado_en'] ?? ''),
        ];
    }

    return [
        'items' => $items,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'search' => $search,
            'estado' => $estadoFilter,
        ],
    ];
}

function rls_fetch_active_user_ids_by_role($roleId, $forUpdate = false)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return [];
    }

    $sql = "
        SELECT DISTINCT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        WHERE ur.id_rol = ?
          AND ur.estado = 1
          AND u.estado = 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute([$roleId]);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $ids = [];
    foreach ((array) $rows as $value) {
        $uid = (int) $value;
        if ($uid > 0) {
            $ids[] = $uid;
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
}

function rls_fetch_users_without_alternative_role_when_disabling($roleId)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return [];
    }

    $sql = "
        SELECT DISTINCT u.id, u.usuario
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur_target
            ON ur_target.id_usuario = u.id
           AND ur_target.id_rol = ?
           AND ur_target.estado = 1
        INNER JOIN lsis_roles r_target
            ON r_target.id = ur_target.id_rol
           AND r_target.estado = 1
        WHERE u.estado = 1
          AND NOT EXISTS (
                SELECT 1
                FROM lsis_usuario_roles ur2
                INNER JOIN lsis_roles r2 ON r2.id = ur2.id_rol
                WHERE ur2.id_usuario = u.id
                  AND ur2.estado = 1
                  AND r2.estado = 1
                  AND ur2.id_rol <> ?
          )
        FOR UPDATE
    ";

    $st = db()->prepare($sql);
    $st->execute([$roleId, $roleId]);
    $rows = $st->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $out[] = [
            'id' => $uid,
            'usuario' => (string) ($row['usuario'] ?? ''),
        ];
    }

    return $out;
}
