<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/instalacion.php';

$isAjaxSetup = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!lsis_is_initialized()) {
    if ($isAjaxSetup) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => false, 'redirect' => 'registro_inicial.php']);
        exit;
    }
    header('Location: registro_inicial.php');
    exit;
}

if (isAuthenticated()) {
    header('Location: inicio.php');
    exit;
}

$err = '';
$notice = '';
$usuarioForm = '';

if (isset($_GET['m']) && $_GET['m'] === 'sesion') {
    $notice = 'Inicia sesión para continuar.';
}
if (isset($_GET['m']) && $_GET['m'] === 'logout') {
    $notice = 'Sesión cerrada correctamente.';
}
if (isset($_GET['m']) && $_GET['m'] === 'acceso_actualizado') {
    $notice = 'Tu sesión se cerró por actualización de roles o permisos. Inicia sesión nuevamente.';
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$csrfLoginToken = lsis_csrf_get_token('login_form');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuarioForm = trim((string)($_POST['usuario'] ?? ''));
    $clave = (string)($_POST['clave'] ?? '');
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    $errorCode = '';
    $retryAfterSeconds = null;
    $retryAfterMinutes = null;

    if (!lsis_csrf_validate_token('login_form', $csrfToken)) {
        $err = 'No se pudo iniciar sesión.';
        $errorCode = 'csrf';
        lsis_security_record_attempt('login', $usuarioForm, 0, 'csrf');
    } else {
        $loginDepsMeta = [];
        if (!lsis_security_login_dependencies_ok($loginDepsMeta)) {
            $err = 'No se pudo iniciar sesión.';
            $errorCode = 'seguridad_incompleta';
            lsis_security_record_attempt('login', $usuarioForm, 0, 'seguridad_incompleta');
        } else {
            $loginBlockMeta = [];
            if (lsis_security_is_login_blocked($usuarioForm, $loginBlockMeta)) {
                if (!empty($loginBlockMeta['fail_closed'])) {
                    $err = 'No se pudo iniciar sesión.';
                    $errorCode = 'seguridad_incompleta';
                    lsis_security_record_attempt('login', $usuarioForm, 0, 'seguridad_incompleta');
                } else {
                    $err = 'Usuario o contrasena incorrectos.';
                    $errorCode = 'bloqueado';

                    if (!empty($loginBlockMeta['blocked_until'])) {
                        $blockedUntilTs = strtotime((string) $loginBlockMeta['blocked_until']);
                        if ($blockedUntilTs !== false) {
                            $secondsLeft = $blockedUntilTs - time();
                            if ($secondsLeft > 0) {
                                $retryAfterSeconds = $secondsLeft;
                                $retryAfterMinutes = (int) ceil($secondsLeft / 60);
                            }
                        }
                    }

                    lsis_security_record_attempt('login', $usuarioForm, 0, 'bloqueado');
                }
            } else {
                try {
                    $r = login($usuarioForm, $clave);
                } catch (Throwable $e) {
                    error_log('[login] Error interno: ' . $e->getMessage());
                    $r = ['ok' => false, 'error' => 'No se pudo iniciar sesión.', 'code' => 'auth_error'];
                }
                $errorCode = (string) ($r['code'] ?? 'error_login');

                if ($r['ok']) {
                    lsis_security_clear_login_block_state($usuarioForm);

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=UTF-8');
                        echo json_encode([
                            'ok' => true,
                            'redirect' => 'inicio.php',
                            'code' => (string) ($r['code'] ?? 'ok'),
                            'csrf_token_nuevo' => lsis_csrf_get_token('login_form'),
                        ]);
                        exit;
                    }

                    header('Location: inicio.php');
                    exit;
                }

                if ($errorCode === 'credenciales_invalidas') {
                    $failureMeta = [];
                    $okFailureUpdate = lsis_security_register_credential_failure($usuarioForm, $failureMeta);
                    if (!$okFailureUpdate && !empty($failureMeta['fail_closed'])) {
                        $err = 'No se pudo iniciar sesión.';
                        $errorCode = 'seguridad_incompleta';
                    }
                }

                if ($err === '') {
                    $err = $r['error'] ?? 'No se pudo iniciar sesión.';
                }
            }
        }
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => false,
            'error' => $err,
            'code' => $errorCode !== '' ? $errorCode : 'error_login',
            'csrf_token_nuevo' => lsis_csrf_get_token('login_form'),
            'retry_after_seconds' => $retryAfterSeconds,
            'retry_after_minutes' => $retryAfterMinutes,
        ]);
        exit;
    }
}

