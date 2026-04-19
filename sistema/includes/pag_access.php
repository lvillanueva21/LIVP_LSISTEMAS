<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_schema.php';

function pag_get_user_role_ids_from_session()
{
    $ids = [];
    $roles = $_SESSION['user']['roles'] ?? [];
    if (!is_array($roles)) {
        return [];
    }

    foreach ($roles as $role) {
        $id = (int) ($role['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
}

function pag_get_allowed_permission_ids_for_roles(array $roleIds)
{
    if (!$roleIds || !pag_schema_tables_ready()) {
        return [];
    }

    $roleIds = array_values(array_filter(array_map('intval', $roleIds), function ($v) {
        return $v > 0;
    }));
    if (!$roleIds) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($roleIds), '?'));
    $sql = "
        SELECT DISTINCT rp.id_permiso
        FROM pag_roles_permisos rp
        INNER JOIN pag_permisos p ON p.id_permiso = rp.id_permiso
        WHERE rp.estado = 1
          AND p.estado = 1
          AND rp.id_rol IN ($marks)
    ";
    $st = db()->prepare($sql);
    $st->execute($roleIds);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $allowed = [];
    foreach ($rows as $idPermiso) {
        $idPermiso = (int) $idPermiso;
        if ($idPermiso > 0) {
            $allowed[$idPermiso] = true;
        }
    }

    return $allowed;
}

function pag_user_has_permission_code($permissionCode, array $roleIds = null)
{
    $permissionCode = trim((string) $permissionCode);
    if (!pag_schema_is_valid_permission_code($permissionCode) || !pag_schema_tables_ready()) {
        return false;
    }

    if ($roleIds === null) {
        $roleIds = pag_get_user_role_ids_from_session();
    }

    $roleIds = array_values(array_filter(array_map('intval', $roleIds), function ($v) {
        return $v > 0;
    }));
    if (!$roleIds) {
        return false;
    }

    $marks = implode(',', array_fill(0, count($roleIds), '?'));
    $sql = "
        SELECT COUNT(*) AS c
        FROM pag_roles_permisos rp
        INNER JOIN pag_permisos p ON p.id_permiso = rp.id_permiso
        WHERE rp.estado = 1
          AND p.estado = 1
          AND p.permiso_codigo = ?
          AND rp.id_rol IN ($marks)
    ";
    $params = array_merge([$permissionCode], $roleIds);
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();

    return !empty($row['c']);
}

function pag_get_page_row_by_slug($slug)
{
    $slug = trim((string) $slug);
    if (!pag_schema_is_valid_slug($slug) || !pag_schema_tables_ready()) {
        return null;
    }

    $sql = "
        SELECT
            p.id_pagina,
            p.titulo_menu,
            p.titulo_pagina,
            p.descripcion_pagina,
            p.slug_pagina,
            p.id_padre,
            p.es_contenedor,
            p.visible_menu,
            p.modulo_codigo,
            p.archivo_section,
            p.id_permiso_requerido,
            p.icono,
            p.orden_menu,
            p.es_fija,
            p.estado,
            p.creado_en,
            p.actualizado_en,
            pp.permiso_codigo,
            pp.estado AS permiso_estado
        FROM pag_paginas p
        LEFT JOIN pag_permisos pp ON pp.id_permiso = p.id_permiso_requerido
        WHERE p.slug_pagina = ?
        LIMIT 1
    ";
    $st = db()->prepare($sql);
    $st->execute([$slug]);
    $row = $st->fetch();

    return $row ?: null;
}

function pag_can_access_page_row(array $pageRow, array $roleIds = null, array $allowedPermissionIds = null)
{
    if ((int) ($pageRow['estado'] ?? 0) !== 1) {
        return false;
    }

    if ((int) ($pageRow['es_contenedor'] ?? 0) === 1) {
        return false;
    }

    $permissionId = (int) ($pageRow['id_permiso_requerido'] ?? 0);
    if ($permissionId <= 0) {
        return true;
    }

    if ((int) ($pageRow['permiso_estado'] ?? 0) !== 1) {
        return false;
    }

    if ($allowedPermissionIds === null) {
        if ($roleIds === null) {
            $roleIds = pag_get_user_role_ids_from_session();
        }
        $allowedPermissionIds = pag_get_allowed_permission_ids_for_roles($roleIds);
    }

    return !empty($allowedPermissionIds[$permissionId]);
}
