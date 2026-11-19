<?php
require_once __DIR__ . '/../../../util/verificar.php';
require_once __DIR__ . '/../../../util/db.php';
$soloDatos = ($_GET['datos'] ?? '') === '1';
if ($soloDatos) {
    header('Cache-Control: no-store');
    if (!haySesionActiva(false)) {
        http_response_code(401);
        exit;
    }
} else {
    requerirSesion();
}
$idCuenta = (int) $_SESSION['usuario_id'];
session_write_close();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$unidades = [];
$error = '';

// Solo dispositivos de apertura de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getPDO()->prepare(
        'SELECT m.idMicrocontrolador AS id_microcontrolador, l.nombreLocal AS nombre_local, e.nombreEmpresa AS nombre_empresa,
                m.ultimaConsulta, m.ultimaApertura, m.habilitado,
                TIMESTAMPDIFF(SECOND, m.ultimaConsulta, UTC_TIMESTAMP()) AS segundos_sin_consulta
         FROM microcontroladores m
         INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
         INNER JOIN empresas e ON e.idEmpresa = m.idEmpresa
         INNER JOIN empresas_cuentas c ON c.idEmpresa = m.idEmpresa
         WHERE c.idCuenta = ?
         ORDER BY m.idMicrocontrolador'
    );
    $stmt->execute([$idCuenta]);
    $unidades = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en verEstado: ' . $e->getMessage());
    $error = 'No se pudieron cargar los dispositivos de apertura.';
    if ($soloDatos) {
        http_response_code(500);
        exit;
    }
}

$fmt = fn($f) => $f ? date('d/m/Y H:i:s', strtotime($f)) : '';
$si = fn($v) => $v ? 'Sí' : 'No';
// ultimaConsulta es un DATETIME en UTC. Los valores futuros no indican actividad.
$activo = fn($seg) => $seg !== null && (int) $seg >= 0 && (int) $seg <= 15;
ob_start();
foreach ($unidades as $u):
    $estaActivo = $activo($u['segundos_sin_consulta']);
    $ultimaConsultaUtc = $u['ultimaConsulta'];
?>
    <article class="tarjeta<?= $estaActivo ? ' activo' : '' ?>">
        <h2>Microcontrolador #<span data-col="0"><?= (int) $u['id_microcontrolador'] ?></span></h2>
        <dl>
            <div><dt>Estado</dt><dd class="<?= $estaActivo ? 'estado-activo' : 'estado-inactivo' ?>" data-col="5"><?= $estaActivo ? 'Activo' : 'Inactivo' ?></dd></div>
            <div><dt>Habilitado</dt><dd data-col="6"><?= $si($u['habilitado']) ?></dd></div>
            <div><dt>Local</dt><dd data-col="1"><?= $h($u['nombre_local']) ?></dd></div>
            <div><dt>Empresa</dt><dd data-col="2"><?= $h($u['nombre_empresa']) ?></dd></div>
            <div><dt>Última consulta</dt><dd><?php if ($ultimaConsultaUtc): ?>
                <time class="consulta-utc" datetime="<?= $h(str_replace(' ', 'T', $ultimaConsultaUtc) . 'Z') ?>"><?= $h($ultimaConsultaUtc) ?> UTC</time>
            <?php else: ?>—<?php endif; ?></dd></div>
            <div><dt>Última apertura</dt><dd><?= $h($fmt($u['ultimaApertura']) ?: '—') ?></dd></div>
        </dl>
    </article>
