<?php
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../util/enc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, bool $ok, string $mensaje): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$cifrado = $_GET['cifrado'] ?? '';
if (!is_string($cifrado) || !preg_match('/^[0-9A-Fa-f]{1000}$/', $cifrado)) {
    responder(400, false, 'Parámetro cifrado no válido.');
}

try {
    $textoDescifrado = CustomCryptographer::descifrarNumero($cifrado);
} catch (RuntimeException $e) {
    error_log('Error en ApiSystem/api_board: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
} catch (Exception $e) {
    responder(401, false, 'Cifrado no válido.');
}

$idMicrocontrolador = ctype_digit($textoDescifrado) && strlen($textoDescifrado) <= 10
    ? (int) $textoDescifrado
    : 0;
if ($idMicrocontrolador <= 0) {
    responder(401, false, 'El cifrado no contiene un id de unidad de control válido.');
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
        responder(404, false, 'Unidad de control no encontrada.');
    }
    if (!$micro['habilitado']) {
        $pdo->rollBack();
        responder(403, false, 'La unidad de control está deshabilitada.');
    }

    $accionar = (int) $micro['accionar'];

    $pdo->prepare('UPDATE microcontroladores SET ultimaConsulta = NOW() WHERE idMicrocontrolador = ?')
        ->execute([$idMicrocontrolador]);

    if ($accionar === 1) {
        // El trigger de locales contabiliza la apertura al emitir la orden.
        $pdo->prepare('UPDATE locales SET accionar = 0 WHERE idLocal = ? AND idEmpresa = ?')
            ->execute([$micro['id_local'], $micro['id_empresa']]);
    }

    $pdo->commit();

    http_response_code(200);
    echo json_encode(['ok' => true, 'accionar' => $accionar], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en ApiSystem/api_board: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
}
