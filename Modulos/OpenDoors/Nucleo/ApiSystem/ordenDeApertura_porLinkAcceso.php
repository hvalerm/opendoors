<?php
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../util/aperturas.php';
require_once __DIR__ . '/../util/iniciar_seguimiento.php';
require_once __DIR__ . '/../util/estado_dispositivo.php';
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

function responderAperturaPendiente(bool $confirmar, string $token, string $mensaje): void
{
    global $urlAccesoPuerta;
    if ($confirmar) {
        session_write_close();
        header('Location: ' . parse_url($urlAccesoPuerta, PHP_URL_PATH) . '?token=' . rawurlencode($token), true, 303);
        exit;
    }
    responder(409, false, $mensaje);
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
    $stmt = $pdo->prepare(
        'SELECT a.idAcceso AS id_acceso, a.idLocal AS id_local, a.idEmpresa AS id_empresa,
                pendiente.idLocal AS local_pendiente,
                (pendiente.accionar = 0 OR pendiente.aperturaVenceEn <= UTC_TIMESTAMP()) AS resolver_pendiente
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         LEFT JOIN locales pendiente ON pendiente.aperturaIdAcceso = a.idAcceso
             AND pendiente.idEmpresa = a.idEmpresa AND pendiente.aperturaEstado = \'pendiente\'
         WHERE a.token = ? AND a.ejecutado = 0 AND l.eliminado = 0'
    );
    $stmt->execute([$token]);
    $acceso = $stmt->fetch();

    if (!$acceso) {
        responder(404, false, 'Acceso no encontrado.');
    }
    if ($acceso['local_pendiente'] !== null && $acceso['resolver_pendiente']) {
        reconciliarAperturaPendiente($pdo, (int) $acceso['local_pendiente'], (int) $acceso['id_empresa'], (int) $acceso['id_acceso']);
    }

    $pdo->beginTransaction();
    $local = bloquearLocalApertura($pdo, (int) $acceso['id_local'], (int) $acceso['id_empresa']);
    if (!$local || $local['eliminado']) {
        throw new DomainException('El local ya no está disponible.');
    }
    $anterior = reconciliarSolicitudApertura($pdo, $local, ahoraAperturas($pdo));
    if (($anterior && $anterior['estado'] === 'pendiente') || (!$anterior && $local['accionar'])) {
        $pdo->rollBack();
        responderAperturaPendiente($confirmar, $token, 'Ya hay una apertura pendiente para este local.');
    }
    $stmt = $pdo->prepare(
        'SELECT idAcceso AS id_acceso, idLocal AS id_local, idEmpresa AS id_empresa,
                activoDesde AS activo_desde, activoHasta AS activo_hasta, habilitado
         FROM accesos WHERE idAcceso = ? AND idEmpresa = ? AND token = ? AND ejecutado = 0 FOR UPDATE'
    );
    $stmt->execute([$acceso['id_acceso'], $acceso['id_empresa'], $token]);
    $actual = $stmt->fetch();
    if (!$actual) {
        $pdo->commit();
        responder(404, false, 'Acceso no encontrado.');
    }
    if ((int) $actual['id_local'] !== (int) $acceso['id_local']) {
        $pdo->commit();
        responder(409, false, 'El local del acceso cambió. Vuelve a intentarlo.');
    }
    $acceso = $actual;
    if (!$acceso['habilitado']) {
        $pdo->commit();
        responder(403, false, 'El acceso está deshabilitado.');
    }
    $ahora = ahoraVigenciaAccesos();
    if ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
        $pdo->commit();
        responder(403, false, 'El acceso está fuera de su horario de vigencia.');
    }

    $pendiente = $pdo->prepare("SELECT idLocal FROM locales WHERE aperturaIdAcceso = ? AND aperturaEstado = 'pendiente'");
    $pendiente->execute([$acceso['id_acceso']]);
    if ($pendiente->fetchColumn() !== false) {
        $pdo->commit();
        responderAperturaPendiente($confirmar, $token, 'Ya hay una apertura pendiente para este acceso.');
    }
    $estadoDispositivo = estadoDispositivoLocal($pdo, (int) $acceso['id_local'], (int) $acceso['id_empresa'], true);
    if ($estadoDispositivo !== 'activo') {
        throw new DomainException(mensajeEstadoDispositivo($estadoDispositivo));
    }
    $idSolicitud = emitirSolicitudApertura($pdo, (int) $acceso['id_local'], (int) $acceso['id_empresa'], (int) $acceso['id_acceso']);
    iniciarSeguimientoApertura((int) $acceso['id_local'], (int) $acceso['id_empresa'], $idSolicitud);

    $pdo->commit();

    if ($confirmar) {
        $_SESSION['confirmacion_apertura'] = [
            'clave' => $confirmacion,
            'id_local' => (int) $acceso['id_local'],
            'id_empresa' => (int) $acceso['id_empresa'],
            'id_solicitud' => $idSolicitud,
            'token_acceso' => $token,
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
} catch (PDOException | LogicException | RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en ApiSystem/ordenDeApertura_porLinkAcceso: ' . $e->getMessage());
    responder(500, false, 'Error interno.');
}
