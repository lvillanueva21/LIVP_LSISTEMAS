<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_access.php';

function uadm_csrf_form_key()
{
    return 'usuarios_form';
}

function uadm_new_csrf_token()
{
    return lsis_csrf_get_token(uadm_csrf_form_key());
}

function uadm_json_response($httpStatus, array $payload)
{
    http_response_code((int) $httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function uadm_guard_request($expectedMethod, $csrfToken, $permissionCode)
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

    if (!isAuthenticated()) {
        return [
            'ok' => false,
            'http_status' => 401,
            'code' => 'sesion_requerida',
            'message' => 'Sesion no valida.',
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

    if (!lsis_csrf_validate_token(uadm_csrf_form_key(), (string) $csrfToken)) {
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

function uadm_required_tables_exist()
{
    $required = ['lsis_usuarios', 'lsis_roles', 'lsis_usuario_roles'];
    foreach ($required as $tableName) {
        if (!lsis_table_exists_cached($tableName)) {
            return false;
        }
    }
    return true;
}

function uadm_user_permission_codes()
{
    return [
        'view' => 'usuarios.view',
        'create' => 'usuarios.create',
        'edit' => 'usuarios.edit',
        'toggle_state' => 'usuarios.toggle_state',
        'reset_password' => 'usuarios.reset_password',
    ];
}

function uadm_reject_unexpected_fields(array $input, array $allowedKeys)
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

function uadm_validate_usuario_identifier($usuario)
{
    $usuario = trim((string) $usuario);
    if ($usuario === '') {
        return [false, '', 'Usuario requerido.'];
    }
    if (preg_match('/^\d{8,11}$/', $usuario) !== 1) {
        return [false, '', 'Usuario invalido.'];
    }
    return [true, $usuario, ''];
}

function uadm_validate_person_name($value, $label)
{
    $value = trim((string) $value);
    if ($value === '') {
        return [false, '', $label . ' requerido.'];
    }
    if (strlen($value) > 100) {
        return [false, '', $label . ' excede longitud permitida.'];
    }
    return [true, $value, ''];
}

function uadm_validate_password_pair($password, $confirmPassword)
{
    $password = (string) $password;
    $confirmPassword = (string) $confirmPassword;

    if ($password === '' || $confirmPassword === '') {
        return [false, '', 'Contrasena y confirmacion son requeridas.'];
    }
    if ($password !== $confirmPassword) {
        return [false, '', 'La confirmacion de contrasena no coincide.'];
    }

    $len = strlen($password);
    if ($len < 8 || $len > 72) {
        return [false, '', 'La contrasena debe tener entre 8 y 72 caracteres.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    if (!is_string($hash) || $hash === '') {
        return [false, '', 'No se pudo procesar la contrasena.'];
    }

    return [true, $hash, ''];
}

function uadm_parse_role_ids($rawRoles)
{
    if (!is_array($rawRoles)) {
        return [false, [], 'Roles invalidos.'];
    }

    $roleIds = [];
    $seen = [];
    foreach ($rawRoles as $rawRoleId) {
        $rawRoleId = trim((string) $rawRoleId);
        if ($rawRoleId === '' || preg_match('/^\d+$/', $rawRoleId) !== 1) {
            return [false, [], 'Roles invalidos.'];
        }
        $rid = (int) $rawRoleId;
        if ($rid <= 0) {
            return [false, [], 'Roles invalidos.'];
        }
        if (isset($seen[$rid])) {
            return [false, [], 'No se permiten roles duplicados.'];
        }
        $seen[$rid] = true;
        $roleIds[] = $rid;
    }
    if (!$roleIds) {
        return [false, [], 'Debes seleccionar al menos un rol activo.'];
    }

    return [true, $roleIds, ''];
}

function uadm_fetch_active_roles_catalog()
{
    $sql = "
        SELECT id, nombre
        FROM lsis_roles
        WHERE estado = 1
        ORDER BY nombre ASC, id ASC
    ";
    $rows = db()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $rid = (int) ($row['id'] ?? 0);
        if ($rid <= 0) {
            continue;
        }
        $out[] = [
            'id' => $rid,
            'nombre' => (string) ($row['nombre'] ?? ''),
        ];
    }
    return $out;
}

function uadm_fetch_active_roles_by_ids(array $roleIds, $forUpdate = false)
{
    $roleIds = array_values(array_filter(array_map('intval', $roleIds), function ($v) {
        return $v > 0;
    }));
    if (!$roleIds) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($roleIds), '?'));
    $sql = "
        SELECT id, nombre
        FROM lsis_roles
        WHERE estado = 1
          AND id IN ($marks)
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute($roleIds);
    $rows = $st->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $rid = (int) ($row['id'] ?? 0);
        if ($rid > 0) {
            $out[$rid] = (string) ($row['nombre'] ?? '');
        }
    }
    return $out;
}

function uadm_fetch_user_by_id($userId, $forUpdate = false)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return null;
    }

    $sql = "
        SELECT id, usuario, nombres, apellidos, estado
        FROM lsis_usuarios
        WHERE id = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute([$userId]);
    $row = $st->fetch();

    return $row ?: null;
}

function uadm_user_has_any_active_role($userId, $forUpdate = false)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $sql = "
        SELECT ur.id
        FROM lsis_usuario_roles ur
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE ur.id_usuario = ?
          AND ur.estado = 1
          AND r.estado = 1
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute([$userId]);
    $row = $st->fetch();

    return !empty($row['id']);
}

