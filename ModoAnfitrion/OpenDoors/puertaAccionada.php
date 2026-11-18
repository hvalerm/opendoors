<?php
require_once __DIR__ . '/../../util/verificar.php';
require_once __DIR__ . '/../../util/db.php';
require_once __DIR__ . '/../../util/contacto.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
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
    session_write_close();
}

// Sin cuenta, el ID procede exclusivamente del permiso temporal guardado en sesión.
function obtenerLocal(int $idLocal, ?int $idCuenta): ?array
{
    $stmt = getPDO()->prepare(
        'SELECT l.accionar, l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local,
                e.telefonoContacto AS telefono_anfitrion
         FROM locales l
         INNER JOIN empresas e ON e.idEmpresa = l.idEmpresa' .
        ($idCuenta !== null ? ' INNER JOIN empresas_cuentas c ON c.idEmpresa = l.idEmpresa' : '') .
        ' WHERE l.idLocal = ? AND l.eliminado = 0' .
        ($idCuenta !== null ? ' AND c.idCuenta = ?' : '')
    );
    $stmt->execute($idCuenta !== null ? [$idLocal, $idCuenta] : [$idLocal]);
    return $stmt->fetch() ?: null;
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
    try {
        $local = $idLocal ? obtenerLocal($idLocal, $esHuesped ? null : (int) $_SESSION['usuario_id']) : null;
    } catch (PDOException $e) {
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
    echo json_encode(['accionar' => (int) $local['accionar']]);
    exit;
}

if (!$esHuesped) {
    requerirSesion();
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$local = null;
try {
    $local = $idLocal ? obtenerLocal($idLocal, $esHuesped ? null : (int) $_SESSION['usuario_id']) : null;
} catch (PDOException $e) {
    error_log('Error en puertaAccionada: ' . $e->getMessage());
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
$urlEstado = 'puertaAccionada.php?estado=1&id=' . (int) $idLocal;
if ($esHuesped && $autorizado) {
    $urlEstado = 'puertaAccionada.php?estado=1&confirmacion=' . rawurlencode($confirmacion);
}
$urlVolver = 'Locales/localSeleccionado.php?id=' . (int) $idLocal . ($embed ? '&embed=1' : '');
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
            var ESPERA_MAX_MS = 30000;
            var INTERVALO_MS = 1500;
            var estado = document.getElementById('estado');
            var icono = document.getElementById('icono');
            var texto = document.getElementById('texto');
            var cuentaRegresiva = document.getElementById('cuentaRegresiva');
            var segundosRestantes = document.getElementById('segundosRestantes');
            var unidadTiempo = document.getElementById('unidadTiempo');
            var contactosError = document.getElementById('contactosError');
            var url = new URL(<?= json_encode($urlEstado) ?>, location.href).href;
            var limite = Date.now() + ESPERA_MAX_MS;
            var finalizado = false;
            var timerCuentaRegresiva;
            <?php if ($esHuesped): ?>
            var cerrarVentana = document.getElementById('cerrarVentana');
            var avisoCierre = document.getElementById('avisoCierre');
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

            function terminar(exito) {
                if (finalizado) return;
                finalizado = true;
                clearInterval(timerCuentaRegresiva);
                cuentaRegresiva.hidden = true;
                if (exito) {
                    mostrar('ok', '😊', 'La orden de apertura fue ejecutada con éxito');
                    <?php if ($esHuesped): ?>
                    cerrarVentana.hidden = false;
                    <?php endif; ?>
                } else {
                    mostrar('fallo', '😞', 'No se confirmó la apertura de la puerta en 30 segundos.');
                    contactosError.hidden = false;
                }
            }

            function actualizarCuentaRegresiva() {
                var segundos = Math.max(0, Math.ceil((limite - Date.now()) / 1000));
                segundosRestantes.textContent = segundos;
                unidadTiempo.textContent = segundos === 1 ? 'segundo' : 'segundos';
                if (segundos === 0) terminar(false);
            }

            async function consultar() {
                var restante = limite - Date.now();
                if (restante <= 0) {
                    terminar(false);
                    return;
                }

                var controller = new AbortController();
                var tiempoAgotado = setTimeout(function () { controller.abort(); }, restante);
                try {
                    var respuesta = await fetch(url, {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: controller.signal
                    });
                    if (Date.now() >= limite) {
                        terminar(false);
                        return;
                    }
                    if (!respuesta.ok) throw new Error('No se pudo consultar el estado de la puerta.');

                    var datos = await respuesta.json();
                    if (Date.now() >= limite) {
                        terminar(false);
                    } else if (datos.accionar === 0) {
                        terminar(true);
                    }
                } catch (error) {
                    if (Date.now() >= limite) {
                        terminar(false);
                    } else {
                        texto.textContent = 'No se pudo consultar el estado; reintentando...';
                    }
                } finally {
                    clearTimeout(tiempoAgotado);
                }

                restante = limite - Date.now();
                if (!finalizado) {
                    setTimeout(consultar, Math.min(INTERVALO_MS, Math.max(restante, 0)));
                }
            }

            actualizarCuentaRegresiva();
            timerCuentaRegresiva = setInterval(actualizarCuentaRegresiva, 250);
            consultar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
