<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/security.php';

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

function lsis_auth_table_exists($tableName)
{
    static $cache = [];

    $tableName = trim((string) $tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $sql = "
            SELECT COUNT(*) AS c
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ";
        $st = db()->prepare($sql);
        $st->execute([$tableName]);
        $row = $st->fetch();
        $cache[$tableName] = !empty($row['c']);
    } catch (Throwable $e) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function lsis_security_defaults()
{
    return lsis_security_policy_defaults();
}

function lsis_security_config()
{
    return lsis_get_security_policy();
}

function lsis_client_ip()
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return '';
    }

    return substr($ip, 0, 45);
}

function lsis_client_user_agent()
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '') {
        return '';
    }

    return substr($ua, 0, 255);
}

function lsis_hash_session_id($sessionId)
{
    return hash('sha256', (string) $sessionId);
}

function lsis_normalize_close_reason($motivo)
{
    $validos = ['logout', 'timeout', 'reemplazada', 'forzada_admin'];
    $motivo = trim((string) $motivo);

    if (!in_array($motivo, $validos, true)) {
        return 'logout';
    }

    return $motivo;
}

function lsis_close_active_sessions_by_ids(array $ids, $motivo)
{
    if (!$ids || !lsis_auth_table_exists('lsis_sesiones')) {
        return;
    }

    $motivo = lsis_normalize_close_reason($motivo);
    $ids = array_values(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    }));

    if (!$ids) {
        return;
    }

    $marks = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        UPDATE lsis_sesiones
        SET estado = 0,
            logout_at = NOW(),
            motivo_cierre = ?,
            actualizado_en = NOW()
        WHERE estado = 1
          AND id IN ($marks)
    ";

    $params = array_merge([$motivo], $ids);
    $st = db()->prepare($sql);
    $st->execute($params);
}

function lsis_close_current_session_db($motivo)
{
    if (!lsis_auth_table_exists('lsis_sesiones')) {
        return;
    }

    $uid = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
    if ($uid <= 0) {
        return;
    }

    $sid = session_id();
    if ($sid === '') {
        return;
    }

    $motivo = lsis_normalize_close_reason($motivo);
    $hash = lsis_hash_session_id($sid);

    $sql = "
        UPDATE lsis_sesiones
        SET estado = 0,
            logout_at = NOW(),
            motivo_cierre = ?,
            actualizado_en = NOW()
        WHERE id_usuario = ?
          AND session_id_hash = ?
          AND estado = 1
    ";

    $st = db()->prepare($sql);
    $st->execute([$motivo, $uid, $hash]);
}

function lsis_update_user_last_login($uid)
{
    if (!lsis_auth_table_exists('lsis_usuarios')) {
        return;
    }

    $uid = (int) $uid;
    if ($uid <= 0) {
        return;
    }

    $ip = lsis_client_ip();
    $sql = "UPDATE lsis_usuarios SET ultimo_login_at = NOW(), ultimo_login_ip = ? WHERE id = ?";
    $st = db()->prepare($sql);
    $st->execute([$ip, $uid]);
}

