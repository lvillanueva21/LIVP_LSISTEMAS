<?php
require __DIR__ . '/includes/auth.php';
requireAuth();
require __DIR__ . '/includes/pag_loader.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: inicio.php');
    exit;
}

$pageContext = pag_loader_get_page_context($slug);
$isOk = !empty($pageContext['ok']);

$tituloPagina = 'Pagina';
$descripcionPagina = '';
if ($isOk) {
    $tituloPagina = (string) ($pageContext['page']['titulo_pagina'] ?? 'Pagina');
    $descripcionPagina = (string) ($pageContext['page']['descripcion_pagina'] ?? '');
} else {
    $tituloPagina = 'Acceso a pagina';
    $descripcionPagina = (string) ($pageContext['message'] ?? '');
}

if (!$isOk) {
    $code = (string) ($pageContext['code'] ?? '');
    if ($code === 'acceso_denegado') {
        http_response_code(403);
    } elseif ($code === 'pagina_no_encontrada' || $code === 'slug_invalido') {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><?php echo htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>
      </div>
      <?php if ($descripcionPagina !== ''): ?>
        <div class="row">
          <div class="col-sm-12">
            <p class="text-muted mb-0"><?php echo htmlspecialchars($descripcionPagina, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if (!$isOk): ?>
        <div class="card card-danger card-outline">
          <div class="card-header">
            <h3 class="card-title">No se pudo cargar la pagina</h3>
          </div>
          <div class="card-body">
            <p class="mb-3"><?php echo htmlspecialchars((string) ($pageContext['message'] ?? 'Ocurrio un error.'), ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="inicio.php" class="btn btn-secondary btn-sm">Volver a inicio</a>
          </div>
        </div>
      <?php else: ?>
        <?php
        if (!defined('PAG_MODULE_CONTEXT')) {
            define('PAG_MODULE_CONTEXT', true);
        }
        $pag_pagina_actual = $pageContext['page'];
        include $pageContext['module_file'];
        ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
