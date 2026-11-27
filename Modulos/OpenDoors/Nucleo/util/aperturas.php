<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/estado_temporal.php';

enum ResultadoApertura: string
{
    case Exitosa = 'exitosa';
    case Fallida = 'fallida';
    case Cancelada = 'cancelada';
}

function ahoraAperturas(PDO $pdo): string
{
    return $pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
}

// Todas las transiciones bloquean primero el local, tambien al confirmar desde la API.
function bloquearLocalApertura(PDO $pdo, int $idLocal, int $idEmpresa): ?array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('El seguimiento de aperturas requiere una transaccion.');
    }
    $stmt = $pdo->prepare('SELECT idLocal, idEmpresa, accionar, eliminado, habilitado,
        aperturaId, aperturaEstado, aperturaVenceEn FROM locales
        WHERE idLocal = ? AND idEmpresa = ? FOR UPDATE');
    $stmt->execute([$idLocal, $idEmpresa]);
    return $stmt->fetch() ?: null;
}

function finalizarSolicitudApertura(PDO $pdo, array $solicitud, ResultadoApertura $resultado, string $ahora): array
{
    $stmt = $pdo->prepare("UPDATE locales SET aperturaEstado = ?, accionar = 0
        WHERE idLocal = ? AND idEmpresa = ? AND aperturaId = ? AND aperturaEstado = 'pendiente'");
    $stmt->execute([$resultado->value, $solicitud['idLocal'], $solicitud['idEmpresa'], $solicitud['idSolicitud']]);
    if ($stmt->rowCount() !== 1) {
        throw new LogicException('La solicitud de apertura ya fue finalizada.');
    }
    $parametros = [$solicitud['idLocal'], $solicitud['idEmpresa']];
    if ($resultado === ResultadoApertura::Exitosa) {
        $pdo->prepare('UPDATE microcontroladores
            SET cantidadAperturasExitosas = cantidadAperturasExitosas + 1, ultimaApertura = ?
            WHERE idLocal = ? AND idEmpresa = ?')->execute([$ahora, ...$parametros]);
    } elseif ($resultado === ResultadoApertura::Fallida) {
        $pdo->prepare('UPDATE microcontroladores
            SET cantidadAperturasFallidas = cantidadAperturasFallidas + 1
            WHERE idLocal = ? AND idEmpresa = ?')->execute($parametros);
    }
    $solicitud['estado'] = $resultado->value;
    return $solicitud;
}

function reconciliarSolicitudApertura(PDO $pdo, array $local, string $ahora): ?array
{
    if ($local['aperturaId'] === null) {
        return null;
    }
    $solicitud = [
        'idSolicitud' => $local['aperturaId'],
        'idLocal' => $local['idLocal'],
        'idEmpresa' => $local['idEmpresa'],
        'estado' => $local['aperturaEstado'],
        'venceEn' => $local['aperturaVenceEn'],
    ];
    if ($solicitud['estado'] !== 'pendiente') {
        return $solicitud;
    }
    if ((int) $local['accionar'] === 0) {
        return finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Exitosa, $ahora);
    }
    if ($solicitud['venceEn'] <= $ahora) {
        return finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Fallida, $ahora);
    }
    return $solicitud;
}

function emitirSolicitudApertura(PDO $pdo, int $idLocal, int $idEmpresa): string
{
    $local = bloquearLocalApertura($pdo, $idLocal, $idEmpresa);
    if (!$local || $local['eliminado']) {
        throw new DomainException('El local ya no esta disponible.');
    }
    $ahora = ahoraAperturas($pdo);
    $anterior = reconciliarSolicitudApertura($pdo, $local, $ahora);
    if (($anterior && $anterior['estado'] === 'pendiente') || (!$anterior && $local['accionar'])) {
        throw new DomainException('Ya hay una apertura pendiente para este local.');
    }
    $idSolicitud = bin2hex(random_bytes(32));
    $venceEn = (new DateTimeImmutable($ahora, new DateTimeZone('UTC')))
        ->modify('+30 seconds')->format('Y-m-d H:i:s');
    $pdo->prepare("UPDATE locales SET accionar = 1, aperturaId = ?, aperturaEstado = 'pendiente',
        aperturaVenceEn = ? WHERE idLocal = ? AND idEmpresa = ?")
        ->execute([$idSolicitud, $venceEn, $idLocal, $idEmpresa]);
    if ($local['aperturaId'] !== null) {
        invalidarEstadoTemporal($local['aperturaId']);
    }
    return $idSolicitud;
}
