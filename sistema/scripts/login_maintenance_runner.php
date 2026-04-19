<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

require_once __DIR__ . '/../includes/login_maintenance.php';

function lsis_runner_usage()
{
    $usage = [];
    $usage[] = 'Uso:';
    $usage[] = '  php sistema/scripts/login_maintenance_runner.php cleanup [--actor-admin-id=0] [--dry-run=0] [--batch-size=500]';
    $usage[] = '  php sistema/scripts/login_maintenance_runner.php close-session --session-id=ID --actor-admin-id=ID';
    $usage[] = '  php sistema/scripts/login_maintenance_runner.php close-user --user-id=ID --actor-admin-id=ID';
    $usage[] = '';
    $usage[] = 'Acciones:';
    $usage[] = '  cleanup       Ejecuta limpieza 90/30/7 (sesiones/intentos/bloqueos).';
    $usage[] = '  close-session Cierra una sesion activa con motivo forzada_admin.';
    $usage[] = '  close-user    Cierra sesiones activas de un usuario con motivo forzada_admin.';
    $usage[] = '';
    $usage[] = 'Notas:';
    $usage[] = '  - El auto-cierre de la sesion activa del admin esta bloqueado por defecto.';
    $usage[] = '  - No hay override expuesto en este runner.';

    return implode(PHP_EOL, $usage) . PHP_EOL;
}

function lsis_runner_parse_args(array $argv)
{
    $parsed = [
        'action' => 'cleanup',
        'actor_admin_id' => 0,
        'session_id' => 0,
        'user_id' => 0,
        'dry_run' => 0,
        'batch_size' => 500,
        'help' => false,
    ];

    if (!empty($argv[1]) && strpos((string) $argv[1], '--') !== 0) {
        $parsed['action'] = trim((string) $argv[1]);
    }

    foreach ($argv as $idx => $arg) {
        if ($idx === 0 || $idx === 1) {
            continue;
        }

        $arg = trim((string) $arg);
        if ($arg === '') {
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $parsed['help'] = true;
            continue;
        }
        if (strpos($arg, '--') !== 0) {
            continue;
        }

        $eqPos = strpos($arg, '=');
        if ($eqPos === false) {
            $key = substr($arg, 2);
            $value = '1';
        } else {
            $key = substr($arg, 2, $eqPos - 2);
            $value = substr($arg, $eqPos + 1);
        }

        $key = trim((string) $key);
        if ($key === '') {
            continue;
        }

        switch ($key) {
            case 'actor-admin-id':
                $parsed['actor_admin_id'] = (int) $value;
                break;
            case 'session-id':
                $parsed['session_id'] = (int) $value;
                break;
            case 'user-id':
                $parsed['user_id'] = (int) $value;
                break;
            case 'dry-run':
                $parsed['dry_run'] = ((int) $value === 1) ? 1 : 0;
                break;
            case 'batch-size':
                $parsed['batch_size'] = (int) $value;
                break;
            default:
                break;
        }
    }

    return $parsed;
}

function lsis_runner_print_result(array $result)
{
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
}

$args = lsis_runner_parse_args($argv);
if ($args['help']) {
    echo lsis_runner_usage();
    exit(0);
}

$action = trim((string) $args['action']);
if ($action === '') {
    $action = 'cleanup';
}

switch ($action) {
    case 'cleanup':
        $result = lsis_maintenance_run_cleanup(
            $args['actor_admin_id'],
            ((int) $args['dry_run'] === 1),
            $args['batch_size'],
            [
                'sesiones' => 90,
                'intentos' => 30,
                'bloqueos' => 7,
            ]
        );
        lsis_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    case 'close-session':
        if ((int) $args['session_id'] <= 0 || (int) $args['actor_admin_id'] <= 0) {
            echo lsis_runner_usage();
            exit(2);
        }
        $result = lsis_admin_close_session_by_id(
            $args['session_id'],
            $args['actor_admin_id'],
            false
        );
        lsis_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    case 'close-user':
        if ((int) $args['user_id'] <= 0 || (int) $args['actor_admin_id'] <= 0) {
            echo lsis_runner_usage();
            exit(2);
        }
        $result = lsis_admin_close_user_sessions(
            $args['user_id'],
            $args['actor_admin_id'],
            false
        );
        lsis_runner_print_result($result);
        exit(!empty($result['ok']) ? 0 : 1);

    default:
        echo lsis_runner_usage();
        exit(2);
}
