<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pag_menu.php';

$u = currentUser();
$nombreCompleto = trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
if ($nombreCompleto === '') {
    $nombreCompleto = $u['usuario'] ?? 'Usuario';
}

$currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$currentSlug = trim((string) ($_GET['slug'] ?? ''));
$isInicioActive = ($currentScript === 'inicio.php');
$menuDinamico = pag_render_sidebar_menu($currentSlug);
?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <a href="inicio.php" class="brand-link">
    <img src="assets/img/circular genesis_ico.ico" alt="Logo" class="brand-image img-circle elevation-3" style="opacity:.8">
    <span class="brand-text font-weight-light">LSISTEMAS</span>
  </a>

  <div class="sidebar">
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
      </div>
      <div class="info">
        <a href="#" class="d-block"><?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></a>
      </div>
    </div>

    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <li class="nav-header">NAVEGACION</li>
        <li class="nav-item">
          <a href="inicio.php" class="nav-link<?php echo $isInicioActive ? ' active' : ''; ?>">
            <i class="nav-icon fas fa-home"></i>
            <p>Inicio</p>
          </a>
        </li>
        <?php echo $menuDinamico; ?>
        <li class="nav-item">
          <a href="#" class="nav-link" onclick="event.preventDefault(); var f = document.getElementById('form-logout'); if (f) { f.submit(); }">
            <i class="nav-icon fas fa-sign-out-alt"></i>
            <p>Cerrar sesion</p>
          </a>
        </li>
      </ul>
    </nav>
  </div>
</aside>
