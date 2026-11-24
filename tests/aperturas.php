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
$worker = null;
$procesos = [];
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
        UNIQUE KEY uq_local_empresa (idLocal, idEmpresa)) ENGINE=InnoDB');
    $admin->exec('CREATE TABLE microcontroladores (idMicrocontrolador int PRIMARY KEY,
        idLocal int NOT NULL, idEmpresa int NOT NULL, ultimaApertura datetime NULL,
        cantidadAperturasExitosas int NULL, cantidadAperturasFallidas int NULL) ENGINE=InnoDB');
    $admin->exec('INSERT INTO locales (idLocal, idEmpresa, accionar) VALUES (1,1,0), (2,2,0), (3,1,1)');
    $admin->exec("INSERT INTO microcontroladores VALUES
        (1,1,1,'2020-01-01 00:00:00',5,NULL), (2,1,1,'2020-01-01 00:00:00',0,0),
        (3,2,2,'2020-01-01 00:00:00',7,8)");
    $sql = implode('', array_filter(file($root . '/migraciones/seguimiento_aperturas.sql'),
        fn($linea) => !str_starts_with($linea, '--')));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
        $admin->exec($sentencia);
    }
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port, 'DB_USER' => 'root',
        'DB_PASS' => '', 'DB_NAME_OPENDOORS' => $base] as $nombre => $valor) {
        putenv("$nombre=$valor");
    }
    require_once $root . '/ModoAnfitrion/OpenDoors/util/aperturas.php';
    $pdo = getOpenDoorsPDO();
    $contadores = fn() => $pdo->query('SELECT * FROM microcontroladores ORDER BY idMicrocontrolador')->fetchAll();
    $estado = function (string $id) use ($pdo): string {
        $stmt = $pdo->prepare('SELECT estado FROM solicitudes_apertura WHERE idSolicitud = ?');
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    };
    $emitir = function () use ($pdo): string {
        $pdo->beginTransaction();
        try {
            $id = emitirSolicitudApertura($pdo, 1, 1);
            $pdo->commit();
            return $id;
        } catch (PDOException | LogicException $e) {
            $pdo->rollBack();
            throw $e;
        }
    };
    comprobarApertura((int) $contadores()[0]['cantidadAperturasFallidas'] === 0,
        'La migracion no inicializa el nuevo contador nullable.');
    comprobarApertura((int) $pdo->query('SELECT TIMESTAMPDIFF(SECOND, solicitadaEn, venceEn)
        FROM solicitudes_apertura WHERE idLocal = 3')->fetchColumn() === 30,
        'La migracion no recupera las ordenes que ya estaban pendientes con un plazo de 30 segundos.');
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 3, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, ahoraAperturas($pdo));
    $pdo->commit();

    $id = $emitir();
    comprobarApertura((int) $pdo->query("SELECT TIMESTAMPDIFF(SECOND, solicitadaEn, venceEn)
        FROM solicitudes_apertura WHERE idSolicitud = '$id'")->fetchColumn() === 30,
        'El plazo de la solicitud no es exactamente 30 segundos.');
    try {
        $emitir();
        throw new RuntimeException('Se permite reemplazar una apertura pendiente.');
    } catch (DomainException $e) {
        comprobarApertura($estado($id) === 'pendiente', 'El rechazo de una segunda apertura altera la primera.');
    }
    comprobarApertura(procesarAperturasPendientes($pdo) === 0 && $estado($id) === 'pendiente',
        'El procesador finaliza una solicitud antes de su vencimiento.');

    // Dos procesos que observan el mismo cambio deben contabilizarlo solo una vez.
    $pdo->exec('UPDATE locales SET accionar = 0 WHERE idLocal = 1');
    $script = $root . '/ModoAnfitrion/OpenDoors/util/procesar_aperturas.php';
    for ($i = 0; $i < 6; $i++) {
        $proceso = proc_open([PHP_BINARY, $script, '--una-vez'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar un procesador concurrente.');
        }
        fclose($pipes[0]);
        $procesos[] = [$proceso, $pipes];
    }
    foreach ($procesos as [$proceso, $pipes]) {
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        comprobarApertura(proc_close($proceso) === 0, "Fallo concurrente: $salida $error");
    }
    $procesos = [];
    $despuesExito = $contadores();
    comprobarApertura($estado($id) === 'exitosa'
        && (int) $despuesExito[0]['cantidadAperturasExitosas'] === 6
        && (int) $despuesExito[1]['cantidadAperturasExitosas'] === 1
        && (int) $despuesExito[2]['cantidadAperturasExitosas'] === 7,
        'Los procesadores concurrentes duplican el exito o mezclan locales/empresas.');
    comprobarApertura($despuesExito[0]['ultimaApertura'] === $despuesExito[1]['ultimaApertura']
        && abs(time() - strtotime($despuesExito[0]['ultimaApertura'] . ' UTC')) <= 5,
        'La confirmacion no actualiza ultimaApertura en UTC para todos los dispositivos del local.');

    $idCancelada = $emitir();
    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, 1, 1);
    $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, ahoraAperturas($pdo));
    $pdo->commit();
    comprobarApertura($contadores() === $despuesExito && $estado($idCancelada) === 'cancelada'
        && (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn() === 0,
        'Una cancelacion modifica contadores o ultimaApertura.');

    $idFallida = $emitir();
    $pdo->exec("UPDATE solicitudes_apertura SET venceEn = UTC_TIMESTAMP() WHERE idSolicitud = '$idFallida'");
    $pdo->exec("CREATE TRIGGER fallo_contador BEFORE UPDATE ON microcontroladores
        FOR EACH ROW BEGIN IF NEW.idMicrocontrolador = 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fallo de prueba'; END IF; END");
    try {
        procesarAperturasPendientes($pdo);
        throw new RuntimeException('Se oculto el error al modificar el segundo dispositivo.');
    } catch (PDOException $e) {
        comprobarApertura(!$pdo->inTransaction() && $estado($idFallida) === 'pendiente'
            && (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn() === 1
            && $contadores() === $despuesExito,
            'Un error parcial no revierte solicitud, bandera y contadores de todos los dispositivos.');
    }
    $pdo->exec('DROP TRIGGER fallo_contador');
    comprobarApertura(procesarAperturasPendientes($pdo) === 1 && $estado($idFallida) === 'fallida',
        'La solicitud no falla al alcanzar exactamente su vencimiento.');
    comprobarApertura(procesarAperturasPendientes($pdo) === 0,
        'El reintento de procesamiento vuelve a contabilizar el fallo.');
    $despuesFallo = $contadores();
    comprobarApertura((int) $despuesFallo[0]['cantidadAperturasFallidas'] === 1
        && (int) $despuesFallo[1]['cantidadAperturasFallidas'] === 1
        && $despuesFallo[2] === $despuesExito[2]
        && $despuesFallo[0]['ultimaApertura'] === $despuesExito[0]['ultimaApertura']
        && (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn() === 0,
        'El fallo altera la ultima apertura, cuenta otra empresa o deja una orden activa.');

    $idSinNavegador = $emitir();
    $venceEn = $pdo->query("SELECT venceEn FROM solicitudes_apertura
        WHERE idSolicitud = '$idSinNavegador'")->fetchColumn();
    $worker = proc_open([PHP_BINARY, $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $workerPipes);
    if (!is_resource($worker)) {
        throw new RuntimeException('No se pudo iniciar el procesador continuo.');
    }
    fclose($workerPipes[0]);
    $inicio = microtime(true);
    $limite = $inicio + 35;
    $prematura = false;
    do {
        usleep(100000);
        $resultado = $estado($idSinNavegador);
        if ($resultado !== 'pendiente' && ahoraAperturas($pdo) < $venceEn) {
            $prematura = true;
        }
    } while ($resultado === 'pendiente' && microtime(true) < $limite);
    comprobarApertura(proc_get_status($worker)['running'] && $estado($idSinNavegador) === 'fallida'
        && !$prematura && microtime(true) - $inicio >= 29
        && (int) $contadores()[0]['cantidadAperturasFallidas'] === 2,
        'El procesador continuo no detecta vencimientos sin peticiones HTTP.');
    echo "OK: $checks comprobaciones de aperturas con MySQL, concurrencia y proceso independiente.\n";
} finally {
    foreach ($procesos as [$proceso, $pipes]) {
        if (is_resource($proceso)) {
            proc_terminate($proceso);
            proc_close($proceso);
        }
    }
    if (is_resource($worker)) {
        proc_terminate($worker);
        fclose($workerPipes[1]);
        fclose($workerPipes[2]);
        proc_close($worker);
    }
    if ($creada) {
        $admin->exec("DROP DATABASE `$base`");
    }
}
