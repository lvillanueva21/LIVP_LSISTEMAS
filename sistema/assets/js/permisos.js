(function () {
  var app = document.getElementById('prm-app');
  if (!app) return;

  var urlGetMatrix = app.getAttribute('data-url-get-matrix') || '';
  var urlSaveMatrix = app.getAttribute('data-url-save-matrix') || '';
  var canAssign = app.getAttribute('data-can-assign') === '1';

  var csrfInput = document.getElementById('prm-csrf-token');
  if (!csrfInput) return;

  var state = {
    selectedRoleId: 0,
    roles: [],
    permissions: [],
    assigned: []
  };

  var els = {
    alert: document.getElementById('prm-alert'),
    roleSelect: document.getElementById('prm-role-select'),
    roleMeta: document.getElementById('prm-role-meta'),
    countInfo: document.getElementById('prm-count-info'),
    tableBody: document.getElementById('prm-table-body'),
    btnRefresh: document.getElementById('prm-btn-refresh'),
    btnSave: document.getElementById('prm-btn-save')
  };

  function getCsrf() {
    return csrfInput.value || '';
  }

  function setCsrf(token) {
    if (typeof token === 'string' && token !== '') {
      csrfInput.value = token;
    }
  }

  function showAlert(type, message) {
    if (!els.alert) return;
    els.alert.className = 'alert mb-3';
    els.alert.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
    els.alert.textContent = message || '';
    els.alert.classList.remove('d-none');
  }

  function hideAlert() {
    if (!els.alert) return;
    els.alert.classList.add('d-none');
    els.alert.textContent = '';
  }

  function setLoading(isLoading) {
    var disabled = !!isLoading;
    if (els.btnRefresh) els.btnRefresh.disabled = disabled;
    if (els.btnSave) els.btnSave.disabled = disabled || !canAssign;
    if (els.roleSelect) els.roleSelect.disabled = disabled;
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function postForm(url, payload, callback) {
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
      var value = payload[key];
      if (Array.isArray(value)) {
        for (var i = 0; i < value.length; i++) {
          formData.append(key + '[]', value[i]);
        }
      } else {
        formData.append(key, value);
      }
    }

    xhr.send(formData);
  }

  function handleResponse(response, defaultError) {
    if (!response || typeof response !== 'object') {
      showAlert('error', defaultError);
      return null;
    }

    if (response.csrf_token_nuevo) {
      setCsrf(response.csrf_token_nuevo);
    }

    if (response.ok !== true) {
      showAlert('error', response.message || defaultError);
      return null;
    }

    return response;
  }

  function getSelectedRoleById(roleId) {
    roleId = Number(roleId || 0);
    for (var i = 0; i < state.roles.length; i++) {
      if (Number(state.roles[i].id) === roleId) return state.roles[i];
    }
    return null;
  }

  function renderRoleSelect() {
    if (!els.roleSelect) return;

    if (!state.roles.length) {
      els.roleSelect.innerHTML = '<option value="">Sin roles</option>';
      return;
    }

    var options = [];
    for (var i = 0; i < state.roles.length; i++) {
      var role = state.roles[i];
      var selected = Number(role.id) === Number(state.selectedRoleId) ? ' selected' : '';
      var estado = Number(role.estado) === 1 ? 'Activo' : 'Inactivo';
      options.push(
        '<option value="' + role.id + '"' + selected + '>' +
          escapeHtml(role.nombre || '') + ' (' + estado + ')' +
        '</option>'
      );
    }

    els.roleSelect.innerHTML = options.join('');
  }

  function renderRoleMeta() {
    if (!els.roleMeta) return;

    var role = getSelectedRoleById(state.selectedRoleId);
    if (!role) {
      els.roleMeta.textContent = '-';
      return;
    }

    var parts = [];
    parts.push('Estado: ' + (Number(role.estado) === 1 ? 'Activo' : 'Inactivo'));
    if (role.es_superadmin === 1 || Number(role.es_superadmin) === 1) {
      parts.push('Rol base protegido por nombre (deuda tecnica V1)');
    }
    if (role.descripcion) {
      parts.push('Descripcion: ' + role.descripcion);
    }

    els.roleMeta.textContent = parts.join(' | ');
  }

  function renderTable() {
    if (!els.tableBody) return;

    if (!state.permissions.length) {
      els.tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sin datos</td></tr>';
      if (els.countInfo) els.countInfo.textContent = '0 permisos';
      return;
    }

    var assignedMap = {};
    for (var i = 0; i < state.assigned.length; i++) {
      assignedMap[Number(state.assigned[i])] = true;
    }

    var html = [];
    for (var j = 0; j < state.permissions.length; j++) {
      var permission = state.permissions[j] || {};
      var pid = Number(permission.id_permiso || 0);
      var checked = assignedMap[pid] ? ' checked' : '';
      var disabled = canAssign ? '' : ' disabled';

      html.push(
        '<tr>' +
          '<td class="text-center">' +
            '<input type="checkbox" class="prm-check" value="' + pid + '"' + checked + disabled + '>' +
          '</td>' +
          '<td>' + escapeHtml(permission.permiso_codigo || '') + '</td>' +
          '<td>' + escapeHtml(permission.nombre_permiso || '') + '</td>' +
          '<td>' + escapeHtml(permission.descripcion || '-') + '</td>' +
        '</tr>'
      );
    }

    els.tableBody.innerHTML = html.join('');
    if (els.countInfo) {
      els.countInfo.textContent = String(state.permissions.length) + ' permisos activos';
    }
  }

  function collectSelectedPermissionIds() {
    if (!els.tableBody) return [];

    var checked = els.tableBody.querySelectorAll('.prm-check:checked');
    var ids = [];
    var seen = {};
    for (var i = 0; i < checked.length; i++) {
      var value = Number(checked[i].value || 0);
      if (value > 0 && !seen[value]) {
        seen[value] = true;
        ids.push(value);
      }
    }
    return ids;
  }

  function loadMatrix(roleId, showSuccessMessage) {
    hideAlert();
    setLoading(true);

    var payload = {
      csrf_token: getCsrf()
    };
    if (roleId && Number(roleId) > 0) {
      payload.id_rol = String(roleId);
    }

    postForm(urlGetMatrix, payload, function (response) {
      setLoading(false);
      var okResponse = handleResponse(response, 'No se pudo cargar la matriz de permisos.');
      if (!okResponse) return;

      var data = okResponse.data || {};
      state.roles = Array.isArray(data.roles_catalog) ? data.roles_catalog : [];
      state.permissions = Array.isArray(data.permissions_catalog) ? data.permissions_catalog : [];
      state.assigned = Array.isArray(data.assigned_permission_ids) ? data.assigned_permission_ids : [];
      state.selectedRoleId = Number(data.role_selected_id || 0);

      renderRoleSelect();
      renderRoleMeta();
      renderTable();

      if (showSuccessMessage) {
        showAlert('success', okResponse.message || 'Matriz actualizada.');
      }
    });
  }

  function saveMatrix() {
    if (!canAssign) {
      showAlert('error', 'No tienes permiso para asignar permisos.');
      return;
    }

    var roleId = Number(state.selectedRoleId || 0);
    if (roleId <= 0) {
      showAlert('error', 'Selecciona un rol valido.');
      return;
    }

    hideAlert();
    setLoading(true);

    var payload = {
      csrf_token: getCsrf(),
      id_rol: String(roleId),
      permisos_ids: collectSelectedPermissionIds()
    };

    postForm(urlSaveMatrix, payload, function (response) {
      setLoading(false);
      var okResponse = handleResponse(response, 'No se pudo guardar la matriz de permisos.');
      if (!okResponse) return;

      showAlert('success', okResponse.message || 'Matriz guardada correctamente.');
      loadMatrix(roleId, false);
    });
  }

  if (els.btnRefresh) {
    els.btnRefresh.addEventListener('click', function () {
      loadMatrix(state.selectedRoleId || 0, true);
    });
  }

  if (els.btnSave) {
    els.btnSave.addEventListener('click', function () {
      saveMatrix();
    });
  }

  if (els.roleSelect) {
    els.roleSelect.addEventListener('change', function () {
      var selected = Number(els.roleSelect.value || 0);
      loadMatrix(selected, false);
    });
  }

  loadMatrix(0, false);
})();
