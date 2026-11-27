<?php
require_once __DIR__ . '/../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../../../util/Seguridad/modulos.php';
require_once __DIR__ . '/../../../util/BaseDatos/db.php';
require_once __DIR__ . '/util/db.php';
require_once __DIR__ . '/util/aperturas.php';
require_once __DIR__ . '/../../../util/Soporte/contacto.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idSolicitud = $_GET['solicitud'] ?? null;
$esHuesped = isset($_GET['confirmacion']);
$autorizado = false;
if ($esHuesped) {
    iniciarSesionSegura();
    $confirmacion = $_GET['confirmacion'];
    $permiso = $_SESSION['confirmacion_apertura'] ?? null;
    $autorizado = is_string($confirmacion) && is_array($permiso)
        && $permiso['expira'] >= time()
        && hash_equals($permiso['clave'], $confirmacion);
    $idLocal = $autorizado ? $permiso['id_local'] : null;
    $idSolicitud = $autorizado ? ($permiso['id_solicitud'] ?? null) : null;
    session_write_close();
}

// Sin cuenta, el ID procede exclusivamente del permiso temporal guardado en sesión.
function obtenerLocal(int $idLocal, ?int $idEmpresa): ?array
{
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT l.idLocal, l.idEmpresa, l.accionar, l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local
         FROM locales l
         WHERE l.idLocal = ? AND l.eliminado = 0' .
        ($idEmpresa !== null ? ' AND l.idEmpresa = ?' : '')
    );
    $stmt->execute($idEmpresa !== null ? [$idLocal, $idEmpresa] : [$idLocal]);
    return $stmt->fetch() ?: null;
}

