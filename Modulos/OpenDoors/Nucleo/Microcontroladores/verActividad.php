<?php
require_once __DIR__ . '/../../../../util/verificar.php';
require_once __DIR__ . '/../util/db.php';
requerirSesion();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$embed = isset($_GET['embed']);
$unidades = [];
$error = '';

// Solo dispositivos de apertura de la empresa de la cuenta con sesión iniciada
try {
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT m.idMicrocontrolador AS id_microcontrolador, l.nombreLocal AS nombre_local, l.direccionLocal AS direccion_local,
                m.cantidadAperturasExitosas, m.cantidadAperturasFallidas
         FROM microcontroladores m
         INNER JOIN locales l ON l.idLocal = m.idLocal AND l.idEmpresa = m.idEmpresa
         WHERE m.idEmpresa = ?
         ORDER BY m.idMicrocontrolador'
    );
    $stmt->execute([(int) $_SESSION['empresa_id']]);
    $unidades = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error en verActividad: ' . $e->getMessage());
    $error = 'No se pudo cargar la actividad de los dispositivos de apertura.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actividad de dispositivos de apertura | <?= $h($nombreWeb) ?></title>
    <link rel="icon" type="image/png" href="../../../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; background: <?= $embed ? '#fff' : '#f2f5f7' ?>; color: #1b2a35; padding: 24px; }
        .panel { max-width: 1100px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin-bottom: 18px; }
        .tarjetas { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr)); gap: 14px; }
        .tarjeta { min-width: 0; aspect-ratio: 1 / 1; background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-left: 5px solid #3a8fb7; }
        .tarjeta h2 { font-size: 1.05rem; margin-bottom: 12px; overflow-wrap: anywhere; }
        .tarjeta dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .tarjeta dt { color: #6b7a86; font-size: .8rem; }
        .tarjeta dd { margin-top: 2px; font-size: .95rem; overflow-wrap: anywhere; }
        .direccion { grid-column: 1 / -1; }
        .vacio { text-align: center; color: #6b7a86; padding: 20px 0; }
        @media (max-width: 480px) { body { padding: 12px; } .panel { padding: 18px; } }
    </style>
</head>
<body>
    <section class="panel">
        <h1>Actividad de dispositivos de apertura</h1>

        <?php if ($error): ?>
            <p class="vacio"><?= $h($error) ?></p>
        <?php elseif (!$unidades): ?>
            <p class="vacio">Tu empresa no tiene dispositivos de apertura registrados.</p>
        <?php else: ?>
            <div class="tarjetas">
                <?php foreach ($unidades as $u): ?>
                    <article class="tarjeta">
                        <h2>Dispositivo de <?= $h($u['nombre_local']) ?></h2>
                        <dl>
                            <div><dt>Local</dt><dd><?= $h($u['nombre_local']) ?></dd></div>
                            <div><dt>Aperturas exitosas</dt><dd><?= (int) $u['cantidadAperturasExitosas'] ?></dd></div>
                            <div><dt>Aperturas fallidas</dt><dd><?= (int) $u['cantidadAperturasFallidas'] ?></dd></div>
                            <div class="direccion"><dt>Dirección</dt><dd><?= $h($u['direccion_local']) ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</body>
</html>
