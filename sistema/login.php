<?php

date_default_timezone_set('America/Lima');
$hour = (int) date('G');
if ($hour < 12) {
    $saludo = 'Buenos días';
} elseif ($hour < 19) {
    $saludo = 'Buenas tardes';
} else {
    $saludo = 'Buenas noches';
}

$mensajes = [
    'Tu compromiso hace la diferencia cada día.',
    'Gracias por contribuir con calidad y eficiencia.',
    'Cada acción cuenta para lograr resultados.',
    'Hoy es un buen día para seguir avanzando.',
    'La seguridad comienza contigo.'
];
$mensajeBienvenida = $saludo . '. ' . $mensajes[array_rand($mensajes)];
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
      cursor: zoom-in;
    }

    .login-cover-carousel .carousel-control-prev,
    .login-cover-carousel .carousel-control-next {
      width: 12%;
    }

    .login-cover-carousel .carousel-control-prev-icon,
    .login-cover-carousel .carousel-control-next-icon {
      background-color: rgba(0, 0, 0, 0.35);
      border-radius: 999px;
      background-size: 55% 55%;
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
          <div id="loginCoverCarousel" class="carousel slide login-cover-carousel" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
              <button type="button" data-bs-target="#loginCoverCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Imagen 1"></button>
              <button type="button" data-bs-target="#loginCoverCarousel" data-bs-slide-to="1" aria-label="Imagen 2"></button>
            </div>
            <div class="carousel-inner">
              <div class="carousel-item active">
                <img
                  src="assets/img/card_01.webp"
                  class="d-block w-100 js-cover-image"
                  alt="Portada card 01"
                  data-bs-toggle="modal"
                  data-bs-target="#coverImageModal"
                  data-full-src="assets/img/card_01.webp"
                  data-download-name="card_01.webp"
                >
              </div>
              <div class="carousel-item">
                <img
                  src="assets/img/card_02.webp"
                  class="d-block w-100 js-cover-image"
                  alt="Portada card 02"
                  data-bs-toggle="modal"
                  data-bs-target="#coverImageModal"
                  data-full-src="assets/img/card_02.webp"
                  data-download-name="card_02.webp"
                >
              </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#loginCoverCarousel" data-bs-slide="prev" aria-label="Anterior">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#loginCoverCarousel" data-bs-slide="next" aria-label="Siguiente">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
            </button>
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

            <div id="login-message" class="alert alert-info py-2 mb-3 d-none" role="alert">
              Esta pantalla es solo de presentación visual. La autenticación aún no está implementada.
            </div>

            <form id="form-login" action="login.php" method="post" class="signin-form" autocomplete="off" novalidate>
              <div class="form-group mt-3">
                <label for="usuario" class="form-label-fixed">
                  Usuario (DNI/CE)
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="Tu usuario es tu documento de identidad."
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
                >
              </div>

              <div class="form-group">
                <label for="password-field" class="form-label-fixed">
                  Contraseña
                  <span
                    class="fa fa-info-circle info-icon"
                    tabindex="0"
                    role="button"
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="No compartas tu contraseña."
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
                <button class="form-control btn btn-primary rounded submit px-3" type="submit">
                  Ingresar
                </button>
              </div>

              <div class="form-group d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-2 small">
                <a
                  class="text-success text-decoration-none d-inline-flex align-items-center"
                  href="https://wa.me/51964881841?text=Hola%2C%20necesito%20apoyo%20del%20%C3%A1rea%20de%20Soporte."
                  target="_blank"
                  rel="noopener noreferrer"
                  title="Contactar a soporte por WhatsApp"
                >
                  <span class="fa fa-whatsapp me-1" aria-hidden="true"></span>
                  Contactar a soporte
                </a>

                <a
                  class="text-secondary text-decoration-none d-inline-flex align-items-center"
                  href="https://wa.me/51964881841?text=Hola%2C%20quiero%20recuperar%20mi%20contrase%C3%B1a%2C%20mi%20DNI%20y%2Fo%20nombre%20completo%20es%3A"
                  target="_blank"
                  rel="noopener noreferrer"
                  title="Solicitar recuperación de contraseña por WhatsApp"
                >
                  <span class="fa fa-unlock-alt me-1" aria-hidden="true"></span>
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
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body text-center">
        <img id="coverModalImage" src="" class="img-fluid rounded" alt="Vista ampliada">
      </div>
      <div class="modal-footer">
        <a id="coverModalDownload" class="btn btn-primary" href="#" download>Descargar imagen</a>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    var triggers = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    triggers.forEach(function (el) {
      new bootstrap.Tooltip(el, { trigger: 'hover focus' });
    });
  })();

  (function () {
    var form = document.getElementById('form-login');
    var message = document.getElementById('login-message');
    if (!form || !message) return;

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      message.classList.remove('d-none');
    });
  })();
</script>
</body>
</html>
