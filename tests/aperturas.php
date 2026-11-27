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
        habilitado boolean NOT NULL DEFAULT 1) ENGINE=InnoDB');
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

    $migrar = function () use ($admin, $root): void {
        $delimiter = ';';
        $sql = '';
        foreach (file($root . '/migraciones/aperturas_en_locales.sql') as $linea) {
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
    comprobarApertura($admin->query('SELECT idSolicitud FROM solicitudes_apertura')->fetchColumn() === 'resultado-antiguo',
        'La migracion altero la tabla anterior.');
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port, 'DB_USER' => 'root',
        'DB_PASS' => '', 'DB_NAME_OPENDOORS' => $base] as $nombre => $valor) {
        putenv("$nombre=$valor");
    }
    require_once $root . '/Modulos/OpenDoors/Nucleo/util/aperturas.php';
    require_once $root . '/Modulos/OpenDoors/Nucleo/util/estado_dispositivo.php';
    $pdo = getOpenDoorsPDO();
    $contadores = fn() => $pdo->query('SELECT * FROM microcontroladores ORDER BY idMicrocontrolador')->fetchAll();
    $emitir = function () use ($pdo): string {
        $pdo->beginTransaction();
        try {
            $id = emitirSolicitudApertura($pdo, 1, 1);
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
    echo "OK: $checks comprobaciones de migracion y aperturas con MySQL.\n";
} finally {
    if ($creada) {
        $admin->exec("DROP DATABASE `$base`");
    }
}
