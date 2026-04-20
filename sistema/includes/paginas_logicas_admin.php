<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_access.php';
require_once __DIR__ . '/pag_loader.php';

function pgl_csrf_form_key()
{
    return 'paginas_logicas_form';
}

function pgl_new_csrf_token()
{
    return lsis_csrf_get_token(pgl_csrf_form_key());
}

function pgl_json_response($httpStatus, array $payload)
{
    http_response_code((int) $httpStatus);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pgl_permission_codes()
{
    return [
        'view' => 'paginas_logicas.view',
        'create' => 'paginas_logicas.create',
        'edit' => 'paginas_logicas.edit',
        'toggle_state' => 'paginas_logicas.toggle_state',
    ];
}

function pgl_guard_request($expectedMethod, $csrfToken, $permissionCode)
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

    if (!lsis_csrf_validate_token(pgl_csrf_form_key(), (string) $csrfToken)) {
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

function pgl_reject_unexpected_fields(array $input, array $allowedKeys)
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

function pgl_required_tables_exist()
{
    $required = [
        'pag_paginas',
        'pag_permisos',
        'pag_roles_permisos',
        'lsis_roles',
        'lsis_usuarios',
        'lsis_usuario_roles',
    ];

    foreach ($required as $tableName) {
        if (!lsis_table_exists_cached($tableName)) {
            return false;
        }
    }

    return true;
}

function pgl_schema_ready()
{
    return pgl_required_tables_exist() && pag_schema_tables_ready();
}

function pgl_parse_bool01($value, $default = 0)
{
    if ($value === null || $value === '') {
        return ((int) $default === 1) ? 1 : 0;
    }

    $value = trim((string) $value);
    return ($value === '1') ? 1 : 0;
}

function pgl_parse_int($value, $default = 0)
{
    if ($value === null || $value === '') {
        return (int) $default;
    }

    $value = trim((string) $value);
    if (preg_match('/^-?\d+$/', $value) !== 1) {
        return (int) $default;
    }

    return (int) $value;
}

function pgl_parse_positive_id_or_null($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return [true, null, ''];
    }

    if (preg_match('/^\d+$/', $value) !== 1) {
        return [false, null, 'Valor invalido.'];
    }

    $id = (int) $value;
    if ($id <= 0) {
        return [false, null, 'Valor invalido.'];
    }

    return [true, $id, ''];
}

function pgl_validate_menu_text($value, $label, $maxLen)
{
    $value = trim((string) $value);
    if ($value === '') {
        return [false, '', $label . ' requerido.'];
    }

    if (strlen($value) > (int) $maxLen) {
        return [false, '', $label . ' excede longitud permitida.'];
    }

    return [true, $value, ''];
}

function pgl_validate_optional_text($value, $maxLen)
{
    $value = trim((string) $value);
    if ($value === '') {
        return [true, null, ''];
    }

    if (strlen($value) > (int) $maxLen) {
        return [false, null, 'Excede longitud permitida.'];
    }

    return [true, $value, ''];
}

function pgl_validate_slug($slug)
{
    $slug = trim((string) $slug);
    if ($slug === '') {
        return [false, '', 'Slug requerido.'];
    }

    if (!pag_schema_is_valid_slug($slug)) {
        return [false, '', 'Slug invalido.'];
    }

    return [true, $slug, ''];
}

function pgl_normalized_permission_code_by_slug($slug)
{
    $slug = trim((string) $slug);
    $slug = str_replace('-', '_', $slug);
    return $slug . '.view';
}

function pgl_fetch_page_by_id($idPagina, $forUpdate = false)
{
    $idPagina = (int) $idPagina;
    if ($idPagina <= 0) {
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
        WHERE p.id_pagina = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute([$idPagina]);
    $row = $st->fetch();
    return $row ?: null;
}

function pgl_fetch_page_by_slug($slug, $forUpdate = false)
{
    $slug = trim((string) $slug);
    if ($slug === '' || !pag_schema_is_valid_slug($slug)) {
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
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute([$slug]);
    $row = $st->fetch();
    return $row ?: null;
}

function pgl_fetch_parent_candidate_by_id($idPadre, $forUpdate = false)
{
    $idPadre = (int) $idPadre;
    if ($idPadre <= 0) {
        return null;
    }

    $sql = "
        SELECT id_pagina, id_padre, es_contenedor, estado
        FROM pag_paginas
        WHERE id_pagina = ?
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute([$idPadre]);
    $row = $st->fetch();
    return $row ?: null;
}

function pgl_validate_parent_rules($idPadre, $currentPageId = 0, $forUpdate = false)
{
    $idPadre = $idPadre !== null ? (int) $idPadre : null;
    $currentPageId = (int) $currentPageId;

    if ($idPadre === null) {
        return [true, null, ''];
    }

    if ($currentPageId > 0 && $idPadre === $currentPageId) {
        return [false, null, 'No se permite self-parent.'];
    }

    $parent = pgl_fetch_parent_candidate_by_id($idPadre, $forUpdate);
    if (!$parent) {
        return [false, null, 'Padre no encontrado.'];
    }

    if ((int) ($parent['estado'] ?? 0) !== 1) {
        return [false, null, 'Padre inactivo.'];
    }

    if ((int) ($parent['es_contenedor'] ?? 0) !== 1) {
        return [false, null, 'Padre debe ser contenedor.'];
    }

    $parentParentId = isset($parent['id_padre']) ? (int) $parent['id_padre'] : 0;
    if ($parentParentId > 0) {
        return [false, null, 'No se permite nivel 3 de menu.'];
    }

    return [true, $idPadre, ''];
}

function pgl_count_children_by_page_id($pageId, $forUpdate = false)
{
    $pageId = (int) $pageId;
    if ($pageId <= 0) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*) AS c
        FROM pag_paginas
        WHERE id_padre = ?
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute([$pageId]);
    $row = $st->fetch();
    return (int) ($row['c'] ?? 0);
}

function pgl_validate_container_level_rules($esContenedor, $idPadre)
{
    $esContenedor = ((int) $esContenedor === 1) ? 1 : 0;
    $idPadre = $idPadre !== null ? (int) $idPadre : null;

    if ($esContenedor === 1 && $idPadre !== null) {
        return [false, 'Un contenedor solo puede existir en nivel 1.'];
    }

    return [true, ''];
}

function pgl_validate_update_children_rules($pageId, $esContenedor, $idPadre, $forUpdate = false)
{
    $pageId = (int) $pageId;
    $esContenedor = ((int) $esContenedor === 1) ? 1 : 0;
    $idPadre = $idPadre !== null ? (int) $idPadre : null;

    if ($pageId <= 0) {
        return [true, ''];
    }

    $childrenCount = pgl_count_children_by_page_id($pageId, $forUpdate);
    if ($childrenCount <= 0) {
        return [true, ''];
    }

    if ($esContenedor !== 1) {
        return [false, 'No puedes convertir en pagina real una pagina que tiene submenus hijos.'];
    }

    if ($idPadre !== null) {
        return [false, 'No puedes mover a nivel 2 una pagina que ya tiene submenus hijos.'];
    }

    return [true, ''];
}

function pgl_scan_modules_catalog()
{
    $catalog = [];

    $baseDir = realpath(__DIR__ . '/../modules');
    if ($baseDir === false || !is_dir($baseDir)) {
        return [];
    }

    $moduleDirs = @scandir($baseDir);
    if (!is_array($moduleDirs)) {
        return [];
    }

    foreach ($moduleDirs as $moduleDirName) {
        if ($moduleDirName === '.' || $moduleDirName === '..') {
            continue;
        }

        if (!pag_schema_is_valid_identifier($moduleDirName)) {
            continue;
        }

        $moduleDirPath = $baseDir . DIRECTORY_SEPARATOR . $moduleDirName;
        if (!is_dir($moduleDirPath)) {
            continue;
        }

        $files = @scandir($moduleDirPath);
        if (!is_array($files)) {
            continue;
        }

        $sections = [];
        foreach ($files as $fileName) {
            if ($fileName === '.' || $fileName === '..') {
                continue;
            }
            if (substr($fileName, -4) !== '.php') {
                continue;
            }

            $section = substr($fileName, 0, -4);
            if (!pag_schema_is_valid_identifier($section)) {
                continue;
            }

            $resolved = pag_loader_resolve_module_file($moduleDirName, $section);
            if ($resolved === '') {
                continue;
            }

            $sections[] = $section;
        }

        $sections = array_values(array_unique($sections));
        sort($sections);

        if (!$sections) {
            continue;
        }

        $catalog[] = [
            'modulo_codigo' => $moduleDirName,
            'sections' => $sections,
        ];
    }

    usort($catalog, function ($a, $b) {
        return strcmp((string) ($a['modulo_codigo'] ?? ''), (string) ($b['modulo_codigo'] ?? ''));
    });

    return $catalog;
}

function pgl_module_section_exists_in_catalog($moduloCodigo, $archivoSection, array $catalog)
{
    $moduloCodigo = trim((string) $moduloCodigo);
    $archivoSection = trim((string) $archivoSection);

    if (!pag_schema_is_valid_identifier($moduloCodigo) || !pag_schema_is_valid_identifier($archivoSection)) {
        return false;
    }

    foreach ($catalog as $row) {
        if ((string) ($row['modulo_codigo'] ?? '') !== $moduloCodigo) {
            continue;
        }
        $sections = is_array($row['sections'] ?? null) ? $row['sections'] : [];
        return in_array($archivoSection, $sections, true);
    }

    return false;
}

function pgl_fetch_active_permissions_catalog()
{
    $rows = db()->query("\n        SELECT id_permiso, permiso_codigo, nombre_permiso, descripcion\n        FROM pag_permisos\n        WHERE estado = 1\n        ORDER BY permiso_codigo ASC, id_permiso ASC\n    ")->fetchAll();

    $out = [];
    foreach ($rows as $row) {
        $pid = (int) ($row['id_permiso'] ?? 0);
        if ($pid <= 0) {
            continue;
        }

        $out[] = [
            'id_permiso' => $pid,
            'permiso_codigo' => (string) ($row['permiso_codigo'] ?? ''),
            'nombre_permiso' => (string) ($row['nombre_permiso'] ?? ''),
            'descripcion' => (string) ($row['descripcion'] ?? ''),
        ];
    }

    return $out;
}

function pgl_fetch_parent_containers_catalog($excludePageId = 0)
{
    $excludePageId = (int) $excludePageId;

    $sql = "
        SELECT id_pagina, titulo_menu, slug_pagina, estado
        FROM pag_paginas
        WHERE es_contenedor = 1
          AND id_padre IS NULL
        ORDER BY orden_menu ASC, id_pagina ASC
    ";

    $rows = db()->query($sql)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id_pagina'] ?? 0);
        if ($id <= 0 || ($excludePageId > 0 && $id === $excludePageId)) {
            continue;
        }

        $out[] = [
            'id_pagina' => $id,
            'titulo_menu' => (string) ($row['titulo_menu'] ?? ''),
            'slug_pagina' => (string) ($row['slug_pagina'] ?? ''),
            'estado' => ((int) ($row['estado'] ?? 0) === 1) ? 1 : 0,
        ];
    }

    return $out;
}

function pgl_fetch_pages_list($page, $perPage, $search, $estadoFiltro, $tipoFiltro)
{
    $page = (int) $page;
    if ($page < 1) {
        $page = 1;
    }

    $perPage = (int) $perPage;
    if ($perPage < 5) {
        $perPage = 10;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }

    $search = trim((string) $search);
    $estadoFiltro = trim((string) $estadoFiltro);
    $tipoFiltro = trim((string) $tipoFiltro);

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.titulo_menu LIKE ? OR p.titulo_pagina LIKE ? OR p.slug_pagina LIKE ?)';
        $needle = '%' . $search . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    if ($estadoFiltro === '0' || $estadoFiltro === '1') {
        $where[] = 'p.estado = ?';
        $params[] = (int) $estadoFiltro;
    }

    if ($tipoFiltro === 'fija') {
        $where[] = 'p.es_fija = 1';
    } elseif ($tipoFiltro === 'contenedor') {
        $where[] = 'p.es_fija = 0 AND p.es_contenedor = 1';
    } elseif ($tipoFiltro === 'real') {
        $where[] = 'p.es_fija = 0 AND p.es_contenedor = 0';
    }

    $whereSql = implode(' AND ', $where);

    $stCount = db()->prepare("SELECT COUNT(*) AS c FROM pag_paginas p WHERE {$whereSql}");
    $stCount->execute($params);
    $countRow = $stCount->fetch();

    $total = (int) ($countRow['c'] ?? 0);
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
            parent.titulo_menu AS parent_titulo_menu,
            pp.permiso_codigo,
            pp.estado AS permiso_estado
        FROM pag_paginas p
        LEFT JOIN pag_paginas parent ON parent.id_pagina = p.id_padre
        LEFT JOIN pag_permisos pp ON pp.id_permiso = p.id_permiso_requerido
        WHERE {$whereSql}
        ORDER BY
            CASE WHEN p.id_padre IS NULL THEN p.id_pagina ELSE p.id_padre END ASC,
            p.id_padre ASC,
            p.orden_menu ASC,
            p.id_pagina ASC
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
        $isFija = ((int) ($row['es_fija'] ?? 0) === 1);
        $isContenedor = ((int) ($row['es_contenedor'] ?? 0) === 1);
        $tipo = 'real';
        if ($isFija) {
            $tipo = 'fija';
        } elseif ($isContenedor) {
            $tipo = 'contenedor';
        }

        $items[] = [
            'id_pagina' => (int) ($row['id_pagina'] ?? 0),
            'titulo_menu' => (string) ($row['titulo_menu'] ?? ''),
            'titulo_pagina' => (string) ($row['titulo_pagina'] ?? ''),
            'descripcion_pagina' => (string) ($row['descripcion_pagina'] ?? ''),
            'slug_pagina' => (string) ($row['slug_pagina'] ?? ''),
            'id_padre' => isset($row['id_padre']) ? (int) $row['id_padre'] : null,
            'parent_titulo_menu' => (string) ($row['parent_titulo_menu'] ?? ''),
            'es_contenedor' => $isContenedor ? 1 : 0,
            'visible_menu' => ((int) ($row['visible_menu'] ?? 0) === 1) ? 1 : 0,
            'modulo_codigo' => (string) ($row['modulo_codigo'] ?? ''),
            'archivo_section' => (string) ($row['archivo_section'] ?? ''),
            'id_permiso_requerido' => isset($row['id_permiso_requerido']) ? (int) $row['id_permiso_requerido'] : null,
            'permiso_codigo' => (string) ($row['permiso_codigo'] ?? ''),
            'permiso_estado' => isset($row['permiso_estado']) ? (int) $row['permiso_estado'] : null,
            'icono' => (string) ($row['icono'] ?? ''),
            'orden_menu' => (int) ($row['orden_menu'] ?? 0),
            'es_fija' => $isFija ? 1 : 0,
            'estado' => ((int) ($row['estado'] ?? 0) === 1) ? 1 : 0,
            'tipo' => $tipo,
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
            'estado' => $estadoFiltro,
            'tipo' => $tipoFiltro,
        ],
    ];
}

