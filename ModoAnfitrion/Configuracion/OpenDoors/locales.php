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
    $pdo = getPDO();
    $idEmpresa = empresaConfiguracionLocales($pdo);
    if ($idEmpresa === null) {
        http_response_code(403);
        $error = 'OpenDoors no está habilitado para tu empresa.';
    } else {
        $locales = datosLocalesConfiguracion($pdo, $idEmpresa);
        $guardado = !empty($_SESSION['configuracion_local_guardado']);
        unset($_SESSION['configuracion_local_guardado']);
    }
} catch (PDOException $e) {
    error_log('Error al listar locales en Configuración: ' . $e->getMessage());
    http_response_code(503);
    $error = 'No se pudieron cargar los locales. Inténtalo más tarde.';
}
session_write_close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Locales | Configuración de OpenDoors</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <link rel="stylesheet" href="locales.css">
</head>
<body<?= $embed ? ' class="embebida"' : '' ?>>
    <main class="panel">
        <h1>Configuración › OpenDoors › Locales</h1>
        <?php if ($guardado): ?>
            <p class="aviso" role="status">Cambios guardados.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="aviso error" role="alert"><?= $h($error) ?></p>
        <?php elseif (!$locales): ?>
            <p>Tu empresa no tiene locales registrados.</p>
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
</body>
</html>
