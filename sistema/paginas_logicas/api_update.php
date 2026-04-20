<?php
require_once __DIR__ . '/../includes/paginas_logicas_admin.php';

$permissions = pgl_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = pgl_guard_request('POST', $csrfToken, $permissions['edit']);
if (empty($guard['ok'])) {
    pgl_json_response((int) $guard['http_status'], [
        'ok' => false,
        'code' => (string) ($guard['code'] ?? 'error'),
        'message' => (string) ($guard['message'] ?? 'No se pudo procesar la solicitud.'),
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

if (!pgl_schema_ready()) {
    pgl_json_response(500, [
        'ok' => false,
        'code' => 'schema_no_disponible',
        'message' => 'No se pudo actualizar pagina por esquema incompleto.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$unexpected = pgl_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_pagina',
    'tipo_pagina',
    'titulo_menu',
    'titulo_pagina',
    'descripcion_pagina',
    'id_padre',
    'visible_menu',
    'icono',
    'orden_menu',
    'estado',
    'modulo_codigo',
    'archivo_section',
    'id_permiso_requerido',
]);
if ($unexpected) {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'payload_invalido',
        'message' => 'Se detectaron campos no permitidos.',
        'errors' => ['unexpected_fields' => $unexpected],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$idPagina = isset($_POST['id_pagina']) ? (int) $_POST['id_pagina'] : 0;
if ($idPagina <= 0) {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'id_invalido',
        'message' => 'Pagina invalida.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$pdo = db();
$ownTx = false;

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $current = pgl_fetch_page_by_id($idPagina, true);
    if (!$current) {
        throw new RuntimeException('pagina_no_encontrada');
    }

    $isFixed = ((int) ($current['es_fija'] ?? 0) === 1);

    if ($isFixed) {
        $blockedFields = ['tipo_pagina', 'id_padre', 'estado', 'modulo_codigo', 'archivo_section', 'id_permiso_requerido'];
        foreach ($blockedFields as $blockedField) {
            if (array_key_exists($blockedField, $_POST)) {
                throw new RuntimeException('campo_bloqueado:' . $blockedField);
            }
        }
    }

    $tituloMenuInput = array_key_exists('titulo_menu', $_POST) ? $_POST['titulo_menu'] : $current['titulo_menu'];
    $tituloPaginaInput = array_key_exists('titulo_pagina', $_POST) ? $_POST['titulo_pagina'] : $current['titulo_pagina'];
    $descripcionInput = array_key_exists('descripcion_pagina', $_POST) ? $_POST['descripcion_pagina'] : $current['descripcion_pagina'];
    $iconoInput = array_key_exists('icono', $_POST) ? $_POST['icono'] : $current['icono'];

    list($okTituloMenu, $tituloMenu, $errTituloMenu) = pgl_validate_menu_text($tituloMenuInput, 'Titulo menu', 120);
    list($okTituloPagina, $tituloPagina, $errTituloPagina) = pgl_validate_menu_text($tituloPaginaInput, 'Titulo pagina', 150);
    list($okDescripcion, $descripcionPagina, $errDescripcion) = pgl_validate_optional_text($descripcionInput, 255);
    list($okIcono, $icono, $errIcono) = pgl_validate_optional_text($iconoInput, 120);

    $visibleMenu = array_key_exists('visible_menu', $_POST)
        ? pgl_parse_bool01($_POST['visible_menu'], (int) ($current['visible_menu'] ?? 1))
        : ((int) ($current['visible_menu'] ?? 1) === 1 ? 1 : 0);

    $ordenMenu = array_key_exists('orden_menu', $_POST)
        ? pgl_parse_int($_POST['orden_menu'], (int) ($current['orden_menu'] ?? 0))
        : (int) ($current['orden_menu'] ?? 0);

    $errors = [];
    if (!$okTituloMenu) {
        $errors['titulo_menu'] = $errTituloMenu;
    }
    if (!$okTituloPagina) {
        $errors['titulo_pagina'] = $errTituloPagina;
    }
    if (!$okDescripcion) {
        $errors['descripcion_pagina'] = $errDescripcion;
    }
    if (!$okIcono) {
        $errors['icono'] = $errIcono;
    }

    $currentEsContenedor = ((int) ($current['es_contenedor'] ?? 0) === 1) ? 1 : 0;
    $currentEstado = ((int) ($current['estado'] ?? 0) === 1) ? 1 : 0;
    $currentPadre = isset($current['id_padre']) ? (int) $current['id_padre'] : null;
    $currentModulo = trim((string) ($current['modulo_codigo'] ?? ''));
    $currentSection = trim((string) ($current['archivo_section'] ?? ''));
    $currentPermiso = isset($current['id_permiso_requerido']) ? (int) $current['id_permiso_requerido'] : null;

    $tipoPaginaInput = array_key_exists('tipo_pagina', $_POST) ? trim((string) $_POST['tipo_pagina']) : '';
    if ($tipoPaginaInput !== '' && $tipoPaginaInput !== 'contenedor' && $tipoPaginaInput !== 'real') {
        $errors['tipo_pagina'] = 'Tipo de pagina invalido.';
    }

    $esContenedor = $currentEsContenedor;
    if (!$isFixed && $tipoPaginaInput !== '') {
        $esContenedor = ($tipoPaginaInput === 'contenedor') ? 1 : 0;
    }

    $estado = $currentEstado;
    if (!$isFixed && array_key_exists('estado', $_POST)) {
        $estado = pgl_parse_bool01($_POST['estado'], $currentEstado);
    }

    $idPadre = $currentPadre;
    if (!$isFixed && array_key_exists('id_padre', $_POST)) {
        list($okPadre, $parsedPadre, $errPadre) = pgl_parse_positive_id_or_null($_POST['id_padre']);
        if (!$okPadre) {
            $errors['id_padre'] = $errPadre;
        } else {
            $idPadre = $parsedPadre;
        }
    }

    if (!$errors) {
        list($okParentRules, $idPadreFinal, $parentRuleError) = pgl_validate_parent_rules($idPadre, $idPagina, true);
        if (!$okParentRules) {
            $errors['id_padre'] = $parentRuleError;
        } else {
            $idPadre = $idPadreFinal;
        }
    }

    if (!$errors) {
        list($okContainerRules, $containerRuleError) = pgl_validate_container_level_rules($esContenedor, $idPadre);
        if (!$okContainerRules) {
            $errors['tipo_pagina'] = $containerRuleError;
        }
    }

    if (!$errors) {
        list($okChildrenRules, $childrenRuleError) = pgl_validate_update_children_rules($idPagina, $esContenedor, $idPadre, true);
        if (!$okChildrenRules) {
            $errors['tipo_pagina'] = $childrenRuleError;
        }
    }

    $moduloCodigo = $currentModulo;
    $archivoSection = $currentSection;
    if (!$isFixed) {
        if (array_key_exists('modulo_codigo', $_POST)) {
            $moduloCodigo = trim((string) $_POST['modulo_codigo']);
        }
        if (array_key_exists('archivo_section', $_POST)) {
            $archivoSection = trim((string) $_POST['archivo_section']);
        }
    }

    $idPermisoRequerido = $currentPermiso;
    if (!$isFixed && array_key_exists('id_permiso_requerido', $_POST)) {
        list($okPermisoParsed, $parsedPermiso, $errPermiso) = pgl_parse_positive_id_or_null($_POST['id_permiso_requerido']);
        if (!$okPermisoParsed) {
            $errors['id_permiso_requerido'] = $errPermiso;
        } else {
            $idPermisoRequerido = $parsedPermiso;
        }
    }

    $modulesCatalog = pgl_scan_modules_catalog();

    if ($esContenedor === 1) {
        $moduloCodigo = null;
        $archivoSection = null;
        $idPermisoRequerido = null;
    } else {
        list($okModuleState, $moduloCodigoFinal, $archivoSectionFinal, $moduleStateError) = pgl_validate_module_section_by_state(
            0,
            $estado,
            $moduloCodigo,
            $archivoSection,
            $modulesCatalog
        );
        if (!$okModuleState) {
            $errors['modulo_codigo'] = $moduleStateError;
        } else {
            $moduloCodigo = $moduloCodigoFinal;
            $archivoSection = $archivoSectionFinal;
        }

        if ($idPermisoRequerido === null || (int) $idPermisoRequerido <= 0) {
            list($okPermisoAuto, $idPermisoAuto, $statusPermiso) = pgl_upsert_permission_for_slug((string) $current['slug_pagina'], $tituloPagina);
            if (!$okPermisoAuto || $idPermisoAuto <= 0) {
                $errors['id_permiso_requerido'] = 'No se pudo definir permiso base para la pagina real.';
            } else {
                $idPermisoRequerido = (int) $idPermisoAuto;
                pgl_assign_permission_to_protected_roles($idPermisoRequerido);
            }
        }

        if ((int) $idPermisoRequerido > 0) {
            $map = pgl_fetch_active_permission_ids_map_by_ids([(int) $idPermisoRequerido], true);
            if (empty($map[(int) $idPermisoRequerido])) {
                $errors['id_permiso_requerido'] = 'Permiso requerido invalido o inactivo.';
            } else {
                pgl_assign_permission_to_protected_roles((int) $idPermisoRequerido);
            }
        }
    }

    if ($errors) {
        throw new RuntimeException('validacion:' . json_encode($errors));
    }

    $oldRealActive = ($currentEsContenedor === 0 && $currentEstado === 1 && (int) $currentPermiso > 0);
    $newRealActive = ($esContenedor === 0 && $estado === 1 && (int) $idPermisoRequerido > 0);
    $oldPermissionId = (int) $currentPermiso;
    $newPermissionId = (int) $idPermisoRequerido;

    $criticalAccessChange = false;
    if ($oldRealActive && !$newRealActive) {
        $criticalAccessChange = true;
    } elseif (!$oldRealActive && $newRealActive) {
        $criticalAccessChange = true;
    } elseif ($oldRealActive && $newRealActive && $oldPermissionId !== $newPermissionId) {
        $criticalAccessChange = true;
    }

    $permissionIdsForInvalidate = [];
    if ($criticalAccessChange) {
        if ($oldPermissionId > 0) {
            $permissionIdsForInvalidate[] = $oldPermissionId;
        }
        if ($newPermissionId > 0) {
            $permissionIdsForInvalidate[] = $newPermissionId;
        }
        $permissionIdsForInvalidate = array_values(array_unique($permissionIdsForInvalidate));
    }

    $stUpdate = $pdo->prepare("\n        UPDATE pag_paginas\n        SET titulo_menu = ?,\n            titulo_pagina = ?,\n            descripcion_pagina = ?,\n            id_padre = ?,\n            es_contenedor = ?,\n            visible_menu = ?,\n            modulo_codigo = ?,\n            archivo_section = ?,\n            id_permiso_requerido = ?,\n            icono = ?,\n            orden_menu = ?,\n            estado = ?,\n            actualizado_en = NOW()\n        WHERE id_pagina = ?\n    ");
    $stUpdate->execute([
        $tituloMenu,
        $tituloPagina,
        $descripcionPagina,
        $idPadre,
        $esContenedor,
        $visibleMenu,
        $moduloCodigo,
        $archivoSection,
        $idPermisoRequerido,
        $icono,
        $ordenMenu,
        $estado,
        $idPagina,
    ]);

    $sesionesInvalidas = 0;
    if ($criticalAccessChange && $permissionIdsForInvalidate) {
        $sesionesInvalidas = pgl_close_sessions_by_permission_ids($permissionIdsForInvalidate);
    }

    if ($ownTx) {
        $pdo->commit();
    }

    pgl_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Pagina actualizada correctamente.',
        'data' => [
            'id_pagina' => $idPagina,
            'slug_pagina' => (string) $current['slug_pagina'],
            'sesiones_invalidadas' => (int) $sesionesInvalidas,
        ],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_actualizacion';
    $message = 'No se pudo actualizar la pagina.';
    $validationErrors = [];

    if ($e instanceof RuntimeException) {
        $msg = $e->getMessage();
        if ($msg === 'pagina_no_encontrada') {
            $code = 'pagina_no_encontrada';
            $message = 'Pagina no encontrada.';
        } elseif (strpos($msg, 'campo_bloqueado:') === 0) {
            $code = 'campo_bloqueado';
            $field = substr($msg, strlen('campo_bloqueado:'));
            $message = 'Campo no editable para pagina fija.';
            $validationErrors[$field] = 'Campo bloqueado en esta V1.';
        } elseif (strpos($msg, 'validacion:') === 0) {
            $code = 'validacion';
            $message = 'Revisa los valores enviados.';
            $json = substr($msg, strlen('validacion:'));
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $validationErrors = $decoded;
            }
        }
    }

    pgl_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $validationErrors,
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}
