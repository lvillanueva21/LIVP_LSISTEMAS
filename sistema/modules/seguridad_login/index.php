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
  <style>
    .slg-info-btn {
      width: 24px;
      height: 24px;
      padding: 0;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }
    .slg-info-tip {
      background: #000;
      color: #fff;
      border-radius: .25rem;
      padding: .5rem .75rem;
      font-size: .83rem;
      margin-top: .45rem;
      line-height: 1.35;
    }
  </style>

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
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <span class="mb-0">Control de sesiones activo</span>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-control_sesiones_activo" aria-expanded="false" aria-controls="slg-info-control_sesiones_activo">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <div class="custom-control custom-switch mt-1">
                <input type="checkbox" class="custom-control-input" id="control_sesiones_activo" name="control_sesiones_activo">
                <label class="custom-control-label" for="control_sesiones_activo"></label>
              </div>
              <div id="slg-info-control_sesiones_activo" class="slg-info-tip d-none">
                Activa validacion de sesion contra BD en cada navegacion. Evita que sesiones caducadas o invalidadas sigan operando. Valor permitido: 0 o 1.
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <span class="mb-0">Limitar dispositivos</span>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-max_dispositivos_activo" aria-expanded="false" aria-controls="slg-info-max_dispositivos_activo">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <div class="custom-control custom-switch mt-1">
                <input type="checkbox" class="custom-control-input" id="max_dispositivos_activo" name="max_dispositivos_activo">
                <label class="custom-control-label" for="max_dispositivos_activo"></label>
              </div>
              <div id="slg-info-max_dispositivos_activo" class="slg-info-tip d-none">
                Activa el limite de sesiones simultaneas por usuario. Ayuda a evitar uso concurrente no controlado. Valor permitido: 0 o 1.
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <label for="max_dispositivos" class="mb-0">Maximo dispositivos</label>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-max_dispositivos" aria-expanded="false" aria-controls="slg-info-max_dispositivos">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <input type="number" class="form-control mt-1" id="max_dispositivos" name="max_dispositivos" min="1" max="10" step="1">
              <div id="slg-info-max_dispositivos" class="slg-info-tip d-none">
                Define cuantas sesiones activas puede tener un usuario cuando el limite esta encendido. Rango permitido: 1 a 10.
              </div>
            </div>
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
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <span class="mb-0">Timeout por inactividad activo</span>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-timeout_inactividad_activo" aria-expanded="false" aria-controls="slg-info-timeout_inactividad_activo">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <div class="custom-control custom-switch mt-1">
                <input type="checkbox" class="custom-control-input" id="timeout_inactividad_activo" name="timeout_inactividad_activo">
                <label class="custom-control-label" for="timeout_inactividad_activo"></label>
              </div>
              <div id="slg-info-timeout_inactividad_activo" class="slg-info-tip d-none">
                Activa cierre automatico de sesion por inactividad. Reduce riesgo de sesiones abiertas sin supervision. Valor permitido: 0 o 1.
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <label for="timeout_inactividad_minutos" class="mb-0">Timeout inactividad (minutos)</label>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-timeout_inactividad_minutos" aria-expanded="false" aria-controls="slg-info-timeout_inactividad_minutos">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <input type="number" class="form-control mt-1" id="timeout_inactividad_minutos" name="timeout_inactividad_minutos" min="1" max="480" step="1">
              <div id="slg-info-timeout_inactividad_minutos" class="slg-info-tip d-none">
                Minutos de inactividad antes de cerrar la sesion activa. Rango permitido: 1 a 480.
              </div>
            </div>
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
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <span class="mb-0">Limitador activo</span>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-limitador_login_activo" aria-expanded="false" aria-controls="slg-info-limitador_login_activo">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <div class="custom-control custom-switch mt-1">
                <input type="checkbox" class="custom-control-input" id="limitador_login_activo" name="limitador_login_activo">
                <label class="custom-control-label" for="limitador_login_activo"></label>
              </div>
              <div id="slg-info-limitador_login_activo" class="slg-info-tip d-none">
                Activa control de intentos fallidos de login por usuario e IP. Ayuda a mitigar fuerza bruta. Valor permitido: 0 o 1.
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <label for="max_intentos_fallidos" class="mb-0">Max intentos fallidos</label>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-max_intentos_fallidos" aria-expanded="false" aria-controls="slg-info-max_intentos_fallidos">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <input type="number" class="form-control mt-1" id="max_intentos_fallidos" name="max_intentos_fallidos" min="1" max="20" step="1">
              <div id="slg-info-max_intentos_fallidos" class="slg-info-tip d-none">
                Cantidad maxima de fallos antes de bloquear temporalmente o dentro de ventana segun politica. Rango permitido: 1 a 20.
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <label for="ventana_intentos_minutos" class="mb-0">Ventana intentos (minutos)</label>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-ventana_intentos_minutos" aria-expanded="false" aria-controls="slg-info-ventana_intentos_minutos">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <input type="number" class="form-control mt-1" id="ventana_intentos_minutos" name="ventana_intentos_minutos" min="1" max="120" step="1">
              <div id="slg-info-ventana_intentos_minutos" class="slg-info-tip d-none">
                Tiempo en minutos que se usa para contar intentos fallidos recientes. Rango permitido: 1 a 120.
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <span class="mb-0">Bloqueo temporal activo</span>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-bloqueo_temporal_activo" aria-expanded="false" aria-controls="slg-info-bloqueo_temporal_activo">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <div class="custom-control custom-switch mt-1">
                <input type="checkbox" class="custom-control-input" id="bloqueo_temporal_activo" name="bloqueo_temporal_activo">
                <label class="custom-control-label" for="bloqueo_temporal_activo"></label>
              </div>
              <div id="slg-info-bloqueo_temporal_activo" class="slg-info-tip d-none">
                Si esta activo, al exceder intentos se bloquea el acceso por un tiempo definido. Valor permitido: 0 o 1.
              </div>
            </div>
            <div class="slg-field mb-3">
              <div class="d-flex align-items-center">
                <label for="bloqueo_temporal_minutos" class="mb-0">Bloqueo temporal (minutos)</label>
                <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-bloqueo_temporal_minutos" aria-expanded="false" aria-controls="slg-info-bloqueo_temporal_minutos">
                  <i class="fas fa-info"></i>
                </button>
              </div>
              <input type="number" class="form-control mt-1" id="bloqueo_temporal_minutos" name="bloqueo_temporal_minutos" min="1" max="240" step="1">
              <div id="slg-info-bloqueo_temporal_minutos" class="slg-info-tip d-none">
                Duracion del bloqueo cuando se supera el maximo de fallos y el bloqueo temporal esta activo. Rango permitido: 1 a 240.
              </div>
            </div>
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
              <div class="slg-field mb-3">
                <div class="d-flex align-items-center">
                  <span class="mb-0">Control abuso setup activo</span>
                  <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-control_abuso_setup_activo" aria-expanded="false" aria-controls="slg-info-control_abuso_setup_activo">
                    <i class="fas fa-info"></i>
                  </button>
                </div>
                <div class="custom-control custom-switch mt-1">
                  <input type="checkbox" class="custom-control-input" id="control_abuso_setup_activo" name="control_abuso_setup_activo">
                  <label class="custom-control-label" for="control_abuso_setup_activo"></label>
                </div>
                <div id="slg-info-control_abuso_setup_activo" class="slg-info-tip d-none">
                  Activa control de abuso sobre registro inicial por IP para evitar intentos automatizados. Valor permitido: 0 o 1.
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="slg-field mb-3">
                <div class="d-flex align-items-center">
                  <label for="max_intentos_setup" class="mb-0">Max intentos setup</label>
                  <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-max_intentos_setup" aria-expanded="false" aria-controls="slg-info-max_intentos_setup">
                    <i class="fas fa-info"></i>
                  </button>
                </div>
                <input type="number" class="form-control mt-1" id="max_intentos_setup" name="max_intentos_setup" min="1" max="20" step="1">
                <div id="slg-info-max_intentos_setup" class="slg-info-tip d-none">
                  Numero maximo de intentos fallidos permitidos en setup dentro de su ventana. Rango permitido: 1 a 20.
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="slg-field mb-3">
                <div class="d-flex align-items-center">
                  <label for="ventana_setup_minutos" class="mb-0">Ventana setup (minutos)</label>
                  <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-ventana_setup_minutos" aria-expanded="false" aria-controls="slg-info-ventana_setup_minutos">
                    <i class="fas fa-info"></i>
                  </button>
                </div>
                <input type="number" class="form-control mt-1" id="ventana_setup_minutos" name="ventana_setup_minutos" min="1" max="120" step="1">
                <div id="slg-info-ventana_setup_minutos" class="slg-info-tip d-none">
                  Minutos usados para contar intentos fallidos recientes de setup. Rango permitido: 1 a 120.
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="slg-field mb-3">
                <div class="d-flex align-items-center">
                  <label for="bloqueo_setup_minutos" class="mb-0">Bloqueo setup (minutos)</label>
                  <button type="button" class="btn btn-dark btn-xs slg-info-btn ml-2" data-slg-info-target="slg-info-bloqueo_setup_minutos" aria-expanded="false" aria-controls="slg-info-bloqueo_setup_minutos">
                    <i class="fas fa-info"></i>
                  </button>
                </div>
                <input type="number" class="form-control mt-1" id="bloqueo_setup_minutos" name="bloqueo_setup_minutos" min="1" max="240" step="1">
                <div id="slg-info-bloqueo_setup_minutos" class="slg-info-tip d-none">
                  Tiempo de bloqueo del setup cuando se exceden intentos permitidos en la ventana definida. Rango permitido: 1 a 240.
                </div>
              </div>
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
