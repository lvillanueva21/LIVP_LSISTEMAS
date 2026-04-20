<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/security.php';

function start_secure_session()
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $cfg = lsis_get_config();
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
    return lsis_table_exists_cached($tableName);
}

function lsis_security_config()
{
    return lsis_get_security_policy();
}

function lsis_auth_roles_hardening_columns_ready()
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    if (!lsis_auth_table_exists('lsis_roles')) {
        $ready = false;
        return $ready;
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
        $ready = isset($map['es_sistema']) && isset($map['es_protegido']);
        return $ready;
    } catch (Throwable $e) {
        $ready = false;
        return $ready;
    }
}

function lsis_auth_get_protected_system_role_ids($forUpdate = false, $onlyActive = true)
{
    if (!lsis_auth_table_exists('lsis_roles')) {
        return [];
    }

    $forUpdate = (bool) $forUpdate;
    $onlyActive = (bool) $onlyActive;
    $ids = [];

    if (lsis_auth_roles_hardening_columns_ready()) {
        $sql = "
            SELECT id
            FROM lsis_roles
            WHERE es_sistema = 1
              AND es_protegido = 1
        ";
        if ($onlyActive) {
            $sql .= " AND estado = 1";
        }
        $sql .= " ORDER BY id ASC";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $rows = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ((array) $rows as $value) {
            $rid = (int) $value;
            if ($rid > 0) {
                $ids[] = $rid;
            }
        }
    }

    // Compatibilidad temporal durante migracion: fallback por nombre.
    if (!$ids) {
        $sql = "
            SELECT id
            FROM lsis_roles
            WHERE LOWER(nombre) = 'superadmin'
        ";
        if ($onlyActive) {
            $sql .= " AND estado = 1";
        }
        $sql .= " ORDER BY id ASC";
        if ($forUpdate) {
            $sql .= " FOR UPDATE";
        }

        $rows = db()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        foreach ((array) $rows as $value) {
            $rid = (int) $value;
            if ($rid > 0) {
                $ids[] = $rid;
            }
        }
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($value) {
        return $value > 0;
    })));
    sort($ids);
    return $ids;
}

function lsis_auth_count_active_protected_admin_users($forUpdate = false)
{
    if (
        !lsis_auth_table_exists('lsis_usuarios')
        || !lsis_auth_table_exists('lsis_roles')
        || !lsis_auth_table_exists('lsis_usuario_roles')
    ) {
        return 0;
    }

    $roleIds = lsis_auth_get_protected_system_role_ids($forUpdate, true);
    if (!$roleIds) {
        return 0;
    }

    $marks = implode(',', array_fill(0, count($roleIds), '?'));
    $sql = "
        SELECT DISTINCT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        WHERE u.estado = 1
          AND ur.estado = 1
          AND ur.id_rol IN ($marks)
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $st = db()->prepare($sql);
    $st->execute($roleIds);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);
    return is_array($rows) ? count($rows) : 0;
}

function lsis_auth_user_is_active_protected_admin($userId, $forUpdate = false)
{
    $userId = (int) $userId;
    if ($userId <= 0) {
        return false;
    }

    if (
        !lsis_auth_table_exists('lsis_usuarios')
        || !lsis_auth_table_exists('lsis_roles')
        || !lsis_auth_table_exists('lsis_usuario_roles')
    ) {
        return false;
    }

    $roleIds = lsis_auth_get_protected_system_role_ids($forUpdate, true);
    if (!$roleIds) {
        return false;
    }

    $marks = implode(',', array_fill(0, count($roleIds), '?'));
    $sql = "
        SELECT u.id
        FROM lsis_usuarios u
        INNER JOIN lsis_usuario_roles ur ON ur.id_usuario = u.id
        WHERE u.id = ?
          AND u.estado = 1
          AND ur.estado = 1
          AND ur.id_rol IN ($marks)
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $params = array_merge([$userId], $roleIds);
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();

    return !empty($row['id']);
}

function lsis_auth_is_superadmin_user_id($userId)
{
    return lsis_auth_user_is_active_protected_admin($userId, false);
}

