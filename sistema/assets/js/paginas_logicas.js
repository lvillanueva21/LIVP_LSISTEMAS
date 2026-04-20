(function () {
  var app = document.getElementById('pgl-app');
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

  var csrfInput = document.getElementById('pgl-csrf-token');
  if (!csrfInput) return;

  var state = {
    page: 1,
    perPage: 10,
    totalPages: 1,
    search: '',
    estado: '',
    tipo: '',
    rows: [],
    meta: {},
    modulesCatalog: [],
    permissionsCatalog: [],
    parentCatalog: [],
    formMode: 'create',
    editingRow: null
  };

  var els = {
    alert: document.getElementById('pgl-alert'),
    tableBody: document.getElementById('pgl-table-body'),
    paginationInfo: document.getElementById('pgl-pagination-info'),
    btnPrev: document.getElementById('pgl-page-prev'),
    btnNext: document.getElementById('pgl-page-next'),
    search: document.getElementById('pgl-search'),
    estadoFilter: document.getElementById('pgl-estado-filter'),
    tipoFilter: document.getElementById('pgl-tipo-filter'),
    perPage: document.getElementById('pgl-per-page'),
    btnSearch: document.getElementById('pgl-btn-search'),
    btnRefresh: document.getElementById('pgl-btn-refresh'),
    btnNew: document.getElementById('pgl-btn-new'),
    modalForm: document.getElementById('pglModalForm'),
    formCreateEdit: document.getElementById('pgl-form-create-edit'),
    modalFormTitle: document.getElementById('pglModalFormLabel'),
    inputIdPagina: document.getElementById('pgl-id-pagina'),
    inputEsFija: document.getElementById('pgl-es-fija'),
    inputTipoPagina: document.getElementById('pgl-tipo-pagina'),
    inputSlug: document.getElementById('pgl-slug-pagina'),
    inputIdPadre: document.getElementById('pgl-id-padre'),
    inputTituloMenu: document.getElementById('pgl-titulo-menu'),
    inputTituloPagina: document.getElementById('pgl-titulo-pagina'),
    inputDescripcion: document.getElementById('pgl-descripcion-pagina'),
    inputVisibleMenu: document.getElementById('pgl-visible-menu'),
    inputEstado: document.getElementById('pgl-estado'),
    inputOrdenMenu: document.getElementById('pgl-orden-menu'),
    inputIcono: document.getElementById('pgl-icono'),
    inputIdPermiso: document.getElementById('pgl-id-permiso'),
    inputModuloCodigo: document.getElementById('pgl-modulo-codigo'),
    inputArchivoSection: document.getElementById('pgl-archivo-section'),
    formNote: document.getElementById('pgl-form-note'),
    btnSave: document.getElementById('pgl-btn-save'),
    modalToggle: document.getElementById('pglModalToggleEstado'),
    toggleIdPagina: document.getElementById('pgl-toggle-id-pagina'),
    toggleEstadoObjetivo: document.getElementById('pgl-toggle-estado-objetivo'),
    toggleMessage: document.getElementById('pgl-toggle-message'),
    btnConfirmToggle: document.getElementById('pgl-btn-confirm-toggle')
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
      var rawText = xhr.responseText || '';

      // Tolera BOM y ruido accidental antes/despues del JSON.
      rawText = rawText.replace(/^\uFEFF/, '');
      var trimmed = rawText.trim();
      if (trimmed !== '') {
        var firstBrace = trimmed.indexOf('{');
        var lastBrace = trimmed.lastIndexOf('}');
        if (firstBrace >= 0 && lastBrace > firstBrace) {
          trimmed = trimmed.substring(firstBrace, lastBrace + 1);
        }
      }

      try {
        response = JSON.parse(trimmed || '{}');
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

  function normalizeModuleCatalog() {
    var map = {};
    for (var i = 0; i < state.modulesCatalog.length; i++) {
      var row = state.modulesCatalog[i] || {};
      var code = String(row.modulo_codigo || '').trim();
      if (!code) continue;
      map[code] = Array.isArray(row.sections) ? row.sections.slice(0) : [];
    }
    return map;
  }

  function renderParentSelect(selectedId, excludeId) {
    if (!els.inputIdPadre) return;

    selectedId = selectedId == null ? '' : String(selectedId);
    excludeId = Number(excludeId || 0);

    var html = ['<option value="">Sin padre</option>'];
    for (var i = 0; i < state.parentCatalog.length; i++) {
      var row = state.parentCatalog[i] || {};
      var id = Number(row.id_pagina || 0);
      if (id <= 0) continue;
      if (excludeId > 0 && id === excludeId) continue;
      var selected = String(id) === selectedId ? ' selected' : '';
      html.push(
        '<option value="' + id + '"' + selected + '>' +
          escapeHtml(row.titulo_menu || '') +
        '</option>'
      );
    }
    els.inputIdPadre.innerHTML = html.join('');
  }

  function renderPermissionSelect(selectedId) {
    if (!els.inputIdPermiso) return;

    selectedId = selectedId == null ? '' : String(selectedId);
    var html = ['<option value="">Auto por slug (.view)</option>'];
    for (var i = 0; i < state.permissionsCatalog.length; i++) {
      var row = state.permissionsCatalog[i] || {};
      var id = Number(row.id_permiso || 0);
      if (id <= 0) continue;
      var selected = String(id) === selectedId ? ' selected' : '';
      var code = String(row.permiso_codigo || '');
      html.push('<option value="' + id + '"' + selected + '>' + escapeHtml(code) + '</option>');
    }
    els.inputIdPermiso.innerHTML = html.join('');
  }

  function renderModuleSelect(selectedModule) {
    if (!els.inputModuloCodigo) return;
    selectedModule = selectedModule == null ? '' : String(selectedModule);

    var html = ['<option value="">Seleccionar modulo</option>'];
    for (var i = 0; i < state.modulesCatalog.length; i++) {
      var row = state.modulesCatalog[i] || {};
      var code = String(row.modulo_codigo || '').trim();
      if (!code) continue;
      var selected = code === selectedModule ? ' selected' : '';
      html.push('<option value="' + escapeHtml(code) + '"' + selected + '>' + escapeHtml(code) + '</option>');
    }
    els.inputModuloCodigo.innerHTML = html.join('');
  }

  function renderSectionSelect(moduleCode, selectedSection) {
    if (!els.inputArchivoSection) return;

    moduleCode = String(moduleCode || '').trim();
    selectedSection = selectedSection == null ? '' : String(selectedSection);

    var moduleMap = normalizeModuleCatalog();
    var sections = moduleMap[moduleCode] || [];

    var html = ['<option value="">Seleccionar section</option>'];
    for (var i = 0; i < sections.length; i++) {
      var section = String(sections[i] || '').trim();
      if (!section) continue;
      var selected = section === selectedSection ? ' selected' : '';
      html.push('<option value="' + escapeHtml(section) + '"' + selected + '>' + escapeHtml(section) + '</option>');
    }
    els.inputArchivoSection.innerHTML = html.join('');
  }

  function syncFormByTypeAndMode() {
    var isCreate = state.formMode === 'create';
    var isFixed = Number(els.inputEsFija.value || 0) === 1;
    var tipo = String(els.inputTipoPagina.value || 'real');
    var isContenedor = tipo === 'contenedor';

    if (els.inputSlug) {
      els.inputSlug.readOnly = !isCreate;
    }
    if (els.inputTipoPagina) {
      els.inputTipoPagina.disabled = isFixed;
    }
    if (els.inputEstado) {
      els.inputEstado.disabled = isFixed;
    }

    var allowParent = !isFixed && !isContenedor;
    if (!allowParent) {
      els.inputIdPadre.value = '';
    }
    els.inputIdPadre.disabled = !allowParent;

    var allowModuleFields = !isFixed && !isContenedor;
    if (!allowModuleFields) {
      els.inputModuloCodigo.value = '';
      renderSectionSelect('', '');
    }
    els.inputModuloCodigo.disabled = !allowModuleFields;
    els.inputArchivoSection.disabled = !allowModuleFields;

    var allowPermission = !isCreate && !isFixed && !isContenedor;
    if (!allowPermission) {
      els.inputIdPermiso.value = '';
    }
    els.inputIdPermiso.disabled = !allowPermission;

    if (isFixed) {
      if (els.formNote) {
        els.formNote.textContent = 'Pagina fija: solo se permite editar metadatos seguros en esta V1.';
      }
    } else if (isContenedor) {
      if (els.formNote) {
        els.formNote.textContent = 'Contenedor: solo nivel 1, sin modulo/section ni permiso base.';
      }
    } else if (els.formNote) {
      els.formNote.textContent = 'Pagina real: para activar, modulo y section deben existir en sistema/modules/.';
    }
  }

  function applyPermissionsFromServer(actions) {
    if (!actions || typeof actions !== 'object') return;
    if (typeof actions.create !== 'undefined') can.create = !!actions.create;
    if (typeof actions.edit !== 'undefined') can.edit = !!actions.edit;
    if (typeof actions.toggle_state !== 'undefined') can.toggle = !!actions.toggle_state;
    if (els.btnNew) {
      els.btnNew.style.display = can.create ? '' : 'none';
    }
  }

  function renderTable() {
    if (!els.tableBody) return;

    if (!state.rows.length) {
      els.tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Sin datos</td></tr>';
    } else {
      var html = [];
      for (var i = 0; i < state.rows.length; i++) {
        var row = state.rows[i] || {};
        var isFija = Number(row.es_fija) === 1;
        var isContenedor = Number(row.es_contenedor) === 1;
        var estado = Number(row.estado) === 1 ? 'Activa' : 'Inactiva';
        var estadoClass = Number(row.estado) === 1 ? 'badge-success' : 'badge-secondary';
        var visible = Number(row.visible_menu) === 1 ? 'Si' : 'No';
        var tipo = isFija ? 'Fija' : (isContenedor ? 'Contenedor' : 'Real');
        var padre = row.parent_titulo_menu ? row.parent_titulo_menu : '-';
        var permiso = row.permiso_codigo ? row.permiso_codigo : '-';
        var moduloSection = '-';
        if (!isContenedor) {
          var modulo = row.modulo_codigo ? row.modulo_codigo : '';
          var section = row.archivo_section ? row.archivo_section : '';
          moduloSection = modulo && section ? (modulo + '/' + section) : '-';
        }

        var actions = [];
        if (can.edit) {
          actions.push('<button type="button" class="btn btn-xs btn-primary pgl-action-edit" data-id="' + row.id_pagina + '"><i class="fas fa-edit"></i></button>');
        }
        if (can.toggle && !isFija) {
          if (Number(row.estado) === 1) {
            actions.push('<button type="button" class="btn btn-xs btn-warning pgl-action-toggle" data-id="' + row.id_pagina + '" data-estado="0">Inactivar</button>');
          } else {
            actions.push('<button type="button" class="btn btn-xs btn-success pgl-action-toggle" data-id="' + row.id_pagina + '" data-estado="1">Activar</button>');
          }
        }

        html.push(
          '<tr data-row-id="' + row.id_pagina + '">' +
            '<td>' + escapeHtml(tipo) + '</td>' +
            '<td>' + escapeHtml(row.titulo_menu || '') + '</td>' +
            '<td>' + escapeHtml(row.titulo_pagina || '') + '</td>' +
            '<td>' + escapeHtml(row.slug_pagina || '') + '</td>' +
            '<td>' + escapeHtml(padre) + '</td>' +
            '<td>' + escapeHtml(permiso) + '</td>' +
            '<td>' + escapeHtml(moduloSection) + '</td>' +
            '<td>' + escapeHtml(visible) + '</td>' +
            '<td><span class="badge ' + estadoClass + '">' + estado + '</span></td>' +
            '<td class="text-nowrap">' + (actions.length ? actions.join(' ') : '-') + '</td>' +
          '</tr>'
        );
      }

      els.tableBody.innerHTML = html.join('');
    }

    if (els.paginationInfo) {
      var total = Number((state.meta && state.meta.total) || 0);
      var current = Number((state.meta && state.meta.page) || 1);
      var pages = Number((state.meta && state.meta.total_pages) || 1);
      els.paginationInfo.textContent = 'Total: ' + total + ' | Pagina ' + current + ' de ' + pages;
    }

    if (els.btnPrev) els.btnPrev.disabled = state.page <= 1;
    if (els.btnNext) els.btnNext.disabled = state.page >= state.totalPages;
  }

  function findRowById(idPagina) {
    idPagina = Number(idPagina || 0);
    for (var i = 0; i < state.rows.length; i++) {
      if (Number(state.rows[i].id_pagina) === idPagina) return state.rows[i];
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
      estado: state.estado,
      tipo: state.tipo
    };

    postForm(urls.list, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo cargar paginas logicas.');
      if (!okResponse) return;

      var data = okResponse.data || {};
      var list = data.list || {};

      state.rows = Array.isArray(list.items) ? list.items : [];
      state.meta = list.meta || {};
      state.page = Number(state.meta.page || 1);
      state.totalPages = Number(state.meta.total_pages || 1);
      state.modulesCatalog = Array.isArray(data.modules_catalog) ? data.modules_catalog : [];
      state.permissionsCatalog = Array.isArray(data.permissions_catalog) ? data.permissions_catalog : [];
      state.parentCatalog = Array.isArray(data.parent_catalog) ? data.parent_catalog : [];

      applyPermissionsFromServer(data.allowed_actions || {});
      renderTable();
    });
  }

  function openCreateModal() {
    if (!els.formCreateEdit) return;

    state.formMode = 'create';
    state.editingRow = null;
    els.formCreateEdit.reset();

    els.inputIdPagina.value = '0';
    els.inputEsFija.value = '0';
    els.inputTipoPagina.value = 'real';
    els.inputVisibleMenu.value = '1';
    els.inputEstado.value = '0';
    els.inputOrdenMenu.value = '0';

    renderParentSelect('', 0);
    renderPermissionSelect('');
    renderModuleSelect('');
    renderSectionSelect('', '');

    if (els.modalFormTitle) els.modalFormTitle.textContent = 'Crear pagina logica';
    syncFormByTypeAndMode();

    if (window.jQuery) {
      window.jQuery(els.modalForm).modal('show');
    }
  }

  function openEditModal(idPagina) {
    var row = findRowById(idPagina);
    if (!row || !els.formCreateEdit) return;

    state.formMode = 'edit';
    state.editingRow = row;
    els.formCreateEdit.reset();

    var isFija = Number(row.es_fija) === 1;
    var tipo = Number(row.es_contenedor) === 1 ? 'contenedor' : 'real';

    els.inputIdPagina.value = String(row.id_pagina || 0);
    els.inputEsFija.value = isFija ? '1' : '0';
    els.inputTipoPagina.value = tipo;
    els.inputSlug.value = String(row.slug_pagina || '');
    els.inputTituloMenu.value = String(row.titulo_menu || '');
    els.inputTituloPagina.value = String(row.titulo_pagina || '');
    els.inputDescripcion.value = String(row.descripcion_pagina || '');
    els.inputVisibleMenu.value = String(Number(row.visible_menu) === 1 ? 1 : 0);
    els.inputEstado.value = String(Number(row.estado) === 1 ? 1 : 0);
    els.inputOrdenMenu.value = String(Number(row.orden_menu || 0));
    els.inputIcono.value = String(row.icono || '');

    renderParentSelect(row.id_padre, row.id_pagina);
    renderPermissionSelect(row.id_permiso_requerido);
    renderModuleSelect(row.modulo_codigo || '');
    renderSectionSelect(row.modulo_codigo || '', row.archivo_section || '');

    if (els.modalFormTitle) {
      els.modalFormTitle.textContent = isFija ? 'Editar pagina fija' : 'Editar pagina logica';
    }

    syncFormByTypeAndMode();

    if (window.jQuery) {
      window.jQuery(els.modalForm).modal('show');
    }
  }

  function buildCreatePayload() {
    return {
      csrf_token: getCsrf(),
      tipo_pagina: String(els.inputTipoPagina.value || 'real'),
      titulo_menu: String(els.inputTituloMenu.value || '').trim(),
      titulo_pagina: String(els.inputTituloPagina.value || '').trim(),
      descripcion_pagina: String(els.inputDescripcion.value || '').trim(),
      slug_pagina: String(els.inputSlug.value || '').trim(),
      id_padre: String(els.inputIdPadre.value || ''),
      visible_menu: String(els.inputVisibleMenu.value || '1'),
      icono: String(els.inputIcono.value || '').trim(),
      orden_menu: String(els.inputOrdenMenu.value || '0'),
      estado: String(els.inputEstado.value || '0'),
      modulo_codigo: String(els.inputModuloCodigo.value || ''),
      archivo_section: String(els.inputArchivoSection.value || '')
    };
  }

  function buildUpdatePayload() {
    var idPagina = Number(els.inputIdPagina.value || 0);
    var isFija = Number(els.inputEsFija.value || 0) === 1;

    var payload = {
      csrf_token: getCsrf(),
      id_pagina: String(idPagina)
    };

    if (isFija) {
      payload.titulo_menu = String(els.inputTituloMenu.value || '').trim();
      payload.titulo_pagina = String(els.inputTituloPagina.value || '').trim();
      payload.descripcion_pagina = String(els.inputDescripcion.value || '').trim();
      payload.icono = String(els.inputIcono.value || '').trim();
      payload.orden_menu = String(els.inputOrdenMenu.value || '0');
      payload.visible_menu = String(els.inputVisibleMenu.value || '1');
      return payload;
    }

    payload.tipo_pagina = String(els.inputTipoPagina.value || 'real');
    payload.titulo_menu = String(els.inputTituloMenu.value || '').trim();
    payload.titulo_pagina = String(els.inputTituloPagina.value || '').trim();
    payload.descripcion_pagina = String(els.inputDescripcion.value || '').trim();
    payload.id_padre = String(els.inputIdPadre.value || '');
    payload.visible_menu = String(els.inputVisibleMenu.value || '1');
    payload.icono = String(els.inputIcono.value || '').trim();
    payload.orden_menu = String(els.inputOrdenMenu.value || '0');
    payload.estado = String(els.inputEstado.value || '0');
    payload.modulo_codigo = String(els.inputModuloCodigo.value || '');
    payload.archivo_section = String(els.inputArchivoSection.value || '');
    payload.id_permiso_requerido = String(els.inputIdPermiso.value || '');

    return payload;
  }

  function submitForm() {
    hideAlert();
    setUiLoading(true);

    var isCreate = state.formMode === 'create';
    var url = isCreate ? urls.create : urls.update;
    var payload = isCreate ? buildCreatePayload() : buildUpdatePayload();

    postForm(url, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, isCreate ? 'No se pudo crear pagina.' : 'No se pudo actualizar pagina.');
      if (!okResponse) return;

      if (window.jQuery) {
        window.jQuery(els.modalForm).modal('hide');
      }

      showAlert('success', okResponse.message || (isCreate ? 'Pagina creada correctamente.' : 'Pagina actualizada correctamente.'));
      loadList(isCreate ? 1 : state.page);
    });
  }

  function openToggleModal(idPagina, estadoObjetivo) {
    var row = findRowById(idPagina);
    if (!row) return;

    els.toggleIdPagina.value = String(idPagina);
    els.toggleEstadoObjetivo.value = String(estadoObjetivo);

    var accion = Number(estadoObjetivo) === 1 ? 'activar' : 'inactivar';
    els.toggleMessage.textContent = 'Confirma ' + accion + ' la pagina "' + (row.titulo_menu || '') + '".';

    if (window.jQuery) {
      window.jQuery(els.modalToggle).modal('show');
    }
  }

  function submitToggle() {
    hideAlert();
    setUiLoading(true);

    var payload = {
      csrf_token: getCsrf(),
      id_pagina: String(els.toggleIdPagina.value || '0'),
      estado_objetivo: String(els.toggleEstadoObjetivo.value || '0')
    };

    postForm(urls.toggle, payload, function (response) {
      setUiLoading(false);
      var okResponse = handleResponse(response, 'No se pudo actualizar estado de pagina.');
      if (!okResponse) return;

      if (window.jQuery) {
        window.jQuery(els.modalToggle).modal('hide');
      }

      showAlert('success', okResponse.message || 'Estado de pagina actualizado correctamente.');
      loadList(state.page);
    });
  }

  if (els.btnSearch) {
    els.btnSearch.addEventListener('click', function () {
      state.search = String(els.search.value || '').trim();
      state.estado = String(els.estadoFilter.value || '').trim();
      state.tipo = String(els.tipoFilter.value || '').trim();
      state.perPage = Number(els.perPage.value || 10) || 10;
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

  if (els.btnNew && can.create) {
    els.btnNew.addEventListener('click', openCreateModal);
  }

  if (els.inputModuloCodigo) {
    els.inputModuloCodigo.addEventListener('change', function () {
      renderSectionSelect(els.inputModuloCodigo.value, '');
    });
  }

  if (els.inputTipoPagina) {
    els.inputTipoPagina.addEventListener('change', function () {
      syncFormByTypeAndMode();
    });
  }

  if (els.formCreateEdit) {
    els.formCreateEdit.addEventListener('submit', function (event) {
      event.preventDefault();
      submitForm();
    });
  }

  if (els.btnConfirmToggle) {
    els.btnConfirmToggle.addEventListener('click', function () {
      submitToggle();
    });
  }

  if (els.tableBody) {
    els.tableBody.addEventListener('click', function (event) {
      var btnEdit = event.target.closest('.pgl-action-edit');
      if (btnEdit) {
        event.preventDefault();
        openEditModal(Number(btnEdit.getAttribute('data-id') || 0));
        return;
      }

      var btnToggle = event.target.closest('.pgl-action-toggle');
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
