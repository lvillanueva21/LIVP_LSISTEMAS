<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = require __DIR__ . '/config.php';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    if (!empty($cfg['app']['session_name'])) {
        session_name($cfg['app']['session_name']);
    }

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        session_set_cookie_params(0, '/');
    }

    session_start();
}

start_secure_session();

function login($usuario, $clave)
{
    $usuario = trim((string) $usuario);
    $clave = (string) $clave;

    if ($usuario === '' || $clave === '') {
        return ['ok' => false, 'error' => 'Ingresa usuario y contrasena.'];
    }

    $sql = "SELECT id, usuario, clave, nombres, apellidos, estado FROM lsis_usuarios WHERE usuario = ? LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([$usuario]);
    $row = $st->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'Usuario o contrasena incorrectos.'];
    }

    if ((int) $row['estado'] !== 1) {
        return ['ok' => false, 'error' => 'Usuario inactivo.'];
    }

    if (!password_verify($clave, $row['clave'])) {
        return ['ok' => false, 'error' => 'Usuario o contrasena incorrectos.'];
    }

    $sqlRoles = "
        SELECT r.id, r.nombre
        FROM lsis_usuario_roles ur
        INNER JOIN lsis_roles r ON r.id = ur.id_rol
        WHERE ur.id_usuario = ?
          AND ur.estado = 1
          AND r.estado = 1
        ORDER BY r.id ASC
    ";

    $uid = (int) $row['id'];
    $stRoles = db()->prepare($sqlRoles);
    $stRoles->execute([$uid]);
    $rolesRows = $stRoles->fetchAll();

    if (!$rolesRows) {
        return ['ok' => false, 'error' => 'El usuario no tiene roles asignados.'];
    }

    $roles = [];
    foreach ($rolesRows as $r) {
        $roles[] = [
            'id' => (int) $r['id'],
            'nombre' => (string) $r['nombre'],
        ];
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'usuario' => (string) $row['usuario'],
        'nombres' => (string) $row['nombres'],
        'apellidos' => (string) $row['apellidos'],
        'roles' => $roles,
        'rol_activo' => $roles[0]['nombre'],
        'rol_activo_id' => $roles[0]['id'],
        'logged_at' => date('Y-m-d H:i:s'),
    ];

    return ['ok' => true];
}

function isAuthenticated()
{
    return !empty($_SESSION['user']['id']);
}

function requireAuth()
{
    if (!isAuthenticated()) {
        header('Location: login.php?m=sesion');
        exit;
    }
}

function currentUser()
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
}

function logout()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
