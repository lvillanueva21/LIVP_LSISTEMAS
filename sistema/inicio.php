<?php
require __DIR__ . '/includes/auth.php';
requireAuth();

$u = currentUser();
$nombreCompleto = trim(($u['nombres'] ?? '') . ' ' . ($u['apellidos'] ?? ''));
if ($nombreCompleto === '') {
    $nombreCompleto = $u['usuario'] ?? 'Usuario';
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Inicio</h1>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary card-outline">
        <div class="card-header">
          <h3 class="card-title">Panel inicial</h3>
        </div>
        <div class="card-body">
          <p class="mb-2">Bienvenido, <strong><?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
          <p class="mb-2">Usuario: <strong><?php echo htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></p>
          <p class="mb-0">Rol activo: <strong><?php echo htmlspecialchars($u['rol_activo'] ?? 'Sin rol', ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
