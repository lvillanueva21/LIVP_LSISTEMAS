<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';

function pag_schema_table_exists($tableName)
{
    return lsis_table_exists_cached($tableName);
}

function pag_schema_tables_ready()
{
    return pag_schema_table_exists('pag_paginas')
        && pag_schema_table_exists('pag_permisos')
        && pag_schema_table_exists('pag_roles_permisos');
}

function pag_schema_is_valid_slug($slug)
{
    $slug = trim((string) $slug);
    if ($slug === '') {
        return false;
    }

    return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

function pag_schema_is_valid_identifier($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    return preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $value) === 1;
}

function pag_schema_is_valid_permission_code($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return false;
    }

    return preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*\.[a-z0-9]+(?:_[a-z0-9]+)*$/', $value) === 1;
}

function pag_schema_ensure_tables()
{
    $result = [
        'ok' => false,
        'code' => 'error',
        'steps' => [],
        'errors' => [],
    ];

    $statements = [
        'create_pag_permisos' => "
            CREATE TABLE IF NOT EXISTS pag_permisos (
                id_permiso INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                permiso_codigo VARCHAR(120) NOT NULL,
                nombre_permiso VARCHAR(120) NOT NULL,
                descripcion VARCHAR(255) DEFAULT NULL,
                estado TINYINT(1) NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_permiso),
                UNIQUE KEY uq_pag_permisos_codigo (permiso_codigo),
                KEY idx_pag_permisos_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'create_pag_paginas' => "
            CREATE TABLE IF NOT EXISTS pag_paginas (
                id_pagina INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                titulo_menu VARCHAR(120) NOT NULL,
                titulo_pagina VARCHAR(150) NOT NULL,
                descripcion_pagina VARCHAR(255) DEFAULT NULL,
                slug_pagina VARCHAR(150) NOT NULL,
                id_padre INT(10) UNSIGNED DEFAULT NULL,
                es_contenedor TINYINT(1) NOT NULL DEFAULT 0,
                visible_menu TINYINT(1) NOT NULL DEFAULT 1,
                modulo_codigo VARCHAR(80) DEFAULT NULL,
                archivo_section VARCHAR(80) DEFAULT NULL,
                id_permiso_requerido INT(10) UNSIGNED DEFAULT NULL,
                icono VARCHAR(120) DEFAULT NULL,
                orden_menu INT(11) NOT NULL DEFAULT 0,
                es_fija TINYINT(1) NOT NULL DEFAULT 0,
                estado TINYINT(1) NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_pagina),
                UNIQUE KEY uq_pag_paginas_slug (slug_pagina),
                KEY idx_pag_paginas_padre (id_padre),
                KEY idx_pag_paginas_permiso (id_permiso_requerido),
                KEY idx_pag_paginas_menu (visible_menu, estado, id_padre, orden_menu),
                CONSTRAINT fk_pag_paginas_padre FOREIGN KEY (id_padre) REFERENCES pag_paginas (id_pagina) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_pag_paginas_permiso FOREIGN KEY (id_permiso_requerido) REFERENCES pag_permisos (id_permiso) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        'create_pag_roles_permisos' => "
            CREATE TABLE IF NOT EXISTS pag_roles_permisos (
                id_rol INT(10) UNSIGNED NOT NULL,
                id_permiso INT(10) UNSIGNED NOT NULL,
                estado TINYINT(1) NOT NULL DEFAULT 1,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_rol, id_permiso),
                KEY idx_pag_roles_permisos_estado (estado),
                KEY idx_pag_roles_permisos_permiso (id_permiso),
                CONSTRAINT fk_pag_roles_permisos_permiso FOREIGN KEY (id_permiso) REFERENCES pag_permisos (id_permiso) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
    ];

    foreach ($statements as $step => $sql) {
        try {
            db()->exec($sql);
            $result['steps'][$step] = 'ok';
        } catch (Throwable $e) {
            $result['steps'][$step] = 'error';
            $result['errors'][] = '[' . $step . '] ' . $e->getMessage();
        }
    }

    if (!empty($result['errors'])) {
        return $result;
    }

    $result['ok'] = pag_schema_tables_ready();
    $result['code'] = $result['ok'] ? 'ok' : 'tablas_no_disponibles';
    return $result;
}