<?php endforeach;
$tarjetas = ob_get_clean();
if ($soloDatos) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['tarjetas' => $tarjetas], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dispositivos de apertura | <?= $h($nombreWeb) ?></title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 8px; }
        .filtros label { display: block; font-size: .8rem; color: #6b7a86; margin-bottom: 4px; }
        .filtros input, .filtros select { width: 100%; padding: 9px 10px; border: 1px solid #cfd8de; border-radius: 8px; font: inherit; background: #fff; }
        .filtros input:focus, .filtros select:focus { outline: 2px solid #3a8fb7; border-color: transparent; }
        .contador { font-size: .85rem; color: #6b7a86; margin: 10px 0 14px; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 14px; }
        .tarjeta { min-width: 0; aspect-ratio: 1 / 1; background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-left: 5px solid #c0392b; }
        .tarjeta.activo { border-left-color: #1e8e3e; }
        .tarjeta h2 { font-size: 1.05rem; margin-bottom: 12px; overflow-wrap: anywhere; }
        .tarjeta dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .tarjeta dt { color: #6b7a86; font-size: .8rem; }
        .tarjeta dd { margin-top: 2px; font-size: .95rem; overflow-wrap: anywhere; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        .estado-activo { color: #1e8e3e; font-weight: 700; }
        .estado-inactivo { color: #c0392b; font-weight: 700; }
        [hidden] { display: none !important; }
        @media (max-width: 480px) { body { padding: 12px; } .panel { padding: 18px; } }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Dispositivos de apertura</h1>

            <p class="vacio" id="avisoEstado" role="status"<?= $error ? '' : ' hidden' ?>><?= $h($error) ?></p>
            <p class="vacio" id="sinDispositivos"<?= $unidades || $error ? ' hidden' : '' ?>>Tu empresa no tiene dispositivos de apertura registrados.</p>
            <div class="filtros" id="filtrosEstado"<?= $unidades ? '' : ' hidden' ?>>
                <div><label for="f-id">ID</label><input type="search" id="f-id" data-col="0" autocomplete="off"></div>
                <div><label for="f-local">Local</label><input type="search" id="f-local" data-col="1" autocomplete="off"></div>
                <div><label for="f-empresa">Empresa</label><input type="search" id="f-empresa" data-col="2" autocomplete="off"></div>
                <div><label for="f-estado">Estado</label>
                    <select id="f-estado" data-col="5" data-exacto="1"><option value="">Todos</option><option>Activo</option><option>Inactivo</option></select></div>
                <div><label for="f-habilitado">Habilitado</label>
                    <select id="f-habilitado" data-col="6" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
            </div>
            <p class="contador" id="contador" aria-live="polite"<?= $unidades ? '' : ' hidden' ?>></p>
            <div class="tarjetas" id="tarjetasEstado"><?= $tarjetas ?></div>
            <p class="vacio" id="sinResultados" hidden>Ninguna unidad de control coincide con los filtros.</p>
    </section>

    <script>
        (function () {
            var tarjetas = document.getElementById('tarjetasEstado');
            var campos = Array.prototype.slice.call(document.querySelectorAll('.filtros [data-col]'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');
            var sinDispositivos = document.getElementById('sinDispositivos');
            var filtros = document.getElementById('filtrosEstado');
            var aviso = document.getElementById('avisoEstado');
            var url = new URL(window.location.href);
            url.searchParams.set('datos', '1');
            url.hash = '';
            var consultando = false;
            var ultimoHtml = tarjetas.innerHTML;
            var formatoConsulta = new Intl.DateTimeFormat('es', {
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
                timeZoneName: 'shortOffset'
            });

            function mostrarConsultasEnHoraLocal() {
                tarjetas.querySelectorAll('time.consulta-utc').forEach(function (elemento) {
                    var fecha = new Date(elemento.dateTime);
                    if (!Number.isNaN(fecha.getTime())) elemento.textContent = formatoConsulta.format(fecha);
                });
            }

            // Minúsculas y sin acentos para que "si" y "Sí" coincidan
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            function filtrar() {
                var activos = campos.map(function (c) {
                    return { col: +c.dataset.col, v: norm(c.value), exacto: !!c.dataset.exacto };
                }).filter(function (f) { return f.v !== ''; });
                var visibles = 0;
                var datos = tarjetas.querySelectorAll('.tarjeta');
                datos.forEach(function (tarjeta) {
                    var ok = activos.every(function (f) {
                        var valor = norm(tarjeta.querySelector('[data-col="' + f.col + '"]').textContent);
                        return f.exacto ? valor === f.v : valor.includes(f.v);
                    });
                    tarjeta.hidden = !ok;
                    if (ok) visibles++;
                });
                filtros.hidden = contador.hidden = datos.length === 0;
                sinDispositivos.hidden = datos.length !== 0 || !aviso.hidden;
                sinResultados.hidden = datos.length === 0 || visibles !== 0;
                var texto = visibles + ' de ' + datos.length + ' dispositivos de apertura';
                if (contador.textContent !== texto) contador.textContent = texto;
            }

            async function actualizarEstado() {
                if (consultando) return;
                consultando = true;
                try {
                    var respuesta = await fetch(url, { cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(10000) });
                    if (respuesta.status === 401) {
                        clearInterval(intervalo);
                        aviso.textContent = 'Tu sesión ha expirado. Vuelve a iniciar sesión para actualizar los dispositivos.';
                        aviso.hidden = false;
                        filtrar();
                        return;
                    }
                    if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
                    var datos = await respuesta.json();
                    if (typeof datos?.tarjetas !== 'string') throw new Error('Respuesta inesperada');
                    var html = datos.tarjetas;
                    var cambio = html !== ultimoHtml || !aviso.hidden;
                    if (html !== ultimoHtml) {
                        tarjetas.innerHTML = html;
                        ultimoHtml = html;
                        mostrarConsultasEnHoraLocal();
                    }
                    aviso.hidden = true;
                    if (cambio) filtrar();
                } catch (error) {
                    var mensaje = 'No se pudo actualizar el estado de los dispositivos. Reintentando…';
                    if (aviso.textContent !== mensaje) aviso.textContent = mensaje;
                    aviso.hidden = false;
                    sinDispositivos.hidden = true;
                } finally {
                    consultando = false;
                }
            }

            campos.forEach(function (c) {
                c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrar);
            });
            mostrarConsultasEnHoraLocal();
            filtrar();
            var intervalo = setInterval(actualizarEstado, 1000);
        })();
    </script>
</body>
</html>