$csrfLoginToken = lsis_csrf_get_token('login_form');

date_default_timezone_set('America/Lima');
$hour = (int) date('G');
if ($hour < 12) {
    $saludo = '¡Buenos días!';
} elseif ($hour < 19) {
    $saludo = '¡Buenas tardes!';
} else {
    $saludo = '¡Buenas noches!';
}

$emojis = ['🌟', '🚀', '💡', '✨', '👋', '🧠', '⚡', '🔐', '📌', '✅'];
$mensajes = [
    '{saludo} {emoji} Tu compromiso hace la diferencia cada día.',
    '{saludo} {emoji} Sigamos trabajando por un mejor servicio.',
    'Bienvenid@ {emoji} Gracias por contribuir con la calidad y eficiencia del sistema.',
    '{saludo} {emoji} Cada acción cuenta para lograr resultados.',
    '¡Excelente jornada por delante! {emoji}',
    '{emoji} Recuerda: la precisión también es parte del progreso.',
    '{saludo} {emoji} Qué gusto verte de nuevo.',
    '¡Hola! {emoji} Esperamos que hoy tengas un gran día.',
    '{saludo} {emoji} Siempre es bueno verte por aquí.',
    'Bienvenid@ de nuevo {emoji} ¡Vamos con todo hoy!',
    '{emoji} Gracias por seguir confiando en nosotros.',
    '{saludo} {emoji} Tu trabajo impulsa grandes resultados.',
    '{saludo} {emoji} Cada día es una oportunidad para mejorar.',
    '{emoji} Hoy es un buen día para avanzar un paso más.',
    '{saludo} {emoji} El éxito comienza con un inicio de sesión.',
    '{emoji} ¡Activa tu potencial y haz que cuente!',
    '{saludo} {emoji} Grandes cosas comienzan con pequeños clics.',
    '{emoji} Inspira, mejora, impacta. ¡Vamos con todo!',
    '{emoji} Recuerda: la seguridad comienza contigo.',
    '{saludo} {emoji} Verifica tus credenciales antes de continuar.',
    '{saludo} 👋 ¿Listo para continuar?',
    '¡Hola! {emoji} Gracias por usar el sistema.',
    '¡Qué gusto verte por aquí! {emoji}',
    '{saludo} {emoji} Hoy es un buen día para avanzar.'
];
$plantilla = $mensajes[array_rand($mensajes)];
$mensajeBienvenida = str_replace(
    ['{saludo}', '{emoji}'],
    [$saludo, $emojis[array_rand($emojis)]],
    $plantilla
);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | Sistema</title>
  <link rel="icon" type="image/x-icon" href="assets/img/circular genesis_ico.ico">

  <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">

  <style>
    .form-group { position: relative; margin-bottom: 1.25rem; }
    .form-control-placeholder { display: none !important; }

    .form-label-fixed {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-weight: 600;
      margin-bottom: .4rem;
    }

    .info-icon {
      cursor: help;
      font-size: .95rem;
      line-height: 1;
      color: #6c757d;
    }

    .info-icon:hover,
    .info-icon:focus { color: #495057; }

    .field-icon {
      position: absolute;
      top: 50%;
      right: .75rem;
      transform: translateY(-50%);
      z-index: 2;
      cursor: pointer;
      user-select: none;
      color: #6c757d;
    }

    #password-field { padding-right: 2.25rem; }

    .login-cover-carousel .carousel-item {
      height: 220px;
    }

    .login-cover-carousel .carousel-item img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      cursor: grab;
      -webkit-user-drag: none;
      user-select: none;
    }

    .login-cover-carousel .carousel-inner {
      cursor: grab;
    }

    .login-cover-carousel.is-dragging .carousel-inner,
    .login-cover-carousel.is-dragging .carousel-item img {
      cursor: grabbing;
    }

    .text-decoration-none:hover { text-decoration: underline !important; }
  </style>
