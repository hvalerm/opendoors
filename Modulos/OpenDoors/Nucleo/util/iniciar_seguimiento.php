<?php

function iniciarSeguimientoApertura(int $idLocal, int $idEmpresa, string $idSolicitud): void
{
    if ($idLocal <= 0 || $idEmpresa <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $idSolicitud)) {
        throw new InvalidArgumentException('Datos de seguimiento de apertura no válidos.');
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('El servidor no permite iniciar el seguimiento de apertura.');
    }

    $configurado = getenv('PHP_CLI_BINARY');
    $nombre = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    $ini = php_ini_loaded_file();
    $candidatos = $configurado !== false && $configurado !== ''
        ? [$configurado]
        : array_filter([
            PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server' ? PHP_BINARY : null,
            $ini ? dirname($ini) . DIRECTORY_SEPARATOR . $nombre : null,
            PHP_BINDIR . DIRECTORY_SEPARATOR . $nombre,
        ]);
    $php = null;
    foreach ($candidatos as $candidato) {
        if (is_file($candidato) && is_executable($candidato)) {
            $php = $candidato;
            break;
        }
    }
    if ($php === null) {
        throw new RuntimeException('No se encontró PHP CLI para el seguimiento de apertura.');
    }

    $senal = tempnam(sys_get_temp_dir(), 'od_apertura_');
    if ($senal === false) {
        throw new RuntimeException('No se pudo preparar el seguimiento de apertura.');
    }
    $log = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opendoors-seguimiento-aperturas.log';
    $argumentos = [
        $php, '-d', 'log_errors=1', '-d', 'error_log=' . $log,
        __DIR__ . DIRECTORY_SEPARATOR . 'seguimiento_temporal.php',
        (string) $idLocal, (string) $idEmpresa, $idSolicitud, $senal,
    ];
    $comando = implode(' ', array_map('escapeshellarg', $argumentos));
    $windows = PHP_OS_FAMILY === 'Windows';
    $comando = $windows ? 'start "" /B ' . $comando : $comando . ' >/dev/null 2>&1 </dev/null &';
    $nulo = $windows ? 'NUL' : '/dev/null';
    $entorno = getenv();
    foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME_OPENDOORS'] as $variable) {
        $entorno[$variable] = (string) constant($variable);
    }
    $directorio = getenv('OPENDOORS_SEGUIMIENTO_DIR');
    if ($directorio !== false) {
        $entorno['OPENDOORS_SEGUIMIENTO_DIR'] = $directorio;
    }
    try {
        $proceso = proc_open($comando, [
            0 => ['file', $nulo, 'r'],
            1 => ['file', $nulo, 'w'],
            2 => ['file', $nulo, 'w'],
        ], $pipes, null, $entorno);
        if (!is_resource($proceso) || proc_close($proceso) !== 0) {
            throw new RuntimeException('No se pudo iniciar el seguimiento de apertura.');
        }
        $limite = microtime(true) + 5;
        do {
            if (file_get_contents($senal) === 'LISTO') {
                return;
            }
            usleep(50000);
        } while (microtime(true) < $limite);
        throw new RuntimeException('El seguimiento de apertura no respondió al iniciar; revisar ' . $log);
    } finally {
        if (!unlink($senal)) {
            error_log('No se pudo eliminar el archivo temporal de inicio: ' . $senal);
        }
    }
}
