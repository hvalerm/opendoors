<?php
// php tests\aperturas.php [puerto MariaDB aislado; predeterminado 13316].
// Requiere TLS y root sin clave. Solo crea/elimina su propia base aleatoria.
declare(strict_types=1);

$port = filter_var($argv[1] ?? '13316', FILTER_VALIDATE_INT);
if (!$port || $port < 1024 || $port > 65535) {
    throw new InvalidArgumentException('Puerto de pruebas no valido.');
}
$root = dirname(__DIR__);
$base = 'opendoors_aperturas_test_' . bin2hex(random_bytes(6));
$directorioSeguimientoAnterior = getenv('OPENDOORS_SEGUIMIENTO_DIR');
$directorioSeguimiento = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $base;
putenv('OPENDOORS_SEGUIMIENTO_DIR=' . $directorioSeguimiento);
$admin = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$creada = false;
$checks = 0;
function comprobarApertura(bool $condicion, string $mensaje): void
{
    global $checks;
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $checks++;
}

try {
    $admin->exec("CREATE DATABASE `$base` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $creada = true;
    $admin->exec("USE `$base`");
    $admin->exec('CREATE TABLE locales (idLocal int PRIMARY KEY, idEmpresa int NOT NULL,
        accionar boolean NOT NULL DEFAULT 0, eliminado boolean NOT NULL DEFAULT 0,
        habilitado boolean NOT NULL DEFAULT 1, UNIQUE KEY (idLocal, idEmpresa)) ENGINE=InnoDB');
    $admin->exec('CREATE TABLE accesos (idAcceso int PRIMARY KEY, idEmpresa int NOT NULL,
        idLocal int NOT NULL, ejecutado boolean NOT NULL DEFAULT 0,
        FOREIGN KEY (idLocal, idEmpresa) REFERENCES locales (idLocal, idEmpresa)) ENGINE=InnoDB');
    $admin->exec('CREATE TABLE microcontroladores (idMicrocontrolador int PRIMARY KEY,
        idLocal int NOT NULL, idEmpresa int NOT NULL, ultimaApertura datetime NULL,
        cantidadAperturasExitosas int NOT NULL DEFAULT 0,
        cantidadAperturasFallidas int NOT NULL DEFAULT 0,
        ultimaConsulta datetime NULL, habilitado boolean NOT NULL DEFAULT 1) ENGINE=InnoDB');
    $admin->exec('CREATE TABLE solicitudes_apertura (idSolicitud char(64) PRIMARY KEY) ENGINE=InnoDB');
    $admin->exec("INSERT INTO solicitudes_apertura VALUES ('resultado-antiguo')");
    $admin->exec('INSERT INTO locales (idLocal,idEmpresa,accionar,eliminado) VALUES (1,1,1,0), (2,2,0,0)');
    $admin->exec("INSERT INTO microcontroladores (idMicrocontrolador,idLocal,idEmpresa,ultimaApertura,
        cantidadAperturasExitosas,cantidadAperturasFallidas) VALUES
        (1,1,1,'2020-01-01 00:00:00',5,0), (2,1,1,'2020-01-01 00:00:00',0,0),
        (3,2,2,'2020-01-01 00:00:00',7,0)");

    $migrar = function (string $archivo = 'aperturas_en_locales.sql') use ($admin, $root): void {
        $delimiter = ';';
        $sql = '';
        foreach (file($root . '/migraciones/' . $archivo) as $linea) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)/', $linea, $m)) {
                $delimiter = $m[1];
            } elseif (!preg_match('/^\s*(--|$)/', $linea)) {
                $sql .= $linea;
                if (str_ends_with(rtrim($sql), $delimiter)) {
                    $admin->exec(substr(rtrim($sql), 0, -strlen($delimiter)));
                    $sql = '';
                }
            }
        }
        if (trim($sql) !== '') {
            throw new RuntimeException('La migracion no se ejecuto completamente.');
        }
    };
    try {
        $migrar();
        throw new RuntimeException('La migracion acepto una orden pendiente.');
    } catch (PDOException $e) {
        comprobarApertura(str_contains($e->getMessage(), 'Hay ordenes pendientes'),
            'La migracion fallo por un motivo distinto a la orden pendiente.');
    }
    $admin->exec('UPDATE locales SET accionar = 0 WHERE idLocal = 1');
    $migrar();
    $admin->exec("UPDATE locales SET accionar = 1, aperturaEstado = 'pendiente' WHERE idLocal = 1");
    try {
        $migrar('acceso_uso_confirmado.sql');
        throw new RuntimeException('La migracion de accesos acepto una orden pendiente.');
    } catch (PDOException $e) {
        comprobarApertura(str_contains($e->getMessage(), 'Hay ordenes pendientes'),
            'La migracion de accesos fallo por un motivo distinto a la orden pendiente.');
    }
    $admin->exec('UPDATE locales SET accionar = 0, aperturaEstado = NULL WHERE idLocal = 1');
    $migrar('acceso_uso_confirmado.sql');
    $admin->exec('INSERT INTO accesos (idAcceso, idEmpresa, idLocal) VALUES (1, 1, 1)');
    comprobarApertura($admin->query('SELECT idSolicitud FROM solicitudes_apertura')->fetchColumn() === 'resultado-antiguo',
        'La migracion altero la tabla anterior.');
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port, 'DB_USER' => 'root',
        'DB_PASS' => '', 'DB_NAME_OPENDOORS' => $base] as $nombre => $valor) {
        putenv("$nombre=$valor");
    }
    require_once $root . '/Modulos/OpenDoors/Nucleo/util/aperturas.php';
    require_once $root . '/Modulos/OpenDoors/Nucleo/util/estado_dispositivo.php';
    require_once $root . '/Modulos/OpenDoors/Nucleo/util/iniciar_seguimiento.php';
    $pdo = getOpenDoorsPDO();
    $contadores = fn() => $pdo->query('SELECT * FROM microcontroladores ORDER BY idMicrocontrolador')->fetchAll();
    $emitir = function (?int $idAcceso = null) use ($pdo): string {
        $pdo->beginTransaction();
        try {
            $id = emitirSolicitudApertura($pdo, 1, 1, $idAcceso);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    };
    $consultar = function () use ($pdo): ?array {
        $pdo->beginTransaction();
        try {
            $local = bloquearLocalApertura($pdo, 1, 1);
            $resultado = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    };
    $id = $emitir();
    comprobarApertura((int) $pdo->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), aperturaVenceEn)
        FROM locales WHERE idLocal = 1')->fetchColumn() === 30,
        'El plazo de la solicitud no es 30 segundos.');
    try {
        $emitir();
        throw new RuntimeException('Se permite reemplazar una apertura pendiente.');
    } catch (DomainException $e) {
        comprobarApertura($consultar()['idSolicitud'] === $id, 'Se altero la solicitud pendiente.');
    }
    $pdo->exec('UPDATE locales SET accionar = 0 WHERE idLocal = 1');
    $codigoConcurrente = 'require ' . var_export($root . '/Modulos/OpenDoors/Nucleo/util/aperturas.php', true)
        . '; $pdo = getOpenDoorsPDO(); $pdo->beginTransaction();'
        . ' $local = bloquearLocalApertura($pdo, 1, 1);'
        . ' reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo)); $pdo->commit();';
    $procesos = [];
    for ($i = 0; $i < 4; $i++) {
        $proceso = proc_open([PHP_BINARY, '-r', $codigoConcurrente],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar una consulta concurrente.');
        }
        fclose($pipes[0]);
        $procesos[] = [$proceso, $pipes];
    }
    foreach ($procesos as [$proceso, $pipes]) {
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        comprobarApertura(proc_close($proceso) === 0, "Fallo de consulta concurrente: $salida $error");
    }
    comprobarApertura($consultar()['estado'] === 'exitosa' && $consultar()['estado'] === 'exitosa',
        'No se conserva el resultado exitoso.');
    $despuesExito = $contadores();
    comprobarApertura((int) $despuesExito[0]['cantidadAperturasExitosas'] === 6
        && (int) $despuesExito[1]['cantidadAperturasExitosas'] === 1
        && (int) $despuesExito[2]['cantidadAperturasExitosas'] === 7,
        'El exito se contabilizo mas de una vez o en otra empresa.');

    $idFallida = $emitir();
    comprobarApertura($idFallida !== $id, 'La nueva apertura no reemplazo el resultado anterior.');
    $pdo->exec('UPDATE locales SET aperturaVenceEn = UTC_TIMESTAMP() WHERE idLocal = 1');
    $pdo->exec("CREATE TRIGGER fallo_contador BEFORE UPDATE ON microcontroladores
        FOR EACH ROW BEGIN IF NEW.idMicrocontrolador = 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba'; END IF; END");
    try {
        $consultar();
        throw new RuntimeException('Se oculto el error al modificar el segundo dispositivo.');
    } catch (PDOException $e) {
        comprobarApertura(!$pdo->inTransaction()
            && $pdo->query('SELECT aperturaEstado FROM locales WHERE idLocal = 1')->fetchColumn() === 'pendiente'
            && $contadores() === $despuesExito,
            'Un error parcial no revierte la orden y todos los contadores.');
    }
    $pdo->exec('DROP TRIGGER fallo_contador');
    comprobarApertura($consultar()['estado'] === 'fallida' && $consultar()['estado'] === 'fallida',
        'La solicitud no falla al alcanzar su vencimiento.');
    $despuesFallo = $contadores();
    comprobarApertura((int) $despuesFallo[0]['cantidadAperturasFallidas'] === 1
        && (int) $despuesFallo[1]['cantidadAperturasFallidas'] === 1
        && $despuesFallo[2] === $despuesExito[2]
        && $despuesFallo[0]['ultimaApertura'] === $despuesExito[0]['ultimaApertura'],
        'El fallo altera otra empresa, la ultima apertura o cuenta dos veces.');
    $emitir();
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, ahoraAperturas($pdo));
    $pdo->commit();
    comprobarApertura($consultar()['estado'] === 'cancelada' && $contadores() === $despuesFallo,
        'La cancelacion modifica contadores.');
    $indice = file_get_contents($root . '/migraciones/dispositivo_unico_por_local.sql');
    try {
        $pdo->exec($indice);
        throw new RuntimeException('La migracion acepto dispositivos duplicados.');
    } catch (PDOException $e) {
        comprobarApertura((int) $pdo->query('SELECT COUNT(*) FROM microcontroladores WHERE idLocal = 1')->fetchColumn() === 2,
            'La migracion no debe eliminar dispositivos duplicados.');
    }
    $pdo->exec('DELETE FROM microcontroladores WHERE idMicrocontrolador = 2');
    $pdo->exec($indice);
    try {
        $pdo->exec('INSERT INTO microcontroladores (idMicrocontrolador, idLocal, idEmpresa) VALUES (4,1,1)');
        throw new RuntimeException('La base acepto un segundo dispositivo para el mismo local.');
    } catch (PDOException $e) {
        comprobarApertura($e->getCode() === '23000', 'El indice no rechazo el dispositivo duplicado.');
    }
    $pdo->exec('UPDATE microcontroladores SET ultimaConsulta = UTC_TIMESTAMP() WHERE idMicrocontrolador = 1');
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    comprobarApertura(estadoDispositivoLocal($pdo, 1, 1, true) === 'activo',
        'La consulta de actividad con bloqueo no reconoce el dispositivo activo.');
    $pdo->commit();
    $pdo->exec('UPDATE microcontroladores SET ultimaConsulta = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 16 SECOND) WHERE idMicrocontrolador = 1');
    comprobarApertura(estadoDispositivoLocal($pdo, 1, 1) === 'inactivo'
        && estadoDispositivoLocal($pdo, 1, 2) === 'sin_dispositivo',
        'No se respeta el limite de actividad o el aislamiento por empresa.');
    $pdo->exec('UPDATE microcontroladores SET ultimaConsulta = UTC_TIMESTAMP(), habilitado = 0 WHERE idMicrocontrolador = 1');
    comprobarApertura(estadoDispositivoLocal($pdo, 1, 1) === 'deshabilitado',
        'El dispositivo deshabilitado se considero disponible.');
    $emitirConSeguimiento = function (?int $idAcceso = null) use ($pdo): string {
        $pdo->beginTransaction();
        try {
            $id = emitirSolicitudApertura($pdo, 1, 1, $idAcceso);
            iniciarSeguimientoApertura(1, 1, $id);
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    };
    putenv('PHP_CLI_BINARY=' . $root . DIRECTORY_SEPARATOR . 'php-inexistente');
    try {
        $emitirConSeguimiento();
        throw new RuntimeException('Se emitio una orden sin iniciar el seguimiento.');
    } catch (RuntimeException $e) {
        comprobarApertura(str_contains($e->getMessage(), 'No se encontró PHP CLI')
            && (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn() === 0,
            'El fallo de arranque no revierte la orden.');
    }
    putenv('PHP_CLI_BINARY');

    $antesFallos = (int) $pdo->query('SELECT cantidadAperturasFallidas FROM microcontroladores WHERE idLocal = 1')->fetchColumn();
    $inicio = microtime(true);
    $idTemporal = $emitirConSeguimiento(1);
    $limiteEstado = microtime(true) + 3;
    do {
        usleep(100000);
        $estadoCompartido = leerEstadoTemporal(1, 1, $idTemporal);
    } while ($estadoCompartido === null && microtime(true) < $limiteEstado);
    comprobarApertura(($estadoCompartido['estado'] ?? null) === 'pendiente'
        && $estadoCompartido['segundos_restantes'] > 0,
        'El proceso no publico el estado y tiempo restante de la orden.');
    do {
        usleep(250000);
        $estado = $pdo->query('SELECT aperturaEstado FROM locales WHERE idLocal = 1')->fetchColumn();
    } while ($estado === 'pendiente' && microtime(true) - $inicio < 32);
    $duracion = microtime(true) - $inicio;
    comprobarApertura($estado === 'fallida' && $duracion >= 29 && $duracion < 32
        && (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn() === 0
        && (int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 0
        && $pdo->query('SELECT aperturaIdAcceso FROM locales WHERE idLocal = 1')->fetchColumn() === null
        && (int) $pdo->query('SELECT cantidadAperturasFallidas FROM microcontroladores WHERE idLocal = 1')->fetchColumn() === $antesFallos + 1,
        'El proceso temporal no registro una sola falla al vencimiento sin consultar la aplicacion.');
    $limiteEstado = microtime(true) + 2;
    do {
        $estadoCompartido = leerEstadoTemporal(1, 1, $idTemporal);
        if (($estadoCompartido['estado'] ?? null) === 'fallida') break;
        usleep(50000);
    } while (microtime(true) < $limiteEstado);
    comprobarApertura(($estadoCompartido['estado'] ?? null) === 'fallida',
        'El proceso no publico el resultado fallido.');

    $idTemporal = $emitir(1);
    $senal = tempnam(sys_get_temp_dir(), 'od_prueba_');
    if ($senal === false) {
        throw new RuntimeException('No se pudo crear el archivo temporal de prueba.');
    }
    $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $proceso = proc_open([PHP_BINARY, $root . '/Modulos/OpenDoors/Nucleo/util/seguimiento_temporal.php',
        '1', '1', $idTemporal, $senal], [
        0 => ['file', $nulo, 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w'],
    ], $pipes);
    if (!is_resource($proceso)) {
        unlink($senal);
        throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
    }
    $limite = microtime(true) + 5;
    do {
        usleep(50000);
        $listo = file_get_contents($senal) === 'LISTO';
    } while (!$listo && microtime(true) < $limite);
    unlink($senal);
    if (!$listo) {
        proc_terminate($proceso);
        proc_close($proceso);
    }
    comprobarApertura($listo, 'El proceso de prueba no inicio.');
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Exitosa, ahoraAperturas($pdo));
    $pdo->commit();
    $limite = microtime(true) + 5;
    do {
        usleep(50000);
        $estadoProceso = proc_get_status($proceso);
    } while ($estadoProceso['running'] && microtime(true) < $limite);
    if ($estadoProceso['running']) {
        proc_terminate($proceso);
    }
    proc_close($proceso);
    comprobarApertura(!$estadoProceso['running'] && $estadoProceso['exitcode'] === 0
        && $pdo->query('SELECT aperturaEstado FROM locales WHERE idLocal = 1')->fetchColumn() === 'exitosa'
        && (int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 1,
        'El seguimiento no termino al recibir la confirmacion exitosa.');
    $pdo->exec('UPDATE accesos SET ejecutado = 0 WHERE idAcceso = 1');

    publicarEstadoTemporal(1, 1, $idTemporal, 'exitosa', 0, 0);
    $idTemporal = $emitir();
    comprobarApertura(leerEstadoTemporal(1, 1, $local['aperturaId']) === null,
        'Una orden nueva no invalido el estado temporal de la orden anterior.');
    publicarEstadoTemporal(1, 1, $local['aperturaId'], 'exitosa', 0, 0);
    comprobarApertura(leerEstadoTemporal(1, 1, $local['aperturaId']) === null,
        'El proceso anterior pudo restaurar un estado temporal invalidado.');
    $pdo->beginTransaction();
    bloquearLocalApertura($pdo, 1, 1);
    $senal = tempnam(sys_get_temp_dir(), 'od_prueba_');
    if ($senal === false) {
        throw new RuntimeException('No se pudo crear el archivo temporal de prueba.');
    }
    $inicioBloqueo = microtime(true);
    $proceso = proc_open([PHP_BINARY, $root . '/Modulos/OpenDoors/Nucleo/util/seguimiento_temporal.php',
        '1', '1', $idTemporal, $senal], [
        0 => ['file', $nulo, 'r'], 1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w'],
    ], $pipes);
    if (!is_resource($proceso)) {
        unlink($senal);
        throw new RuntimeException('No se pudo iniciar el proceso de prueba.');
    }
    do {
        usleep(100000);
        $estadoProceso = proc_get_status($proceso);
    } while ($estadoProceso['running'] && microtime(true) - $inicioBloqueo < 33);
    $duracionBloqueo = microtime(true) - $inicioBloqueo;
    if ($estadoProceso['running']) {
        proc_terminate($proceso);
    }
    proc_close($proceso);
    unlink($senal);
    $pdo->rollBack();
    comprobarApertura(!$estadoProceso['running'] && $duracionBloqueo >= 29 && $duracionBloqueo < 33,
        'El proceso bloqueado no termino dentro del limite de 32 segundos.');
    $consultar();

    $idTemporal = $emitirConSeguimiento();
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, ahoraAperturas($pdo));
    $idNueva = emitirSolicitudApertura($pdo, 1, 1);
    $pdo->exec('UPDATE locales SET aperturaVenceEn = UTC_TIMESTAMP() WHERE idLocal = 1');
    $pdo->commit();
    usleep(1500000);
    comprobarApertura($pdo->query('SELECT aperturaEstado FROM locales WHERE idLocal = 1')->fetchColumn() === 'pendiente'
        && $pdo->query('SELECT aperturaId FROM locales WHERE idLocal = 1')->fetchColumn() === $idNueva,
        'El seguimiento de una orden anterior modifico la orden nueva.');
    $consultar();

    $emitirAcceso = function () use ($pdo): void {
        $pdo->beginTransaction();
        emitirSolicitudApertura($pdo, 1, 1, 1);
        $pdo->commit();
    };
    $emitirAcceso();
    comprobarApertura((int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 0,
        'Una orden pendiente consumio el acceso.');
    try {
        $pdo->exec('DELETE FROM accesos WHERE idAcceso = 1');
        throw new RuntimeException('Se pudo eliminar un acceso con una orden pendiente.');
    } catch (PDOException $e) {
        comprobarApertura($e->getCode() === '23000', 'La referencia no protege el acceso pendiente.');
    }
    $pdo->exec('UPDATE locales SET aperturaVenceEn = UTC_TIMESTAMP() WHERE idLocal = 1');
    reconciliarAperturaPendiente($pdo, 1, 1, 1);
    comprobarApertura($pdo->query('SELECT aperturaEstado FROM locales WHERE idLocal = 1')->fetchColumn() === 'fallida'
        && (int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 0,
        'No se recupero el acceso despues de un fallo sin proceso temporal.');
    $emitirAcceso();
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, ahoraAperturas($pdo));
    $pdo->commit();
    comprobarApertura((int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 0
        && $pdo->query('SELECT aperturaIdAcceso FROM locales WHERE idLocal = 1')->fetchColumn() === null,
        'La cancelacion consumio o no libero el acceso.');
    $emitirAcceso();
    $pdo->exec("CREATE TRIGGER fallo_exito BEFORE UPDATE ON microcontroladores
        FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba'");
    try {
        $pdo->beginTransaction();
        $local = bloquearLocalApertura($pdo, 1, 1);
        $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
        finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Exitosa, ahoraAperturas($pdo));
        $pdo->commit();
        throw new RuntimeException('Se oculto el fallo al confirmar el acceso.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        comprobarApertura((int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 0
            && (int) $pdo->query('SELECT aperturaIdAcceso FROM locales WHERE idLocal = 1')->fetchColumn() === 1,
            'El fallo de confirmacion no revirtio el consumo y la reserva.');
    }
    $pdo->exec('DROP TRIGGER fallo_exito');
    $pdo->exec('INSERT INTO locales (idLocal, idEmpresa) VALUES (3, 1)');
    $pdo->exec('UPDATE accesos SET idLocal = 3 WHERE idAcceso = 1');
    $pdo->exec('UPDATE locales SET accionar = 0 WHERE idLocal = 1');
    reconciliarAperturaPendiente($pdo, 1, 1, 1);
    comprobarApertura((int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso = 1')->fetchColumn() === 1
        && $pdo->query('SELECT aperturaIdAcceso FROM locales WHERE idLocal = 1')->fetchColumn() === null,
        'La confirmacion no consumio el acceso original despues de cambiar su local.');

    $pdo->exec('UPDATE accesos SET ejecutado = 0 WHERE idAcceso = 1');
    $codigoCompetencia = 'require ' . var_export($root . '/Modulos/OpenDoors/Nucleo/util/aperturas.php', true)
        . '; $pdo = getOpenDoorsPDO(); $id = (int) $argv[1]; $pdo->beginTransaction();'
        . ' bloquearLocalApertura($pdo, $id, 1);'
        . ' $pdo->query("SELECT idAcceso FROM accesos WHERE idAcceso = 1 FOR UPDATE")->fetchColumn();'
        . ' $pendiente = $pdo->query("SELECT idLocal FROM locales WHERE aperturaIdAcceso = 1 AND aperturaEstado = \'pendiente\'")->fetchColumn();'
        . ' if ($pendiente === false) { emitirSolicitudApertura($pdo, $id, 1, 1); usleep(300000); echo "emitida"; }'
        . ' else { echo "pendiente"; } $pdo->commit();';
    $procesos = [];
    foreach ([1, 3] as $idLocal) {
        $proceso = proc_open([PHP_BINARY, '-r', $codigoCompetencia, (string) $idLocal],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar una orden concurrente.');
        }
        fclose($pipes[0]);
        $procesos[] = [$proceso, $pipes];
    }
    $resultados = [];
    foreach ($procesos as [$proceso, $pipes]) {
        $resultados[] = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        comprobarApertura(proc_close($proceso) === 0, "Fallo de orden concurrente: $error");
    }
    sort($resultados);
    comprobarApertura($resultados === ['emitida', 'pendiente']
        && (int) $pdo->query('SELECT COUNT(*) FROM locales WHERE aperturaIdAcceso = 1')->fetchColumn() === 1,
        'Dos locales reservaron el mismo acceso simultaneamente.');
    echo "OK: $checks comprobaciones de migracion y aperturas con MySQL.\n";
} finally {
    if ($creada) {
        $admin->exec("DROP DATABASE `$base`");
    }
    if (is_dir($directorioSeguimiento)) {
        foreach (new DirectoryIterator($directorioSeguimiento) as $archivo) {
            if ($archivo->isFile() && preg_match('/^estado-[a-f0-9]{64}\.json$/D', $archivo->getFilename())) {
                unlink($archivo->getPathname());
            }
        }
        rmdir($directorioSeguimiento);
    }
    if ($directorioSeguimientoAnterior === false) {
        putenv('OPENDOORS_SEGUIMIENTO_DIR');
    } else {
        putenv('OPENDOORS_SEGUIMIENTO_DIR=' . $directorioSeguimientoAnterior);
    }
}