</head>
<body>
<section class="ftco-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-7 text-center mb-5">
        <h2 class="heading-section"><?php echo htmlspecialchars($mensajeBienvenida, ENT_QUOTES, 'UTF-8'); ?></h2>
      </div>
    </div>

    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-5">
        <div class="wrap">
          <div id="loginCoverCarousel" class="carousel slide login-cover-carousel" data-ride="carousel" data-interval="5000">
            <div class="carousel-inner">
              <div class="carousel-item active">
                <img
                  src="assets/img/card_01.webp"
                  class="d-block w-100 js-cover-image"
                  alt="Portada card 01"
                  draggable="false"
                  data-toggle="modal"
                  data-target="#coverImageModal"
                  data-full-src="assets/img/card_01.webp"
                  data-download-name="card_01.webp"
                >
              </div>
              <div class="carousel-item">
                <img
                  src="assets/img/card_02.webp"
                  class="d-block w-100 js-cover-image"
                  alt="Portada card 02"
                  draggable="false"
                  data-toggle="modal"
                  data-target="#coverImageModal"
                  data-full-src="assets/img/card_02.webp"
                  data-download-name="card_02.webp"
                >
              </div>
            </div>
          </div>

          <div class="login-wrap p-4 p-md-5">
            <div class="d-flex align-items-center mb-2">
              <div class="w-100">
                <h4 class="mb-0">Iniciar sesión</h4>
              </div>
              <div class="w-100">
                <p class="social-media d-flex justify-content-end m-0">
                  <a
                    href="https://wa.me/51964881841"
                    class="social-icon d-flex align-items-center justify-content-center"
                    title="WhatsApp de soporte"
                    target="_blank"
                    rel="noopener noreferrer"
                  ><span class="fa fa-whatsapp"></span></a>
                  <a
                    href="https://sso.mtc.gob.pe/"
                    class="social-icon d-flex align-items-center justify-content-center"
                    title="Sistema MTC"
                    target="_blank"
                    rel="noopener noreferrer"
                  ><span class="fa fa-car"></span></a>
                </p>
              </div>
            </div>

            <?php if ($notice !== ''): ?>
              <div class="alert alert-info py-2 mb-3" role="alert"><?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($err !== ''): ?>
              <div class="alert alert-danger py-2 mb-3" role="alert"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div id="login-feedback" class="alert alert-danger py-2 mb-3 d-none" role="alert"></div>

            <form id="form-login" action="login.php" method="post" class="signin-form" autocomplete="off" novalidate>
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfLoginToken, ENT_QUOTES, 'UTF-8'); ?>">
              <div class="form-group mt-3">
                <label for="usuario" class="form-label-fixed">
                  Usuario (DNI/CE)
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-toggle="tooltip"
                    data-placement="right"
                    title="Tu usuario es tu documento de identidad."
                    aria-label="Más información sobre el campo Usuario"
                  ></span>
                </label>
                <input
                  id="usuario"
                  type="text"
                  name="usuario"
                  class="form-control"
                  maxlength="11"
                  pattern="\d{8,11}"
                  autocomplete="username"
                  required
                  autofocus
                  value="<?php echo htmlspecialchars($usuarioForm, ENT_QUOTES, 'UTF-8'); ?>"
                >
              </div>

              <div class="form-group">
                <label for="password-field" class="form-label-fixed">
                  Contraseña
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-toggle="tooltip"
                    data-placement="right"
                    title="No compartas tu contraseña."
                    aria-label="Más información sobre el campo Contraseña"
                  ></span>
                </label>

                <div class="position-relative">
                  <input
                    id="password-field"
                    type="password"
                    name="clave"
                    class="form-control"
                    minlength="6"
                    autocomplete="current-password"
                    required
                  >
                  <span
                    toggle="#password-field"
                    class="fa fa-fw fa-eye field-icon toggle-password"
                    title="Mostrar u ocultar"
                  ></span>
                </div>
              </div>

              <div class="form-group">
                <button id="btn-login" class="form-control btn btn-primary rounded submit px-3" type="submit">
                  Ingresar
                </button>
              </div>

              <div class="form-group d-flex flex-column flex-md-row justify-content-between align-items-center mt-2 small">
                <a
                  class="text-success text-decoration-none d-inline-flex align-items-center"
                  href="https://wa.me/51964881841?text=Hola%2C%20necesito%20apoyo%20del%20%C3%A1rea%20de%20Soporte."
                  target="_blank"
                  rel="noopener noreferrer"
                  title="Contactar a soporte por WhatsApp"
                >
                  <span class="fa fa-whatsapp mr-1" aria-hidden="true"></span>
                  Contactar a soporte
                </a>

                <a
                  class="text-secondary text-decoration-none d-inline-flex align-items-center"
                  href="https://wa.me/51964881841?text=Hola%2C%20quiero%20recuperar%20mi%20contrase%C3%B1a%2C%20mi%20DNI%20y%2Fo%20nombre%20completo%20es%3A"
                  target="_blank"
                  rel="noopener noreferrer"
                  title="Solicitar recuperación de contraseña por WhatsApp"
                >
                  <span class="fa fa-unlock-alt mr-1" aria-hidden="true"></span>
                  Recuperar contraseña
                </a>
              </div>
            </form>

            <p class="text-center text-muted mt-3 mb-0 small">
              © <?php echo date('Y'); ?> - LuigiSistemas - Todos los derechos reservados.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="modal fade" id="coverImageModal" tabindex="-1" aria-labelledby="coverImageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coverImageModalLabel">Vista de la imagen</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img id="coverModalImage" src="" class="img-fluid rounded" alt="Vista ampliada">
      </div>
      <div class="modal-footer">
        <a id="coverModalDownload" class="btn btn-primary" href="#" download>Descargar imagen</a>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var carouselEl = document.getElementById('loginCoverCarousel');
    if (!carouselEl) return;

    var $carousel = window.jQuery ? window.jQuery(carouselEl) : null;
    if ($carousel) {
      $carousel.carousel({ interval: 5000, pause: false });
    }

    var coverImages = carouselEl.querySelectorAll('.js-cover-image');
    var startX = 0;
    var pointerDown = false;
    var dragMoved = false;
    var suppressClick = false;
    var dragThreshold = 35;

    carouselEl.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse' && event.button !== 0) return;

      pointerDown = true;
      dragMoved = false;
      startX = event.clientX;
      carouselEl.classList.add('is-dragging');
    });

    carouselEl.addEventListener('pointermove', function (event) {
      if (!pointerDown) return;
      if (Math.abs(event.clientX - startX) > 6) {
        dragMoved = true;
      }
    });

    function endDrag(event) {
      if (!pointerDown) return;

      var diffX = event.clientX - startX;
      pointerDown = false;
      carouselEl.classList.remove('is-dragging');

      if (Math.abs(diffX) >= dragThreshold && $carousel) {
        if (diffX < 0) {
          $carousel.carousel('next');
        } else {
          $carousel.carousel('prev');
        }
        suppressClick = true;
        setTimeout(function () {
          suppressClick = false;
        }, 0);
      }
    }

    carouselEl.addEventListener('pointerup', endDrag);
    carouselEl.addEventListener('pointercancel', endDrag);

    coverImages.forEach(function (image) {
      image.addEventListener('click', function (event) {
        if (suppressClick || dragMoved) {
          event.preventDefault();
          event.stopPropagation();
        }
      });
    });
  })();

  (function () {
    var coverImages = document.querySelectorAll('.js-cover-image');
    var modalImage = document.getElementById('coverModalImage');
    var modalDownload = document.getElementById('coverModalDownload');

    if (!coverImages.length || !modalImage || !modalDownload) return;

    coverImages.forEach(function (item) {
      item.addEventListener('click', function () {
        var src = this.getAttribute('data-full-src') || this.getAttribute('src');
        var downloadName = this.getAttribute('data-download-name') || 'imagen.webp';

        modalImage.setAttribute('src', src);
        modalDownload.setAttribute('href', src);
        modalDownload.setAttribute('download', downloadName);
      });
    });
  })();

  (function () {
    var toggler = document.querySelector('.toggle-password');
    if (!toggler) return;

    toggler.addEventListener('click', function () {
      var target = document.querySelector(this.getAttribute('toggle'));
      if (!target) return;

      var isPassword = target.getAttribute('type') === 'password';
      target.setAttribute('type', isPassword ? 'text' : 'password');
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });
  })();

  (function () {
    if (!window.jQuery) return;
    window.jQuery('[data-toggle="tooltip"]').tooltip({ trigger: 'hover focus' });
  })();

  (function () {
    var form = document.getElementById('form-login');
    var feedback = document.getElementById('login-feedback');
    var btn = document.getElementById('btn-login');
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
      xhr.open('POST', form.getAttribute('action') || 'login.php', true);
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
          if (response && response.csrf_token_nuevo) {
            var csrfInputFail = form.querySelector('input[name="csrf_token"]');
            if (csrfInputFail) csrfInputFail.value = response.csrf_token_nuevo;
          }
          if (response && response.redirect) {
            window.location.href = response.redirect;
            return;
          }
          var msg = (response && response.error) ? response.error : 'No se pudo iniciar sesión.';
          feedback.textContent = msg;
          feedback.classList.remove('d-none');
          return;
        }

        if (response && response.csrf_token_nuevo) {
          var csrfInputOk = form.querySelector('input[name="csrf_token"]');
          if (csrfInputOk) csrfInputOk.value = response.csrf_token_nuevo;
        }

        window.location.href = response.redirect || 'inicio.php';
      };

      xhr.send(new FormData(form));
    });
  })();
</script>
</body>
</html>
