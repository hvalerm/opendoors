<?php
require '../../../../db.php';
require '../../../../validarAcceso.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cuenta_accion'], $_POST['nuevo_estado'])) {
        $id_cuenta = (int) $_POST['id_cuenta_accion'];
        $nuevo_estado = (int) $_POST['nuevo_estado'];
        $stmt = $pdo->prepare('UPDATE Cuenta SET id_EstadoCuenta = :estado WHERE id_cuenta = :id_cuenta AND id_tipoCuenta = 1');
        $stmt->execute([':estado' => $nuevo_estado, ':id_cuenta' => $id_cuenta]);
        header('Location: huesped_habilitarInhabilitar.php');
        exit;
}

$busqueda = trim($_GET['busqueda'] ?? '');
$stmt = $pdo->prepare('SELECT C.id_cuenta, C.correo_cuenta, C.id_EstadoCuenta,
                                                            H.dni_huesped, H.nombre_huesped, H.apellido_huesped
                                             FROM Cuenta C
                                             INNER JOIN Huesped H ON C.id_cuenta = H.id_cuenta
                                             WHERE C.id_tipoCuenta = 1
                                                 AND (C.correo_cuenta LIKE :busqueda
                                                            OR H.dni_huesped LIKE :busqueda
                                                            OR H.nombre_huesped LIKE :busqueda
                                                            OR H.apellido_huesped LIKE :busqueda)
                                             ORDER BY H.apellido_huesped, H.nombre_huesped');
$stmt->execute([':busqueda' => '%' . $busqueda . '%']);
$huespedes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Habilitar o Inhabilitar Huésped</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col m-0">
        <div class="w-full">
            <?php require '../../../../barraNav.php'; ?>
        </div>
        <div class="w-full px-4 pt-4">
            <a href="../../area_huesped.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg transition duration-200">
                &larr; Regresar al área de huéspedes
            </a>
        </div>

        <main class="max-w-6xl mx-auto w-full px-4 py-10 space-y-6">
            <div class="flex flex-col md:flex-row justify-between items-center bg-white p-6 rounded-xl shadow-md border border-gray-100 gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Huéspedes</h1>
                <form method="GET" class="flex w-full md:w-auto gap-2">
                    <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar huésped..." class="border border-gray-300 rounded-lg px-4 py-2 text-sm w-full md:w-72">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Buscar</button>
                    <?php if ($busqueda !== ''): ?><a href="huesped_habilitarInhabilitar.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-3 py-2 rounded-lg text-sm">Limpiar</a><?php endif; ?>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead><tr class="bg-gray-100 text-gray-700 text-sm uppercase"><th class="py-3 px-6">Huésped</th><th class="py-3 px-6">DNI</th><th class="py-3 px-6">Correo</th><th class="py-3 px-6 text-center">Estado</th><th class="py-3 px-6 text-center">Acción</th></tr></thead>
                        <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                            <?php if ($huespedes): ?>
                                <?php foreach ($huespedes as $huesped): ?>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-gray-900"><?php echo htmlspecialchars($huesped['nombre_huesped'] . ' ' . $huesped['apellido_huesped']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($huesped['dni_huesped']); ?></td>
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($huesped['correo_cuenta']); ?></td>
                                        <td class="py-4 px-6 text-center"><?php if ((int) $huesped['id_EstadoCuenta'] === 1): ?><span class="bg-green-100 text-green-700 font-semibold px-3 py-1 rounded-full text-xs">Habilitado</span><?php else: ?><span class="bg-red-100 text-red-700 font-semibold px-3 py-1 rounded-full text-xs">Inhabilitado</span><?php endif; ?></td>
                                        <td class="py-4 px-6 text-center">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id_cuenta_accion" value="<?php echo (int) $huesped['id_cuenta']; ?>">
                                                <?php if ((int) $huesped['id_EstadoCuenta'] === 1): ?>
                                                    <input type="hidden" name="nuevo_estado" value="2"><button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs">Inhabilitar</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="nuevo_estado" value="1"><button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs">Habilitar</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?><tr><td colspan="5" class="py-6 text-center text-gray-500">No se encontraron huéspedes.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </body>
</html>
