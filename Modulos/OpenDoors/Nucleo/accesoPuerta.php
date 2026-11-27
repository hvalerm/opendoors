<?php
require_once __DIR__ . '/util/db.php';
require_once __DIR__ . '/../../../util/Configuracion/DatosWeb.php';
require_once __DIR__ . '/util/vigencia_accesos.php';
require_once __DIR__ . '/util/estado_dispositivo.php';
require_once __DIR__ . '/util/aperturas.php';

header('Cache-Control: no-store');
// El token viaja en la URL: no debe filtrarse por Referer ni indexarse
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$esValidacion = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'validar';
$estado = 'invalido';
$acceso = null;
$estadoDispositivo = null;

if (is_string($token) && preg_match('/^[A-Za-z0-9]{1,250}$/', $token)) {
    try {
        $pdo = getOpenDoorsPDO();
        $stmt = $pdo->prepare(
            'SELECT a.idAcceso AS id_acceso, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta, a.ejecutado, a.habilitado,
                    a.idLocal AS id_local, a.idEmpresa AS id_empresa,
                    l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local, l.zonaHoraria,
                    pendiente.idLocal AS local_pendiente,
                    (pendiente.accionar = 0 OR pendiente.aperturaVenceEn <= UTC_TIMESTAMP()) AS resolver_pendiente
             FROM accesos a
             INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
             LEFT JOIN locales pendiente ON pendiente.aperturaIdAcceso = a.idAcceso
                 AND pendiente.idEmpresa = a.idEmpresa AND pendiente.aperturaEstado = \'pendiente\'
             WHERE a.token = ? AND l.eliminado = 0'
        );
        $stmt->execute([$token]);
        $acceso = $stmt->fetch();
        if ($acceso && $acceso['local_pendiente'] !== null && $acceso['resolver_pendiente']) {
            reconciliarAccesoPendiente($pdo, (int) $acceso['local_pendiente'], (int) $acceso['id_empresa'], (int) $acceso['id_acceso']);
            $stmt->execute([$token]);
            $acceso = $stmt->fetch();
        }
    } catch (PDOException | LogicException $e) {
        error_log('Error en accesoPuerta: ' . $e->getMessage());
        $estado = 'error';
    }

    if ($estado !== 'error') {
        $ahora = ahoraVigenciaAccesos();
        if (!$acceso) {
            $estado = 'invalido';
        } elseif ($acceso['ejecutado']) {
            $estado = 'usado';
        } elseif (!$acceso['habilitado']) {
            $estado = 'deshabilitado';
        } elseif ($ahora < $acceso['activo_desde'] || $ahora > $acceso['activo_hasta']) {
            $estado = 'fuera_de_horario';
        } else {
            $estado = 'valido';
            try {
                $estadoDispositivo = estadoDispositivoLocal(getOpenDoorsPDO(), (int) $acceso['id_local'], (int) $acceso['id_empresa']);
            } catch (PDOException $e) {
                error_log('Error al consultar dispositivo en accesoPuerta: ' . $e->getMessage());
                $estado = 'error';
            }
        }
    }
}

