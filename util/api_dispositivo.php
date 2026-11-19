<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/enc.php';

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
        $pdo = getPDO();
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT m.idLocal AS id_local, m.idEmpresa AS id_empresa, m.habilitado, l.accionar
             FROM microcontroladores m
             INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
             WHERE m.idMicrocontrolador = ? AND l.eliminado = 0
             FOR UPDATE'
        );
        $stmt->execute([$idMicrocontrolador]);
        $micro = $stmt->fetch();

        if (!$micro) {
            $pdo->rollBack();
            responderDispositivo(404, false, 'Unidad de control no encontrada.');
        }
        if (!$micro['habilitado']) {
            $pdo->rollBack();
            responderDispositivo(403, false, 'La unidad de control está deshabilitada.');
        }

        $accionar = (int) $micro['accionar'];
        if (!$restablecer) {
            $pdo->prepare('UPDATE microcontroladores SET ultimaConsulta = NOW() WHERE idMicrocontrolador = ?')
                ->execute([$idMicrocontrolador]);
        }
        if ($operacion !== OperacionDispositivo::Consulta && $accionar === 1) {
            $pdo->prepare('UPDATE locales SET accionar = 0 WHERE idLocal = ? AND idEmpresa = ?')
                ->execute([$micro['id_local'], $micro['id_empresa']]);
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['ok' => true, 'accionar' => $restablecer ? 0 : $accionar], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error en API de dispositivo (' . $operacion->name . '): ' . $e->getMessage());
        responderDispositivo(500, false, 'Error interno.');
    }
}
