<?php
require_once __DIR__ . '/../../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../util/db.php';
require_once __DIR__ . '/../util/vigencia_accesos.php';
require_once __DIR__ . '/../../../../util/Seguridad/modulos.php';
requerirSesion();
requerirModulo('OpenDoors');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$accesos = [];
$locales = [];
$error = '';

// Solo accesos de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT a.idAcceso AS id_acceso, a.idLocal AS id_local, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta,
                l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local, ? AS nombre_empresa,
                a.ejecutado, a.habilitado, l.zonaHoraria,
                (? BETWEEN a.activoDesde AND a.activoHasta AND a.habilitado = 1 AND a.ejecutado = 0) AS en_rango
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         WHERE a.idEmpresa = ?
         ORDER BY a.idAcceso DESC'
    );
    $stmt->execute([$_SESSION['empresa_nombre'], ahoraVigenciaAccesos(), (int) $_SESSION['empresa_id']]);
    $accesos = $stmt->fetchAll();
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT l.idLocal, l.nombreLocal, l.direccionLocal
         FROM locales l
         WHERE l.idEmpresa = ? AND (l.eliminado = 0 OR EXISTS (
             SELECT 1 FROM accesos a WHERE a.idLocal = l.idLocal AND a.idEmpresa = l.idEmpresa
         ))
         ORDER BY l.nombreLocal, l.direccionLocal, l.idLocal'
    );
    $stmt->execute([(int) $_SESSION['empresa_id']]);
    $locales = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en listarAccesos: ' . $e->getMessage());
    $accesos = [];
    $error = 'No se pudieron cargar los accesos.';
}

