<?php
require '../../../../db.php';
$rol_pagina = 4;
require '../../../../validarAcceso.php';

$error = '';
$editado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cuenta'])) {
    $id_cuenta = (int) $_POST['id_cuenta'];
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $id_pais = trim($_POST['pais'] ?? '');
    $nueva_credencial = $_POST['nueva_credencial'] ?? '';
    $nueva_credencial_confirmacion = $_POST['nueva_credencial_confirmacion'] ?? '';

    if ($dni === '' || $nombre === '' || $apellido === '' || $correo === '' || $id_pais === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido.';
    } elseif (($nueva_credencial !== '' || $nueva_credencial_confirmacion !== '') && ($nueva_credencial === '' || $nueva_credencial_confirmacion === '')) {
        $error = 'Debes ingresar y confirmar la nueva contraseña.';
    } elseif ($nueva_credencial !== $nueva_credencial_confirmacion) {
        $error = 'Las nuevas contraseñas no coinciden.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE Cuenta SET correo_cuenta = :correo WHERE id_cuenta = :id_cuenta AND id_tipoCuenta = 1');
            $stmt->execute([':correo' => $correo, ':id_cuenta' => $id_cuenta]);

            $stmt = $pdo->prepare('UPDATE Huesped SET dni_huesped = :dni, nombre_huesped = :nombre, apellido_huesped = :apellido, id_pais = :id_pais WHERE id_cuenta = :id_cuenta');
            $stmt->execute([
                ':dni' => $dni,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':id_pais' => $id_pais,
                ':id_cuenta' => $id_cuenta
            ]);

            if ($nueva_credencial !== '') {
                $stmt = $pdo->prepare('UPDATE Cuenta SET credencial_cuenta = :credencial WHERE id_cuenta = :id_cuenta AND id_tipoCuenta = 1');
                $stmt->execute([
                    ':credencial' => password_hash($nueva_credencial, PASSWORD_ARGON2ID),
                    ':id_cuenta' => $id_cuenta
                ]);
            }

            $pdo->commit();
            $editado = true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getCode() === '23000' ? 'El correo o DNI ya está registrado.' : 'No se pudieron actualizar los datos.';
        }
    }
}

