<?php
if (!defined('PAG_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$rlsCsrfToken = lsis_csrf_get_token('roles_form');
$rlsCanCreate = pag_user_has_permission_code('roles.create');
$rlsCanEdit = pag_user_has_permission_code('roles.edit');
$rlsCanToggle = pag_user_has_permission_code('roles.toggle_state');
?>
<div
  id="rls-app"
  data-url-list="roles/api_list.php"
  data-url-create="roles/api_create.php"
  data-url-update="roles/api_update.php"
  data-url-toggle="roles/api_toggle_state.php"
  data-can-create="<?php echo $rlsCanCreate ? '1' : '0'; ?>"
  data-can-edit="<?php echo $rlsCanEdit ? '1' : '0'; ?>"
  data-can-toggle="<?php echo $rlsCanToggle ? '1' : '0'; ?>"
>
  <input type="hidden" id="rls-csrf-token" value="<?php echo htmlspecialchars($rlsCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="card card-primary card-outline">
    <div class="card-header">
      <h3 class="card-title">Roles</h3>
      <div class="card-tools">
        <?php if ($rlsCanCreate): ?>
          <button type="button" id="rls-btn-new" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i>Nuevo rol
          </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div id="rls-alert" class="alert d-none mb-3" role="alert"></div>

      <div class="row">
        <div class="col-md-4">
          <label for="rls-search">Buscar</label>
          <input type="text" class="form-control" id="rls-search" placeholder="Nombre o descripcion">
        </div>
        <div class="col-md-3">
          <label for="rls-estado-filter">Estado</label>
          <select class="form-control" id="rls-estado-filter">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="rls-per-page">Registros por pagina</label>
          <select class="form-control" id="rls-per-page">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="50">50</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="btn-group w-100">
            <button type="button" class="btn btn-default" id="rls-btn-search">
              <i class="fas fa-search"></i>
            </button>
            <button type="button" class="btn btn-default" id="rls-btn-refresh">
              <i class="fas fa-sync-alt"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm table-bordered table-hover">
          <thead>
            <tr>
              <th>Rol</th>
              <th>Descripcion</th>
              <th>Estado</th>
              <th>Usuarios activos asignados</th>
              <th>Actualizado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="rls-table-body">
            <tr>
              <td colspan="6" class="text-center text-muted">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted" id="rls-pagination-info">Mostrando 0 registros</div>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-default" id="rls-page-prev">Anterior</button>
          <button type="button" class="btn btn-default" id="rls-page-next">Siguiente</button>
        </div>
      </div>

      <p class="text-muted small mt-3 mb-0">
        Nota tecnica: la proteccion de Superadmin en V1 se basa en nombre y queda como deuda tecnica futura.
      </p>
    </div>
  </div>

  <div class="modal fade" id="rlsModalForm" tabindex="-1" role="dialog" aria-labelledby="rlsModalFormLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form id="rls-form-create-edit" autocomplete="off" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="rlsModalFormLabel">Rol</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="rls-id-rol" value="0">

            <div class="form-group">
              <label for="rls-nombre">Nombre</label>
              <input type="text" class="form-control" id="rls-nombre" maxlength="80">
            </div>
            <div class="form-group mb-0">
              <label for="rls-descripcion">Descripcion</label>
              <textarea class="form-control" id="rls-descripcion" rows="3" maxlength="255"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="rls-btn-save">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="rlsModalToggleEstado" tabindex="-1" role="dialog" aria-labelledby="rlsModalToggleEstadoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rlsModalToggleEstadoLabel">Confirmar cambio de estado</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="rls-toggle-id-rol" value="0">
          <input type="hidden" id="rls-toggle-estado-objetivo" value="0">
          <p class="mb-0" id="rls-toggle-message">Confirma esta operacion.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="rls-btn-confirm-toggle">Confirmar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/roles.js"></script>
