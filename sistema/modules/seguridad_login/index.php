<?php
if (!defined('PAG_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

$slgCsrfToken = lsis_csrf_get_token('seguridad_login_form');
?>
<div
  id="slg-app"
  data-url-get="seguridad_login/api_get_config.php"
  data-url-save="seguridad_login/api_save_config.php"
>
  <input type="hidden" id="slg-csrf-token" value="<?php echo htmlspecialchars($slgCsrfToken, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="card card-primary card-outline">
    <div class="card-header">
      <h3 class="card-title">Seguridad del login</h3>
      <div class="card-tools">
        <button type="button" id="slg-btn-actualizar" class="btn btn-default btn-sm">
          <i class="fas fa-sync-alt mr-1"></i>Actualizar
        </button>
      </div>
    </div>
    <div class="card-body">
      <div id="slg-alert" class="alert d-none mb-3" role="alert"></div>

      <div class="row">
        <div class="col-md-6">
          <p class="mb-1"><strong>Permiso requerido:</strong> seguridad_login.manage</p>
        </div>
        <div class="col-md-6 text-md-right">
          <p class="mb-1"><strong>Actualizado:</strong> <span id="slg-actualizado-en">-</span></p>
        </div>
      </div>
    </div>
  </div>

  <form id="slg-form" autocomplete="off" novalidate>
    <div class="card card-outline card-info">
      <div class="card-header">
        <h3 class="card-title">Configuracion general</h3>
      </div>
      <div class="card-body">
        <p class="text-muted mb-0">Ajusta la politica de seguridad del login y guarda los cambios.</p>
      </div>
    </div>

    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">Sesiones</h3>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="control_sesiones_activo" name="control_sesiones_activo">
              <label class="custom-control-label" for="control_sesiones_activo">Control de sesiones activo</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="max_dispositivos_activo" name="max_dispositivos_activo">
              <label class="custom-control-label" for="max_dispositivos_activo">Limitar dispositivos</label>
            </div>
          </div>
          <div class="col-md-4">
            <label for="max_dispositivos">Maximo dispositivos</label>
            <input type="number" class="form-control" id="max_dispositivos" name="max_dispositivos" min="1" max="10" step="1">
          </div>
        </div>
      </div>
    </div>

    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">Timeout</h3>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="timeout_inactividad_activo" name="timeout_inactividad_activo">
              <label class="custom-control-label" for="timeout_inactividad_activo">Timeout por inactividad activo</label>
            </div>
          </div>
          <div class="col-md-6">
            <label for="timeout_inactividad_minutos">Timeout inactividad (minutos)</label>
            <input type="number" class="form-control" id="timeout_inactividad_minutos" name="timeout_inactividad_minutos" min="1" max="480" step="1">
          </div>
        </div>
      </div>
    </div>

    <div class="card card-outline card-primary">
      <div class="card-header">
        <h3 class="card-title">Intentos y bloqueo</h3>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3">
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="limitador_login_activo" name="limitador_login_activo">
              <label class="custom-control-label" for="limitador_login_activo">Limitador activo</label>
            </div>
          </div>
          <div class="col-md-3">
            <label for="max_intentos_fallidos">Max intentos fallidos</label>
            <input type="number" class="form-control" id="max_intentos_fallidos" name="max_intentos_fallidos" min="1" max="20" step="1">
          </div>
          <div class="col-md-3">
            <label for="ventana_intentos_minutos">Ventana intentos (minutos)</label>
            <input type="number" class="form-control" id="ventana_intentos_minutos" name="ventana_intentos_minutos" min="1" max="120" step="1">
          </div>
          <div class="col-md-3">
            <div class="custom-control custom-switch mb-3">
              <input type="checkbox" class="custom-control-input" id="bloqueo_temporal_activo" name="bloqueo_temporal_activo">
              <label class="custom-control-label" for="bloqueo_temporal_activo">Bloqueo temporal activo</label>
            </div>
            <label for="bloqueo_temporal_minutos">Bloqueo temporal (minutos)</label>
            <input type="number" class="form-control" id="bloqueo_temporal_minutos" name="bloqueo_temporal_minutos" min="1" max="240" step="1">
          </div>
        </div>
      </div>
    </div>

    <div class="card card-outline card-secondary">
      <div class="card-header">
        <h3 class="card-title">Setup inicial (avanzado)</h3>
        <div class="card-tools">
          <button
            type="button"
            class="btn btn-tool"
            data-toggle="collapse"
            data-target="#slg-setup-avanzado"
            aria-expanded="false"
            aria-controls="slg-setup-avanzado"
          >
            <i class="fas fa-plus"></i>
          </button>
        </div>
      </div>
      <div id="slg-setup-avanzado" class="collapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <div class="custom-control custom-switch mb-3">
                <input type="checkbox" class="custom-control-input" id="control_abuso_setup_activo" name="control_abuso_setup_activo">
                <label class="custom-control-label" for="control_abuso_setup_activo">Control abuso setup activo</label>
              </div>
            </div>
            <div class="col-md-3">
              <label for="max_intentos_setup">Max intentos setup</label>
              <input type="number" class="form-control" id="max_intentos_setup" name="max_intentos_setup" min="1" max="20" step="1">
            </div>
            <div class="col-md-3">
              <label for="ventana_setup_minutos">Ventana setup (minutos)</label>
              <input type="number" class="form-control" id="ventana_setup_minutos" name="ventana_setup_minutos" min="1" max="120" step="1">
            </div>
            <div class="col-md-3">
              <label for="bloqueo_setup_minutos">Bloqueo setup (minutos)</label>
              <input type="number" class="form-control" id="bloqueo_setup_minutos" name="bloqueo_setup_minutos" min="1" max="240" step="1">
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mb-3">
      <button type="submit" id="slg-btn-guardar" class="btn btn-primary">
        <i class="fas fa-save mr-1"></i>Guardar cambios
      </button>
    </div>
  </form>

  <div class="card card-outline card-info">
    <div class="card-header">
      <h3 class="card-title">Resumen operativo</h3>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-primary"><i class="fas fa-desktop"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Sesiones activas</span>
              <span class="info-box-number" id="slg-sesiones-activas">0</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-lock"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Bloqueos activos</span>
              <span class="info-box-number" id="slg-bloqueos-activos">0</span>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-history"></i></span>
            <div class="info-box-content">
              <span class="info-box-text">Bloqueos recientes (24h)</span>
              <span class="info-box-number" id="slg-bloqueos-recientes">0</span>
            </div>
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm table-bordered">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Endpoint</th>
              <th>Resultado</th>
              <th>Motivo</th>
              <th>Usuario</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody id="slg-intentos-body">
            <tr>
              <td colspan="6" class="text-center text-muted">Sin datos</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/seguridad_login.js"></script>
