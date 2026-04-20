(function () {
  var app = document.getElementById('usr-app');
  if (!app) return;

  var urls = {
    list: app.getAttribute('data-url-list') || '',
    create: app.getAttribute('data-url-create') || '',
    update: app.getAttribute('data-url-update') || '',
    toggle: app.getAttribute('data-url-toggle') || '',
    resetPassword: app.getAttribute('data-url-reset-password') || ''
  };

  var can = {
    create: app.getAttribute('data-can-create') === '1',
    edit: app.getAttribute('data-can-edit') === '1',
    toggle: app.getAttribute('data-can-toggle') === '1',
    resetPassword: app.getAttribute('data-can-reset-password') === '1'
  };

  var csrfInput = document.getElementById('usr-csrf-token');
  if (!csrfInput) return;

  var state = {
    page: 1,
    perPage: 10,
    totalPages: 1,
    search: '',
    estado: '',
    rows: [],
    rolesCatalog: []
  };

  var els = {
    alert: document.getElementById('usr-alert'),
    tableBody: document.getElementById('usr-table-body'),
    paginationInfo: document.getElementById('usr-pagination-info'),
    btnPrev: document.getElementById('usr-page-prev'),
    btnNext: document.getElementById('usr-page-next'),
    search: document.getElementById('usr-search'),
    estadoFilter: document.getElementById('usr-estado-filter'),
    perPage: document.getElementById('usr-per-page'),
    btnSearch: document.getElementById('usr-btn-search'),
    btnRefresh: document.getElementById('usr-btn-refresh'),
    btnNew: document.getElementById('usr-btn-new'),
    modalForm: document.getElementById('usrModalForm'),
    formCreateEdit: document.getElementById('usr-form-create-edit'),
    modalFormTitle: document.getElementById('usrModalFormLabel'),
    inputIdUsuario: document.getElementById('usr-id-usuario'),
    inputUsuario: document.getElementById('usr-usuario'),
    inputNombres: document.getElementById('usr-nombres'),
    inputApellidos: document.getElementById('usr-apellidos'),
    inputClave: document.getElementById('usr-clave'),
    inputClaveConfirmar: document.getElementById('usr-clave-confirmar'),
    sectionCreatePasswords: document.getElementById('usr-password-create-fields'),
    rolesContainer: document.getElementById('usr-roles-container'),
    btnSave: document.getElementById('usr-btn-save'),
    modalReset: document.getElementById('usrModalResetPassword'),
    formReset: document.getElementById('usr-form-reset-password'),
    resetIdUsuario: document.getElementById('usr-reset-id-usuario'),
    resetUsuario: document.getElementById('usr-reset-usuario'),
    resetClave: document.getElementById('usr-reset-clave'),
    resetClaveConfirmar: document.getElementById('usr-reset-clave-confirmar'),
    btnResetPassword: document.getElementById('usr-btn-reset-password'),
    modalToggle: document.getElementById('usrModalToggleEstado'),
    toggleIdUsuario: document.getElementById('usr-toggle-id-usuario'),
    toggleEstadoObjetivo: document.getElementById('usr-toggle-estado-objetivo'),
    toggleMessage: document.getElementById('usr-toggle-message'),
    btnConfirmToggle: document.getElementById('usr-btn-confirm-toggle')
  };

  function getCsrf() {
    return csrfInput.value || '';
  }

  function setCsrf(token) {
    if (typeof token === 'string' && token !== '') {
      csrfInput.value = token;
    }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
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

  function setUiLoading(isLoading) {
    var disabled = !!isLoading;
    var controls = [
      els.btnSearch,
      els.btnRefresh,
      els.btnPrev,
      els.btnNext,
      els.btnSave,
      els.btnResetPassword,
      els.btnConfirmToggle,
      els.btnNew
    ];
    for (var i = 0; i < controls.length; i++) {
      if (controls[i]) controls[i].disabled = disabled;
    }
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
      var code = String(response.code || '');
      if (code === 'acceso_actualizado' || code === 'sesion_requerida' || code === 'sesion_invalida' || code === 'timeout') {
        var loginMessage = (code === 'acceso_actualizado') ? 'acceso_actualizado' : 'sesion';
        window.location.href = 'login.php?m=' + encodeURIComponent(loginMessage);
        return null;
      }
      showAlert('error', response.message || defaultError);
      return null;
    }
    return response;
  }

  function parseRoleIdsCsv(csv) {
    csv = String(csv || '');
    if (!csv) return [];
    var parts = csv.split(',');
    var out = [];
    for (var i = 0; i < parts.length; i++) {
      var n = parseInt(parts[i], 10);
      if (!isNaN(n) && n > 0 && out.indexOf(n) < 0) out.push(n);
    }
    return out;
  }

  function renderRolesCatalog(selectedRoleIds) {
    selectedRoleIds = Array.isArray(selectedRoleIds) ? selectedRoleIds : [];

    if (!els.rolesContainer) return;
    if (!state.rolesCatalog.length) {
      els.rolesContainer.innerHTML = '<div class="text-muted small">Sin roles activos.</div>';
      return;
    }

    var html = [];
    for (var i = 0; i < state.rolesCatalog.length; i++) {
      var role = state.rolesCatalog[i];
      var roleId = Number(role.id || 0);
      var checked = selectedRoleIds.indexOf(roleId) >= 0 ? ' checked' : '';
      html.push(
        '<div class="custom-control custom-checkbox mb-1">' +
          '<input type="checkbox" class="custom-control-input usr-role-checkbox" id="usr-role-' + roleId + '" value="' + roleId + '"' + checked + '>' +
          '<label class="custom-control-label" for="usr-role-' + roleId + '">' + escapeHtml(role.nombre || '') + '</label>' +
        '</div>'
      );
    }
    els.rolesContainer.innerHTML = html.join('');
  }

  function collectSelectedRoles() {
    if (!els.rolesContainer) return [];
    var checked = els.rolesContainer.querySelectorAll('.usr-role-checkbox:checked');
    var roleIds = [];
    for (var i = 0; i < checked.length; i++) {
      var value = parseInt(checked[i].value, 10);
      if (!isNaN(value) && value > 0 && roleIds.indexOf(value) < 0) roleIds.push(value);
    }
    return roleIds;
  }

  function renderTable() {
    if (!els.tableBody) return;
    if (!state.rows.length) {
      els.tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Sin datos</td></tr>';
    } else {
      var html = [];
      for (var i = 0; i < state.rows.length; i++) {
        var row = state.rows[i];
        var rowEstado = Number(row.estado) === 1 ? 'Activo' : 'Inactivo';
        var rowEstadoClass = Number(row.estado) === 1 ? 'badge-success' : 'badge-secondary';
        var rolesText = row.roles_activos ? row.roles_activos : '-';
        var ultimoLogin = row.ultimo_login_at ? row.ultimo_login_at : '-';
        var ultimoIp = row.ultimo_login_ip ? row.ultimo_login_ip : '-';

        var actions = [];
        if (can.edit) {
          actions.push('<button type="button" class="btn btn-xs btn-primary usr-action-edit" data-id="' + row.id + '"><i class="fas fa-edit"></i></button>');
        }
        if (can.toggle) {
          if (Number(row.estado) === 1) {
            actions.push('<button type="button" class="btn btn-xs btn-warning usr-action-toggle" data-id="' + row.id + '" data-estado="0">Inactivar</button>');
          } else {
            actions.push('<button type="button" class="btn btn-xs btn-success usr-action-toggle" data-id="' + row.id + '" data-estado="1">Activar</button>');
          }
        }
        if (can.resetPassword) {
          actions.push('<button type="button" class="btn btn-xs btn-dark usr-action-reset" data-id="' + row.id + '">Reset clave</button>');
        }

        html.push(
          '<tr data-row-id="' + row.id + '">' +
            '<td>' + escapeHtml(row.usuario) + '</td>' +
            '<td>' + escapeHtml(row.nombres) + '</td>' +
            '<td>' + escapeHtml(row.apellidos) + '</td>' +
            '<td><span class="badge ' + rowEstadoClass + '">' + rowEstado + '</span></td>' +
            '<td>' + escapeHtml(rolesText) + '</td>' +
            '<td>' + escapeHtml(ultimoLogin) + '</td>' +
            '<td>' + escapeHtml(ultimoIp) + '</td>' +
            '<td class="text-nowrap">' + (actions.length ? actions.join(' ') : '-') + '</td>' +
          '</tr>'
        );
      }
      els.tableBody.innerHTML = html.join('');
    }

    if (els.paginationInfo) {
      var total = (state.meta && state.meta.total) ? state.meta.total : 0;
      var current = (state.meta && state.meta.page) ? state.meta.page : 1;
      var pages = (state.meta && state.meta.total_pages) ? state.meta.total_pages : 1;
      els.paginationInfo.textContent = 'Total: ' + total + ' | Pagina ' + current + ' de ' + pages;
    }

    if (els.btnPrev) els.btnPrev.disabled = state.page <= 1;
    if (els.btnNext) els.btnNext.disabled = state.page >= state.totalPages;
  }

  function findRowById(userId) {
    userId = Number(userId || 0);
    for (var i = 0; i < state.rows.length; i++) {
      if (Number(state.rows[i].id) === userId) return state.rows[i];
    }
    return null;
  }

  function loadList(page) {
    hideAlert();
    setUiLoading(true);

    if (typeof page === 'number' && page > 0) {
      state.page = page;
    }

    var payload = {
      csrf_token: getCsrf(),
      page: state.page,
      per_page: state.perPage,
      search: state.search,
      estado: state.estado
    };

    postForm(urls.list, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo cargar usuarios.');
      if (!okResponse) return;

      var data = okResponse.data || {};
      var list = data.list || {};
      state.rows = Array.isArray(list.items) ? list.items : [];
      state.meta = list.meta || {};
      state.page = Number(state.meta.page || 1);
      state.totalPages = Number(state.meta.total_pages || 1);
      state.rolesCatalog = Array.isArray(data.roles_catalog) ? data.roles_catalog : [];

      if (data.allowed_actions) {
        can.create = !!data.allowed_actions.create;
        can.edit = !!data.allowed_actions.edit;
        can.toggle = !!data.allowed_actions.toggle_state;
        can.resetPassword = !!data.allowed_actions.reset_password;
      }

      renderTable();
    });
  }

  function openCreateModal() {
    if (!els.formCreateEdit) return;
    els.formCreateEdit.reset();
    els.inputIdUsuario.value = '0';
    els.inputUsuario.readOnly = false;
    els.inputUsuario.disabled = false;
    els.sectionCreatePasswords.style.display = '';
    if (els.modalFormTitle) els.modalFormTitle.textContent = 'Crear usuario';
    renderRolesCatalog([]);
    window.jQuery(els.modalForm).modal('show');
  }

  function openEditModal(userId) {
    var row = findRowById(userId);
    if (!row) return;

    els.formCreateEdit.reset();
    els.inputIdUsuario.value = String(row.id);
    els.inputUsuario.value = String(row.usuario || '');
    els.inputUsuario.readOnly = true;
    els.inputUsuario.disabled = true;
    els.inputNombres.value = String(row.nombres || '');
    els.inputApellidos.value = String(row.apellidos || '');
    els.sectionCreatePasswords.style.display = 'none';
    if (els.modalFormTitle) els.modalFormTitle.textContent = 'Editar usuario';

    renderRolesCatalog(parseRoleIdsCsv(row.roles_activos_ids));
    window.jQuery(els.modalForm).modal('show');
  }

  function openResetModal(userId) {
    var row = findRowById(userId);
    if (!row) return;

    els.formReset.reset();
    els.resetIdUsuario.value = String(row.id);
    if (els.resetUsuario) els.resetUsuario.textContent = String(row.usuario || '-');
    window.jQuery(els.modalReset).modal('show');
  }

  function openToggleModal(userId, estadoObjetivo) {
    var row = findRowById(userId);
    if (!row) return;

    els.toggleIdUsuario.value = String(row.id);
    els.toggleEstadoObjetivo.value = String(estadoObjetivo);
    var accion = Number(estadoObjetivo) === 1 ? 'activar' : 'inactivar';
    if (els.toggleMessage) {
      els.toggleMessage.textContent = 'Confirma ' + accion + ' al usuario ' + (row.usuario || '') + '.';
    }
    window.jQuery(els.modalToggle).modal('show');
  }

  function saveUser() {
    hideAlert();
    setUiLoading(true);

    var isCreate = Number(els.inputIdUsuario.value || 0) <= 0;
    var payload = {
      csrf_token: getCsrf(),
      nombres: (els.inputNombres.value || '').trim(),
      apellidos: (els.inputApellidos.value || '').trim(),
      roles: collectSelectedRoles()
    };

    var url = urls.update;
    if (isCreate) {
      url = urls.create;
      payload.usuario = (els.inputUsuario.value || '').trim();
      payload.clave = els.inputClave.value || '';
      payload.clave_confirmar = els.inputClaveConfirmar.value || '';
    } else {
      payload.id_usuario = Number(els.inputIdUsuario.value || 0);
    }

    postForm(url, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, isCreate ? 'No se pudo crear usuario.' : 'No se pudo actualizar usuario.');
      if (!okResponse) return;

      window.jQuery(els.modalForm).modal('hide');
      showAlert('success', okResponse.message || (isCreate ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.'));
      loadList(isCreate ? 1 : state.page);
    });
  }

  function toggleState() {
    hideAlert();
    setUiLoading(true);

    var payload = {
      csrf_token: getCsrf(),
      id_usuario: Number(els.toggleIdUsuario.value || 0),
      estado_objetivo: String(els.toggleEstadoObjetivo.value || '0')
    };

    postForm(urls.toggle, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo actualizar estado.');
      if (!okResponse) return;

      window.jQuery(els.modalToggle).modal('hide');
      showAlert('success', okResponse.message || 'Estado actualizado correctamente.');
      loadList(state.page);
    });
  }

  function resetPassword() {
    hideAlert();
    setUiLoading(true);

    var payload = {
      csrf_token: getCsrf(),
      id_usuario: Number(els.resetIdUsuario.value || 0),
      clave_nueva: els.resetClave.value || '',
      clave_confirmar: els.resetClaveConfirmar.value || ''
    };

    postForm(urls.resetPassword, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo resetear contrasena.');
      if (!okResponse) return;

      window.jQuery(els.modalReset).modal('hide');
      showAlert('success', okResponse.message || 'Contrasena reseteada correctamente.');
      loadList(state.page);
    });
  }

  if (els.btnSearch) {
    els.btnSearch.addEventListener('click', function () {
      state.search = (els.search.value || '').trim();
      state.estado = (els.estadoFilter.value || '').trim();
      state.perPage = parseInt(els.perPage.value || '10', 10) || 10;
      loadList(1);
    });
  }

  if (els.btnRefresh) {
    els.btnRefresh.addEventListener('click', function () {
      loadList(state.page);
    });
  }

  if (els.btnPrev) {
    els.btnPrev.addEventListener('click', function () {
      if (state.page > 1) loadList(state.page - 1);
    });
  }

  if (els.btnNext) {
    els.btnNext.addEventListener('click', function () {
      if (state.page < state.totalPages) loadList(state.page + 1);
    });
  }

  if (els.btnNew && can.create) {
    els.btnNew.addEventListener('click', function () {
      openCreateModal();
    });
  }

  if (els.formCreateEdit) {
    els.formCreateEdit.addEventListener('submit', function (event) {
      event.preventDefault();
      saveUser();
    });
  }

  if (els.formReset) {
    els.formReset.addEventListener('submit', function (event) {
      event.preventDefault();
      resetPassword();
    });
  }

  if (els.btnConfirmToggle) {
    els.btnConfirmToggle.addEventListener('click', function () {
      toggleState();
    });
  }

  if (els.tableBody) {
    els.tableBody.addEventListener('click', function (event) {
      var btnEdit = event.target.closest('.usr-action-edit');
      if (btnEdit) {
        event.preventDefault();
        openEditModal(Number(btnEdit.getAttribute('data-id') || 0));
        return;
      }

      var btnToggle = event.target.closest('.usr-action-toggle');
      if (btnToggle) {
        event.preventDefault();
        openToggleModal(
          Number(btnToggle.getAttribute('data-id') || 0),
          Number(btnToggle.getAttribute('data-estado') || 0)
        );
        return;
      }

      var btnReset = event.target.closest('.usr-action-reset');
      if (btnReset) {
        event.preventDefault();
        openResetModal(Number(btnReset.getAttribute('data-id') || 0));
      }
    });
  }

  loadList(1);
})();