function lsis_register_user_session($uid)
{
    if (!lsis_auth_table_exists('lsis_sesiones')) {
        return;
    }

    $uid = (int) $uid;
    if ($uid <= 0) {
        return;
    }

    $sid = session_id();
    if ($sid === '') {
        return;
    }

    $cfgSeg = lsis_security_config();
    $controlActivo = ((int) $cfgSeg['control_sesiones_activo'] === 1);
    $maxDispositivosActivo = ((int) $cfgSeg['max_dispositivos_activo'] === 1);
    $maxDispositivos = (int) $cfgSeg['max_dispositivos'];
    if ($maxDispositivos < 1) {
        $maxDispositivos = 1;
    }

    $hash = lsis_hash_session_id($sid);
    $ip = lsis_client_ip();
    $ua = lsis_client_user_agent();

    $pdo = db();
    $ownTx = false;

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $ownTx = true;
        }

        if ($controlActivo && $maxDispositivosActivo) {
            $stActivas = $pdo->prepare("SELECT id FROM lsis_sesiones WHERE id_usuario = ? AND estado = 1 AND session_id_hash <> ? ORDER BY ultima_actividad_at ASC, id ASC");
            $stActivas->execute([$uid, $hash]);
            $idsActivas = $stActivas->fetchAll(PDO::FETCH_COLUMN);
            $idsActivas = array_map('intval', $idsActivas ?: []);

            $aCerrar = count($idsActivas) - $maxDispositivos + 1;
            if ($aCerrar > 0) {
                $idsCerrar = array_slice($idsActivas, 0, $aCerrar);
                lsis_close_active_sessions_by_ids($idsCerrar, 'reemplazada');
            }
        }

        $stIns = $pdo->prepare("
            INSERT INTO lsis_sesiones
                (id_usuario, session_id_hash, ip, user_agent, login_at, ultima_actividad_at, logout_at, estado, motivo_cierre, creado_en, actualizado_en)
            VALUES
                (?, ?, ?, ?, NOW(), NOW(), NULL, 1, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                id_usuario = VALUES(id_usuario),
                ip = VALUES(ip),
                user_agent = VALUES(user_agent),
                login_at = NOW(),
                ultima_actividad_at = NOW(),
                logout_at = NULL,
                estado = 1,
                motivo_cierre = NULL,
                actualizado_en = NOW()
        ");
        $stIns->execute([$uid, $hash, $ip, $ua]);

        if ($ownTx) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

function login($usuario, $clave)
{
    $usuario = trim((string) $usuario);
    $clave = (string) $clave;

    if ($usuario === '' || $clave === '') {
        lsis_security_record_attempt('login', $usuario, 0, 'datos_incompletos');
        return ['ok' => false, 'error' => 'Ingresa usuario y contrasena.', 'code' => 'datos_incompletos'];
    }

    $sql = "SELECT id, usuario, clave, nombres, apellidos, estado FROM lsis_usuarios WHERE usuario = ? LIMIT 1";
    $st = db()->prepare($sql);
    $st->execute([$usuario]);
    $row = $st->fetch();

    if (!$row) {
        lsis_security_record_attempt('login', $usuario, 0, 'credenciales_invalidas');
        return ['ok' => false, 'error' => 'Usuario o contrasena incorrectos.', 'code' => 'credenciales_invalidas'];
    }

    if ((int) $row['estado'] !== 1) {
        lsis_security_record_attempt('login', $usuario, 0, 'usuario_inactivo');
        return ['ok' => false, 'error' => 'Usuario inactivo.', 'code' => 'usuario_inactivo'];
    }

    if (!password_verify($clave, $row['clave'])) {
        lsis_security_record_attempt('login', $usuario, 0, 'credenciales_invalidas');
        return ['ok' => false, 'error' => 'Usuario o contrasena incorrectos.', 'code' => 'credenciales_invalidas'];
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
        lsis_security_record_attempt('login', $usuario, 0, 'sin_roles');
        return ['ok' => false, 'error' => 'El usuario no tiene roles asignados.', 'code' => 'usuario_sin_roles'];
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

    try {
        lsis_update_user_last_login($uid);
        lsis_register_user_session($uid);
    } catch (Throwable $e) {
        // Mantener contrato funcional del login sin alterar respuesta visible.
    }

    lsis_security_record_attempt('login', $usuario, 1, 'ok');

    return ['ok' => true, 'code' => 'ok'];
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

    $cfgSeg = lsis_security_config();
    if ((int) $cfgSeg['control_sesiones_activo'] !== 1) {
        return;
    }

    if (!lsis_auth_table_exists('lsis_sesiones')) {
        return;
    }

    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $sid = session_id();

    if ($uid <= 0 || $sid === '') {
        logout();
        header('Location: login.php?m=sesion');
        exit;
    }

    $hash = lsis_hash_session_id($sid);

    try {
        $st = db()->prepare("SELECT id, ultima_actividad_at FROM lsis_sesiones WHERE id_usuario = ? AND session_id_hash = ? AND estado = 1 LIMIT 1");
        $st->execute([$uid, $hash]);
        $sesion = $st->fetch();

        if (!$sesion) {
            logout();
            header('Location: login.php?m=sesion');
            exit;
        }

        $timeoutActivo = ((int) $cfgSeg['timeout_inactividad_activo'] === 1);
        $timeoutMin = (int) $cfgSeg['timeout_inactividad_minutos'];
        if ($timeoutMin < 1) {
            $timeoutMin = 1;
        }

        if ($timeoutActivo) {
            $lastAt = strtotime((string) ($sesion['ultima_actividad_at'] ?? ''));
            if ($lastAt !== false) {
                $elapsed = time() - $lastAt;
                if ($elapsed > ($timeoutMin * 60)) {
                    $stTimeout = db()->prepare("UPDATE lsis_sesiones SET estado = 0, logout_at = NOW(), motivo_cierre = 'timeout', actualizado_en = NOW() WHERE id = ? AND estado = 1");
                    $stTimeout->execute([(int) $sesion['id']]);

                    logout();
                    header('Location: login.php?m=sesion');
                    exit;
                }
            }
        }

        $stTouch = db()->prepare("UPDATE lsis_sesiones SET ultima_actividad_at = NOW(), ip = ?, user_agent = ?, actualizado_en = NOW() WHERE id = ? AND estado = 1");
        $stTouch->execute([lsis_client_ip(), lsis_client_user_agent(), (int) $sesion['id']]);
    } catch (Throwable $e) {
        // Si hay error tecnico, no romper flujo visible actual.
    }
}

function currentUser()
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : [];
}

function logout()
{
    try {
        lsis_close_current_session_db('logout');
    } catch (Throwable $e) {
        // Mantener comportamiento de cierre de sesion aunque falle BD.
    }

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
