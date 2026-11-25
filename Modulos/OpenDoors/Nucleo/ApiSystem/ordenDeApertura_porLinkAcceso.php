<?php
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../util/aperturas.php';
require_once __DIR__ . '/../../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../util/vigencia_accesos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, bool $ok, string $mensaje): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// Paso 1: recibir el token por POST (un GET lo podría consumir una vista previa del enlace) y validar su formato
// (solo letras y números, hasta 250 caracteres)
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder(405, false, 'Método no permitido.');
}
$token = $_POST['token'] ?? '';
if (!is_string($token) || !preg_match('/^[A-Za-z0-9]{1,250}$/', $token)) {
    responder(400, false, 'Token no válido.');
}

try {
    $confirmar = ($_POST['confirmar'] ?? '') === '1';
    if ($confirmar) {
        iniciarSesionSegura();
        $confirmacion = bin2hex(random_bytes(32));
    }
    $pdo = getOpenDoorsPDO();
    // Paso 2: comparar el token con accesos.token y obtener los datos del acceso y su local
    // (el acceso no debe estar ejecutado y el local no debe estar eliminado)
    $stmt = $pdo->prepare(
        'SELECT a.idAcceso AS id_acceso, a.idLocal AS id_local, a.idEmpresa AS id_empresa, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta, a.habilitado, l.accionar
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         WHERE a.token = ? AND a.ejecutado = 0 AND l.eliminado = 0'
    );
    $stmt->execute([$token]);
    $acceso = $stmt->fetch();

    if (!$acceso) {
        responder(404, false, 'Acceso no encontrado.');
    }
    // Paso 3: verificar que el acceso esté habilitado
    if (!$acceso['habilitado']) {
        responder(403, false, 'El acceso está deshabilitado.');
    }
    // Paso 4: verificar que la hora actual esté dentro del rango de vigencia
    $ahora = ahoraVigenciaAccesos();
    if ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
        responder(403, false, 'El acceso está fuera de su horario de vigencia.');
    }

    // Paso 5: emitir la orden de apertura (accionar = 1 en el local) y marcar el acceso como ejecutado
    $pdo->beginTransaction();

    $marca = $pdo->prepare('UPDATE accesos SET ejecutado = 1 WHERE idAcceso = ? AND ejecutado = 0');
    $marca->execute([$acceso['id_acceso']]);
    if ($marca->rowCount() !== 1) {
        $pdo->rollBack();
        responder(404, false, 'Acceso no encontrado.');
    }

    $idSolicitud = emitirSolicitudApertura($pdo, (int) $acceso['id_local'], (int) $acceso['id_empresa']);

    $pdo->commit();

    if ($confirmar) {
        $_SESSION['confirmacion_apertura'] = [
            'clave' => $confirmacion,
            'id_local' => (int) $acceso['id_local'],
            'id_solicitud' => $idSolicitud,
            'expira' => time() + 120,
        ];
        session_write_close();
        header('Location: ../ModoAnfitrion/OpenDoors/puertaAccionada?confirmacion=' . $confirmacion, true, 303);
        exit;
    }

    // Los clientes de la API conservan la respuesta JSON.
    responder(200, true, 'Orden de apertura emitida.');
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(409, false, $e->getMessage());
} catch (PDOException | LogicException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en ApiSystem/ordenDeApertura_porLinkAcceso: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
}