function uadm_superadmin_role_id($forUpdate = false)
{
    $sql = "
        SELECT id
        FROM lsis_roles
        WHERE estado = 1
          AND LOWER(nombre) = 'superadmin'
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $row = db()->query($sql)->fetch();
    return $row ? (int) $row['id'] : 0;
}

function uadm_user_is_active_superadmin($userId, $forUpdate = false)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $sql = "
        SELECT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE u.id = ?
          AND u.estado = 1
          AND ur.estado = 1
          AND r.estado = 1
          AND LOWER(r.nombre) = 'superadmin'
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute([$userId]);
    $row = $st->fetch();
    return !empty($row['id']);
}

function uadm_count_active_superadmins($forUpdate = false)
{
    $sql = "
        SELECT DISTINCT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE u.estado = 1
          AND ur.estado = 1
          AND r.estado = 1
          AND LOWER(r.nombre) = 'superadmin'
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $rows = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    return is_array($rows) ? count($rows) : 0;
}

function uadm_sync_user_roles($userId, array $roleIds)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    $roleIds = array_values(array_filter(array_map('intval', $roleIds), function ($v) {
        return $v > 0;
    }));
    if (!$roleIds) {
        return false;
    }

    $stDisable = db()->prepare("
        UPDATE lsis_usuario_roles
        SET estado = 0,
            actualizado_en = NOW()
        WHERE id_usuario = ?
    ");
    $stDisable->execute([$userId]);

    $stUpsert = db()->prepare("
        INSERT INTO lsis_usuario_roles (id_usuario, id_rol, estado, creado_en, actualizado_en)
        VALUES (?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            estado = 1,
            actualizado_en = NOW()
    ");

    foreach ($roleIds as $roleId) {
        $stUpsert->execute([$userId, $roleId]);
    }

    return true;
}

function uadm_list_users($page, $perPage, $search, $estado)
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

    $where = ["1=1"];
    $params = [];
    if ($search !== '') {
        $where[] = "(u.usuario LIKE ? OR u.nombres LIKE ? OR u.apellidos LIKE ?)";
        $needle = '%' . $search . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }
    if ($estadoFilter !== null) {
        $where[] = "u.estado = ?";
        $params[] = $estadoFilter;
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "
        SELECT COUNT(*) AS c
        FROM lsis_usuarios u
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
            u.id,
            u.usuario,
            u.nombres,
            u.apellidos,
            u.estado,
            u.ultimo_login_at,
            u.ultimo_login_ip,
            GROUP_CONCAT(DISTINCT CASE WHEN ur.estado = 1 AND r.estado = 1 THEN r.nombre END ORDER BY r.nombre ASC SEPARATOR ', ') AS roles_activos,
            GROUP_CONCAT(DISTINCT CASE WHEN ur.estado = 1 AND r.estado = 1 THEN ur.id_rol END ORDER BY ur.id_rol ASC SEPARATOR ',') AS roles_activos_ids,
            MAX(CASE WHEN ur.estado = 1 AND r.estado = 1 AND LOWER(r.nombre) = 'superadmin' THEN 1 ELSE 0 END) AS es_superadmin_activo
        FROM lsis_usuarios u
        LEFT JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        LEFT JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE {$whereSql}
        GROUP BY u.id, u.usuario, u.nombres, u.apellidos, u.estado, u.ultimo_login_at, u.ultimo_login_ip
        ORDER BY u.id DESC
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
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'usuario' => (string) ($row['usuario'] ?? ''),
            'nombres' => (string) ($row['nombres'] ?? ''),
            'apellidos' => (string) ($row['apellidos'] ?? ''),
            'estado' => ((int) ($row['estado'] ?? 0) === 1) ? 1 : 0,
            'roles_activos' => (string) ($row['roles_activos'] ?? ''),
            'roles_activos_ids' => (string) ($row['roles_activos_ids'] ?? ''),
            'es_superadmin_activo' => ((int) ($row['es_superadmin_activo'] ?? 0) === 1) ? 1 : 0,
            'ultimo_login_at' => (string) ($row['ultimo_login_at'] ?? ''),
            'ultimo_login_ip' => (string) ($row['ultimo_login_ip'] ?? ''),
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

function uadm_get_user_detail_for_edit($userId)
{
    $user = uadm_fetch_user_by_id($userId, false);
    if (!$user) {
        return null;
    }

    $stRoles = db()->prepare("
        SELECT ur.id_rol
        FROM lsis_usuario_roles ur
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE ur.id_usuario = ?
          AND ur.estado = 1
          AND r.estado = 1
        ORDER BY ur.id_rol ASC
    ");
    $stRoles->execute([(int) $userId]);
    $roleIds = $stRoles->fetchAll(PDO::FETCH_COLUMN);
    $roleIds = array_map('intval', is_array($roleIds) ? $roleIds : []);

    return [
        'id' => (int) ($user['id'] ?? 0),
        'usuario' => (string) ($user['usuario'] ?? ''),
        'nombres' => (string) ($user['nombres'] ?? ''),
        'apellidos' => (string) ($user['apellidos'] ?? ''),
        'estado' => ((int) ($user['estado'] ?? 0) === 1) ? 1 : 0,
        'roles' => array_values(array_unique(array_filter($roleIds, function ($v) {
            return $v > 0;
        }))),
    ];
}
