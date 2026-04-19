<?php
if (!defined('PAG_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$usrCsrfToken = lsis_csrf_get_token('usuarios_form');
$usrCanCreate = pag_user_has_permission_code('usuarios.create');
$usrCanEdit = pag_user_has_permission_code('usuarios.edit');
$usrCanToggle = pag_user_has_permission_code('usuarios.toggle_state');
$usrCanResetPassword = pag_user_has_permission_code('usuarios.reset_password');
?>
<div
  id="usr-app"
  data-url-list="usuarios/api_list.php"
  data-url-create="usuarios/api_create.php"
  data-url-update="usuarios/api_update.php"
  data-url-toggle="usuarios/api_toggle_state.php"
  data-url-reset-password="usuarios/api_reset_password.php"
  data-can-create="<?php echo $usrCanCreate ? '1' : '0'; ?>"
  data-can-edit="<?php echo $usrCanEdit ? '1' : '0'; ?>"
  data-can-toggle="<?php echo $usrCanToggle ? '1' : '0'; ?>"
  data-can-reset-password="<?php echo $usrCanResetPassword ? '1' : '0'; ?>"
>
  <input type="hidden" id="usr-csrf-token" value="<?php echo htmlspecialchars($usrCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="card card-primary card-outline">
    <div class="card-header">
      <h3 class="card-title">Usuarios</h3>
      <div class="card-tools">
        <?php if ($usrCanCreate): ?>
          <button type="button" id="usr-btn-new" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus mr-1"></i>Nuevo usuario
          </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div id="usr-alert" class="alert d-none mb-3" role="alert"></div>

      <div class="row">
        <div class="col-md-4">
          <label for="usr-search">Buscar</label>
          <input type="text" class="form-control" id="usr-search" placeholder="Usuario, nombres o apellidos">
        </div>
        <div class="col-md-3">
          <label for="usr-estado-filter">Estado</label>
          <select class="form-control" id="usr-estado-filter">
            <option value="">Todos</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="usr-per-page">Registros por pagina</label>
          <select class="form-control" id="usr-per-page">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="50">50</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="btn-group w-100">
            <button type="button" class="btn btn-default" id="usr-btn-search">
              <i class="fas fa-search"></i>
            </button>
            <button type="button" class="btn btn-default" id="usr-btn-refresh">
              <i class="fas fa-sync-alt"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-sm table-bordered table-hover">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Nombres</th>
              <th>Apellidos</th>
              <th>Estado</th>
              <th>Roles activos</th>
              <th>Ultimo login</th>
              <th>Ultima IP</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="usr-table-body">
            <tr>
              <td colspan="8" class="text-center text-muted">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted" id="usr-pagination-info">Mostrando 0 registros</div>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-default" id="usr-page-prev">Anterior</button>
          <button type="button" class="btn btn-default" id="usr-page-next">Siguiente</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="usrModalForm" tabindex="-1" role="dialog" aria-labelledby="usrModalFormLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <form id="usr-form-create-edit" autocomplete="off" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="usrModalFormLabel">Usuario</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="usr-id-usuario" value="0">

            <div class="row">
              <div class="col-md-4">
                <label for="usr-usuario">Usuario (DNI/CE)</label>
                <input type="text" class="form-control" id="usr-usuario" maxlength="11">
                <small class="text-muted">Identificador de acceso estable.</small>
              </div>
              <div class="col-md-4">
                <label for="usr-nombres">Nombres</label>
                <input type="text" class="form-control" id="usr-nombres" maxlength="100">
              </div>
              <div class="col-md-4">
                <label for="usr-apellidos">Apellidos</label>
                <input type="text" class="form-control" id="usr-apellidos" maxlength="100">
              </div>
            </div>

            <div class="row mt-2" id="usr-password-create-fields">
              <div class="col-md-6">
                <label for="usr-clave">Contrasena</label>
                <input type="password" class="form-control" id="usr-clave" maxlength="72">
              </div>
              <div class="col-md-6">
                <label for="usr-clave-confirmar">Confirmar contrasena</label>
                <input type="password" class="form-control" id="usr-clave-confirmar" maxlength="72">
              </div>
            </div>

            <div class="mt-3">
              <label class="mb-2">Roles activos</label>
              <div id="usr-roles-container" class="border rounded p-2" style="max-height:220px; overflow:auto;">
                <div class="text-muted small">Sin roles</div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="usr-btn-save">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="usrModalResetPassword" tabindex="-1" role="dialog" aria-labelledby="usrModalResetPasswordLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <form id="usr-form-reset-password" autocomplete="off" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="usrModalResetPasswordLabel">Resetear contrasena</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="usr-reset-id-usuario" value="0">
            <p class="mb-2">Usuario: <strong id="usr-reset-usuario">-</strong></p>
            <div class="form-group">
              <label for="usr-reset-clave">Nueva contrasena</label>
              <input type="password" class="form-control" id="usr-reset-clave" maxlength="72">
            </div>
            <div class="form-group mb-0">
              <label for="usr-reset-clave-confirmar">Confirmar contrasena</label>
              <input type="password" class="form-control" id="usr-reset-clave-confirmar" maxlength="72">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="usr-btn-reset-password">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="usrModalToggleEstado" tabindex="-1" role="dialog" aria-labelledby="usrModalToggleEstadoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="usrModalToggleEstadoLabel">Confirmar cambio de estado</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="usr-toggle-id-usuario" value="0">
          <input type="hidden" id="usr-toggle-estado-objetivo" value="0">
          <p class="mb-0" id="usr-toggle-message">Confirma esta operacion.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="usr-btn-confirm-toggle">Confirmar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/usuarios.js"></script>
