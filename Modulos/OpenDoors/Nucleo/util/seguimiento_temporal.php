<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/aperturas.php';

try {
    set_time_limit(32);
    if ($argc !== 5 || !ctype_digit($argv[1]) || (int) $argv[1] <= 0
        || !ctype_digit($argv[2]) || (int) $argv[2] <= 0
        || !preg_match('/^[a-f0-9]{64}$/D', $argv[3])) {
        throw new InvalidArgumentException('Argumentos de seguimiento no válidos.');
    }
    $inicio = hrtime(true);
    $pdo = getOpenDoorsPDO();
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
    try {
        limpiarEstadosTemporales();
    } catch (RuntimeException $e) {
        error_log('No se pudieron limpiar estados temporales: ' . $e->getMessage());
    }
    $senal = fopen($argv[4], 'r+');
    if ($senal === false || fwrite($senal, 'LISTO') !== 5 || !fclose($senal)) {
        throw new RuntimeException('No se pudo confirmar el inicio del seguimiento.');
    }

    while ((hrtime(true) - $inicio) < 31000000000) {
        try {
            $pdo->beginTransaction();
            $local = bloquearLocalApertura($pdo, (int) $argv[1], (int) $argv[2]);
            $estado = 'sin_solicitud';
            $accionar = 0;
            $restante = 0;
            if ($local && !$local['eliminado'] && $local['aperturaId'] === $argv[3]) {
                $ahora = ahoraAperturas($pdo);
                $resultado = reconciliarSolicitudApertura($pdo, $local, $ahora);
                $estado = $resultado['estado'];
                $accionar = $estado === 'pendiente' ? (int) $local['accionar'] : 0;
                if ($estado === 'pendiente') {
                    $restante = max(0, strtotime($resultado['venceEn'] . ' UTC') - strtotime($ahora . ' UTC'));
                }
            }
            $pdo->commit();
            try {
                publicarEstadoTemporal((int) $argv[1], (int) $argv[2], $argv[3], $estado, $accionar, $restante);
            } catch (RuntimeException | JsonException $e) {
                error_log('No se pudo publicar el estado temporal de apertura: ' . $e->getMessage());
            }
            if ($estado !== 'pendiente') {
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!in_array($e->errorInfo[1] ?? null, [1205, 1213], true)) {
                throw $e;
            }
            error_log('Reintento de seguimiento por bloqueo de base de datos: ' . $e->getMessage());
        }
        $tiempoRestante = 31000000000 - (hrtime(true) - $inicio);
        if ($tiempoRestante > 0) {
            usleep((int) min(500000, intdiv($tiempoRestante, 1000)));
        }
    }
    error_log('El seguimiento de apertura terminó sin obtener un resultado: ' . $argv[3]);
    exit(1);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en seguimiento temporal de apertura: ' . $e->getMessage());
    exit(1);
}
