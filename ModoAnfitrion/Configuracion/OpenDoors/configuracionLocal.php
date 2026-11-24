<?php
require_once __DIR__ . '/comunLocales.php';
requerirSesion();
header('Cache-Control: no-store');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$embed = isset($_GET['embed']);
$idLocal = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$listaUrl = 'locales.php' . ($embed ? '?embed=1' : '');
$local = null;
$error = '';
$zonas = DateTimeZone::listIdentifiers();

try {
    $idEmpresa = empresaConfiguracionLocales();
    if ($idEmpresa === null) {
        http_response_code(403);
        $error = 'OpenDoors no está habilitado para tu empresa.';
    } elseif (!$idLocal || !($local = datosLocalesConfiguracion(getOpenDoorsPDO(), $idEmpresa, $idLocal)[0] ?? null)) {
        http_response_code(404);
        $error = 'El local no existe o no pertenece a tu empresa.';
    } else {
        if (!in_array($local['zonaHoraria'], $zonas, true)
            && in_array($local['zonaHoraria'], DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            $zonas[] = $local['zonaHoraria'];
        }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf'] ?? '';
            if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
                http_response_code(403);
                $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
            } else {
                $campo = fn(string $clave): string => is_string($_POST[$clave] ?? null) ? trim($_POST[$clave]) : '';
                $local['nombreLocal'] = $campo('nombreLocal');
                $local['direccionLocal'] = $campo('direccionLocal');
                $local['zonaHoraria'] = $campo('zonaHoraria');
                $eliminado = $campo('eliminado');
                if ($eliminado === '0' || $eliminado === '1') {
                    $local['eliminado'] = (int) $eliminado;
                }

                if ($local['nombreLocal'] === '' || mb_strlen($local['nombreLocal'], 'UTF-8') > 50) {
                    $error = 'Escribe un nombre de hasta 50 caracteres.';
                } elseif ($local['direccionLocal'] === '' || mb_strlen($local['direccionLocal'], 'UTF-8') > 100) {
                    $error = 'Escribe una dirección de hasta 100 caracteres.';
                } elseif (!in_array($local['zonaHoraria'], $zonas, true)) {
                    $error = 'Selecciona una zona horaria válida.';
                } elseif ($eliminado !== '0' && $eliminado !== '1') {
                    $error = 'Selecciona si el local está eliminado.';
                }
                if ($error !== '') {
                    http_response_code(422);
                } else {
                    $stmt = getOpenDoorsPDO()->prepare(
                        'UPDATE locales SET nombreLocal = ?, direccionLocal = ?, zonaHoraria = ?, eliminado = ?
                         WHERE idLocal = ? AND idEmpresa = ?'
                    );
                    $stmt->execute([$local['nombreLocal'], $local['direccionLocal'], $local['zonaHoraria'],
                        $local['eliminado'], $idLocal, $idEmpresa]);
                    $_SESSION['configuracion_local_guardado'] = true;
                    header('Location: ' . $listaUrl, true, 303);
                    exit;
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log('Error al acceder a la configuración del local: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No se pudo completar la operación. Inténtalo más tarde.';
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar local | OpenDoors</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <link rel="stylesheet" href="locales.css">
</head>
<body<?= $embed ? ' class="embebida"' : '' ?>>
    <main class="panel panel-edicion">
        <a class="volver" href="<?= $h($listaUrl) ?>">← Volver a Locales</a>
        <h1>Configurar local</h1>
        <?php if ($error !== ''): ?>
            <p class="aviso error" role="alert"><?= $h($error) ?></p>
        <?php endif; ?>
        <?php if ($local): ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <label for="nombreLocal">Nombre del local</label>
                <input id="nombreLocal" name="nombreLocal" value="<?= $h($local['nombreLocal']) ?>" maxlength="50" required>
                <label for="direccionLocal">Dirección</label>
                <input id="direccionLocal" name="direccionLocal" value="<?= $h($local['direccionLocal']) ?>" maxlength="100" required>
                <label for="zonaHoraria">Zona horaria</label>
                <div id="filtroZonaHoraria" class="filtro-zona" hidden>
                    <input type="search" id="buscarZonaHoraria" aria-label="Buscar zona horaria" aria-controls="zonaHoraria" placeholder="Buscar por ciudad o región" autocomplete="off">
                    <button type="button" id="limpiarZonaHoraria" aria-label="Limpiar búsqueda" title="Limpiar búsqueda">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18"/>
                        </svg>
                    </button>
                </div>
                <select id="zonaHoraria" name="zonaHoraria" required>
                    <option value="">Selecciona una zona horaria</option>
                    <?php foreach ($zonas as $zona): ?>
                        <option value="<?= $h($zona) ?>"<?= $zona === $local['zonaHoraria'] ? ' selected' : '' ?>><?= $h($zona) ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="avisoZonaHoraria" class="ayuda" role="status"></p>
                <label for="nombreEmpresa">Empresa</label>
                <input id="nombreEmpresa" value="<?= $h($local['nombreEmpresa']) ?>" readonly>
                <label for="eliminado">Eliminado</label>
                <select id="eliminado" name="eliminado" aria-describedby="ayudaEliminado">
                    <option value="0"<?= !$local['eliminado'] ? ' selected' : '' ?>>No</option>
                    <option value="1"<?= $local['eliminado'] ? ' selected' : '' ?>>Sí</option>
                </select>
                <p class="ayuda" id="ayudaEliminado">El local seguirá apareciendo aquí. Para restaurarlo, cambia esta opción a «No».</p>
                <button type="submit">Guardar cambios</button>
            </form>
        <?php endif; ?>
    </main>
    <?php if ($local): ?>
    <script>
        (() => {
            const filtro = document.getElementById('filtroZonaHoraria');
            const buscar = document.getElementById('buscarZonaHoraria');
            const limpiar = document.getElementById('limpiarZonaHoraria');
            const selector = document.getElementById('zonaHoraria');
            const aviso = document.getElementById('avisoZonaHoraria');
            const normalizar = texto => texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .toLowerCase().replace(/_/g, ' ').trim();
            const opciones = Array.from(selector.options).slice(1);

            function filtrar() {
                const texto = normalizar(buscar.value);
                let coincidencias = 0;
                let ultimaCoincidencia;
                for (const opcion of opciones) {
                    opcion.hidden = !normalizar(opcion.textContent).includes(texto);
                    if (!opcion.hidden) {
                        coincidencias++;
                        ultimaCoincidencia = opcion;
                    }
                }
                if (texto && coincidencias === 1) {
                    selector.value = ultimaCoincidencia.value;
                    aviso.textContent = 'Zona horaria seleccionada: ' + ultimaCoincidencia.textContent + '.';
                } else {
                    aviso.textContent = coincidencias ? '' : 'No se encontraron zonas horarias.';
                }
            }

            buscar.addEventListener('input', filtrar);
            buscar.addEventListener('keydown', evento => {
                if (evento.key === 'Enter' && !evento.isComposing) evento.preventDefault();
            });
            limpiar.addEventListener('click', () => {
                buscar.value = '';
                filtrar();
                buscar.focus();
            });
            filtrar();
            filtro.hidden = false;
        })();
    </script>
    <?php endif; ?>
</body>
</html>
