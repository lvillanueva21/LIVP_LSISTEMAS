<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';

function lsis_table_exists($tableName)
{
    $sql = "
        SELECT COUNT(*) AS c
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ";
    $st = db()->prepare($sql);
    $st->execute([$tableName]);
    $row = $st->fetch();

    return !empty($row['c']);
}

function lsis_get_superadmin_role_id()
{
    if (!lsis_table_exists('lsis_roles')) {
        return 0;
    }

    $sql = "SELECT id FROM lsis_roles WHERE nombre = 'Superadmin' LIMIT 1";
    $res = db()->query($sql);
    $row = $res ? $res->fetch() : null;

    return $row ? (int) $row['id'] : 0;
}

function lsis_superadmin_exists()
{
    if (!lsis_table_exists('lsis_usuarios') || !lsis_table_exists('lsis_usuario_roles') || !lsis_table_exists('lsis_roles')) {
        return false;
    }

    $sql = "
        SELECT COUNT(*) AS c
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE r.nombre = 'Superadmin'
          AND u.estado = 1
          AND ur.estado = 1
          AND r.estado = 1
    ";
    $res = db()->query($sql);
    $row = $res ? $res->fetch() : null;

    return !empty($row['c']);
}

function lsis_config_table_initialized()
{
    if (!lsis_table_exists('lsis_configuracion_sistema')) {
        return false;
    }

    $sql = "
        SELECT sistema_inicializado
        FROM lsis_configuracion_sistema
        WHERE id = 1
        LIMIT 1
    ";
    $res = db()->query($sql);
    $row = $res ? $res->fetch() : null;

    return $row && (int) $row['sistema_inicializado'] === 1;
}

function lsis_is_initialized()
{
    if (lsis_config_table_initialized()) {
        return true;
    }

    return lsis_superadmin_exists();
}

function lsis_can_run_initial_setup()
{
    return !lsis_is_initialized();
}