function pgl_fetch_active_permission_ids_map_by_ids(array $permissionIds, $forUpdate = false)
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
          AND id_permiso IN ($marks)
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute($permissionIds);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $map = [];
    foreach ($rows as $value) {
        $pid = (int) $value;
        if ($pid > 0) {
            $map[$pid] = true;
        }
    }

    return $map;
}

function pgl_upsert_permission_for_slug($slug, $tituloPagina)
{
    $slug = trim((string) $slug);
    $tituloPagina = trim((string) $tituloPagina);
    if ($slug === '') {
        return [false, 0, 'slug_invalido'];
    }

    $permisoCodigo = pgl_normalized_permission_code_by_slug($slug);
    if (!pag_schema_is_valid_permission_code($permisoCodigo)) {
        return [false, 0, 'permiso_codigo_invalido'];
    }

    $nombre = $tituloPagina !== '' ? ('Ver ' . $tituloPagina) : ('Ver ' . str_replace('-', ' ', $slug));
    if (strlen($nombre) > 120) {
        $nombre = substr($nombre, 0, 120);
    }

    $descripcion = 'Permiso base de visualizacion para la pagina ' . $slug . '.';
    if (strlen($descripcion) > 255) {
        $descripcion = substr($descripcion, 0, 255);
    }

    $st = db()->prepare("\n        INSERT INTO pag_permisos (permiso_codigo, nombre_permiso, descripcion, estado, creado_en, actualizado_en)\n        VALUES (?, ?, ?, 1, NOW(), NOW())\n        ON DUPLICATE KEY UPDATE\n            nombre_permiso = VALUES(nombre_permiso),\n            descripcion = VALUES(descripcion),\n            estado = 1,\n            actualizado_en = NOW()\n    ");
    $st->execute([$permisoCodigo, $nombre, $descripcion]);

    $idPermiso = pag_schema_find_permission_id_by_code($permisoCodigo);
    if ($idPermiso <= 0) {
        return [false, 0, 'permiso_no_disponible'];
    }

    return [true, $idPermiso, 'ok'];
}