function pag_schema_find_permission_id_by_code($permissionCode)
{
    $permissionCode = trim((string) $permissionCode);
    if ($permissionCode === '') {
        return 0;
    }

    $st = db()->prepare("SELECT id_permiso FROM pag_permisos WHERE permiso_codigo = ? LIMIT 1");
    $st->execute([$permissionCode]);
    $row = $st->fetch();

    return $row ? (int) $row['id_permiso'] : 0;
}

function pag_schema_find_page_id_by_slug($slug)
{
    $slug = trim((string) $slug);
    if ($slug === '') {
        return 0;
    }

    $st = db()->prepare("SELECT id_pagina FROM pag_paginas WHERE slug_pagina = ? LIMIT 1");
    $st->execute([$slug]);
    $row = $st->fetch();

    return $row ? (int) $row['id_pagina'] : 0;
}

function pag_schema_upsert_permission($permissionCode, $name, $description = null)
{
    $permissionCode = trim((string) $permissionCode);
    $name = trim((string) $name);
    $description = $description !== null ? trim((string) $description) : null;

    if (!pag_schema_is_valid_permission_code($permissionCode) || $name === '') {
        return 0;
    }

    $sql = "
        INSERT INTO pag_permisos (permiso_codigo, nombre_permiso, descripcion, estado, creado_en, actualizado_en)
        VALUES (?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            nombre_permiso = VALUES(nombre_permiso),
            descripcion = VALUES(descripcion),
            estado = 1,
            actualizado_en = NOW()
    ";
    $st = db()->prepare($sql);
    $st->execute([$permissionCode, $name, $description]);

    return pag_schema_find_permission_id_by_code($permissionCode);
}

function pag_schema_upsert_page($slug, array $data)
{
    if (!pag_schema_is_valid_slug($slug)) {
        return 0;
    }

    $tituloMenu = trim((string) ($data['titulo_menu'] ?? ''));
    $tituloPagina = trim((string) ($data['titulo_pagina'] ?? ''));
    $descripcionPagina = isset($data['descripcion_pagina']) ? trim((string) $data['descripcion_pagina']) : null;
    $parentSlug = trim((string) ($data['parent_slug'] ?? ''));
    $esContenedor = !empty($data['es_contenedor']) ? 1 : 0;
    $visibleMenu = isset($data['visible_menu']) ? ((int) $data['visible_menu'] === 1 ? 1 : 0) : 1;
    $moduloCodigo = isset($data['modulo_codigo']) ? trim((string) $data['modulo_codigo']) : null;
    $archivoSection = isset($data['archivo_section']) ? trim((string) $data['archivo_section']) : null;
    $permisoCodigo = isset($data['permiso_codigo']) ? trim((string) $data['permiso_codigo']) : '';
    $icono = isset($data['icono']) ? trim((string) $data['icono']) : null;
    $ordenMenu = (int) ($data['orden_menu'] ?? 0);
    $esFija = !empty($data['es_fija']) ? 1 : 0;
    $estado = isset($data['estado']) ? ((int) $data['estado'] === 1 ? 1 : 0) : 1;

    if ($tituloMenu === '' || $tituloPagina === '') {
        return 0;
    }

    $idPadre = null;
    if ($parentSlug !== '') {
        if (!pag_schema_is_valid_slug($parentSlug)) {
            return 0;
        }
        $foundParent = pag_schema_find_page_id_by_slug($parentSlug);
        if ($foundParent <= 0) {
            return 0;
        }
        $idPadre = $foundParent;
    }

    $idPermiso = null;
    if ($permisoCodigo !== '') {
        $idPermiso = pag_schema_find_permission_id_by_code($permisoCodigo);
        if ($idPermiso <= 0) {
            return 0;
        }
    }

    if ($esContenedor === 1) {
        $moduloCodigo = null;
        $archivoSection = null;
        $idPermiso = null;
    } else {
        if (!pag_schema_is_valid_identifier($moduloCodigo) || !pag_schema_is_valid_identifier($archivoSection)) {
            return 0;
        }
    }

    if ($descripcionPagina === '') {
        $descripcionPagina = null;
    }
    if ($icono === '') {
        $icono = null;
    }

    $sql = "
        INSERT INTO pag_paginas
            (titulo_menu, titulo_pagina, descripcion_pagina, slug_pagina, id_padre, es_contenedor, visible_menu, modulo_codigo, archivo_section, id_permiso_requerido, icono, orden_menu, es_fija, estado, creado_en, actualizado_en)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            titulo_menu = VALUES(titulo_menu),
            titulo_pagina = VALUES(titulo_pagina),
            descripcion_pagina = VALUES(descripcion_pagina),
            id_padre = VALUES(id_padre),
            es_contenedor = VALUES(es_contenedor),
            visible_menu = VALUES(visible_menu),
            modulo_codigo = VALUES(modulo_codigo),
            archivo_section = VALUES(archivo_section),
            id_permiso_requerido = VALUES(id_permiso_requerido),
            icono = VALUES(icono),
            orden_menu = VALUES(orden_menu),
            es_fija = VALUES(es_fija),
            estado = VALUES(estado),
            actualizado_en = NOW()
    ";
    $st = db()->prepare($sql);
    $st->execute([
        $tituloMenu,
        $tituloPagina,
        $descripcionPagina,
        $slug,
        $idPadre,
        $esContenedor,
        $visibleMenu,
        $moduloCodigo,
        $archivoSection,
        $idPermiso,
        $icono,
        $ordenMenu,
        $esFija,
        $estado,
    ]);

    return pag_schema_find_page_id_by_slug($slug);
}

function pag_schema_seed_fixed_pages()
{
    $result = [
        'ok' => false,
        'code' => 'error',
        'permissions_upserted' => 0,
        'pages_upserted' => 0,
        'superadmin_roles_found' => 0,
        'roles_permissions_upserted' => 0,
        'errors' => [],
    ];

    $schemaResult = pag_schema_ensure_tables();
    if (empty($schemaResult['ok'])) {
        $result['code'] = 'schema_no_disponible';
        $result['errors'] = array_merge($result['errors'], $schemaResult['errors']);
        return $result;
    }

    $permissions = [
        ['codigo' => 'seguridad_login.manage', 'nombre' => 'Gestionar seguridad login', 'descripcion' => 'Permite administrar configuraciones de seguridad del login.'],
        ['codigo' => 'usuarios.view', 'nombre' => 'Ver usuarios', 'descripcion' => 'Permite ver listado de usuarios.'],
        ['codigo' => 'usuarios.create', 'nombre' => 'Crear usuarios', 'descripcion' => 'Permite crear usuarios.'],
        ['codigo' => 'usuarios.edit', 'nombre' => 'Editar usuarios', 'descripcion' => 'Permite editar usuarios.'],
        ['codigo' => 'usuarios.toggle_state', 'nombre' => 'Cambiar estado usuarios', 'descripcion' => 'Permite activar o inactivar usuarios.'],
        ['codigo' => 'usuarios.reset_password', 'nombre' => 'Resetear contrasena usuarios', 'descripcion' => 'Permite resetear contrasenas de usuarios.'],
        ['codigo' => 'roles.view', 'nombre' => 'Ver roles', 'descripcion' => 'Permite ver listado de roles.'],
        ['codigo' => 'roles.create', 'nombre' => 'Crear roles', 'descripcion' => 'Permite crear roles.'],
        ['codigo' => 'roles.edit', 'nombre' => 'Editar roles', 'descripcion' => 'Permite editar roles.'],
        ['codigo' => 'roles.delete', 'nombre' => 'Eliminar roles', 'descripcion' => 'Permite eliminar roles.'],
        ['codigo' => 'permisos.view', 'nombre' => 'Ver permisos', 'descripcion' => 'Permite ver listado de permisos.'],
        ['codigo' => 'permisos.create', 'nombre' => 'Crear permisos', 'descripcion' => 'Permite crear permisos.'],
        ['codigo' => 'permisos.edit', 'nombre' => 'Editar permisos', 'descripcion' => 'Permite editar permisos.'],
        ['codigo' => 'permisos.delete', 'nombre' => 'Eliminar permisos', 'descripcion' => 'Permite eliminar permisos.'],
        ['codigo' => 'paginas_logicas.view', 'nombre' => 'Ver paginas logicas', 'descripcion' => 'Permite ver paginas logicas.'],
        ['codigo' => 'paginas_logicas.create', 'nombre' => 'Crear paginas logicas', 'descripcion' => 'Permite crear paginas logicas.'],
        ['codigo' => 'paginas_logicas.edit', 'nombre' => 'Editar paginas logicas', 'descripcion' => 'Permite editar paginas logicas.'],
        ['codigo' => 'paginas_logicas.delete', 'nombre' => 'Eliminar paginas logicas', 'descripcion' => 'Permite eliminar paginas logicas.'],
    ];

    foreach ($permissions as $permission) {
        $id = pag_schema_upsert_permission($permission['codigo'], $permission['nombre'], $permission['descripcion']);
        if ($id > 0) {
            $result['permissions_upserted']++;
        } else {
            $result['errors'][] = 'No se pudo registrar permiso: ' . $permission['codigo'];
        }
    }

    $pages = [
        [
            'slug' => 'superadmin',
            'titulo_menu' => 'Superadmin',
            'titulo_pagina' => 'Superadmin',
            'descripcion_pagina' => 'Contenedor de paginas administrativas del sistema.',
            'es_contenedor' => 1,
            'visible_menu' => 1,
            'icono' => 'fas fa-user-shield',
            'orden_menu' => 900,
            'es_fija' => 1,
            'estado' => 1,
        ],
        [
            'slug' => 'seguridad-login',
            'parent_slug' => 'superadmin',
            'titulo_menu' => 'Seguridad login',
            'titulo_pagina' => 'Seguridad del login',
            'descripcion_pagina' => 'Panel base para configuracion de seguridad del login.',
            'modulo_codigo' => 'seguridad_login',
            'archivo_section' => 'index',
            'permiso_codigo' => 'seguridad_login.manage',
            'icono' => 'fas fa-shield-alt',
            'orden_menu' => 10,
            'visible_menu' => 1,
            'es_fija' => 1,
            'estado' => 1,
        ],
        [
            'slug' => 'usuarios',
            'parent_slug' => 'superadmin',
            'titulo_menu' => 'Usuarios',
            'titulo_pagina' => 'Usuarios',
            'descripcion_pagina' => 'Panel base para administracion de usuarios.',
            'modulo_codigo' => 'usuarios',
            'archivo_section' => 'index',
            'permiso_codigo' => 'usuarios.view',
            'icono' => 'fas fa-users',
            'orden_menu' => 20,
            'visible_menu' => 1,
            'es_fija' => 1,
            'estado' => 1,
        ],
        [
            'slug' => 'roles',
            'parent_slug' => 'superadmin',
            'titulo_menu' => 'Roles',
            'titulo_pagina' => 'Roles',
            'descripcion_pagina' => 'Panel base para administracion de roles.',
            'modulo_codigo' => 'roles',
            'archivo_section' => 'index',
            'permiso_codigo' => 'roles.view',
            'icono' => 'fas fa-user-tag',
            'orden_menu' => 30,
            'visible_menu' => 1,
            'es_fija' => 1,
            'estado' => 1,
        ],
        [
            'slug' => 'permisos',
            'parent_slug' => 'superadmin',
            'titulo_menu' => 'Permisos',
            'titulo_pagina' => 'Permisos',
            'descripcion_pagina' => 'Panel base para administracion de permisos.',
            'modulo_codigo' => 'permisos',
            'archivo_section' => 'index',
            'permiso_codigo' => 'permisos.view',
            'icono' => 'fas fa-key',
            'orden_menu' => 40,
            'visible_menu' => 1,
            'es_fija' => 1,
            'estado' => 1,
        ],
        [
            'slug' => 'paginas-logicas',
            'parent_slug' => 'superadmin',
            'titulo_menu' => 'Paginas logicas',
            'titulo_pagina' => 'Paginas logicas',
            'descripcion_pagina' => 'Panel base para administrar paginas logicas.',
            'modulo_codigo' => 'paginas_logicas',
            'archivo_section' => 'index',
            'permiso_codigo' => 'paginas_logicas.view',
            'icono' => 'fas fa-sitemap',
            'orden_menu' => 50,
            'visible_menu' => 1,
            'es_fija' => 1,
            'estado' => 1,
        ],
    ];

    foreach ($pages as $page) {
        $slug = $page['slug'];
        unset($page['slug']);
        $id = pag_schema_upsert_page($slug, $page);
        if ($id > 0) {
            $result['pages_upserted']++;
        } else {
            $result['errors'][] = 'No se pudo registrar pagina: ' . $slug;
        }
    }

    if (pag_schema_table_exists('lsis_roles')) {
        $roleRows = db()->query("SELECT id FROM lsis_roles WHERE estado = 1 AND LOWER(nombre) = 'superadmin'")->fetchAll();
        $roleIds = [];
        foreach ($roleRows as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid > 0) {
                $roleIds[] = $rid;
            }
        }
        $roleIds = array_values(array_unique($roleIds));
        $result['superadmin_roles_found'] = count($roleIds);

        if ($roleIds) {
            $permissionRows = db()->query("SELECT id_permiso FROM pag_permisos WHERE estado = 1")->fetchAll();
            $permissionIds = [];
            foreach ($permissionRows as $row) {
                $pid = (int) ($row['id_permiso'] ?? 0);
                if ($pid > 0) {
                    $permissionIds[] = $pid;
                }
            }

            if ($permissionIds) {
                $stMap = db()->prepare("
                    INSERT INTO pag_roles_permisos (id_rol, id_permiso, estado, creado_en, actualizado_en)
                    VALUES (?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        estado = 1,
                        actualizado_en = NOW()
                ");

                foreach ($roleIds as $rid) {
                    foreach ($permissionIds as $pid) {
                        $stMap->execute([$rid, $pid]);
                        $result['roles_permissions_upserted']++;
                    }
                }
            }
        } else {
            $result['errors'][] = 'No se encontro rol Superadmin activo para asignar permisos base.';
        }
    } else {
        $result['errors'][] = 'Tabla lsis_roles no disponible para asignar permisos base.';
    }

    $result['ok'] = true;
    $result['code'] = empty($result['errors']) ? 'ok' : 'ok_con_advertencias';
    return $result;
}

function pag_schema_sync_all()
{
    $schema = pag_schema_ensure_tables();
    if (empty($schema['ok'])) {
        return [
            'ok' => false,
            'code' => 'schema_no_disponible',
            'schema' => $schema,
            'seed' => null,
        ];
    }

    $seed = pag_schema_seed_fixed_pages();
    return [
        'ok' => !empty($seed['ok']),
        'code' => !empty($seed['ok']) ? $seed['code'] : 'seed_error',
        'schema' => $schema,
        'seed' => $seed,
    ];
}
