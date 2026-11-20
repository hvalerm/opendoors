<?php
require_once __DIR__ . '/../../../util/verificar.php';
require_once __DIR__ . '/../../../util/db.php';
require_once __DIR__ . '/../../../util/vigencia_accesos.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$idAcceso = filter_var($_GET['id'] ?? $_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$embed = isset($_GET['embed']) || isset($_POST['embed']);
$acceso = null;
$error = '';
$mensaje = '';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// Solo accesos de la empresa de la cuenta con sesión iniciada
function cargarAcceso(PDO $pdo, int $idAcceso, int $idCuenta): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.idAcceso AS id_acceso, a.idEmpresa AS id_empresa, a.activoDesde AS activo_desde, a.activoHasta AS activo_hasta, l.nombreLocal AS nombre_local, e.nombreEmpresa AS nombre_empresa,
                a.token, a.ejecutado, a.habilitado, l.zonaHoraria
         FROM accesos a
         INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
         INNER JOIN empresas e ON e.idEmpresa = a.idEmpresa
         INNER JOIN empresas_cuentas c ON c.idEmpresa = a.idEmpresa
         WHERE a.idAcceso = ? AND c.idCuenta = ?'
    );
    $stmt->execute([$idAcceso, $idCuenta]);
    return $stmt->fetch() ?: null;
}

$origen = ($_GET['origen'] ?? $_POST['origen'] ?? '') === 'busqueda' ? 'busqueda' : '';
$urlLista = ($origen ? 'busquedaAccesoRevocar' : 'listarAccesos') . ($embed ? '?embed=1' : '');

try {
    $pdo = getPDO();
    if ($idAcceso) {
        $acceso = cargarAcceso($pdo, $idAcceso, (int) $_SESSION['usuario_id']);
    }

    if ($acceso && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
        } else {
            $nuevo = $acceso['habilitado'] ? 0 : 1;
            $upd = $pdo->prepare('UPDATE accesos SET habilitado = ? WHERE idAcceso = ? AND idEmpresa = ?');
            $upd->execute([$nuevo, $acceso['id_acceso'], $acceso['id_empresa']]);
            header('Location: ' . $urlLista);
            exit;
        }
    }
} catch (PDOException $e) {
    error_log('Error en revocarAcceso: ' . $e->getMessage());
    $error = 'Ocurrió un error al actualizar el acceso.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Habilitar/Deshabilitar acceso | Administrar Alojamiento</title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        dl { display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; margin-bottom: 24px; }
        dt { color: #6b7a86; }
        dd { word-break: break-all; }
        .acciones { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { display: inline-block; border: none; border-radius: 8px; padding: 12px 22px; font: inherit; font-weight: 600; color: #fff; background: #3a8fb7; cursor: pointer; text-decoration: none; }
        .btn.peligro { background: #b3261e; }
        .btn.ok { background: #25a244; }
        .btn.enlace { background: #6b7a86; }
        .error { background: #fdecea; color: #b3261e; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .exito { background: #e6f4ea; color: #1e7e34; padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <section class="panel">
        <?php if (!$acceso): ?>
            <h1>Acceso no encontrado</h1>
            <p>El acceso no existe o no pertenece a tu empresa.</p>
            <div class="acciones" style="margin-top:18px"><a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a></div>
        <?php else: ?>
            <h1>Acceso</h1>
            <?php if ($error): ?><div class="error"><?= $h($error) ?></div><?php endif; ?>
            <?php if ($mensaje): ?><div class="exito"><?= $h($mensaje) ?></div><?php endif; ?>
            <dl>
                <dt>ID acceso</dt><dd><?= (int) $acceso['id_acceso'] ?></dd>
                <dt>Local</dt><dd><?= $h($acceso['nombre_local']) ?></dd>
                <dt>Empresa</dt><dd><?= $h($acceso['nombre_empresa']) ?></dd>
                <dt>Activo desde</dt><dd><?= $h(mostrarFechaAcceso($acceso['activo_desde'], $acceso['zonaHoraria'])) ?></dd>
                <dt>Activo hasta</dt><dd><?= $h(mostrarFechaAcceso($acceso['activo_hasta'], $acceso['zonaHoraria'])) ?></dd>
                <dt>Zona horaria</dt><dd><?= $h($acceso['zonaHoraria']) ?></dd>
                <dt>Token</dt><dd><?= $h($acceso['token']) ?></dd>
                <dt>Ejecutado</dt><dd><?= $acceso['ejecutado'] ? 'Sí' : 'No' ?></dd>
                <dt>Habilitado</dt><dd><?= $acceso['habilitado'] ? 'Sí' : 'No' ?></dd>
            </dl>
            <form method="post" action="revocarAcceso" class="acciones">
                <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <input type="hidden" name="id" value="<?= (int) $acceso['id_acceso'] ?>">
                <?php if ($embed): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
                <?php if ($origen): ?><input type="hidden" name="origen" value="busqueda"><?php endif; ?>
                <?php if ($acceso['habilitado']): ?>
                    <button type="submit" class="btn peligro">Deshabilitar acceso</button>
                <?php else: ?>
                    <button type="submit" class="btn ok">Habilitar acceso</button>
                <?php endif; ?>
                <a class="btn enlace" href="<?= $h($urlLista) ?>">Volver a la lista</a>
            </form>
        <?php endif; ?>
    </section>
</body>
</html>