function estadoAperturaLocal(array $local, mixed $idSolicitud): array
{
    if ($idSolicitud !== null && (!is_string($idSolicitud) || !preg_match('/^[a-f0-9]{64}$/D', $idSolicitud))) {
        error_log('Consulta de apertura con identificador de solicitud no valido.');
        http_response_code(400);
        return ['accionar' => (int) $local['accionar'], 'estado' => 'sin_solicitud', 'segundos_restantes' => 0];
    }
    $pdo = getOpenDoorsPDO();
    try {
        $pdo->beginTransaction();
        $bloqueado = bloquearLocalApertura($pdo, (int) $local['idLocal'], (int) $local['idEmpresa']);
        if (!$bloqueado) {
            throw new DomainException('El local ya no existe.');
        }
        $ahora = ahoraAperturas($pdo);
        $actual = reconciliarSolicitudApertura($pdo, $bloqueado, $ahora);
        $solicitud = $actual && ($idSolicitud === null || hash_equals($actual['idSolicitud'], $idSolicitud))
            ? $actual : null;
        $pdo->commit();
        $restante = $solicitud && $solicitud['estado'] === 'pendiente'
            ? max(0, strtotime($solicitud['venceEn'] . ' UTC') - strtotime($ahora . ' UTC')) : 0;
        return [
            'accionar' => $actual && $actual['estado'] !== 'pendiente' ? 0 : (int) $bloqueado['accionar'],
            'estado' => $solicitud['estado'] ?? 'sin_solicitud',
            'segundos_restantes' => $restante,
        ];
    } catch (PDOException | LogicException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Endpoint consultado por la página mientras espera el resultado de la apertura.
if (isset($_GET['estado'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($esHuesped ? !$autorizado : !haySesionActiva()) {
        http_response_code(401);
        echo json_encode(['error' => 'sesion']);
        exit;
    }
    $empresaOpenDoors = $esHuesped ? null : empresaOpenDoors();
    $empresaEstado = $esHuesped ? ($permiso['id_empresa'] ?? null) : $empresaOpenDoors['id_empresa'];
    if ($idLocal && is_numeric($empresaEstado) && (int) $empresaEstado > 0
        && is_string($idSolicitud) && preg_match('/^[a-f0-9]{64}$/D', $idSolicitud)) {
        try {
            $estadoTemporal = leerEstadoTemporal((int) $idLocal, (int) $empresaEstado, $idSolicitud);
            if ($estadoTemporal !== null) {
                echo json_encode($estadoTemporal);
                exit;
            }
        } catch (RuntimeException | JsonException $e) {
            error_log('No se pudo consultar el estado temporal de apertura: ' . $e->getMessage());
        }
    }
    try {
        $local = $idLocal ? obtenerLocal($idLocal, $esHuesped ? null : (int) $empresaOpenDoors['id_empresa']) : null;
        $estadoApertura = $local ? estadoAperturaLocal($local, $idSolicitud) : null;
    } catch (PDOException | LogicException $e) {
        error_log('Error en puertaAccionada: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'bd']);
        exit;
    }
    if ($local === null) {
        http_response_code(404);
        echo json_encode(['error' => 'local']);
        exit;
    }
    echo json_encode($estadoApertura);
    exit;
}

if (!$esHuesped) {
    requerirSesion();
    $empresaOpenDoors = empresaOpenDoors();
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$local = null;
$estadoApertura = null;
try {
    $local = $idLocal ? obtenerLocal($idLocal, $esHuesped ? null : (int) $empresaOpenDoors['id_empresa']) : null;
    $estadoApertura = $local ? estadoAperturaLocal($local, $idSolicitud) : null;
    if ($local) {
        $empresa = getPDO()->prepare('SELECT telefonoContacto FROM empresas WHERE idEmpresa = ?');
        $empresa->execute([$local['idEmpresa']]);
        $telefono = $empresa->fetchColumn();
        if ($telefono === false) {
            throw new LogicException('No existe la empresa del local.');
        }
        $local['telefono_anfitrion'] = $telefono;
    }
} catch (PDOException | LogicException $e) {
    error_log('Error en puertaAccionada: ' . $e->getMessage());
    http_response_code(503);
    exit('No se pudo consultar la apertura. Intentalo mas tarde.');
}
$existe = $local !== null;
$telefonoAnfitrion = $existe ? normalizarTelefonoContacto($local['telefono_anfitrion'] ?? null) : null;
$telefonoSoporte = null;
if ($existe) {
    try {
        $telefonoSoporte = obtenerTelefonoSoporte(getPDO());
    } catch (PDOException $e) {
        error_log('Error al obtener el teléfono de soporte: ' . $e->getMessage());
    }
}
$urlEstado = 'puertaAccionada?estado=1&id=' . (int) $idLocal;
if (is_string($idSolicitud)) {
    $urlEstado .= '&solicitud=' . rawurlencode($idSolicitud);
}
if ($esHuesped && $autorizado) {
    $urlEstado = 'puertaAccionada?estado=1&confirmacion=' . rawurlencode($confirmacion);
} elseif (!$esHuesped) {
    $urlEstado = urlEmpresaOpenDoors($urlEstado, (int) $empresaOpenDoors['id_empresa']);
}
$urlVolver = 'Locales/localSeleccionado?id=' . (int) $idLocal . ($embed ? '&embed=1' : '');
if (!$esHuesped) {
    $urlVolver = urlEmpresaOpenDoors($urlVolver, (int) $empresaOpenDoors['id_empresa']);
}
$urlReintentar = $esHuesped && $autorizado && isset($permiso['token_acceso'])
    ? $urlAccesoPuerta . '?token=' . rawurlencode($permiso['token_acceso'])
    : null;
$mensajeContacto = rawurlencode(
    'Hola, no se confirmó la apertura de la puerta del local "' . ($local['nombre_local'] ?? '') .
    '", ubicado en ' . ($local['direccion_local'] ?? '') .
    ', después de 30 segundos. ¿Me pueden ayudar?'
);
$urlAnfitrion = $telefonoAnfitrion !== null
    ? 'https://wa.me/' . $telefonoAnfitrion . '?text=' . $mensajeContacto
    : null;
$urlSoporte = $telefonoSoporte !== null
    ? 'https://wa.me/' . $telefonoSoporte . '?text=' . $mensajeContacto
    : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apertura de puerta | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 40px 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); text-align: center; }
        .estado { display: flex; align-items: center; justify-content: center; gap: 14px; font-size: 1.3rem; font-weight: 600; }
        .estado .cara { font-size: 2.2rem; line-height: 1; }
        .estado.espera { color: #6b7a86; }
        .estado.ok { color: #1e8e3e; }
        .estado.fallo { color: #c5221f; }
        .cuenta-regresiva { margin-top: 18px; color: #52616b; line-height: 1.5; }
        .cuenta-regresiva strong { display: block; margin-top: 6px; color: #1b2a35; font-size: 1.2rem; }
        .spinner { width: 22px; height: 22px; border: 3px solid #d5dde2; border-top-color: #2c5364; border-radius: 50%; animation: giro 1s linear infinite; }
        @keyframes giro { to { transform: rotate(360deg); } }
        .contactos-error { display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }
        .contactos-error[hidden] { display: none; }
        .btn-contacto { display: block; padding: 13px 18px; border-radius: 8px; color: #fff; font-weight: 600; text-decoration: none; }
        .btn-anfitrion { background: #2c5364; }
        .btn-soporte { background: #25d366; }
        .volver { display: inline-block; margin-top: 26px; color: #2c5364; font-weight: 600; text-decoration: none; }
        .volver[hidden] { display: none; }
        .volver:hover { text-decoration: underline; }
        .cerrar { margin-top: 26px; padding: 13px 18px; border: 0; border-radius: 8px; background: #2c5364; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .cerrar[hidden] { display: none; }
        .aviso-cierre { margin-top: 12px; color: #52616b; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$existe): ?>
            <p><?= $esHuesped ? 'La confirmación no es válida o ha expirado.' : 'El local no existe o no pertenece a tu empresa.' ?></p>
        <?php else: ?>
            <div class="estado espera" id="estado" role="status" aria-live="polite">
                <span class="spinner" id="icono"></span>
                <span id="texto">Orden de apertura de puerta emitida, esperando confirmación</span>
            </div>
            <p class="cuenta-regresiva" id="cuentaRegresiva" role="timer" aria-live="off">
                Esperamos a que el dispositivo de apertura confirme que recibió la orden. Si no responde dentro de este tiempo, te mostraremos cómo contactar al anfitrión o a soporte.
                <strong>Tiempo restante: <span id="segundosRestantes">30</span> <span id="unidadTiempo">segundos</span></strong>
            </p>
            <div class="contactos-error" id="contactosError" hidden>
                <?php if ($urlAnfitrion !== null): ?>
                    <a class="btn-contacto btn-anfitrion" href="<?= $h($urlAnfitrion) ?>" target="_blank" rel="noopener">Contactar al anfitrión</a>
                <?php else: ?>
                    <p class="contacto-no-disponible">El teléfono del anfitrión no está disponible.</p>
                <?php endif; ?>
                <?php if ($urlSoporte !== null): ?>
                    <a class="btn-contacto btn-soporte" href="<?= $h($urlSoporte) ?>" target="_blank" rel="noopener">Contactar a soporte</a>
                <?php else: ?>
                    <p class="contacto-no-disponible">El teléfono de soporte no está disponible.</p>
                <?php endif; ?>
            </div>
            <?php if ($esHuesped): ?>
                <?php if ($urlReintentar !== null): ?>
                    <a class="volver" id="reintentar" href="<?= $h($urlReintentar) ?>" hidden>Volver al enlace para reintentar</a>
                <?php endif; ?>
                <button type="button" class="cerrar" id="cerrarVentana" hidden>Cerrar ventana</button>
                <p class="aviso-cierre" id="avisoCierre" role="status" hidden>Tu navegador no permite cerrar esta ventana automáticamente. Ciérrala desde el navegador.</p>
            <?php else: ?>
                <a class="volver" href="<?= $h($urlVolver) ?>">Volver al local</a>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($existe): ?>
    <script>
        (function () {
            var INTERVALO_MS = 1500;
            var estado = document.getElementById('estado');
            var icono = document.getElementById('icono');
            var texto = document.getElementById('texto');
            var cuentaRegresiva = document.getElementById('cuentaRegresiva');
            var segundosRestantes = document.getElementById('segundosRestantes');
            var unidadTiempo = document.getElementById('unidadTiempo');
            var contactosError = document.getElementById('contactosError');
            var url = new URL(<?= json_encode($urlEstado) ?>, location.href).href;
            var estadoInicial = <?= json_encode($estadoApertura, JSON_HEX_TAG) ?>;
            var limite = Date.now() + estadoInicial.segundos_restantes * 1000;
            var finalizado = false;
            var timerCuentaRegresiva;
            <?php if ($esHuesped): ?>
            var cerrarVentana = document.getElementById('cerrarVentana');
            var avisoCierre = document.getElementById('avisoCierre');
            var reintentar = document.getElementById('reintentar');
            cerrarVentana.addEventListener('click', function () {
                window.close();
                setTimeout(function () { avisoCierre.hidden = false; }, 300);
            });
            <?php endif; ?>

            function mostrar(clase, cara, mensaje) {
                estado.className = 'estado ' + clase;
                icono.className = 'cara';
                icono.textContent = cara;
                texto.textContent = mensaje;
            }

            function terminar(resultado) {
                if (finalizado) return;
                finalizado = true;
                clearInterval(timerCuentaRegresiva);
                cuentaRegresiva.hidden = true;
                if (resultado === 'exitosa') {
                    mostrar('ok', '😊', 'La orden de apertura fue ejecutada con éxito');
                    <?php if ($esHuesped): ?>
                    cerrarVentana.hidden = false;
                    <?php endif; ?>
                } else if (resultado === 'fallida') {
                    mostrar('fallo', '😞', 'No se confirmó la apertura de la puerta en 30 segundos.');
                    contactosError.hidden = false;
                    <?php if ($esHuesped): ?>
                    if (reintentar) reintentar.hidden = false;
                    <?php endif; ?>
                } else {
                    mostrar('fallo', '—', resultado === 'cancelada'
                        ? 'La orden de apertura fue anulada.'
                        : 'No hay una solicitud de apertura para confirmar.');
                    <?php if ($esHuesped): ?>
                    if (resultado === 'cancelada' && reintentar) reintentar.hidden = false;
                    <?php endif; ?>
                }
            }

            function actualizarCuentaRegresiva() {
                var segundos = Math.max(0, Math.ceil((limite - Date.now()) / 1000));
                segundosRestantes.textContent = segundos;
                unidadTiempo.textContent = segundos === 1 ? 'segundo' : 'segundos';
            }

            async function consultar() {
                var controller = new AbortController();
                var tiempoAgotado = setTimeout(function () { controller.abort(); }, 5000);
                try {
                    var respuesta = await fetch(url, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: controller.signal
                    });
                    if (!respuesta.ok) throw new Error('No se pudo consultar el estado de la puerta.');

                    var datos = await respuesta.json();
                    if (datos.estado === 'pendiente') {
                        limite = Date.now() + datos.segundos_restantes * 1000;
                        actualizarCuentaRegresiva();
                    } else if (['exitosa', 'fallida', 'cancelada', 'sin_solicitud'].includes(datos.estado)) {
                        terminar(datos.estado);
                    } else {
                        throw new Error('Estado de apertura no valido.');
                    }
                } catch (error) {
                    texto.textContent = 'No se pudo consultar el estado; reintentando...';
                } finally {
                    clearTimeout(tiempoAgotado);
                }

                if (!finalizado) {
                    setTimeout(consultar, INTERVALO_MS);
                }
            }

            actualizarCuentaRegresiva();
            timerCuentaRegresiva = setInterval(actualizarCuentaRegresiva, 250);
            if (estadoInicial.estado === 'pendiente') {
                consultar();
            } else {
                terminar(estadoInicial.estado);
            }
        })();
    </script>
    <?php endif; ?>
</body>
</html>
