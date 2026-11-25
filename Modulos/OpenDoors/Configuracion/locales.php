<?php
require_once __DIR__ . '/comunLocales.php';
requerirSesion();
header('Cache-Control: no-store');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$embed = isset($_GET['embed']);
$locales = [];
$error = '';
$guardado = false;

try {
    $idEmpresa = empresaConfiguracionLocales();
    if ($idEmpresa === null) {
        http_response_code(403);
        $error = 'OpenDoors no está habilitado para tu empresa.';
    } else {
        $locales = datosLocalesConfiguracion(getOpenDoorsPDO(), $idEmpresa);
        $guardado = !empty($_SESSION['configuracion_local_guardado']);
        unset($_SESSION['configuracion_local_guardado']);
    }
} catch (PDOException $e) {
    error_log('Error al listar locales en Configuración: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No se pudieron cargar los lugares. Inténtalo más tarde.';
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lugares | Configuración de OpenDoors</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <link rel="stylesheet" href="locales.css">
</head>
<body<?= $embed ? ' class="embebida"' : '' ?>>
    <main class="panel">
        <h1>Configuración › OpenDoors › Lugares</h1>
        <?php if ($guardado): ?>
            <p class="aviso" role="status">Cambios guardados.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="aviso error" role="alert"><?= $h($error) ?></p>
        <?php elseif (!$locales): ?>
            <p>Tu empresa no tiene lugares registrados.</p>
        <?php else: ?>
            <p class="intro">Selecciona un local para editar su configuración.</p>
            <div class="tarjetas">
                <?php foreach ($locales as $local): ?>
                    <a class="tarjeta<?= $local['eliminado'] ? ' eliminado' : '' ?>" href="configuracionLocal.php?id=<?= (int) $local['idLocal'] ?><?= $embed ? '&amp;embed=1' : '' ?>">
                        <h2><?= $h($local['nombreLocal']) ?></h2>
                        <dl>
                            <div><dt>Dirección</dt><dd><?= $h($local['direccionLocal']) ?></dd></div>
                            <div><dt>Zona horaria</dt><dd><?= $h($local['zonaHoraria']) ?></dd></div>
                            <div><dt>Empresa</dt><dd><?= $h($local['nombreEmpresa']) ?></dd></div>
                            <div><dt>Eliminado</dt><dd><?= $local['eliminado'] ? 'Sí' : 'No' ?></dd></div>
                        </dl>
                        <span class="cta">Configurar local →</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($embed && $guardado): ?>
        <?php
        $localesMenu = [];
        foreach ($locales as $local) {
            if (!$local['eliminado']) {
                $localesMenu[] = ['id' => (int) $local['idLocal'], 'nombre' => $local['nombreLocal']];
            }
        }
        ?>
        <script>
            window.parent.postMessage({
                tipo: 'locales-opendoors-actualizados',
                locales: <?= json_encode($localesMenu, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) ?>
            }, window.location.origin);
        </script>
    <?php endif; ?>
</body>
</html>
