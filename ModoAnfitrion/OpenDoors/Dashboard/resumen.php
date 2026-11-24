<?php
require_once __DIR__ . '/../../../util/verificar.php';
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../../../util/vigencia_accesos.php';
$soloDispositivos = ($_GET['datos'] ?? '') === 'dispositivos';
if ($soloDispositivos) {
    header('Cache-Control: no-store');
    if (!haySesionActiva(false)) {
        http_response_code(401);
        exit;
    }
} else {
    requerirSesion();
}

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idEmpresa = (int) $_SESSION['empresa_id'];
session_write_close();

function cargarDispositivosDashboard(PDO $pdo, int $idEmpresa): array
{
    $q = $pdo->prepare(
        'SELECT l.nombreLocal AS nombre_local, l.zonaHoraria, m.idMicrocontrolador AS id_microcontrolador,
                m.habilitado, m.cantidadAperturasExitosas, m.cantidadAperturasFallidas, m.ultimaConsulta, m.ultimaApertura,
                TIMESTAMPDIFF(SECOND, m.ultimaConsulta, UTC_TIMESTAMP()) AS seg
         FROM microcontroladores m
         INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
         WHERE m.idEmpresa = ? ORDER BY m.cantidadAperturasExitosas DESC, l.nombreLocal'
    );
    $q->execute([$idEmpresa]);
    $dispositivos = $q->fetchAll();
    foreach ($dispositivos as &$dispositivo) {
        $dispositivo['activo'] = $dispositivo['seg'] !== null && (int) $dispositivo['seg'] >= 0 && (int) $dispositivo['seg'] <= 15;
    }
    return $dispositivos;
}