function pgl_assign_permission_to_protected_roles($idPermiso)
{
    $idPermiso = (int) $idPermiso;
    if ($idPermiso <= 0) {
        return 0;
    }

    $roleIds = lsis_auth_get_protected_system_role_ids(true, true);
    if (!$roleIds) {
        return 0;
    }

    $st = db()->prepare("\n        INSERT INTO pag_roles_permisos (id_rol, id_permiso, estado, creado_en, actualizado_en)\n        VALUES (?, ?, 1, NOW(), NOW())\n        ON DUPLICATE KEY UPDATE\n            estado = 1,\n            actualizado_en = NOW()\n    ");

    $count = 0;
    foreach ($roleIds as $idRol) {
        $idRol = (int) $idRol;
        if ($idRol <= 0) {
            continue;
        }
        $st->execute([$idRol, $idPermiso]);
        $count++;
    }

    return $count;
}

function pgl_fetch_active_user_ids_by_permission_ids(array $permissionIds, $forUpdate = false)
{
    $permissionIds = array_values(array_filter(array_map('intval', $permissionIds), function ($value) {
        return $value > 0;
    }));

    if (!$permissionIds) {
        return [];
    }

    $marks = implode(',', array_fill(0, count($permissionIds), '?'));
    $sql = "
        SELECT DISTINCT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        INNER JOIN pag_roles_permisos rp ON rp.id_rol = ur.id_rol
        WHERE u.estado = 1
          AND ur.estado = 1
          AND rp.estado = 1
          AND rp.id_permiso IN ($marks)
    ";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $st = db()->prepare($sql);
    $st->execute($permissionIds);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $ids = [];
    foreach ($rows as $value) {
        $uid = (int) $value;
        if ($uid > 0) {
            $ids[] = $uid;
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);
    return $ids;
}

function pgl_close_sessions_by_permission_ids(array $permissionIds)
{
    $userIds = pgl_fetch_active_user_ids_by_permission_ids($permissionIds, true);
    if (!$userIds) {
        return 0;
    }

    return lsis_close_active_sessions_by_user_ids($userIds, 'actualizacion_acceso');
}

function pgl_validate_module_section_by_state($esContenedor, $estado, $moduloCodigo, $archivoSection, array $catalog)
{
    $esContenedor = ((int) $esContenedor === 1) ? 1 : 0;
    $estado = ((int) $estado === 1) ? 1 : 0;
    $moduloCodigo = trim((string) $moduloCodigo);
    $archivoSection = trim((string) $archivoSection);

    if ($esContenedor === 1) {
        return [true, null, null, ''];
    }

    if ($estado === 1) {
        if ($moduloCodigo === '' || $archivoSection === '') {
            return [false, null, null, 'Modulo y section son obligatorios para activar una pagina real.'];
        }

        if (!pgl_module_section_exists_in_catalog($moduloCodigo, $archivoSection, $catalog)) {
            return [false, null, null, 'El modulo/section seleccionado no existe en sistema/modules/.'];
        }

        return [true, $moduloCodigo, $archivoSection, ''];
    }

    if ($moduloCodigo === '' && $archivoSection === '') {
        return [true, null, null, ''];
    }

    if ($moduloCodigo === '' || $archivoSection === '') {
        return [false, null, null, 'Para borrador, si informas modulo debes informar section valido.'];
    }

    if (!pgl_module_section_exists_in_catalog($moduloCodigo, $archivoSection, $catalog)) {
        return [false, null, null, 'El modulo/section seleccionado no existe en sistema/modules/.'];
    }

    return [true, $moduloCodigo, $archivoSection, ''];
}
