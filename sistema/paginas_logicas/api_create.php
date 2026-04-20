<?php
require_once __DIR__ . '/../includes/paginas_logicas_admin.php';

$permissions = pgl_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = pgl_guard_request('POST', $csrfToken, $permissions['create']);
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
        'message' => 'No se pudo crear pagina por esquema incompleto.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$unexpected = pgl_reject_unexpected_fields($_POST, [
    'csrf_token',
    'tipo_pagina',
    'titulo_menu',
    'titulo_pagina',
    'descripcion_pagina',
    'slug_pagina',
    'id_padre',
    'visible_menu',
    'icono',
    'orden_menu',
    'estado',
    'modulo_codigo',
    'archivo_section',
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

$tipoPagina = trim((string) ($_POST['tipo_pagina'] ?? ''));
if ($tipoPagina !== 'contenedor' && $tipoPagina !== 'real') {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'tipo_invalido',
        'message' => 'Tipo de pagina invalido.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

list($okTituloMenu, $tituloMenu, $errTituloMenu) = pgl_validate_menu_text($_POST['titulo_menu'] ?? '', 'Titulo menu', 120);
list($okTituloPagina, $tituloPagina, $errTituloPagina) = pgl_validate_menu_text($_POST['titulo_pagina'] ?? '', 'Titulo pagina', 150);
list($okDescripcion, $descripcionPagina, $errDescripcion) = pgl_validate_optional_text($_POST['descripcion_pagina'] ?? '', 255);
list($okSlug, $slugPagina, $errSlug) = pgl_validate_slug($_POST['slug_pagina'] ?? '');
list($okPadre, $idPadre, $errPadre) = pgl_parse_positive_id_or_null($_POST['id_padre'] ?? '');
list($okIcono, $icono, $errIcono) = pgl_validate_optional_text($_POST['icono'] ?? '', 120);

$visibleMenu = pgl_parse_bool01($_POST['visible_menu'] ?? '1', 1);
$estado = pgl_parse_bool01($_POST['estado'] ?? '0', 0);
$ordenMenu = pgl_parse_int($_POST['orden_menu'] ?? '0', 0);
$moduloCodigoRaw = trim((string) ($_POST['modulo_codigo'] ?? ''));
$archivoSectionRaw = trim((string) ($_POST['archivo_section'] ?? ''));

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
if (!$okSlug) {
    $errors['slug_pagina'] = $errSlug;
}
if (!$okPadre) {
    $errors['id_padre'] = $errPadre;
}
if (!$okIcono) {
    $errors['icono'] = $errIcono;
}
if ($errors) {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'validacion',
        'message' => 'Revisa los valores enviados.',
        'errors' => $errors,
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$esContenedor = ($tipoPagina === 'contenedor') ? 1 : 0;

$pdo = db();
$ownTx = false;

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $ownTx = true;
    }

    $rowSlug = pgl_fetch_page_by_slug($slugPagina, true);
    if ($rowSlug) {
        throw new RuntimeException('slug_duplicado');
    }

    list($okParentRules, $idPadreFinal, $parentRuleError) = pgl_validate_parent_rules($idPadre, 0, true);
    if (!$okParentRules) {
        throw new RuntimeException('padre_invalido:' . $parentRuleError);
    }

    list($okContainerRules, $containerRuleError) = pgl_validate_container_level_rules($esContenedor, $idPadreFinal);
    if (!$okContainerRules) {
        throw new RuntimeException('tipo_jerarquia_invalida:' . $containerRuleError);
    }

    $modulesCatalog = pgl_scan_modules_catalog();
    list($okModuleState, $moduloCodigoFinal, $archivoSectionFinal, $moduleStateError) = pgl_validate_module_section_by_state(
        $esContenedor,
        $estado,
        $moduloCodigoRaw,
        $archivoSectionRaw,
        $modulesCatalog
    );
    if (!$okModuleState) {
        throw new RuntimeException('modulo_section_invalido:' . $moduleStateError);
    }

    $idPermisoRequerido = null;
    $permisoCodigoAuto = '';

    if ($esContenedor === 0) {
        list($okPermiso, $idPermisoGenerado, $permisoStatus) = pgl_upsert_permission_for_slug($slugPagina, $tituloPagina);
        if (!$okPermiso || $idPermisoGenerado <= 0) {
            throw new RuntimeException('permiso_base_error');
        }

        $idPermisoRequerido = (int) $idPermisoGenerado;
        $permisoCodigoAuto = pgl_normalized_permission_code_by_slug($slugPagina);
        pgl_assign_permission_to_protected_roles($idPermisoRequerido);
    }

    $stInsert = $pdo->prepare("\n        INSERT INTO pag_paginas\n            (titulo_menu, titulo_pagina, descripcion_pagina, slug_pagina, id_padre, es_contenedor, visible_menu, modulo_codigo, archivo_section, id_permiso_requerido, icono, orden_menu, es_fija, estado, creado_en, actualizado_en)\n        VALUES\n            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())\n    ");
    $stInsert->execute([
        $tituloMenu,
        $tituloPagina,
        $descripcionPagina,
        $slugPagina,
        $idPadreFinal,
        $esContenedor,
        $visibleMenu,
        $moduloCodigoFinal,
        $archivoSectionFinal,
        $idPermisoRequerido,
        $icono,
        $ordenMenu,
        $estado,
    ]);

    $newPageId = (int) $pdo->lastInsertId();
    if ($newPageId <= 0) {
        throw new RuntimeException('pagina_no_creada');
    }

    if ($ownTx) {
        $pdo->commit();
    }

    pgl_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Pagina creada correctamente.',
        'data' => [
            'id_pagina' => $newPageId,
            'slug_pagina' => $slugPagina,
            'permiso_codigo_base' => $permisoCodigoAuto,
        ],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_creacion';
    $message = 'No se pudo crear la pagina.';
    $validationErrors = [];
    $httpStatus = 500;

    if ($e instanceof RuntimeException) {
        $msg = $e->getMessage();
        if ($msg === 'slug_duplicado') {
            $code = 'slug_duplicado';
            $message = 'El slug ya existe.';
            $validationErrors['slug_pagina'] = 'El slug ya existe.';
            $httpStatus = 422;
        } elseif (strpos($msg, 'padre_invalido:') === 0) {
            $code = 'padre_invalido';
            $message = substr($msg, strlen('padre_invalido:'));
            $validationErrors['id_padre'] = $message;
            $httpStatus = 422;
        } elseif (strpos($msg, 'modulo_section_invalido:') === 0) {
            $code = 'modulo_section_invalido';
            $message = substr($msg, strlen('modulo_section_invalido:'));
            $validationErrors['modulo_codigo'] = $message;
            $httpStatus = 422;
        } elseif (strpos($msg, 'tipo_jerarquia_invalida:') === 0) {
            $code = 'tipo_jerarquia_invalida';
            $message = substr($msg, strlen('tipo_jerarquia_invalida:'));
            $validationErrors['tipo_pagina'] = $message;
            $httpStatus = 422;
        } elseif ($msg === 'permiso_base_error') {
            $code = 'permiso_base_error';
            $message = 'No se pudo generar el permiso base .view.';
        }
    }

    pgl_json_response($httpStatus, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $validationErrors,
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}
