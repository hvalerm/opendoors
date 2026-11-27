<?php

function directorioEstadoTemporal(bool $crear): ?string
{
    $configurado = getenv('OPENDOORS_SEGUIMIENTO_DIR');
    $directorio = $configurado !== false && $configurado !== ''
        ? $configurado
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opendoors-estados-apertura';
    if (!is_dir($directorio)) {
        if (!$crear) {
            return null;
        }
        if (!@mkdir($directorio, 0700, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de estados temporales.');
        }
    }
    $permisos = fileperms($directorio);
    if (is_link($directorio) || $permisos === false
        || (PHP_OS_FAMILY !== 'Windows' && ($permisos & 0077) !== 0)) {
        throw new RuntimeException('El directorio de estados temporales no es privado.');
    }
    return $directorio;
}

function rutaEstadoTemporal(string $idSolicitud, bool $crear): ?string
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $idSolicitud)) {
        throw new InvalidArgumentException('Identificador de apertura no válido.');
    }
    $directorio = directorioEstadoTemporal($crear);
    return $directorio === null ? null : $directorio . DIRECTORY_SEPARATOR . 'estado-' . $idSolicitud . '.json';
}

function escribirRegistroTemporal(mixed $archivo, array $datos): void
{
    $contenido = json_encode($datos, JSON_THROW_ON_ERROR);
    if (!ftruncate($archivo, 0) || !rewind($archivo)
        || fwrite($archivo, $contenido) !== strlen($contenido) || !fflush($archivo)) {
        throw new RuntimeException('No se pudo guardar el estado temporal de apertura.');
    }
}

function publicarEstadoTemporal(int $idLocal, int $idEmpresa, string $idSolicitud, string $estado, int $accionar, int $restante): void
{
    $ruta = rutaEstadoTemporal($idSolicitud, true);
    if (is_link($ruta)) {
        throw new RuntimeException('El archivo de estado temporal no es válido.');
    }
    $archivo = fopen($ruta, 'c+b');
    if ($archivo === false) {
        throw new RuntimeException('No se pudo abrir el estado temporal de apertura.');
    }
    try {
        if (!flock($archivo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el estado temporal de apertura.');
        }
        $anterior = stream_get_contents($archivo);
        if ($anterior === false) {
            throw new RuntimeException('No se pudo leer el estado temporal de apertura.');
        }
        if ($anterior !== '' && (json_decode($anterior, true, 512, JSON_THROW_ON_ERROR)['invalidado'] ?? false) === true) {
            return;
        }
        escribirRegistroTemporal($archivo, [
            'id_local' => $idLocal, 'id_empresa' => $idEmpresa, 'id_solicitud' => $idSolicitud,
            'estado' => $estado, 'accionar' => $accionar, 'restante' => $restante,
            'actualizado' => microtime(true),
        ]);
    } finally {
        flock($archivo, LOCK_UN);
        fclose($archivo);
    }
}

function leerEstadoTemporal(int $idLocal, int $idEmpresa, string $idSolicitud): ?array
{
    $ruta = rutaEstadoTemporal($idSolicitud, false);
    if ($ruta === null || !is_file($ruta)) {
        return null;
    }
    if (is_link($ruta)) {
        throw new RuntimeException('El archivo de estado temporal no es válido.');
    }
    $archivo = @fopen($ruta, 'rb');
    if ($archivo === false) {
        if (!is_file($ruta)) {
            return null;
        }
        throw new RuntimeException('No se pudo leer el estado temporal de apertura.');
    }
    try {
        if (!flock($archivo, LOCK_SH)) {
            throw new RuntimeException('No se pudo bloquear el estado temporal de apertura.');
        }
        $contenido = stream_get_contents($archivo);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el estado temporal de apertura.');
        }
    } finally {
        flock($archivo, LOCK_UN);
        fclose($archivo);
    }
    $datos = json_decode($contenido, true, 512, JSON_THROW_ON_ERROR);
    if (is_array($datos) && ($datos['invalidado'] ?? false) === true) {
        return null;
    }
    if (!is_array($datos) || ($datos['id_local'] ?? null) !== $idLocal
        || ($datos['id_empresa'] ?? null) !== $idEmpresa || ($datos['id_solicitud'] ?? null) !== $idSolicitud
        || !in_array($datos['estado'] ?? null, ['pendiente', 'exitosa', 'fallida', 'cancelada', 'sin_solicitud'], true)
        || !in_array($datos['accionar'] ?? null, [0, 1], true)
        || !is_int($datos['restante'] ?? null) || $datos['restante'] < 0
        || !is_numeric($datos['actualizado'] ?? null)) {
        throw new RuntimeException('El estado temporal de apertura no es válido.');
    }
    $transcurrido = microtime(true) - (float) $datos['actualizado'];
    if ($transcurrido < 0 || $transcurrido > 2.5) {
        return null;
    }
    return [
        'accionar' => $datos['accionar'],
        'estado' => $datos['estado'],
        'segundos_restantes' => $datos['estado'] === 'pendiente'
            ? max(0, (int) ceil($datos['restante'] - $transcurrido)) : 0,
    ];
}

function invalidarEstadoTemporal(string $idSolicitud): void
{
    $ruta = rutaEstadoTemporal($idSolicitud, true);
    if (is_link($ruta)) {
        throw new RuntimeException('El archivo de estado temporal no es válido.');
    }
    $archivo = fopen($ruta, 'c+b');
    if ($archivo === false) {
        throw new RuntimeException('No se pudo invalidar el estado temporal anterior.');
    }
    try {
        if (!flock($archivo, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el estado temporal anterior.');
        }
        escribirRegistroTemporal($archivo, ['invalidado' => true]);
    } finally {
        flock($archivo, LOCK_UN);
        fclose($archivo);
    }
}

function limpiarEstadosTemporales(): void
{
    $directorio = directorioEstadoTemporal(true);
    foreach (new DirectoryIterator($directorio) as $archivo) {
        if (!$archivo->isFile() || !preg_match('/^estado-[a-f0-9]{64}\.json$/D', $archivo->getFilename())
            || $archivo->getMTime() >= time() - 300) {
            continue;
        }
        if (!@unlink($archivo->getPathname()) && is_file($archivo->getPathname())) {
            error_log('No se pudo limpiar un estado temporal de apertura.');
        }
    }
}