function lsis_auth_admin_context_ok($actorAdminId, &$meta = null, array $options = [])
{
    $defaults = [
        'allow_system_actor' => false,
        'require_session_match' => (PHP_SAPI !== 'cli'),
        'require_superadmin' => true,
        'context' => 'admin_operation',
    ];
    $opts = array_merge($defaults, $options);

    $actorAdminId = (int) $actorAdminId;
    $meta = [
        'ok' => false,
        'reason' => '',
        'actor_admin_id' => $actorAdminId,
        'context' => (string) $opts['context'],
        'system_actor' => false,
    ];

    if ($actorAdminId <= 0) {
        if (!empty($opts['allow_system_actor']) && $actorAdminId === 0) {
            $meta['ok'] = true;
            $meta['reason'] = 'system_actor';
            $meta['system_actor'] = true;
            return true;
        }

        $meta['reason'] = 'actor_admin_invalido';
        return false;
    }

    if (!empty($opts['require_session_match'])) {
        if (!isAuthenticated()) {
            $meta['reason'] = 'sesion_admin_requerida';
            return false;
        }

        $sessionUserId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($sessionUserId <= 0 || $sessionUserId !== $actorAdminId) {
            $meta['reason'] = 'actor_admin_no_coincide_con_sesion';
            return false;
        }
    }

    if (!empty($opts['require_superadmin']) && !lsis_auth_is_superadmin_user_id($actorAdminId)) {
        $meta['reason'] = 'actor_admin_no_superadmin';
        return false;
    }

    $meta['ok'] = true;
    $meta['reason'] = 'ok';
    return true;
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
    $validos = ['logout', 'timeout', 'reemplazada', 'forzada_admin', 'actualizacion_acceso'];
    $motivo = trim((string) $motivo);

    if (!in_array($motivo, $validos, true)) {
        return 'logout';
    }

    return $motivo;
}

function lsis_close_active_sessions_by_ids(array $ids, $motivo)
{
    if (!$ids || !lsis_auth_table_exists('lsis_sesiones')) {
        return 0;
    }

    $motivo = lsis_normalize_close_reason($motivo);
    $ids = array_values(array_filter(array_map('intval', $ids), function ($v) {
        return $v > 0;
    }));

    if (!$ids) {
        return 0;
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
    return (int) $st->rowCount();
}

function lsis_close_active_sessions_by_user_ids(array $userIds, $motivo)
{
    if (!$userIds || !lsis_auth_table_exists('lsis_sesiones')) {
        return 0;
    }

    $userIds = array_values(array_filter(array_map('intval', $userIds), function ($value) {
        return $value > 0;
    }));
    if (!$userIds) {
        return 0;
    }

    $motivo = lsis_normalize_close_reason($motivo);
    $marks = implode(',', array_fill(0, count($userIds), '?'));

    $sql = "
        UPDATE lsis_sesiones
        SET estado = 0,
            logout_at = NOW(),
            motivo_cierre = ?,
            actualizado_en = NOW()
        WHERE estado = 1
          AND id_usuario IN ($marks)
    ";

    $params = array_merge([$motivo], $userIds);
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) $st->rowCount();
}

