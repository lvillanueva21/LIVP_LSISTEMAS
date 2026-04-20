<?php
require_once __DIR__ . '/../includes/paginas_logicas_admin.php';

$permissions = pgl_permission_codes();
$csrfToken = (string) ($_POST['csrf_token'] ?? '');
$guard = pgl_guard_request('POST', $csrfToken, $permissions['toggle_state']);
if (empty($guard['ok'])) {
    pgl_json_response((int) ($guard['http_status'] ?? 401), [
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
        'message' => 'No se pudo actualizar estado por esquema incompleto.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

$unexpected = pgl_reject_unexpected_fields($_POST, [
    'csrf_token',
    'id_pagina',
    'estado_objetivo',
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
$estadoRaw = trim((string) ($_POST['estado_objetivo'] ?? ''));
if ($idPagina <= 0 || ($estadoRaw !== '0' && $estadoRaw !== '1')) {
    pgl_json_response(422, [
        'ok' => false,
        'code' => 'parametros_invalidos',
        'message' => 'Parametros invalidos.',
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}
$estadoObjetivo = (int) $estadoRaw;

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

    if ((int) ($current['es_fija'] ?? 0) === 1) {
        throw new RuntimeException('pagina_fija_estado_bloqueado');
    }

    $estadoActual = ((int) ($current['estado'] ?? 0) === 1) ? 1 : 0;
    if ($estadoActual === $estadoObjetivo) {
        if ($ownTx) {
            $pdo->commit();
        }
        pgl_json_response(200, [
            'ok' => true,
            'code' => 'sin_cambios',
            'message' => 'No hubo cambios en el estado.',
            'csrf_token_nuevo' => pgl_new_csrf_token(),
        ]);
    }

    $esContenedor = ((int) ($current['es_contenedor'] ?? 0) === 1) ? 1 : 0;
    $idPermiso = isset($current['id_permiso_requerido']) ? (int) $current['id_permiso_requerido'] : 0;

    if ($esContenedor === 0) {
        $modulesCatalog = pgl_scan_modules_catalog();
        list($okModuleState, $moduloCodigoFinal, $archivoSectionFinal, $moduleStateError) = pgl_validate_module_section_by_state(
            0,
            $estadoObjetivo,
            (string) ($current['modulo_codigo'] ?? ''),
            (string) ($current['archivo_section'] ?? ''),
            $modulesCatalog
        );
        if (!$okModuleState) {
            throw new RuntimeException('modulo_section_invalido:' . $moduleStateError);
        }
    }

    $st = $pdo->prepare("
        UPDATE pag_paginas
        SET estado = ?,
            actualizado_en = NOW()
        WHERE id_pagina = ?
    ");
    $st->execute([$estadoObjetivo, $idPagina]);

    $sesionesInvalidas = 0;
    $criticalAccessChange = ($esContenedor === 0 && $idPermiso > 0);
    if ($criticalAccessChange) {
        $sesionesInvalidas = pgl_close_sessions_by_permission_ids([$idPermiso]);
    }

    if ($ownTx) {
        $pdo->commit();
    }

    pgl_json_response(200, [
        'ok' => true,
        'code' => 'ok',
        'message' => 'Estado de la pagina actualizado correctamente.',
        'data' => [
            'id_pagina' => $idPagina,
            'estado_objetivo' => $estadoObjetivo,
            'sesiones_invalidadas' => (int) $sesionesInvalidas,
        ],
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
} catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $code = 'error_estado';
    $message = 'No se pudo actualizar estado de la pagina.';
    $errors = [];

    if ($e instanceof RuntimeException) {
        $msg = $e->getMessage();
        if ($msg === 'pagina_no_encontrada') {
            $code = 'pagina_no_encontrada';
            $message = 'Pagina no encontrada.';
        } elseif ($msg === 'pagina_fija_estado_bloqueado') {
            $code = 'pagina_fija_estado_bloqueado';
            $message = 'No se permite cambiar estado en paginas fijas en esta V1.';
        } elseif (strpos($msg, 'modulo_section_invalido:') === 0) {
            $code = 'modulo_section_invalido';
            $message = substr($msg, strlen('modulo_section_invalido:'));
            $errors['modulo_codigo'] = $message;
        }
    }

    pgl_json_response(500, [
        'ok' => false,
        'code' => $code,
        'message' => $message,
        'errors' => $errors,
        'csrf_token_nuevo' => pgl_new_csrf_token(),
    ]);
}

