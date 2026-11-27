<?php
require_once __DIR__ . '/../../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../util/aperturas.php';
require_once __DIR__ . '/../util/iniciar_seguimiento.php';
require_once __DIR__ . '/../util/estado_dispositivo.php';
require_once __DIR__ . '/../../../../util/Seguridad/modulos.php';
requerirSesion();
requerirModulo('OpenDoors');

$idEmpresa = (int) $_SESSION['empresa_id'];
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']);
$mensaje = '';
$esError = false;
$local = null;
$estadoDispositivo = null;
$consultaEstado = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado_dispositivo';
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Solo se accede a locales de la empresa de la cuenta con sesión iniciada
function cargarLocal(int $idLocal, int $idEmpresa): ?array
{
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT l.idLocal AS id_local, l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local, ? AS nombre_empresa,
                l.accionar, l.habilitado, l.eliminado, l.idEmpresa AS id_empresa, l.zonaHoraria
         FROM locales l
         WHERE l.idLocal = ? AND l.idEmpresa = ? AND l.eliminado = 0'
    );
    $stmt->execute([$_SESSION['empresa_nombre'], $idLocal, $idEmpresa]);
    return $stmt->fetch() ?: null;
}

try {
    if ($idLocal) {
        $local = cargarLocal($idLocal, $idEmpresa);
        if ($local) {
            $estadoDispositivo = estadoDispositivoLocal(getOpenDoorsPDO(), (int) $local['id_local'], $idEmpresa);
        }
    }

    if ($consultaEstado) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $valido = $local && $local['habilitado'] && $estadoDispositivo === 'activo';
        echo json_encode([
            'valido' => (bool) $valido,
            'mensaje' => !$local ? 'El local ya no está disponible.'
                : (!$local['habilitado'] ? 'El local está deshabilitado.' : mensajeEstadoDispositivo($estadoDispositivo)),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf'] ?? '';
        if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
            $mensaje = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
            $esError = true;
        } elseif (($_POST['accion'] ?? '') === 'abrir_puerta') {
            if (!$local['habilitado']) {
                $mensaje = 'El local está deshabilitado.';
                $esError = true;
            } else {
                $pdo = getOpenDoorsPDO();
                $pdo->beginTransaction();
                $bloqueado = bloquearLocalApertura($pdo, (int) $local['id_local'], $idEmpresa);
                if (!$bloqueado || $bloqueado['eliminado'] || !$bloqueado['habilitado']) {
                    throw new DomainException('El lugar ya no está disponible o está deshabilitado.');
                }
                $estadoDispositivo = estadoDispositivoLocal($pdo, (int) $local['id_local'], $idEmpresa, true);
                if ($estadoDispositivo !== 'activo') {
                    throw new DomainException(mensajeEstadoDispositivo($estadoDispositivo));
                }
                $idSolicitud = emitirSolicitudApertura($pdo, (int) $local['id_local'], $idEmpresa);
                iniciarSeguimientoApertura((int) $local['id_local'], $idEmpresa, $idSolicitud);
                $pdo->commit();
                header('Location: ../puertaAccionada?id=' . $local['id_local'] . '&solicitud=' . $idSolicitud . ($embed ? '&embed=1' : ''));
                exit;
            }
        } elseif (($_POST['accion'] ?? '') === 'anular_apertura') {
            $pdo = getOpenDoorsPDO();
            $pdo->beginTransaction();
            $bloqueado = bloquearLocalApertura($pdo, (int) $local['id_local'], $idEmpresa);
            $ahora = ahoraAperturas($pdo);
            $solicitud = $bloqueado ? reconciliarSolicitudApertura($pdo, $bloqueado, $ahora) : null;
            $cancelada = $solicitud && $solicitud['estado'] === 'pendiente';
            if ($cancelada) {
                finalizarSolicitudApertura($pdo, $solicitud, ResultadoApertura::Cancelada, $ahora);
            }
            $pdo->commit();
            if ($cancelada) {
                $mensaje = 'La apertura de puerta fue anulada.';
            } else {
                $mensaje = 'No hay una apertura pendiente para anular.';
                $esError = true;
            }
            $local = cargarLocal($idLocal, $idEmpresa);
        } else {
            http_response_code(400);
            $mensaje = 'Acción no válida.';
            $esError = true;
        }
    }
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(409);
    $mensaje = $e->getMessage();
    $esError = true;
} catch (PDOException | LogicException | RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error en localSeleccionado: ' . $e->getMessage());
    if ($consultaEstado) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['valido' => false, 'mensaje' => 'No se pudo consultar el dispositivo.']);
        exit;
    }
    $mensaje = 'No se pudo completar la operación. Inténtalo más tarde.';
    $esError = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local seleccionado | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 24px; }
        dt { color: #6b7a86; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; }
        .acciones form { margin: 0; }
        .btn { display: inline-block; text-decoration: none; border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #2c5364; cursor: pointer; }
        .btn:hover { background: #203a43; }
        .btn.secundario { background: #3a8fb7; }
        .btn.peligro { background: #b3261e; }
        .btn.peligro:hover { background: #8c1d18; }
        .btn:disabled { background: #a3adb5; cursor: not-allowed; }
        .aviso { margin-bottom: 18px; padding: 10px 14px; border-radius: 8px; background: #e6f4ea; color: #1e6b34; }
        .aviso.error { background: #fdecea; color: #b3261e; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$local): ?>
            <h1>Local no encontrado</h1>
            <p>El local no existe o no pertenece a tu empresa.</p>
        <?php else:
            $zonaHoraria = $local['zonaHoraria'];
            $desfaseUtc = (new DateTimeImmutable('now', new DateTimeZone($zonaHoraria)))->format('P');
        ?>
            <h1><?= $h($local['nombre_local']) ?></h1>
            <?php if ($mensaje): ?>
                <p class="aviso<?= $esError ? ' error' : '' ?>"><?= $h($mensaje) ?></p>
            <?php elseif (!$local['habilitado']): ?>
                <p class="aviso error">El lugar está deshabilitado.</p>
            <?php endif; ?>
            <p class="aviso error" id="estadoError" role="status" <?= $estadoDispositivo === 'activo' ? 'hidden' : '' ?>><?= $h($estadoDispositivo === null ? 'No se pudo consultar el dispositivo.' : mensajeEstadoDispositivo($estadoDispositivo)) ?></p>
            <dl>
                <dt>Nombre</dt><dd><?= $h($local['nombre_local']) ?></dd>
                <dt>Dirección</dt><dd><?= $h($local['direccion_local']) ?></dd>
                <dt>Empresa</dt><dd><?= $h($local['nombre_empresa']) ?></dd>
                <dt>Zona horaria</dt><dd><?= $h($zonaHoraria . ' (' . $desfaseUtc . ')') ?></dd>
                <dt>Accionar</dt><dd><?= $local['accionar'] ? 'Activado (puerta solicitada)' : 'Inactivo' ?></dd>
                <dt>Habilitado</dt><dd><?= $local['habilitado'] ? 'Sí' : 'No' ?></dd>
            </dl>
            <div class="acciones">
                <form method="post" action="" id="formAbrir" data-accion-estado="estado_dispositivo">
                    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="accion" value="abrir_puerta">
                    <button type="submit" class="btn" id="btnAbrir" <?= $local['habilitado'] && $estadoDispositivo === 'activo' ? '' : 'disabled' ?>>Abrir puerta</button>
                </form>
                <form method="post" action="">
                    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
                    <input type="hidden" name="accion" value="anular_apertura">
                    <button type="submit" class="btn peligro" <?= (int) $local['accionar'] === 1 ? '' : 'disabled' ?>>Anular apertura de puerta</button>
                </form>
                <a class="btn secundario" href="../Accesos/CrearAccesoLocal?id=<?= (int) $local['id_local'] ?><?= $embed ? '&amp;embed=1' : '' ?>">Crear Acceso</a>
            </div>
        <?php endif; ?>
    </section>
    <?php if ($local): require __DIR__ . '/../util/monitor_apertura.php'; endif; ?>
</body>
</html>
