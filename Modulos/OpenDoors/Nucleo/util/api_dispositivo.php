<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/aperturas.php';
require_once __DIR__ . '/../../../../util/enc.php';

enum OperacionDispositivo
{
    case Consulta;
    case Restablecer;
    case Legado;
}

function responderDispositivo(int $codigo, bool $ok, string $mensaje): never
{
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function atenderDispositivo(OperacionDispositivo $operacion): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $restablecer = $operacion === OperacionDispositivo::Restablecer;
    if ($restablecer && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        responderDispositivo(405, false, 'Método no permitido.');
    }
    $cifrado = ($restablecer ? $_POST : $_GET)['cifrado'] ?? '';
    if (!is_string($cifrado) || !preg_match('/^[0-9A-Fa-f]{1000}$/', $cifrado)) {
        responderDispositivo(400, false, 'Parámetro cifrado no válido.');
    }

    try {
        $textoDescifrado = CustomCryptographer::descifrarNumero($cifrado);
    } catch (RuntimeException $e) {
        error_log('Error en API de dispositivo (' . $operacion->name . '): ' . $e->getMessage());
        responderDispositivo(500, false, 'Error interno.');
    } catch (Exception $e) {
        responderDispositivo(401, false, 'Cifrado no válido.');
    }

    $idMicrocontrolador = ctype_digit($textoDescifrado) && strlen($textoDescifrado) <= 10
        ? (int) $textoDescifrado
        : 0;
    if ($idMicrocontrolador <= 0) {
        responderDispositivo(401, false, 'El cifrado no contiene un id de unidad de control válido.');
    }

    try {
        $pdo = getOpenDoorsPDO();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT m.idLocal AS id_local, m.idEmpresa AS id_empresa, m.habilitado, l.accionar
             FROM microcontroladores m
             INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
             WHERE m.idMicrocontrolador = ? AND l.eliminado = 0'
        );
        $stmt->execute([$idMicrocontrolador]);
        $micro = $stmt->fetch();

        if (!$micro) {
            $pdo->rollBack();
            responderDispositivo(404, false, 'Unidad de control no encontrada.');
        }
        $local = bloquearLocalApertura($pdo, (int) $micro['id_local'], (int) $micro['id_empresa']);
        $stmt = $pdo->prepare('SELECT habilitado FROM microcontroladores WHERE idMicrocontrolador = ?
            AND idLocal = ? AND idEmpresa = ? FOR UPDATE');
        $stmt->execute([$idMicrocontrolador, $micro['id_local'], $micro['id_empresa']]);
        $habilitado = $stmt->fetchColumn();
        if (!$local || $local['eliminado'] || $habilitado === false) {
            $pdo->rollBack();
            responderDispositivo(404, false, 'Unidad de control no encontrada.');
        }
        if (!$habilitado) {
            $pdo->rollBack();
            responderDispositivo(403, false, 'La unidad de control está deshabilitada.');
        }

        $ahora = ahoraAperturas($pdo);
        $solicitud = reconciliarSolicitudApertura($pdo, $local, $ahora);
        if (!$solicitud && $local['accionar']) {
            throw new LogicException('El local tiene una orden sin seguimiento de apertura.');
        }
        $accionar = $solicitud && $solicitud['estado'] === 'pendiente' ? 1 : 0;
        if (!$restablecer) {
            $pdo->prepare('UPDATE microcontroladores SET ultimaConsulta = UTC_TIMESTAMP() WHERE idMicrocontrolador = ?')
                ->execute([$idMicrocontrolador]);
        }
        if ($operacion !== OperacionDispositivo::Consulta && $accionar === 1) {
            finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Exitosa, $ahora);
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['ok' => true, 'accionar' => $restablecer ? 0 : $accionar], JSON_UNESCAPED_UNICODE);
    } catch (PDOException | LogicException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error en API de dispositivo (' . $operacion->name . '): ' . $e->getMessage());
        responderDispositivo(500, false, 'Error interno.');
    }
}
