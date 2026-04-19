<?php
require_once __DIR__ . '/auth.php';

$u = currentUser();
$nombreCompleto = trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
if ($nombreCompleto === '') {
    $nombreCompleto = $u['usuario'] ?? 'Usuario';
}
$rolActivo = $u['rol_activo'] ?? 'Sin rol';
$csrfLogoutToken = lsis_csrf_get_token('logout_form');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>LSISTEMAS</title>
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <link rel="icon" type="image/x-icon" href="assets/img/circular genesis_ico.ico">
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">
  <nav class="main-header navbar navbar-expand navbar-dark">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="inicio.php" class="nav-link">Inicio</a>
      </li>
    </ul>

    <ul class="navbar-nav ml-auto">
      <li class="nav-item d-none d-md-block">
        <span class="badge badge-primary mr-2"><?php echo htmlspecialchars($rolActivo, ENT_QUOTES, 'UTF-8'); ?></span>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#" title="Usuario">
          <i class="far fa-user"></i>
          <span class="d-none d-sm-inline"><?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-danger" href="#" title="Salir" onclick="event.preventDefault(); var f = document.getElementById('form-logout'); if (f) { f.submit(); }">
          <i class="fas fa-sign-out-alt"></i>
        </a>
      </li>
    </ul>
  </nav>

  <form id="form-logout" method="post" action="logout.php" class="d-none">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfLogoutToken, ENT_QUOTES, 'UTF-8'); ?>">
  </form>

  <?php include __DIR__ . '/sidebar.php'; ?>
