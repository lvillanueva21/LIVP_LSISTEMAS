<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';

function lsis_table_exists($tableName)
{
    return lsis_table_exists_cached($tableName);
}

function lsis_roles_hardening_columns_ready()
{
    if (!lsis_table_exists('lsis_roles')) {
        return false;
    }

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
}

function lsis_get_superadmin_role_id()
{
    if (!lsis_table_exists('lsis_roles') || !lsis_roles_hardening_columns_ready()) {
        return 0;
    }

    $sql = "
        SELECT id
        FROM lsis_roles
        WHERE es_sistema = 1
          AND es_protegido = 1
          AND estado = 1
        ORDER BY id ASC
        LIMIT 1
    ";
    $res = db()->query($sql);
    $row = $res ? $res->fetch() : null;

    return $row ? (int) $row['id'] : 0;
}

function lsis_superadmin_exists()
{
    if (
        !lsis_table_exists('lsis_usuarios')
        || !lsis_table_exists('lsis_usuario_roles')
        || !lsis_table_exists('lsis_roles')
        || !lsis_roles_hardening_columns_ready()
    ) {
        return false;
    }

    $sql = "
        SELECT COUNT(*) AS c
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE r.es_sistema = 1
          AND r.es_protegido = 1
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