$si = fn($v) => $v ? 'Sí' : 'No';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Listar accesos | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1200px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        .filtros { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 8px; }
        .filtros label { display: block; font-size: .8rem; color: #6b7a86; margin-bottom: 4px; }
        .filtros input, .filtros select { width: 100%; padding: 9px 10px; border: 1px solid #cfd8de; border-radius: 8px; font: inherit; background: #fff; }
        .filtros input:focus, .filtros select:focus { outline: 2px solid #3a8fb7; border-color: transparent; }
        .contador { font-size: .85rem; color: #6b7a86; margin: 10px 0 14px; }
        .instruccion { color: #52616b; margin-bottom: 12px; }
        .tabla { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e1e8ec; white-space: nowrap; }
        th { color: #6b7a86; font-weight: 600; background: #f7f9fa; }
        .fila { cursor: pointer; }
        .fila:hover { background: #f2f7fa; }
        .enlace-id { color: #2c5364; font-weight: 600; text-decoration: none; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        .si { color: #1e8e3e; font-weight: 700; }
        .no { color: #c0392b; font-weight: 700; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Listar accesos</h1>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$accesos): ?>
            <p class="vacio">Tu empresa no tiene accesos registrados.</p>
        <?php else: ?>
            <div class="filtros">
                <div><label for="f-id">ID acceso</label><input type="search" id="f-id" data-col="0" autocomplete="off"></div>
                <div><label for="f-desde">Activo desde (a partir de)</label><input type="date" id="f-desde"></div>
                <div><label for="f-hasta">Activo hasta (hasta)</label><input type="date" id="f-hasta"></div>
                <div>
                    <label for="f-local">Local</label>
                    <input type="search" id="f-local" data-col="3" list="opciones-local" placeholder="Nombre o dirección" autocomplete="off">
                    <datalist id="opciones-local">
                        <?php foreach ($locales as $local): ?>
                            <option value="<?= $h($local['nombreLocal'] . ' - ' . $local['direccionLocal']) ?>" data-local-id="<?= (int) $local['idLocal'] ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div><label for="f-empresa">Empresa</label><input type="search" id="f-empresa" data-col="4" autocomplete="off"></div>
                <div><label for="f-ejecutado">Ejecutado</label>
                    <select id="f-ejecutado" data-col="5" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
                <div><label for="f-habilitado">Habilitado</label>
                    <select id="f-habilitado" data-col="6" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
                <div><label for="f-enrango">¿Se puede usar?</label>
                    <select id="f-enrango" data-col="7" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
            </div>
            <p class="contador" id="contador" aria-live="polite"></p>
            <p class="instruccion">Seleccione un acceso para editarlo</p>
            <div class="tabla">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Activo desde</th><th>Activo hasta</th><th>Local</th><th>Empresa</th><th>Ejecutado</th><th>Habilitado</th><th>¿Se puede usar?</th></tr>
                    </thead>
                    <tbody id="cuerpo">
                        <?php foreach ($accesos as $a): $zona = $a['zonaHoraria']; ?>
                            <tr class="fila" data-href="vetanaDelAcceso?id=<?= (int) $a['id_acceso'] ?><?= $embed ? '&amp;embed=1' : '' ?>" data-local-id="<?= (int) $a['id_local'] ?>" data-desde="<?= $h(mostrarFechaAcceso($a['activo_desde'], $zona, 'Y-m-d')) ?>" data-hasta="<?= $h(mostrarFechaAcceso($a['activo_hasta'], $zona, 'Y-m-d')) ?>">
                                <td><a class="enlace-id" href="vetanaDelAcceso?id=<?= (int) $a['id_acceso'] ?><?= $embed ? '&amp;embed=1' : '' ?>"><?= (int) $a['id_acceso'] ?></a></td>
                                <td><?= $h(mostrarFechaAcceso($a['activo_desde'], $zona, 'd/m/Y H:i T')) ?></td>
                                <td><?= $h(mostrarFechaAcceso($a['activo_hasta'], $zona, 'd/m/Y H:i T')) ?></td>
                                <td data-busqueda="<?= $h($a['nombre_local'] . ' - ' . $a['direccion_local']) ?>"><?= $h($a['nombre_local']) ?></td>
                                <td><?= $h($a['nombre_empresa']) ?></td>
                                <td><?= $si($a['ejecutado']) ?></td>
                                <td><?= $si($a['habilitado']) ?></td>
                                <td class="<?= $a['en_rango'] ? 'si' : 'no' ?>"><?= $si($a['en_rango']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="vacio" id="sinResultados" hidden>Ningún acceso coincide con los filtros.</p>
        <?php endif; ?>
    </section>

    <?php if ($accesos): ?>
    <script>
        (function () {
            var filas = Array.prototype.slice.call(document.querySelectorAll('#cuerpo tr'));
            var campos = Array.prototype.slice.call(document.querySelectorAll('.filtros [data-col]'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');
            var opcionesLocal = Array.prototype.slice.call(document.querySelectorAll('#opciones-local option'));

            var fDesde = document.getElementById('f-desde');
            filas.forEach(function (tr) {
                tr.addEventListener('click', function () { window.location.href = tr.dataset.href; });
            });
            var fHasta = document.getElementById('f-hasta');

            // Minúsculas y sin acentos para que "si" y "Sí" coincidan
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            var datos = filas.map(function (tr) {
                return {
                    tr: tr,
                    localId: tr.dataset.localId,
                    desde: tr.dataset.desde,
                    hasta: tr.dataset.hasta,
                    celdas: Array.prototype.map.call(tr.cells, function (td) { return norm(td.dataset.busqueda || td.textContent); })
                };
            });

            function filtrar() {
                var activos = campos.map(function (c) {
                    return { col: +c.dataset.col, v: norm(c.value), exacto: !!c.dataset.exacto };
                }).filter(function (f) { return f.v !== ''; });
                var textoLocal = norm(document.getElementById('f-local').value);
                var localesElegidos = opcionesLocal.filter(function (opcion) {
                    return textoLocal !== '' && norm(opcion.value) === textoLocal;
                }).map(function (opcion) { return opcion.dataset.localId; });
                var visibles = 0;
                datos.forEach(function (x) {
                    // El formato yyyy-mm-dd permite comparar como texto
                    var ok = activos.every(function (f) {
                        if (f.col === 3 && localesElegidos.length) return localesElegidos.indexOf(x.localId) !== -1;
                        return f.exacto ? x.celdas[f.col] === f.v : x.celdas[f.col].indexOf(f.v) !== -1;
                    }) && (!fDesde.value || x.desde >= fDesde.value)
                       && (!fHasta.value || x.hasta <= fHasta.value);
                    x.tr.hidden = !ok;
                    if (ok) visibles++;
                });
                sinResultados.hidden = visibles !== 0;
                contador.textContent = visibles + ' de ' + datos.length + ' accesos';
            }

            campos.forEach(function (c) {
                c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrar);
            });
            fDesde.addEventListener('input', filtrar);
            fHasta.addEventListener('input', filtrar);
            filtrar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