function lsis_auth_fetch_current_active_session_row($userId, $sessionHash)
{
    $userId = (int) $userId;
    $sessionHash = trim((string) $sessionHash);
    if ($userId <= 0 || $sessionHash === '') {
        return null;
    }

    $st = db()->prepare("
        SELECT id, ultima_actividad_at
        FROM lsis_sesiones
        WHERE id_usuario = ?
          AND session_id_hash = ?
          AND estado = 1
        LIMIT 1
    ");
    $st->execute([$userId, $sessionHash]);
    $row = $st->fetch();
    return $row ?: null;
}

function lsis_auth_fetch_current_session_close_reason($userId, $sessionHash)
{
    $userId = (int) $userId;
    $sessionHash = trim((string) $sessionHash);
    if ($userId <= 0 || $sessionHash === '') {
        return '';
    }

    $st = db()->prepare("
        SELECT estado, motivo_cierre
        FROM lsis_sesiones
        WHERE id_usuario = ?
          AND session_id_hash = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $st->execute([$userId, $sessionHash]);
    $row = $st->fetch();
    if (!$row) {
        return '';
    }

    if ((int) ($row['estado'] ?? 0) === 1) {
        return '';
    }

    return trim((string) ($row['motivo_cierre'] ?? ''));
}

function lsis_auth_check_current_session_state(array $options = [])
{
    $defaults = [
        'touch_activity' => true,
        'enforce_timeout' => true,
    ];
    $opts = array_merge($defaults, $options);

    $meta = [
        'ok' => false,
        'code' => 'sesion_requerida',
        'message' => 'Sesion no valida.',
        'login_m' => 'sesion',
        'session_row_id' => 0,
    ];

    if (!isAuthenticated()) {
        return $meta;
    }

    $cfgSeg = lsis_security_config();
    if ((int) ($cfgSeg['control_sesiones_activo'] ?? 0) !== 1) {
        $meta['ok'] = true;
        $meta['code'] = 'ok';
        $meta['message'] = 'ok';
        return $meta;
    }

    if (!lsis_auth_table_exists('lsis_sesiones')) {
        $meta['code'] = 'sesion_invalida';
        return $meta;
    }

    $uid = (int) ($_SESSION['user']['id'] ?? 0);
    $sid = session_id();
    if ($uid <= 0 || $sid === '') {
        $meta['code'] = 'sesion_invalida';
        return $meta;
    }

    $hash = lsis_hash_session_id($sid);

    try {
        $sesion = lsis_auth_fetch_current_active_session_row($uid, $hash);
        if (!$sesion) {
            $closeReason = lsis_auth_fetch_current_session_close_reason($uid, $hash);
            if ($closeReason === 'actualizacion_acceso') {
                $meta['code'] = 'acceso_actualizado';
                $meta['message'] = 'Tu sesion se cerro por actualizacion de roles o permisos.';
                $meta['login_m'] = 'acceso_actualizado';
                return $meta;
            }

            $meta['code'] = 'sesion_invalida';
            return $meta;
        }

        $meta['session_row_id'] = (int) ($sesion['id'] ?? 0);

        if (!empty($opts['enforce_timeout'])) {
            $timeoutActivo = ((int) ($cfgSeg['timeout_inactividad_activo'] ?? 0) === 1);
            $timeoutMin = (int) ($cfgSeg['timeout_inactividad_minutos'] ?? 0);
            if ($timeoutMin < 1) {
                $timeoutMin = 1;
            }

            if ($timeoutActivo) {
                $lastAt = strtotime((string) ($sesion['ultima_actividad_at'] ?? ''));
                if ($lastAt !== false) {
                    $elapsed = time() - $lastAt;
                    if ($elapsed > ($timeoutMin * 60)) {
                        $stTimeout = db()->prepare("
                            UPDATE lsis_sesiones
                            SET estado = 0,
                                logout_at = NOW(),
                                motivo_cierre = 'timeout',
                                actualizado_en = NOW()
                            WHERE id = ?
                              AND estado = 1
                        ");
                        $stTimeout->execute([(int) $sesion['id']]);

                        $meta['code'] = 'timeout';
                        return $meta;
                    }
                }
            }
        }

        if (!empty($opts['touch_activity'])) {
            $stTouch = db()->prepare("
                UPDATE lsis_sesiones
                SET ultima_actividad_at = NOW(),
                    ip = ?,
                    user_agent = ?,
                    actualizado_en = NOW()
                WHERE id = ?
                  AND estado = 1
            ");
            $stTouch->execute([lsis_client_ip(), lsis_client_user_agent(), (int) $sesion['id']]);
        }

        $meta['ok'] = true;
        $meta['code'] = 'ok';
        $meta['message'] = 'ok';
        return $meta;
    } catch (Throwable $e) {
        $meta['code'] = 'sesion_error';
        return $meta;
    }
}

function lsis_auth_guard_active_session(array $options = [])
{
    $defaults = [
        'touch_activity' => true,
        'enforce_timeout' => true,
        'logout_on_fail' => true,
    ];
    $opts = array_merge($defaults, $options);

    $check = lsis_auth_check_current_session_state([
        'touch_activity' => !empty($opts['touch_activity']),
        'enforce_timeout' => !empty($opts['enforce_timeout']),
    ]);

    if (!empty($check['ok'])) {
        return [
            'ok' => true,
            'http_status' => 200,
            'code' => 'ok',
            'message' => 'ok',
            'login_m' => 'sesion',
        ];
    }

    if (!empty($opts['logout_on_fail']) && isAuthenticated()) {
        logout();
    }

    $code = (string) ($check['code'] ?? 'sesion_requerida');
    $message = 'Sesion no valida.';
    $loginM = (string) ($check['login_m'] ?? 'sesion');

    if ($code === 'acceso_actualizado') {
        $message = 'Tu sesion se cerro por actualizacion de roles o permisos.';
        $loginM = 'acceso_actualizado';
    }

    return [
        'ok' => false,
        'http_status' => 401,
        'code' => $code,
        'message' => $message,
        'login_m' => $loginM,
    ];
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

    if (!lsis_auth_table_exists('lsis_usuarios') || !lsis_auth_table_exists('lsis_roles') || !lsis_auth_table_exists('lsis_usuario_roles')) {
        lsis_security_record_attempt('login', $usuario, 0, 'auth_incompleta');
        return ['ok' => false, 'error' => 'No se pudo iniciar sesiÃ³n.', 'code' => 'auth_incompleta'];
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

    $sessionGuard = lsis_auth_guard_active_session([
        'touch_activity' => true,
        'enforce_timeout' => true,
        'logout_on_fail' => true,
    ]);
    if (empty($sessionGuard['ok'])) {
        $loginM = (string) ($sessionGuard['login_m'] ?? 'sesion');
        header('Location: login.php?m=' . rawurlencode($loginM));
        exit;
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
