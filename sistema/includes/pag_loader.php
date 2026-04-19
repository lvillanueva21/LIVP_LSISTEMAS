<?php
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Acceso directo no permitido.');
}

require_once __DIR__ . '/pag_access.php';

function pag_loader_resolve_module_file($moduloCodigo, $archivoSection)
{
    $moduloCodigo = trim((string) $moduloCodigo);
    $archivoSection = trim((string) $archivoSection);

    if (!pag_schema_is_valid_identifier($moduloCodigo) || !pag_schema_is_valid_identifier($archivoSection)) {
        return '';
    }

    $modulesBaseDir = realpath(__DIR__ . '/../modules');
    if ($modulesBaseDir === false) {
        return '';
    }

    $candidate = $modulesBaseDir . DIRECTORY_SEPARATOR . $moduloCodigo . DIRECTORY_SEPARATOR . $archivoSection . '.php';
    $resolved = realpath($candidate);
    if ($resolved === false || !is_file($resolved)) {
        return '';
    }

    $modulesBaseDir = rtrim($modulesBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $resolvedPrefix = substr($resolved, 0, strlen($modulesBaseDir));
    if ($resolvedPrefix !== $modulesBaseDir) {
        return '';
    }

    return $resolved;
}

function pag_loader_get_page_context($slug)
{
    $slug = trim((string) $slug);

    $context = [
        'ok' => false,
        'code' => 'error',
        'message' => 'No se pudo cargar la pagina solicitada.',
        'slug' => $slug,
        'page' => null,
        'module_file' => '',
    ];

    if (!pag_schema_tables_ready()) {
        $context['code'] = 'schema_no_disponible';
        $context['message'] = 'La configuracion de paginas no esta disponible.';
        return $context;
    }

    if (!pag_schema_is_valid_slug($slug)) {
        $context['code'] = 'slug_invalido';
        $context['message'] = 'La pagina solicitada es invalida.';
        return $context;
    }

    $pageRow = pag_get_page_row_by_slug($slug);
    if (!$pageRow) {
        $context['code'] = 'pagina_no_encontrada';
        $context['message'] = 'La pagina solicitada no existe.';
        return $context;
    }

    if ((int) ($pageRow['estado'] ?? 0) !== 1) {
        $context['code'] = 'pagina_inactiva';
        $context['message'] = 'La pagina solicitada no esta activa.';
        return $context;
    }

    if ((int) ($pageRow['es_contenedor'] ?? 0) === 1) {
        $context['code'] = 'pagina_contenedor';
        $context['message'] = 'La pagina solicitada es un contenedor de menu.';
        return $context;
    }

    if (!pag_can_access_page_row($pageRow)) {
        $context['code'] = 'acceso_denegado';
        $context['message'] = 'No tienes permiso para acceder a esta pagina.';
        return $context;
    }

    $moduleFile = pag_loader_resolve_module_file(
        (string) ($pageRow['modulo_codigo'] ?? ''),
        (string) ($pageRow['archivo_section'] ?? '')
    );
    if ($moduleFile === '') {
        $context['code'] = 'modulo_no_disponible';
        $context['message'] = 'El modulo de la pagina no esta disponible.';
        return $context;
    }

    $context['ok'] = true;
    $context['code'] = 'ok';
    $context['message'] = 'ok';
    $context['page'] = $pageRow;
    $context['module_file'] = $moduleFile;
    return $context;
}
