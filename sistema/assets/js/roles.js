(function () {
  var app = document.getElementById('rls-app');
  if (!app) return;

  var urls = {
    list: app.getAttribute('data-url-list') || '',
    create: app.getAttribute('data-url-create') || '',
    update: app.getAttribute('data-url-update') || '',
    toggle: app.getAttribute('data-url-toggle') || ''
  };

  var can = {
    create: app.getAttribute('data-can-create') === '1',
    edit: app.getAttribute('data-can-edit') === '1',
    toggle: app.getAttribute('data-can-toggle') === '1'
  };

  var csrfInput = document.getElementById('rls-csrf-token');
  if (!csrfInput) return;

  var state = {
    page: 1,
    perPage: 10,
    totalPages: 1,
    search: '',
    estado: '',
    rows: [],
    meta: {}
  };

  var els = {
    alert: document.getElementById('rls-alert'),
    tableBody: document.getElementById('rls-table-body'),
    paginationInfo: document.getElementById('rls-pagination-info'),
    btnPrev: document.getElementById('rls-page-prev'),
    btnNext: document.getElementById('rls-page-next'),
    search: document.getElementById('rls-search'),
    estadoFilter: document.getElementById('rls-estado-filter'),
    perPage: document.getElementById('rls-per-page'),
    btnSearch: document.getElementById('rls-btn-search'),
    btnRefresh: document.getElementById('rls-btn-refresh'),
    btnNew: document.getElementById('rls-btn-new'),
    modalForm: document.getElementById('rlsModalForm'),
    formCreateEdit: document.getElementById('rls-form-create-edit'),
    modalFormTitle: document.getElementById('rlsModalFormLabel'),
    inputIdRol: document.getElementById('rls-id-rol'),
    inputNombre: document.getElementById('rls-nombre'),
    inputDescripcion: document.getElementById('rls-descripcion'),
    btnSave: document.getElementById('rls-btn-save'),
    modalToggle: document.getElementById('rlsModalToggleEstado'),
    toggleIdRol: document.getElementById('rls-toggle-id-rol'),
    toggleEstadoObjetivo: document.getElementById('rls-toggle-estado-objetivo'),
    toggleMessage: document.getElementById('rls-toggle-message'),
    btnConfirmToggle: document.getElementById('rls-btn-confirm-toggle')
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
      formData.append(key, payload[key]);
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

  function findRowById(roleId) {
    roleId = Number(roleId || 0);
    for (var i = 0; i < state.rows.length; i++) {
      if (Number(state.rows[i].id) === roleId) return state.rows[i];
    }
    return null;
  }

  function renderTable() {
    if (!els.tableBody) return;

    if (!state.rows.length) {
      els.tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Sin datos</td></tr>';
    } else {
      var html = [];
      for (var i = 0; i < state.rows.length; i++) {
        var row = state.rows[i] || {};
        var estado = Number(row.estado) === 1 ? 'Activo' : 'Inactivo';
        var estadoClass = Number(row.estado) === 1 ? 'badge-success' : 'badge-secondary';
        var esProtegido = Number(row.es_protegido) === 1;

        var actions = [];
        if (can.edit) {
          actions.push('<button type="button" class="btn btn-xs btn-primary rls-action-edit" data-id="' + row.id + '"><i class="fas fa-edit"></i></button>');
        }

        if (can.toggle) {
          if (esProtegido) {
            actions.push('<span class="badge badge-dark">Protegido</span>');
          } else if (Number(row.estado) === 1) {
            actions.push('<button type="button" class="btn btn-xs btn-warning rls-action-toggle" data-id="' + row.id + '" data-estado="0">Inactivar</button>');
          } else {
            actions.push('<button type="button" class="btn btn-xs btn-success rls-action-toggle" data-id="' + row.id + '" data-estado="1">Activar</button>');
          }
        }

        html.push(
          '<tr data-row-id="' + row.id + '">' +
            '<td>' + escapeHtml(row.nombre || '') + '</td>' +
            '<td>' + escapeHtml(row.descripcion || '-') + '</td>' +
            '<td><span class="badge ' + estadoClass + '">' + estado + '</span></td>' +
            '<td>' + escapeHtml(row.usuarios_activos_asignados || 0) + '</td>' +
            '<td>' + escapeHtml(row.actualizado_en || '-') + '</td>' +
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
      var okResponse = handleResponse(response, 'No se pudo cargar roles.');
      if (!okResponse) return;

      var data = okResponse.data || {};
      var list = data.list || {};
      state.rows = Array.isArray(list.items) ? list.items : [];
      state.meta = list.meta || {};
      state.page = Number(state.meta.page || 1);
      state.totalPages = Number(state.meta.total_pages || 1);

      if (data.allowed_actions) {
        can.create = !!data.allowed_actions.create;
        can.edit = !!data.allowed_actions.edit;
        can.toggle = !!data.allowed_actions.toggle_state;
      }

      renderTable();
    });
  }

  function openCreateModal() {
    if (!els.formCreateEdit) return;
    els.formCreateEdit.reset();
    if (els.inputIdRol) els.inputIdRol.value = '0';
    if (els.modalFormTitle) els.modalFormTitle.textContent = 'Crear rol';
    if (window.jQuery) {
      window.jQuery(els.modalForm).modal('show');
    }
  }

  function openEditModal(roleId) {
    var row = findRowById(roleId);
    if (!row || !els.formCreateEdit) return;

    els.formCreateEdit.reset();
    if (els.inputIdRol) els.inputIdRol.value = String(row.id || 0);
    if (els.inputNombre) {
      els.inputNombre.value = row.nombre || '';
      els.inputNombre.readOnly = Number(row.es_protegido) === 1;
    }
    if (els.inputDescripcion) els.inputDescripcion.value = row.descripcion || '';
    if (els.modalFormTitle) els.modalFormTitle.textContent = 'Editar rol';

    if (window.jQuery) {
      window.jQuery(els.modalForm).modal('show');
    }
  }

  function submitCreateEdit() {
    if (!els.inputIdRol || !els.inputNombre || !els.inputDescripcion) return;

    var idRol = Number(els.inputIdRol.value || 0);
    var payload = {
      csrf_token: getCsrf(),
      nombre: (els.inputNombre.value || '').trim(),
      descripcion: (els.inputDescripcion.value || '').trim()
    };

    var endpoint = urls.create;
    if (idRol > 0) {
      endpoint = urls.update;
      payload.id_rol = String(idRol);
    }

    setUiLoading(true);
    hideAlert();

    postForm(endpoint, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, idRol > 0 ? 'No se pudo actualizar rol.' : 'No se pudo crear rol.');
      if (!okResponse) return;

      if (window.jQuery) {
        window.jQuery(els.modalForm).modal('hide');
      }
      showAlert('success', okResponse.message || 'Operacion realizada correctamente.');
      loadList(state.page);
    });
  }

  function openToggleModal(roleId, estadoObjetivo) {
    var row = findRowById(roleId);
    if (!row || !els.toggleIdRol || !els.toggleEstadoObjetivo || !els.toggleMessage) return;

    els.toggleIdRol.value = String(row.id || 0);
    els.toggleEstadoObjetivo.value = String(estadoObjetivo);
    els.toggleMessage.textContent = 'Confirma ' + (Number(estadoObjetivo) === 1 ? 'activar' : 'inactivar') + ' el rol "' + (row.nombre || '') + '".';

    if (window.jQuery) {
      window.jQuery(els.modalToggle).modal('show');
    }
  }

  function submitToggle() {
    if (!els.toggleIdRol || !els.toggleEstadoObjetivo) return;

    var payload = {
      csrf_token: getCsrf(),
      id_rol: String(els.toggleIdRol.value || '0'),
      estado_objetivo: String(els.toggleEstadoObjetivo.value || '0')
    };

    setUiLoading(true);
    hideAlert();

    postForm(urls.toggle, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo actualizar estado del rol.');
      if (!okResponse) return;

      if (window.jQuery) {
        window.jQuery(els.modalToggle).modal('hide');
      }
      showAlert('success', okResponse.message || 'Estado actualizado correctamente.');
      loadList(state.page);
    });
  }

  if (els.btnSearch) {
    els.btnSearch.addEventListener('click', function () {
      state.search = (els.search && els.search.value) ? els.search.value.trim() : '';
      state.estado = els.estadoFilter ? String(els.estadoFilter.value || '') : '';
      state.perPage = els.perPage ? Number(els.perPage.value || 10) : 10;
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
      if (state.page > 1) {
        loadList(state.page - 1);
      }
    });
  }

  if (els.btnNext) {
    els.btnNext.addEventListener('click', function () {
      if (state.page < state.totalPages) {
        loadList(state.page + 1);
      }
    });
  }

  if (els.btnNew) {
    els.btnNew.addEventListener('click', openCreateModal);
  }

  if (els.formCreateEdit) {
    els.formCreateEdit.addEventListener('submit', function (event) {
      event.preventDefault();
      submitCreateEdit();
    });
  }

  if (els.btnConfirmToggle) {
    els.btnConfirmToggle.addEventListener('click', submitToggle);
  }

  if (els.tableBody) {
    els.tableBody.addEventListener('click', function (event) {
      var btnEdit = event.target.closest('.rls-action-edit');
      if (btnEdit) {
        event.preventDefault();
        openEditModal(Number(btnEdit.getAttribute('data-id') || 0));
        return;
      }

      var btnToggle = event.target.closest('.rls-action-toggle');
      if (btnToggle) {
        event.preventDefault();
        openToggleModal(
          Number(btnToggle.getAttribute('data-id') || 0),
          Number(btnToggle.getAttribute('data-estado') || 0)
        );
      }
    });
  }

  loadList(1);
})();
