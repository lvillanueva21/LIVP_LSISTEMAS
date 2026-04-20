<?php
if (!defined('PAG_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$pglCsrfToken = lsis_csrf_get_token('paginas_logicas_form');
$pglCanCreate = pag_user_has_permission_code('paginas_logicas.create');
$pglCanEdit = pag_user_has_permission_code('paginas_logicas.edit');
$pglCanToggle = pag_user_has_permission_code('paginas_logicas.toggle_state');
?>
<div
  id="pgl-app"
  data-url-list="paginas_logicas/api_list.php"
  data-url-create="paginas_logicas/api_create.php"
  data-url-update="paginas_logicas/api_update.php"
  data-url-toggle="paginas_logicas/api_toggle_state.php"
  data-can-create="<?php echo $pglCanCreate ? '1' : '0'; ?>"
  data-can-edit="<?php echo $pglCanEdit ? '1' : '0'; ?>"
  data-can-toggle="<?php echo $pglCanToggle ? '1' : '0'; ?>"
>
  <input type="hidden" id="pgl-csrf-token" value="<?php echo htmlspecialchars($pglCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="card card-primary card-outline">
    <div class="card-header">
      <h3 class="card-title">Paginas logicas</h3>
      <div class="card-tools">
        <?php if ($pglCanCreate): ?>
          <button type="button" id="pgl-btn-new" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i>Nueva pagina
          </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div id="pgl-alert" class="alert d-none mb-3" role="alert"></div>

      <div class="row">
        <div class="col-md-4">
          <label for="pgl-search">Buscar</label>
          <input type="text" class="form-control" id="pgl-search" placeholder="Titulo o slug">
        </div>
        <div class="col-md-2">
          <label for="pgl-estado-filter">Estado</label>
          <select class="form-control" id="pgl-estado-filter">
            <option value="">Todos</option>
            <option value="1">Activas</option>
            <option value="0">Inactivas</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="pgl-tipo-filter">Tipo</label>
          <select class="form-control" id="pgl-tipo-filter">
            <option value="">Todos</option>
            <option value="fija">Fija</option>
            <option value="contenedor">Contenedor</option>
            <option value="real">Real</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="pgl-per-page">Registros por pagina</label>
          <select class="form-control" id="pgl-per-page">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="50">50</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="btn-group w-100">
            <button type="button" class="btn btn-default" id="pgl-btn-search">
              <i class="fas fa-search"></i>
            </button>
            <button type="button" class="btn btn-default" id="pgl-btn-refresh">
              <i class="fas fa-sync-alt"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm table-bordered table-hover">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Titulo menu</th>
              <th>Titulo pagina</th>
              <th>Slug</th>
              <th>Padre</th>
              <th>Permiso</th>
              <th>Modulo/section</th>
              <th>Visible menu</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="pgl-table-body">
            <tr>
              <td colspan="10" class="text-center text-muted">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted" id="pgl-pagination-info">Mostrando 0 registros</div>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-default" id="pgl-page-prev">Anterior</button>
          <button type="button" class="btn btn-default" id="pgl-page-next">Siguiente</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="pglModalForm" tabindex="-1" role="dialog" aria-labelledby="pglModalFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <form id="pgl-form-create-edit" autocomplete="off" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="pglModalFormLabel">Pagina logica</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="pgl-id-pagina" value="0">
            <input type="hidden" id="pgl-es-fija" value="0">

            <div class="row">
              <div class="col-md-4">
                <label for="pgl-tipo-pagina">Tipo de pagina</label>
                <select class="form-control" id="pgl-tipo-pagina">
                  <option value="real">Real cargable</option>
                  <option value="contenedor">Contenedor</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="pgl-slug-pagina">Slug</label>
                <input type="text" class="form-control" id="pgl-slug-pagina" maxlength="150">
                <small class="text-muted">Inmutable despues de crear.</small>
              </div>
              <div class="col-md-4">
                <label for="pgl-id-padre">Padre (nivel 1)</label>
                <select class="form-control" id="pgl-id-padre">
                  <option value="">Sin padre</option>
                </select>
              </div>
            </div>

            <div class="row mt-2">
              <div class="col-md-6">
                <label for="pgl-titulo-menu">Titulo menu</label>
                <input type="text" class="form-control" id="pgl-titulo-menu" maxlength="120">
              </div>
              <div class="col-md-6">
                <label for="pgl-titulo-pagina">Titulo pagina</label>
                <input type="text" class="form-control" id="pgl-titulo-pagina" maxlength="150">
              </div>
            </div>

            <div class="form-group mt-2">
              <label for="pgl-descripcion-pagina">Descripcion pagina</label>
              <textarea class="form-control" id="pgl-descripcion-pagina" rows="2" maxlength="255"></textarea>
            </div>

            <div class="row">
              <div class="col-md-4">
                <label for="pgl-visible-menu">Visible en sidebar</label>
                <select class="form-control" id="pgl-visible-menu">
                  <option value="1">Si</option>
                  <option value="0">No</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="pgl-estado">Estado</label>
                <select class="form-control" id="pgl-estado">
                  <option value="0">Borrador / Inactiva</option>
                  <option value="1">Activa</option>
                </select>
              </div>
              <div class="col-md-4">
                <label for="pgl-orden-menu">Orden menu</label>
                <input type="number" class="form-control" id="pgl-orden-menu" min="0" step="1" value="0">
              </div>
            </div>

            <div class="row mt-2">
              <div class="col-md-6">
                <label for="pgl-icono">Icono (Font Awesome)</label>
                <input type="text" class="form-control" id="pgl-icono" maxlength="120" placeholder="fas fa-folder">
              </div>
              <div class="col-md-6">
                <label for="pgl-id-permiso">Permiso base requerido</label>
                <select class="form-control" id="pgl-id-permiso">
                  <option value="">Auto por slug (.view)</option>
                </select>
              </div>
            </div>

            <div class="row mt-2">
              <div class="col-md-6">
                <label for="pgl-modulo-codigo">Modulo</label>
                <select class="form-control" id="pgl-modulo-codigo">
                  <option value="">Seleccionar modulo</option>
                </select>
              </div>
              <div class="col-md-6">
                <label for="pgl-archivo-section">Section</label>
                <select class="form-control" id="pgl-archivo-section">
                  <option value="">Seleccionar section</option>
                </select>
              </div>
            </div>

            <div class="mt-2">
              <small class="text-muted" id="pgl-form-note">Para pagina real activa, modulo y section son obligatorios.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="pgl-btn-save">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="pglModalToggleEstado" tabindex="-1" role="dialog" aria-labelledby="pglModalToggleEstadoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="pglModalToggleEstadoLabel">Confirmar cambio de estado</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="pgl-toggle-id-pagina" value="0">
          <input type="hidden" id="pgl-toggle-estado-objetivo" value="0">
          <p class="mb-0" id="pgl-toggle-message">Confirma esta operacion.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="pgl-btn-confirm-toggle">Confirmar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/paginas_logicas.js"></script>