$paises = $pdo->query('SELECT id_pais, pais FROM Pais ORDER BY pais')->fetchAll(PDO::FETCH_ASSOC);
$huesped_editar = null;
$id_editar = (int) ($_GET['editar'] ?? 0);
if ($id_editar > 0) {
    $stmt = $pdo->prepare('SELECT C.id_cuenta, C.correo_cuenta, H.dni_huesped, H.nombre_huesped, H.apellido_huesped, H.id_pais
                           FROM Cuenta C INNER JOIN Huesped H ON C.id_cuenta = H.id_cuenta
                           WHERE C.id_cuenta = :id_cuenta AND C.id_tipoCuenta = 1');
    $stmt->execute([':id_cuenta' => $id_editar]);
    $huesped_editar = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$busqueda = trim($_GET['busqueda'] ?? '');
$stmt = $pdo->prepare('SELECT C.id_cuenta, C.correo_cuenta, H.dni_huesped, H.nombre_huesped, H.apellido_huesped, P.pais
                       FROM Cuenta C
                       INNER JOIN Huesped H ON C.id_cuenta = H.id_cuenta
                       LEFT JOIN Pais P ON H.id_pais = P.id_pais
                       WHERE C.id_tipoCuenta = 1
                         AND (C.correo_cuenta LIKE :busqueda OR H.dni_huesped LIKE :busqueda
                              OR H.nombre_huesped LIKE :busqueda OR H.apellido_huesped LIKE :busqueda)
                       ORDER BY H.apellido_huesped, H.nombre_huesped');
$stmt->execute([':busqueda' => '%' . $busqueda . '%']);
$huespedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Editar Datos de Huésped</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col m-0">
        <div class="w-full">
            <?php require '../../../../barraNav.php'; ?>
        </div>
        <div class="w-full px-4 pt-4">
            <a href="../huesped_administrarDatos.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-200">
                &larr; Regresar a administración de huéspedes
            </a>
        </div>

        <main class="max-w-6xl mx-auto w-full px-4 py-10 space-y-6">
            <?php if ($error !== ''): ?><p class="bg-red-100 text-red-700 p-3 rounded-lg text-center"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
            <?php if ($editado): ?><p class="bg-green-100 text-green-700 p-3 rounded-lg text-center">Datos actualizados correctamente.</p><?php endif; ?>

            <?php if ($huesped_editar): ?>
                <section class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                    <h1 class="text-2xl font-bold text-gray-800 mb-5">Editar Datos de Huésped</h1>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <input type="hidden" name="id_cuenta" value="<?php echo (int) $huesped_editar['id_cuenta']; ?>">
                        <input type="text" name="dni" value="<?php echo htmlspecialchars($huesped_editar['dni_huesped']); ?>" maxlength="8" required class="border border-gray-300 rounded-lg px-4 py-2" placeholder="DNI">
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($huesped_editar['nombre_huesped']); ?>" required class="border border-gray-300 rounded-lg px-4 py-2" placeholder="Nombre">
                        <input type="text" name="apellido" value="<?php echo htmlspecialchars($huesped_editar['apellido_huesped']); ?>" required class="border border-gray-300 rounded-lg px-4 py-2" placeholder="Apellido">
                        <input type="email" name="correo" value="<?php echo htmlspecialchars($huesped_editar['correo_cuenta']); ?>" required class="border border-gray-300 rounded-lg px-4 py-2" placeholder="Correo electrónico">
                        <select name="pais" required class="border border-gray-300 rounded-lg px-4 py-2">
                            <?php foreach ($paises as $pais): ?><option value="<?php echo (int) $pais['id_pais']; ?>" <?php echo (int) $pais['id_pais'] === (int) $huesped_editar['id_pais'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pais['pais']); ?></option><?php endforeach; ?>
                        </select>
                        <div class="md:col-span-2 border-t border-gray-200 pt-4">
                            <h2 class="font-semibold text-gray-800 mb-3">Modificar contraseña</h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="password" name="nueva_credencial" placeholder="Nueva contraseña (opcional)" class="border border-gray-300 rounded-lg px-4 py-2">
                                <input type="password" name="nueva_credencial_confirmacion" placeholder="Confirmar nueva contraseña" class="border border-gray-300 rounded-lg px-4 py-2">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg">Guardar cambios</button>
                            <a href="huesped_editarDatos.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-4 py-2 rounded-lg">Cancelar</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                <div class="p-6 flex flex-col md:flex-row justify-between items-center gap-4">
                    <h1 class="text-2xl font-bold text-gray-800">Huéspedes</h1>
                    <form method="GET" class="flex w-full md:w-auto gap-2">
                        <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar huésped..." class="border border-gray-300 rounded-lg px-4 py-2 text-sm w-full md:w-72">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Buscar</button>
                        <?php if ($busqueda !== ''): ?><a href="huesped_editarDatos.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-3 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead><tr class="bg-gray-100 text-gray-700 text-sm uppercase"><th class="py-3 px-6">Huésped</th><th class="py-3 px-6">DNI</th><th class="py-3 px-6">Correo</th><th class="py-3 px-6">País</th><th class="py-3 px-6 text-center">Acción</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                            <?php if ($huespedes): ?>
                                <?php foreach ($huespedes as $huesped): ?>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-gray-900"><?php echo htmlspecialchars($huesped['nombre_huesped'] . ' ' . $huesped['apellido_huesped']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($huesped['dni_huesped']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($huesped['correo_cuenta']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($huesped['pais'] ?? ''); ?></td>
                                        <td class="py-4 px-6 text-center"><a href="?editar=<?php echo (int) $huesped['id_cuenta']; ?><?php echo $busqueda !== '' ? '&busqueda=' . urlencode($busqueda) : ''; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs">Editar</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?><tr><td colspan="5" class="py-6 text-center text-gray-500">No se encontraron huéspedes.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
