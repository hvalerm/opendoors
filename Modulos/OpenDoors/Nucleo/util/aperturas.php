<?php
require_once __DIR__ . '/db.php';

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
    $stmt = $pdo->prepare('SELECT idLocal, idEmpresa, accionar, eliminado FROM locales
        WHERE idLocal = ? AND idEmpresa = ? FOR UPDATE');
    $stmt->execute([$idLocal, $idEmpresa]);
    return $stmt->fetch() ?: null;
}

function finalizarSolicitudApertura(PDO $pdo, array $solicitud, ResultadoApertura $resultado, string $ahora): array
{
    $stmt = $pdo->prepare("UPDATE solicitudes_apertura SET estado = ?, finalizadaEn = ?
        WHERE idSolicitud = ? AND estado = 'pendiente'");
    $stmt->execute([$resultado->value, $ahora, $solicitud['idSolicitud']]);
    if ($stmt->rowCount() !== 1) {
        throw new LogicException('La solicitud de apertura ya fue finalizada.');
    }
    $parametros = [$solicitud['idLocal'], $solicitud['idEmpresa']];
    $pdo->prepare('UPDATE locales SET accionar = 0 WHERE idLocal = ? AND idEmpresa = ?')
        ->execute($parametros);
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
    $solicitud['finalizadaEn'] = $ahora;
    return $solicitud;
}

function reconciliarSolicitudApertura(PDO $pdo, array $local, string $ahora): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_apertura
        WHERE idLocal = ? AND idEmpresa = ? AND estado = 'pendiente' FOR UPDATE");
    $stmt->execute([$local['idLocal'], $local['idEmpresa']]);
    $solicitud = $stmt->fetch();
    if (!$solicitud) {
        return null;
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
    $pdo->prepare("INSERT INTO solicitudes_apertura
        (idSolicitud, idLocal, idEmpresa, estado, solicitadaEn, venceEn)
        VALUES (?, ?, ?, 'pendiente', ?, ?)")
        ->execute([$idSolicitud, $idLocal, $idEmpresa, $ahora, $venceEn]);
    $pdo->prepare('UPDATE locales SET accionar = 1 WHERE idLocal = ? AND idEmpresa = ?')
        ->execute([$idLocal, $idEmpresa]);
    return $idSolicitud;
}

function procesarAperturasPendientes(PDO $pdo): int
{
    $stmt = $pdo->prepare("SELECT s.idLocal, s.idEmpresa FROM solicitudes_apertura s
        INNER JOIN locales l ON l.idLocal = s.idLocal AND l.idEmpresa = s.idEmpresa
        WHERE s.estado = 'pendiente' AND (s.venceEn <= ? OR l.accionar = 0)
        ORDER BY s.venceEn, s.idSolicitud LIMIT 100");
    $stmt->execute([ahoraAperturas($pdo)]);
    $locales = $stmt->fetchAll();
    $procesadas = 0;
    foreach ($locales as $ids) {
        try {
            $pdo->beginTransaction();
            $local = bloquearLocalApertura($pdo, (int) $ids['idLocal'], (int) $ids['idEmpresa']);
            if ($local) {
                $solicitud = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
                if ($solicitud && $solicitud['estado'] !== 'pendiente') {
                    $procesadas++;
                }
            }
            $pdo->commit();
        } catch (PDOException | LogicException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
    return $procesadas;
}