function renderDispositivosDashboard(array $dispositivos, callable $h): string
{
    if (!$dispositivos) {
        return '<p class="vacio">Aún no tienes dispositivos de apertura registrados.</p>';
    }
    ob_start();
    ?>
    <table>
        <thead><tr><th>Local</th><th>Estado</th><th>Última consulta</th><th>Última apertura</th></tr></thead>
        <tbody>
        <?php foreach ($dispositivos as $d): ?>
            <tr>
                <td><?= $h($d['nombre_local']) ?> (<?= $h($d['zonaHoraria']) ?>)</td>
                <td class="<?= $d['activo'] ? 'a' : 'i' ?>"><?= $d['activo'] ? 'Activo' : 'Inactivo' ?></td>
                <?php foreach (['ultimaConsulta', 'ultimaApertura'] as $campo): ?>
                    <td><?php if ($d[$campo]): ?>
                        <time datetime="<?= $h(str_replace(' ', 'T', $d[$campo]) . 'Z') ?>"><?= $h(mostrarFechaAcceso($d[$campo], $d['zonaHoraria'], 'd/m/Y H:i:s')) ?></time>
                    <?php else: ?>—<?php endif; ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

if ($soloDispositivos) {
    try {
        $html = renderDispositivosDashboard(cargarDispositivosDashboard(getOpenDoorsPDO(), $idEmpresa), $h);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['dispositivos' => $html], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        error_log('Error al actualizar dispositivos del resumen: ' . $e->getMessage());
        http_response_code(503);
    }
    exit;
}
$embed = isset($_GET['embed']);
$error = '';
$k = ['locales' => 0, 'locales_ok' => 0, 'vigentes' => 0, 'proximos' => 0, 'usados' => 0, 'revocados' => 0,
      'disp' => 0, 'disp_activos' => 0, 'aperturas' => 0, 'fallidas' => 0, 'puertas_pendientes' => 0];
$proximos = [];
$noUsados = [];
$k['no_usados'] = 0;
$alertas = [];
$porLocal = [];

try {
    $pdo = getOpenDoorsPDO();
    $ahora = ahoraVigenciaAccesos();

    // Locales
    $q = $pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(habilitado = 1), 0) AS ok, COALESCE(SUM(accionar = 1), 0) AS pend
                        FROM locales WHERE idEmpresa = ? AND eliminado = 0');
    $q->execute([$idEmpresa]);
    $r = $q->fetch();
    $k['locales'] = (int) $r['total'];
    $k['locales_ok'] = (int) $r['ok'];
    $k['puertas_pendientes'] = (int) $r['pend'];

    // Accesos por situación
    $q = $pdo->prepare(
        'SELECT COALESCE(SUM(habilitado = 1 AND ejecutado = 0 AND ? BETWEEN activoDesde AND activoHasta), 0) AS vigentes,
                COALESCE(SUM(habilitado = 1 AND ejecutado = 0 AND activoDesde > ?), 0) AS proximos,
                COALESCE(SUM(ejecutado = 1), 0) AS usados,
                COALESCE(SUM(habilitado = 0 AND ejecutado = 0), 0) AS revocados
         FROM accesos WHERE idEmpresa = ?'
    );
    $q->execute([$ahora, $ahora, $idEmpresa]);
    $r = $q->fetch();
    foreach (['vigentes', 'proximos', 'usados', 'revocados'] as $c) {
        $k[$c] = (int) $r[$c];
    }

    // Próximas llegadas y accesos vigentes sin usar
    $q = $pdo->prepare(
        'SELECT l.nombreLocal AS nombre_local, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta, l.zonaHoraria, (a.activoDesde > ?) AS futuro
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         WHERE a.idEmpresa = ? AND a.habilitado = 1 AND a.ejecutado = 0 AND a.activoHasta >= ?
         ORDER BY a.activoDesde LIMIT 5'
    );
    $q->execute([$ahora, $idEmpresa, $ahora]);
    $proximos = $q->fetchAll();

    // Accesos vencidos que nunca se usaron
    $q = $pdo->prepare(
        'SELECT l.nombreLocal AS nombre_local, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta, l.zonaHoraria
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         WHERE a.idEmpresa = ? AND a.habilitado = 1 AND a.ejecutado = 0 AND a.activoHasta < ?
         ORDER BY a.activoHasta DESC'
    );
    $q->execute([$idEmpresa, $ahora]);
    $noUsados = $q->fetchAll();
    $k['no_usados'] = count($noUsados);
    $noUsados = array_slice($noUsados, 0, 5);

    // Dispositivos y aperturas por local
    foreach (cargarDispositivosDashboard($pdo, $idEmpresa) as $d) {
        $k['disp']++;
        $k['aperturas'] += (int) $d['cantidadAperturasExitosas'];
        $k['fallidas'] += (int) $d['cantidadAperturasFallidas'];
        if ($d['activo']) {
            $k['disp_activos']++;
        } elseif ($d['habilitado']) {
            $alertas[] = 'El dispositivo de «' . $d['nombre_local'] . '» no responde. Revisa que esté encendido y con internet.';
        }
        $porLocal[] = $d;
    }

    // Locales habilitados sin dispositivo
    $q = $pdo->prepare(
        'SELECT l.nombreLocal AS nombre_local FROM locales l
         WHERE l.idEmpresa = ? AND l.eliminado = 0 AND l.habilitado = 1
           AND NOT EXISTS (SELECT 1 FROM microcontroladores m WHERE m.idLocal = l.idLocal AND m.idEmpresa = l.idEmpresa)'
    );
    $q->execute([$idEmpresa]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $alertas[] = '«' . $n . '» no tiene dispositivo de apertura, sus huéspedes no podrán abrir la puerta.';
    }
} catch (PDOException $e) {
    error_log('Error en resumen: ' . $e->getMessage());
    $error = 'No se pudo cargar el resumen.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumen | <?= $h($nombreWeb) ?></title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin-bottom: 4px; }
        .sub { color: #6b7a86; margin-bottom: 20px; }
        h2 { font-size: 1rem; margin-bottom: 12px; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .tarjeta { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border-left: 5px solid #3a8fb7; }
        .tarjeta .n { font-size: 2rem; font-weight: 700; line-height: 1.1; }
        .tarjeta .t { font-weight: 600; margin-top: 4px; }
        .tarjeta .d { font-size: .82rem; color: #6b7a86; margin-top: 2px; }
        .verde { border-left-color: #1e8e3e; } .naranja { border-left-color: #e08a00; } .rojo { border-left-color: #c0392b; } .gris { border-left-color: #8a98a3; }
        .caja { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
        .dos { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
        .alerta { background: #fff4e5; border: 1px solid #f0c987; color: #7a4a00; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; font-size: .9rem; }
        .ok { background: #e8f5ec; border: 1px solid #a9d8b8; color: #17632c; border-radius: 8px; padding: 10px 14px; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #e1e8ec; }
        th { color: #6b7a86; font-weight: 600; background: #f7f9fa; }
        td.num, th.num { text-align: right; }
        .vacio { color: #6b7a86; padding: 8px 0; }
        .chip { display: inline-block; font-size: .75rem; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
        .chip.ahora { background: #e8f5ec; color: #1e8e3e; } .chip.luego { background: #e8f1f7; color: #2c6f94; }
        .a { color: #1e8e3e; font-weight: 700; } .i { color: #c0392b; font-weight: 700; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Resumen</h1>
        <p class="sub">Lo más importante de tu negocio, de un vistazo.</p>

        <?php if ($error): ?>
            <div class="caja"><p class="vacio"><?= $h($error) ?></p></div>
        <?php else: ?>
            <div class="caja">
                <h2>Atención</h2>
                <?php foreach ($alertas as $a): ?><div class="alerta">⚠️ <?= $h($a) ?></div><?php endforeach; ?>
                <?php if ($k['puertas_pendientes']): ?>
                    <div class="alerta">🚪 Hay <?= $k['puertas_pendientes'] ?> orden(es) de apertura esperando a ser ejecutada(s) por el dispositivo.</div>
                <?php endif; ?>
                <?php if (!$alertas && !$k['puertas_pendientes']): ?>
                    <div class="ok">✅ Todo en orden. No hay nada que requiera tu atención.</div>
                <?php endif; ?>
            </div>

            <div class="tarjetas">
                <div class="tarjeta"><div class="n"><?= $k['locales_ok'] ?>/<?= $k['locales'] ?></div><div class="t">Locales habilitados</div><div class="d">Alojamientos listos para recibir huéspedes</div></div>
                <div class="tarjeta <?= $k['disp'] && $k['disp_activos'] === $k['disp'] ? 'verde' : 'rojo' ?>"><div class="n"><?= $k['disp_activos'] ?>/<?= $k['disp'] ?></div><div class="t">Dispositivos activos</div><div class="d">Equipos conectados ahora mismo</div></div>
                <div class="tarjeta verde"><div class="n"><?= $k['vigentes'] ?></div><div class="t">Accesos vigentes</div><div class="d">Huéspedes que pueden entrar ahora</div></div>
                <div class="tarjeta naranja"><div class="n"><?= $k['proximos'] ?></div><div class="t">Próximos accesos</div><div class="d">Llegadas programadas</div></div>
                <div class="tarjeta gris"><div class="n"><?= $k['usados'] ?></div><div class="t">Accesos usados</div><div class="d">Huéspedes que ya abrieron</div></div>
                <div class="tarjeta <?= $k['no_usados'] ? 'rojo' : 'verde' ?>"><div class="n"><?= $k['no_usados'] ?></div><div class="t">Accesos sin usar</div>                <div class="d">Vencieron sin que el huésped abriera</div></div>
                <div class="tarjeta"><div class="n"><?= $k['aperturas'] ?></div><div class="t">Aperturas exitosas</div><div class="d">Confirmaciones de los dispositivos</div></div>
                <div class="tarjeta <?= $k['fallidas'] ? 'rojo' : 'verde' ?>"><div class="n"><?= $k['fallidas'] ?></div><div class="t">Aperturas fallidas</div><div class="d">Sin confirmación en 30 segundos</div></div>
            </div>

            <div class="dos">
                <div class="caja">
                    <h2>Próximas llegadas</h2>
                    <?php if (!$proximos): ?>
                        <p class="vacio">No hay accesos pendientes. Crea uno en Accesos → Crear.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Local</th><th>Desde</th><th>Hasta</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($proximos as $p): ?>
                                <tr>
                                    <td><?= $h($p['nombre_local']) ?></td>
                                    <td><?= $h(mostrarFechaAcceso($p['activo_desde'], $p['zonaHoraria'], 'd/m/Y H:i T')) ?></td>
                                    <td><?= $h(mostrarFechaAcceso($p['activo_hasta'], $p['zonaHoraria'], 'd/m/Y H:i T')) ?></td>
                                    <td><?= $p['futuro'] ? '<span class="chip luego">Próximo</span>' : '<span class="chip ahora">Vigente</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="caja">
                    <h2>Dispositivo(s) de local(es)</h2>
                    <p class="alerta" id="avisoDispositivos" role="status" hidden></p>
                    <div id="dispositivosDashboard"><?= renderDispositivosDashboard($porLocal, $h) ?></div>
                </div>
            </div>

            <div class="caja">
                <h2>Accesos que no se usaron<?= $k['no_usados'] > 5 ? ' (últimos 5 de ' . $k['no_usados'] . ')' : '' ?></h2>
                <?php if (!$noUsados): ?>
                    <p class="vacio">Ningún acceso venció sin usarse. ¡Bien!</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Local</th><th>Desde</th><th>Venció</th></tr></thead>
                        <tbody>
                        <?php foreach ($noUsados as $n): ?>
                            <tr>
                                <td><?= $h($n['nombre_local']) ?></td>
                                <td><?= $h(mostrarFechaAcceso($n['activo_desde'], $n['zonaHoraria'], 'd/m/Y H:i T')) ?></td>
                                <td><?= $h(mostrarFechaAcceso($n['activo_hasta'], $n['zonaHoraria'], 'd/m/Y H:i T')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if ($k['revocados']): ?>
                <p class="vacio"><?= $k['revocados'] ?> acceso(s) revocado(s) sin usar.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php if (!$error): ?>
    <script>
        (function () {
            var contenedor = document.getElementById('dispositivosDashboard');
            var aviso = document.getElementById('avisoDispositivos');
            var url = new URL(window.location.href);
            url.searchParams.set('datos', 'dispositivos');
            var consultando = false;
            var intervalo = setInterval(async function () {
                if (consultando) return;
                consultando = true;
                try {
                    var respuesta = await fetch(url, { cache: 'no-store', redirect: 'error', signal: AbortSignal.timeout(10000) });
                    if (respuesta.status === 401) {
                        clearInterval(intervalo);
                        aviso.textContent = 'Tu sesión ha expirado. Vuelve a iniciar sesión para actualizar los dispositivos.';
                        aviso.hidden = false;
                        return;
                    }
                    if (!respuesta.ok) throw new Error('HTTP ' + respuesta.status);
                    var datos = await respuesta.json();
                    if (typeof datos?.dispositivos !== 'string') throw new Error('Respuesta inesperada');
                    if (contenedor.innerHTML !== datos.dispositivos) contenedor.innerHTML = datos.dispositivos;
                    aviso.hidden = true;
                } catch (error) {
                    aviso.textContent = 'No se pudo actualizar la última consulta de los dispositivos. Reintentando…';
                    aviso.hidden = false;
                } finally {
                    consultando = false;
                }
            }, 1000);
        })();
    </script>
    <?php endif; ?>
</body>
</html>
