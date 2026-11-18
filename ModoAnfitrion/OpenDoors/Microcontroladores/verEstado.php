<?php
require_once __DIR__ . '/../../../util/verificar.php';
require_once __DIR__ . '/../../../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$unidades = [];
$error = '';

// Solo dispositivos de apertura de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getPDO()->prepare(
        'SELECT m.idMicrocontrolador AS id_microcontrolador, l.nombreLocal AS nombre_local, e.nombreEmpresa AS nombre_empresa,
                m.ultimaConsulta, m.ultimaApertura, m.habilitado,
                TIMESTAMPDIFF(SECOND, m.ultimaConsulta, NOW()) AS segundos_sin_consulta
         FROM microcontroladores m
         INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
         INNER JOIN empresas e ON e.idEmpresa = m.idEmpresa
         INNER JOIN empresas_cuentas c ON c.idEmpresa = m.idEmpresa
         WHERE c.idCuenta = ?
         ORDER BY m.idMicrocontrolador'
    );
    $stmt->execute([$_SESSION['usuario_id']]);
    $unidades = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en verEstado: ' . $e->getMessage());
    $error = 'No se pudieron cargar los dispositivos de apertura.';
}

$fmt = fn($f) => $f ? date('d/m/Y H:i:s', strtotime($f)) : '';
$si = fn($v) => $v ? 'Sí' : 'No';
// Activo si la última consulta fue hace 15 segundos o menos (según la hora de la BD)
$activo = fn($seg) => $seg !== null && (int) $seg <= 15;
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

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$unidades): ?>
            <p class="vacio">Tu empresa no tiene dispositivos de apertura registrados.</p>
        <?php else: ?>
            <div class="filtros">
                <div><label for="f-id">ID</label><input type="search" id="f-id" data-col="0" autocomplete="off"></div>
                <div><label for="f-local">Local</label><input type="search" id="f-local" data-col="1" autocomplete="off"></div>
                <div><label for="f-empresa">Empresa</label><input type="search" id="f-empresa" data-col="2" autocomplete="off"></div>
                <div><label for="f-estado">Estado</label>
                    <select id="f-estado" data-col="5" data-exacto="1"><option value="">Todos</option><option>Activo</option><option>Inactivo</option></select></div>
                <div><label for="f-habilitado">Habilitado</label>
                    <select id="f-habilitado" data-col="6" data-exacto="1"><option value="">Todos</option><option>Sí</option><option>No</option></select></div>
            </div>
            <p class="contador" id="contador" aria-live="polite"></p>
            <div class="tarjetas">
                <?php foreach ($unidades as $u): $estaActivo = $activo($u['segundos_sin_consulta']); ?>
                    <article class="tarjeta<?= $estaActivo ? ' activo' : '' ?>">
                        <h2>Microcontrolador #<span data-col="0"><?= (int) $u['id_microcontrolador'] ?></span></h2>
                        <dl>
                            <div><dt>Estado</dt><dd class="<?= $estaActivo ? 'estado-activo' : 'estado-inactivo' ?>" data-col="5"><?= $estaActivo ? 'Activo' : 'Inactivo' ?></dd></div>
                            <div><dt>Habilitado</dt><dd data-col="6"><?= $si($u['habilitado']) ?></dd></div>
                            <div><dt>Local</dt><dd data-col="1"><?= $h($u['nombre_local']) ?></dd></div>
                            <div><dt>Empresa</dt><dd data-col="2"><?= $h($u['nombre_empresa']) ?></dd></div>
                            <div><dt>Última consulta</dt><dd><?= $h($fmt($u['ultimaConsulta']) ?: '—') ?></dd></div>
                            <div><dt>Última apertura</dt><dd><?= $h($fmt($u['ultimaApertura']) ?: '—') ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="vacio" id="sinResultados" hidden>Ninguna unidad de control coincide con los filtros.</p>
        <?php endif; ?>
    </section>

    <?php if ($unidades): ?>
    <script>
        (function () {
            var tarjetas = Array.prototype.slice.call(document.querySelectorAll('.tarjetas .tarjeta'));
            var campos = Array.prototype.slice.call(document.querySelectorAll('.filtros [data-col]'));
            var contador = document.getElementById('contador');
            var sinResultados = document.getElementById('sinResultados');

            // Minúsculas y sin acentos para que "si" y "Sí" coincidan
            function norm(s) {
                return s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
            }

            var datos = tarjetas.map(function (tarjeta) {
                var celdas = {};
                tarjeta.querySelectorAll('[data-col]').forEach(function (elemento) {
                    celdas[elemento.dataset.col] = norm(elemento.textContent);
                });
                return {
                    tarjeta: tarjeta,
                    celdas: celdas
                };
            });

            function filtrar() {
                var activos = campos.map(function (c) {
                    return { col: +c.dataset.col, v: norm(c.value), exacto: !!c.dataset.exacto };
                }).filter(function (f) { return f.v !== ''; });
                var visibles = 0;
                datos.forEach(function (x) {
                    var ok = activos.every(function (f) {
                        return f.exacto ? x.celdas[f.col] === f.v : x.celdas[f.col].indexOf(f.v) !== -1;
                    });
                    x.tarjeta.hidden = !ok;
                    if (ok) visibles++;
                });
                sinResultados.hidden = visibles !== 0;
                contador.textContent = visibles + ' de ' + datos.length + ' dispositivos de apertura';
            }

            campos.forEach(function (c) {
                c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', filtrar);
            });
            filtrar();
        })();
    </script>
    <?php endif; ?>
</body>
</html>
