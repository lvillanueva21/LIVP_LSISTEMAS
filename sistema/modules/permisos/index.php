<?php
if (!defined('PAG_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$prmCsrfToken = lsis_csrf_get_token('permisos_form');
$prmCanAssign = pag_user_has_permission_code('permisos.assign');
?>
<div
  id="prm-app"
  data-url-get-matrix="permisos/api_get_matrix.php"
  data-url-save-matrix="permisos/api_save_matrix.php"
  data-can-assign="<?php echo $prmCanAssign ? '1' : '0'; ?>"
>
  <input type="hidden" id="prm-csrf-token" value="<?php echo htmlspecialchars($prmCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="card card-primary card-outline">
    <div class="card-header">
      <h3 class="card-title">Permisos</h3>
      <div class="card-tools">
        <button type="button" class="btn btn-default btn-sm" id="prm-btn-refresh">
          <i class="fas fa-sync-alt mr-1"></i>Actualizar
        </button>
        <?php if ($prmCanAssign): ?>
          <button type="button" class="btn btn-primary btn-sm" id="prm-btn-save">
            <i class="fas fa-save mr-1"></i>Guardar matriz
          </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div id="prm-alert" class="alert d-none mb-3" role="alert"></div>

      <div class="row">
        <div class="col-md-6">
          <label for="prm-role-select">Rol</label>
          <select class="form-control" id="prm-role-select"></select>
          <small class="text-muted" id="prm-role-meta">-</small>
        </div>
        <div class="col-md-6">
          <label>Catalogo de permisos activos</label>
          <p class="form-control-plaintext mb-0" id="prm-count-info">0 permisos</p>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm table-bordered table-hover">
          <thead>
            <tr>
              <th style="width:60px;">Asignado</th>
              <th>Codigo</th>
              <th>Nombre</th>
              <th>Descripcion</th>
            </tr>
          </thead>
          <tbody id="prm-table-body">
            <tr>
              <td colspan="4" class="text-center text-muted">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="text-muted small mb-0">
        En V1 la matriz se guarda en lote por rol. Se permite administrar permisos tambien para roles inactivos.
      </p>
    </div>
  </div>
</div>

<script src="assets/js/permisos.js"></script>
