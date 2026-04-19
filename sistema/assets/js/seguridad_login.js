(function () {
  var app = document.getElementById('slg-app');
  if (!app) return;

  var urlGet = app.getAttribute('data-url-get') || '';
  var urlSave = app.getAttribute('data-url-save') || '';
  var csrfInput = document.getElementById('slg-csrf-token');
  var form = document.getElementById('slg-form');
  var btnActualizar = document.getElementById('slg-btn-actualizar');
  var btnGuardar = document.getElementById('slg-btn-guardar');
  var alertBox = document.getElementById('slg-alert');

  if (!urlGet || !urlSave || !csrfInput || !form) return;

  function getCsrfToken() {
    return csrfInput.value || '';
  }

  function setCsrfToken(token) {
    if (typeof token === 'string' && token !== '') {
      csrfInput.value = token;
    }
  }

  function setLoading(isLoading) {
    if (btnActualizar) btnActualizar.disabled = !!isLoading;
    if (btnGuardar) btnGuardar.disabled = !!isLoading;
  }

  function showAlert(type, message) {
    if (!alertBox) return;
    alertBox.className = 'alert mb-3';
    if (type === 'success') {
      alertBox.classList.add('alert-success');
    } else {
      alertBox.classList.add('alert-danger');
    }
    alertBox.textContent = message || '';
    alertBox.classList.remove('d-none');
  }

  function hideAlert() {
    if (!alertBox) return;
    alertBox.classList.add('d-none');
    alertBox.textContent = '';
  }

  function boolToChecked(fieldId, value) {
    var el = document.getElementById(fieldId);
    if (!el) return;
    el.checked = Number(value) === 1;
  }

  function setInputValue(fieldId, value) {
    var el = document.getElementById(fieldId);
    if (!el) return;
    el.value = String(value == null ? '' : value);
  }

  function getCheckedInt(fieldId) {
    var el = document.getElementById(fieldId);
    return el && el.checked ? 1 : 0;
  }

  function getInputValue(fieldId) {
    var el = document.getElementById(fieldId);
    return el ? String(el.value || '').trim() : '';
  }

  function renderPolicy(policy, actualizadoEn) {
    if (!policy) return;

    boolToChecked('control_sesiones_activo', policy.control_sesiones_activo);
    boolToChecked('max_dispositivos_activo', policy.max_dispositivos_activo);
    setInputValue('max_dispositivos', policy.max_dispositivos);

    boolToChecked('timeout_inactividad_activo', policy.timeout_inactividad_activo);
    setInputValue('timeout_inactividad_minutos', policy.timeout_inactividad_minutos);

    boolToChecked('limitador_login_activo', policy.limitador_login_activo);
    setInputValue('max_intentos_fallidos', policy.max_intentos_fallidos);
    setInputValue('ventana_intentos_minutos', policy.ventana_intentos_minutos);
    boolToChecked('bloqueo_temporal_activo', policy.bloqueo_temporal_activo);
    setInputValue('bloqueo_temporal_minutos', policy.bloqueo_temporal_minutos);

    boolToChecked('control_abuso_setup_activo', policy.control_abuso_setup_activo);
    setInputValue('max_intentos_setup', policy.max_intentos_setup);
    setInputValue('ventana_setup_minutos', policy.ventana_setup_minutos);
    setInputValue('bloqueo_setup_minutos', policy.bloqueo_setup_minutos);

    var actualizadoEl = document.getElementById('slg-actualizado-en');
    if (actualizadoEl) {
      actualizadoEl.textContent = actualizadoEn ? String(actualizadoEn) : '-';
    }
  }

  function renderSnapshot(snapshot) {
    snapshot = snapshot || {};

    var sesionesActivas = document.getElementById('slg-sesiones-activas');
    var bloqueosActivos = document.getElementById('slg-bloqueos-activos');
    var bloqueosRecientes = document.getElementById('slg-bloqueos-recientes');
    if (sesionesActivas) sesionesActivas.textContent = String(snapshot.sesiones_activas_totales || 0);
    if (bloqueosActivos) bloqueosActivos.textContent = String(snapshot.bloqueos_activos_totales || 0);
    if (bloqueosRecientes) bloqueosRecientes.textContent = String(snapshot.bloqueos_recientes_totales || 0);

    var tbody = document.getElementById('slg-intentos-body');
    if (!tbody) return;

    var intentos = Array.isArray(snapshot.ultimos_intentos) ? snapshot.ultimos_intentos : [];
    if (!intentos.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>';
      return;
    }

    var rows = [];
    for (var i = 0; i < intentos.length; i++) {
      var item = intentos[i] || {};
      rows.push(
        '<tr>' +
          '<td>' + escapeHtml(item.intento_at || '') + '</td>' +
          '<td>' + escapeHtml(item.endpoint || '') + '</td>' +
          '<td>' + (Number(item.exito) === 1 ? 'OK' : 'FALLIDO') + '</td>' +
          '<td>' + escapeHtml(item.motivo || '') + '</td>' +
          '<td>' + escapeHtml(item.usuario_ref || '') + '</td>' +
          '<td>' + escapeHtml(item.ip_ref || '') + '</td>' +
        '</tr>'
      );
    }

    tbody.innerHTML = rows.join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function handleResponse(response, defaultErrorMessage) {
    if (!response || typeof response !== 'object') {
      showAlert('error', defaultErrorMessage);
      return false;
    }

    if (response.csrf_token_nuevo) {
      setCsrfToken(response.csrf_token_nuevo);
    }

    if (response.ok !== true) {
      showAlert('error', response.message || defaultErrorMessage);
      return false;
    }

    var data = response.data || {};
    renderPolicy(data.policy || {}, data.actualizado_en || null);
    renderSnapshot(data.snapshot || {});
    return true;
  }

  function postJsonLike(url, payload, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;

      var response = null;
      try {
        response = JSON.parse(xhr.responseText || '{}');
      } catch (e) {
        response = null;
      }

      callback(response, xhr.status);
    };

    var formData = new FormData();
    for (var key in payload) {
      if (!Object.prototype.hasOwnProperty.call(payload, key)) continue;
      formData.append(key, payload[key]);
    }
    xhr.send(formData);
  }

  function loadConfig() {
    hideAlert();
    setLoading(true);

    postJsonLike(urlGet, { csrf_token: getCsrfToken() }, function (response) {
      setLoading(false);
      var ok = handleResponse(response, 'No se pudo cargar la configuracion.');
      if (ok) {
        showAlert('success', 'Configuracion actualizada.');
      }
    });
  }

  function saveConfig() {
    hideAlert();
    setLoading(true);

    var payload = {
      csrf_token: getCsrfToken(),
      control_sesiones_activo: getCheckedInt('control_sesiones_activo'),
      max_dispositivos_activo: getCheckedInt('max_dispositivos_activo'),
      max_dispositivos: getInputValue('max_dispositivos'),
      timeout_inactividad_activo: getCheckedInt('timeout_inactividad_activo'),
      timeout_inactividad_minutos: getInputValue('timeout_inactividad_minutos'),
      limitador_login_activo: getCheckedInt('limitador_login_activo'),
      max_intentos_fallidos: getInputValue('max_intentos_fallidos'),
      ventana_intentos_minutos: getInputValue('ventana_intentos_minutos'),
      bloqueo_temporal_activo: getCheckedInt('bloqueo_temporal_activo'),
      bloqueo_temporal_minutos: getInputValue('bloqueo_temporal_minutos'),
      control_abuso_setup_activo: getCheckedInt('control_abuso_setup_activo'),
      max_intentos_setup: getInputValue('max_intentos_setup'),
      ventana_setup_minutos: getInputValue('ventana_setup_minutos'),
      bloqueo_setup_minutos: getInputValue('bloqueo_setup_minutos')
    };

    postJsonLike(urlSave, payload, function (response) {
      setLoading(false);
      var ok = handleResponse(response, 'No se pudo guardar la configuracion.');
      if (ok) {
        showAlert('success', response.message || 'Configuracion guardada correctamente.');
      }
    });
  }

  if (btnActualizar) {
    btnActualizar.addEventListener('click', function () {
      loadConfig();
    });
  }

  app.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-slg-info-target]');
    if (!trigger) return;

    event.preventDefault();
    var targetId = trigger.getAttribute('data-slg-info-target') || '';
    if (!targetId) return;

    var target = document.getElementById(targetId);
    if (!target) return;

    var isOpen = !target.classList.contains('d-none');

    var tips = app.querySelectorAll('.slg-info-tip');
    for (var i = 0; i < tips.length; i++) {
      tips[i].classList.add('d-none');
    }

    var triggers = app.querySelectorAll('[data-slg-info-target]');
    for (var j = 0; j < triggers.length; j++) {
      triggers[j].setAttribute('aria-expanded', 'false');
    }

    if (!isOpen) {
      target.classList.remove('d-none');
      trigger.setAttribute('aria-expanded', 'true');
    }
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    saveConfig();
  });

  loadConfig();
})();
