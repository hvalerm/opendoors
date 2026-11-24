<?php
require_once __DIR__ . '/comunLocales.php';
require_once __DIR__ . '/../../../util/vigencia_accesos.php';
requerirSesion();
header('Cache-Control: no-store');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$embed = isset($_GET['embed']);
$opciones = [
    'ultimaApertura' => ['Hora de última apertura', 'La fecha y hora se sustituirán por las del restablecimiento.'],
    'cantidadAperturasExitosas' => ['Aperturas exitosas', 'El contador de aperturas exitosas de cada dispositivo se pondrá en cero.'],
    'cantidadAperturasFallidas' => ['Aperturas fallidas', 'El contador de aperturas fallidas de cada dispositivo se pondrá en cero.'],
    'accesosInservibles' => ['Accesos que ya no sirven', 'Se deshabilitarán los accesos vencidos o ya usados. No se borrarán registros.'],
];
$locales = [];
$alcance = 'todos';
$seleccion = [];
$error = '';
$restaurado = false;
$pdo = null;
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

try {
    $idEmpresa = empresaConfiguracionLocales();
    if ($idEmpresa === null) {
        http_response_code(403);
        $error = 'OpenDoors no está habilitado para tu empresa.';
    } else {
        $pdo = getOpenDoorsPDO();
        $stmt = $pdo->prepare(
            'SELECT idLocal, nombreLocal FROM locales WHERE idEmpresa = ? AND eliminado = 0 ORDER BY nombreLocal, idLocal'
        );
        $stmt->execute([$idEmpresa]);
        $locales = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf = $_POST['csrf'] ?? '';
            if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
                http_response_code(403);
                $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
            } else {
                $entrada = $_POST['alcance'] ?? '';
                $alcance = is_string($entrada) ? $entrada : '';
                $campos = $_POST['campos'] ?? null;
                $validos = is_array($campos) && $campos !== [];
                if ($validos) {
                    foreach ($campos as $campo) {
                        if (!is_string($campo) || !isset($opciones[$campo])) {
                            $validos = false;
                            break;
                        }
                        $seleccion[$campo] = true;
                    }
                }

                if (!$locales || ($alcance !== 'todos' && !isset($locales[$alcance]))) {
                    http_response_code(422);
                    $error = 'Selecciona un local no eliminado de tu empresa o todos los locales disponibles.';
                } elseif (!$validos) {
                    http_response_code(422);
                    $error = 'Selecciona al menos un dato válido para restaurar.';
                } else {
                    $ahora = ahoraVigenciaAccesos();
                    $filtro = ' WHERE idEmpresa = ? AND idLocal IN
                        (SELECT idLocal FROM locales WHERE idEmpresa = ? AND eliminado = 0'
                        . ($alcance === 'todos' ? ')' : ' AND idLocal = ?)');
                    $parametros = [$idEmpresa, $idEmpresa];
                    if ($alcance !== 'todos') {
                        $parametros[] = (int) $alcance;
                    }

                    $pdo->beginTransaction();
                    if (isset($seleccion['ultimaApertura'])) {
                        $pdo->prepare('UPDATE microcontroladores SET ultimaApertura = ?' . $filtro)
                            ->execute([$ahora, ...$parametros]);
                    }
                    if (isset($seleccion['cantidadAperturasExitosas'])) {
                        $pdo->prepare('UPDATE microcontroladores SET cantidadAperturasExitosas = 0' . $filtro)
                            ->execute($parametros);
                    }
                    if (isset($seleccion['cantidadAperturasFallidas'])) {
                        $pdo->prepare('UPDATE microcontroladores SET cantidadAperturasFallidas = 0' . $filtro)
                            ->execute($parametros);
                    }
                    if (isset($seleccion['accesosInservibles'])) {
                        $pdo->prepare('UPDATE accesos SET habilitado = 0' . $filtro
                            . ' AND habilitado = 1 AND (activoHasta < ? OR ejecutado = 1)')
                            ->execute([...$parametros, $ahora]);
                    }
                    $pdo->commit();
                    $_SESSION['informacion_opendoors_restaurada'] = true;
                    header('Location: RestaurarInformacion.php' . ($embed ? '?embed=1' : ''), true, 303);
                    exit;
                }
            }
        } elseif (!empty($_SESSION['informacion_opendoors_restaurada'])) {
            $restaurado = true;
            unset($_SESSION['informacion_opendoors_restaurada']);
        }
    }
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al restaurar información de OpenDoors: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No se pudo restaurar la información. Inténtalo más tarde.';
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restaurar información | OpenDoors</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <link rel="stylesheet" href="locales.css">
</head>
<body<?= $embed ? ' class="embebida"' : '' ?>>
    <main class="panel panel-edicion">
        <h1>Restaurar información</h1>
        <?php if ($error !== ''): ?>
            <p class="aviso error" role="alert"><?= $h($error) ?></p>
        <?php elseif ($restaurado): ?>
            <p class="aviso" role="status">Información restaurada.</p>
        <?php endif; ?>
        <?php if ($locales): ?>
            <p class="intro">Elige el alcance y marca solo los datos que quieras restablecer. Los locales eliminados no se incluyen.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <label for="alcance">Locales</label>
                <select id="alcance" name="alcance" required>
                    <option value="todos"<?= !isset($locales[$alcance]) ? ' selected' : '' ?>>Todos los locales no eliminados</option>
                    <?php foreach ($locales as $id => $nombre): ?>
                        <option value="<?= $h($id) ?>"<?= $alcance === (string) $id ? ' selected' : '' ?>><?= $h($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
                <fieldset class="datos-restaurables">
                    <legend>Datos a restaurar</legend>
                    <div id="grupoSeleccionarTodos" hidden>
                        <input type="checkbox" id="seleccionarTodos">
                        <label for="seleccionarTodos">Seleccionar todos los datos</label>
                    </div>
                    <?php foreach ($opciones as $clave => [$titulo, $detalle]): ?>
                        <div>
                            <input type="checkbox" name="campos[]" id="<?= $h($clave) ?>" value="<?= $h($clave) ?>"
                                aria-describedby="<?= $h($clave) ?>-detalle"<?= isset($seleccion[$clave]) ? ' checked' : '' ?>>
                            <div>
                                <label for="<?= $h($clave) ?>"><?= $h($titulo) ?></label>
                                <p class="ayuda" id="<?= $h($clave) ?>-detalle"><?= $h($detalle) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit">Restaurar</button>
            </form>
        <?php elseif ($error === ''): ?>
            <p>Tu empresa no tiene locales no eliminados.</p>
        <?php endif; ?>
    </main>
    <?php if ($locales): ?>
    <script>
        (() => {
            const todos = document.getElementById('seleccionarTodos');
            const campos = Array.from(todos.closest('fieldset').querySelectorAll('input[name="campos[]"]'));

            function sincronizarSeleccion() {
                const seleccionados = campos.filter(campo => campo.checked).length;
                todos.checked = seleccionados === campos.length;
                todos.indeterminate = seleccionados > 0 && !todos.checked;
            }

            todos.addEventListener('change', () => {
                campos.forEach(campo => { campo.checked = todos.checked; });
                sincronizarSeleccion();
            });
            campos.forEach(campo => campo.addEventListener('change', sincronizarSeleccion));
            sincronizarSeleccion();
            document.getElementById('grupoSeleccionarTodos').hidden = false;
        })();
    </script>
    <?php endif; ?>
</body>
</html>