$mensajes = [
    'usado' => 'Acceso usado.',
    'invalido' => 'El enlace de acceso no es válido.',
    'deshabilitado' => 'Este acceso está deshabilitado.',
    'fuera_de_horario' => 'Este acceso no está vigente en este momento.',
    'error' => 'Ocurrió un error. Inténtalo nuevamente en unos minutos.',
];
// Validación previa al clic en "Abrir puerta": responde el estado actual del acceso en JSON
if ($esValidacion) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'valido' => $estado === 'valido' && $acceso['local_pendiente'] === null && $estadoDispositivo === 'activo',
        'mensaje' => $estado === 'valido'
            ? ($acceso['local_pendiente'] !== null ? 'Ya hay una apertura pendiente para este acceso.' : mensajeEstadoDispositivo($estadoDispositivo))
            : $mensajes[$estado],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | <?= $h($nombreWeb) ?></title>
    <link rel="icon" type="image/png" href="img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); padding: 20px; }
        .card { width: 100%; max-width: 480px; background: #fff; border-radius: 14px; padding: 32px 28px; box-shadow: 0 10px 30px rgba(0,0,0,.3); text-align: center; }
        h1 { font-size: 1.4rem; color: #1b2a35; margin-bottom: 18px; }
        .dato { text-align: left; background: #f4f7f9; border-radius: 8px; padding: 12px 14px; margin-bottom: 10px; }
        .dato span { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7a86; }
        .dato strong { color: #1b2a35; font-size: 1.05rem; }
        .advertencia { margin: 20px 0 10px; padding: 16px; border: 3px solid #c0392b; border-radius: 10px; background: #fdecea; color: #8e1b10; text-align: left; font-weight: 700; line-height: 1.45; }
        .advertencia .titulo { display: block; font-size: 1.1rem; margin-bottom: 6px; text-transform: uppercase; }
        .unico { margin-bottom: 18px; padding: 12px 14px; border: 2px solid #e67e22; border-radius: 10px; background: #fff4e5; color: #8a4b08; text-align: left; font-weight: 600; line-height: 1.4; }
        .btn { display: block; width: 100%; padding: 15px; border-radius: 8px; background: #27ae60; color: #fff; font-size: 1.1rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; font-family: inherit; }
        .btn:hover { background: #1f8f4f; }
        .btn:disabled { background: #a3adb5; cursor: not-allowed; }
        .estado { padding: 22px 16px; border-radius: 10px; background: #fdecea; color: #8e1b10; font-size: 1.3rem; font-weight: 700; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <section class="card">
        <?php if ($estado === 'valido'): ?>
            <h1>Acceso al alojamiento</h1>
            <div class="dato"><span>Local</span><strong><?= $h($acceso['nombre_local']) ?></strong></div>
            <div class="dato"><span>Dirección</span><strong><?= $h($acceso['direccion_local']) ?></strong></div>
            <div class="dato"><span>Vigente desde</span><strong><?= $h(mostrarFechaAcceso($acceso['activo_desde'], $acceso['zonaHoraria'])) ?></strong></div>
            <div class="dato"><span>Vigente hasta</span><strong><?= $h(mostrarFechaAcceso($acceso['activo_hasta'], $acceso['zonaHoraria'])) ?></strong></div>
            <div class="dato"><span>Zona horaria del alojamiento</span><strong><?= $h($acceso['zonaHoraria']) ?></strong></div>

            <div class="advertencia" role="alert">
                <span class="titulo">⚠ Advertencia de seguridad</span>
                Debes estar físicamente frente a la puerta del hospedaje antes de continuar. Al presionar el botón la puerta se abrirá y sólo deben ingresar los huéspedes.
            </div>
            <div class="unico">Este enlace es de un único uso: se podrá volver a intentar si la apertura falla, pero no después de una apertura confirmada.</div>

            <form method="post" action="<?= $h(parse_url($urlApiOrdenApertura, PHP_URL_PATH)) ?>" id="formAbrir" data-accion-estado="validar">
                <input type="hidden" name="token" value="<?= $h($token) ?>">
                <input type="hidden" name="confirmar" value="1">
                <button type="submit" class="btn" id="btnAbrir" <?= $estadoDispositivo === 'activo' && $acceso['local_pendiente'] === null ? '' : 'disabled' ?>>Abrir puerta</button>
            </form>
            <div class="estado" id="estadoError" role="status" <?= $estadoDispositivo === 'activo' && $acceso['local_pendiente'] === null ? 'hidden' : '' ?>><?= $h($acceso['local_pendiente'] !== null ? 'Ya hay una apertura pendiente para este acceso.' : mensajeEstadoDispositivo($estadoDispositivo)) ?></div>
        <?php else: ?>
            <h1>Acceso al alojamiento</h1>
            <div class="estado"><?= $h($mensajes[$estado]) ?></div>
        <?php endif; ?>
    </section>
    <?php if ($estado === 'valido'): ?>
    <?php require __DIR__ . '/util/monitor_apertura.php'; ?>
    <?php endif; ?>
</body>
</html>
