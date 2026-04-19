<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

require_once __DIR__ . '/../includes/pag_schema.php';

function pag_runner_usage()
{
    $lines = [];
    $lines[] = 'Uso:';
    $lines[] = '  php sistema/scripts/pag_seed_runner.php sync';
    $lines[] = '  php sistema/scripts/pag_seed_runner.php schema';
    $lines[] = '  php sistema/scripts/pag_seed_runner.php seed';
    $lines[] = '';
    $lines[] = 'Acciones:';
    $lines[] = '  sync   Crea/actualiza tablas pag_* y siembra permisos/paginas fijas.';
    $lines[] = '  schema Solo crea/actualiza tablas pag_*.';
    $lines[] = '  seed   Solo siembra permisos/paginas fijas y asigna permisos a Superadmin.';
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function pag_runner_print_result(array $result)
{
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
}

$action = 'sync';
if (!empty($argv[1]) && strpos((string) $argv[1], '--') !== 0) {
    $action = trim((string) $argv[1]);
}
if ($action === '--help' || $action === '-h') {
    echo pag_runner_usage();
    exit(0);
}

switch ($action) {
    case 'schema':
        $result = pag_schema_ensure_tables();
        pag_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    case 'seed':
        $result = pag_schema_seed_fixed_pages();
        pag_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    case 'sync':
        $result = pag_schema_sync_all();
        pag_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    default:
        echo pag_runner_usage();
        exit(2);
}
