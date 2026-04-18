<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/instalacion.php';

if (!lsis_can_run_initial_setup()) {
    header('Location: login.php');
    exit;
}

$cfg = require __DIR__ . '/includes/config.php';
$installKey = trim((string) ($cfg['app']['install_key'] ?? ''));
$installKeyEnabled = ($installKey !== '');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$setupError = '';
$usuarioForm = '';
$nombresForm = '';
$apellidosForm = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $nombres = trim((string) ($_POST['nombres'] ?? ''));
    $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');
    $claveConfirmar = (string) ($_POST['clave_confirmar'] ?? '');
    $claveInstalacion = trim((string) ($_POST['clave_instalacion'] ?? ''));

    $usuarioForm = $usuario;
    $nombresForm = $nombres;
    $apellidosForm = $apellidos;

    $errores = [];

    if (!lsis_can_run_initial_setup()) {
        $errores[] = 'El sistema ya fue inicializado.';
    }

    if (!preg_match('/^\d{8,11}$/', $usuario)) {
        $errores[] = 'Usuario debe tener entre 8 y 11 digitos.';
    }
    if ($nombres === '') {
        $errores[] = 'Nombres son obligatorios.';
    }
    if ($apellidos === '') {
        $errores[] = 'Apellidos son obligatorios.';
    }
    if (strlen($clave) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    }
    if ($clave !== $claveConfirmar) {
        $errores[] = 'La confirmacion de contrasena no coincide.';
    }
    if ($installKeyEnabled && !hash_equals($installKey, $claveInstalacion)) {
        $errores[] = 'Clave de instalacion invalida.';
    }

    if (!lsis_table_exists('lsis_usuarios') || !lsis_table_exists('lsis_roles') || !lsis_table_exists('lsis_usuario_roles')) {
        $errores[] = 'Faltan tablas base del sistema.';
    }
    if (!lsis_table_exists('lsis_configuracion_sistema')) {
        $errores[] = 'Falta tabla de configuracion del sistema.';
    }

    if (!$errores) {
        db()->beginTransaction();
        try {
            $stUser = db()->prepare('SELECT id FROM lsis_usuarios WHERE usuario = ? LIMIT 1');
            $stUser->execute([$usuario]);
            $existe = $stUser->fetch();

            if ($existe) {
                throw new Exception('El usuario ya existe.');
            }

            $roleId = lsis_get_superadmin_role_id();
            if ($roleId <= 0) {
                $estadoRol = 1;
                $nombreRol = 'Superadmin';
                $stRole = db()->prepare('INSERT INTO lsis_roles (nombre, estado) VALUES (?, ?)');
                $stRole->execute([$nombreRol, $estadoRol]);
                $roleId = (int) db()->lastInsertId();
            }

            $hash = password_hash($clave, PASSWORD_BCRYPT);
            $estadoUsuario = 1;
            $stInsertUser = db()->prepare('INSERT INTO lsis_usuarios (usuario, clave, nombres, apellidos, estado) VALUES (?, ?, ?, ?, ?)');
            $stInsertUser->execute([$usuario, $hash, $nombres, $apellidos, $estadoUsuario]);
            $userId = (int) db()->lastInsertId();

            $estadoRolUsuario = 1;
            $stInsertUR = db()->prepare('INSERT INTO lsis_usuario_roles (id_usuario, id_rol, estado) VALUES (?, ?, ?)');
            $stInsertUR->execute([$userId, $roleId, $estadoRolUsuario]);

            $inicializado = 1;
            $stCfg = db()->prepare(
                'INSERT INTO lsis_configuracion_sistema (id, sistema_inicializado, id_usuario_inicial, fecha_inicializacion, actualizado_en)
                 VALUES (1, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    sistema_inicializado = VALUES(sistema_inicializado),
                    id_usuario_inicial = VALUES(id_usuario_inicial),
                    fecha_inicializacion = VALUES(fecha_inicializacion),
                    actualizado_en = NOW()'
            );
            $stCfg->execute([$inicializado, $userId]);

            db()->commit();

            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => true, 'redirect' => 'login.php']);
                exit;
            }

            header('Location: login.php');
            exit;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errores[] = $e->getMessage() !== '' ? $e->getMessage() : 'No se pudo completar el registro inicial.';
        }
    }

    $setupError = implode(' ', $errores);

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'error' => $setupError]);
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registro Inicial | Sistema</title>
  <link rel="icon" type="image/x-icon" href="assets/img/circular genesis_ico.ico">

  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .form-group { position: relative; margin-bottom: 1rem; }
    .setup-title { font-weight: 700; margin-bottom: .5rem; }
    .setup-subtitle { font-size: .92rem; color: #6c757d; margin-bottom: 1rem; }
  </style>
</head>
<body>
<section class="ftco-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-8 text-center mb-4">
        <h2 class="heading-section">Registro inicial del sistema</h2>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="wrap">
          <div class="img" style="background-image: url(assets/img/MTC_PRO_inline.webp);"></div>

          <div class="login-wrap p-4 p-md-5">
            <div class="setup-title">Registro inicial del superadmin</div>
            <div class="setup-subtitle">Completa los datos para inicializar el sistema.</div>

            <?php if ($setupError !== ''): ?>
              <div class="alert alert-danger py-2 mb-3" role="alert"><?php echo htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div id="setup-feedback" class="alert alert-danger py-2 mb-3 d-none" role="alert"></div>

            <form id="form-registro-inicial" action="registro_inicial.php" method="post" autocomplete="off" novalidate>
              <div class="form-group">
                <label for="usuario">Usuario (DNI/CE)</label>
                <input id="usuario" type="text" name="usuario" class="form-control" maxlength="11" pattern="\d{8,11}" required value="<?php echo htmlspecialchars($usuarioForm, ENT_QUOTES, 'UTF-8'); ?>">
              </div>

              <div class="form-group">
                <label for="nombres">Nombres</label>
                <input id="nombres" type="text" name="nombres" class="form-control" required value="<?php echo htmlspecialchars($nombresForm, ENT_QUOTES, 'UTF-8'); ?>">
              </div>

              <div class="form-group">
                <label for="apellidos">Apellidos</label>
                <input id="apellidos" type="text" name="apellidos" class="form-control" required value="<?php echo htmlspecialchars($apellidosForm, ENT_QUOTES, 'UTF-8'); ?>">
              </div>

              <div class="form-group">
                <label for="clave">Contrasena</label>
                <input id="clave" type="password" name="clave" class="form-control" minlength="8" required>
              </div>

              <div class="form-group">
                <label for="clave_confirmar">Confirmar contrasena</label>
                <input id="clave_confirmar" type="password" name="clave_confirmar" class="form-control" minlength="8" required>
              </div>

              <?php if ($installKeyEnabled): ?>
                <div class="form-group">
                  <label for="clave_instalacion">Clave de instalacion</label>
                  <input id="clave_instalacion" type="password" name="clave_instalacion" class="form-control" required>
                </div>
              <?php endif; ?>

              <div class="form-group mb-0">
                <button id="btn-registro-inicial" type="submit" class="form-control btn btn-primary rounded submit px-3">
                  Crear superadmin inicial
                </button>
              </div>
            </form>

            <p class="text-center text-muted mt-3 mb-0 small">
              &copy; <?php echo date('Y'); ?> - LuigiSistemas - Todos los derechos reservados.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var form = document.getElementById('form-registro-inicial');
    var feedback = document.getElementById('setup-feedback');
    var btn = document.getElementById('btn-registro-inicial');
    if (!form || !feedback || !btn) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      if (form.checkValidity && !form.checkValidity()) {
        form.reportValidity();
        return;
      }

      feedback.classList.add('d-none');
      feedback.textContent = '';
      btn.disabled = true;

      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('action') || 'registro_inicial.php', true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        btn.disabled = false;

        var response = null;
        try {
          response = JSON.parse(xhr.responseText);
        } catch (e) {
          response = null;
        }

        if (!response || response.ok !== true) {
          feedback.textContent = (response && response.error) ? response.error : 'No se pudo completar el registro inicial.';
          feedback.classList.remove('d-none');
          return;
        }

        window.location.href = response.redirect || 'login.php';
      };

      xhr.send(new FormData(form));
    });
  })();
</script>
</body>
</html>
