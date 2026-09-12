<?php
require '../../db.php';
require '../../validarAcceso.php';

// 1. Obtener el id_local desde la URL (GET)
$id_local = isset($_GET['id_local']) ? intval($_GET['id_local']) : 0;

$local = null;
if ($id_local > 0) {
    try {
        // Consultar los datos del local para mostrarlos en pantalla
        $stmt = $pdo->prepare("SELECT nombre_local, direccion_local FROM Local WHERE id_local = :id_local");
        $stmt->execute([':id_local' => $id_local]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error al cargar la información del local.";
    }
} else {
    $error = "ID de local no válido.";
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Puerta Accionada - <?php echo $local ? htmlspecialchars($local['nombre_local']) : 'Apertura'; ?></title>
        <!-- Importamos Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col justify-between">

        <!-- Barra de Navegación -->
        <?php require '../../barraNav.php'; ?>

        <!-- Contenido Principal -->
        <main class="max-w-md mx-auto px-4 py-16 w-full flex flex-col items-center">

            <?php if (isset($error) || !$local): ?>
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center space-y-4 w-full">
                    <div class="inline-flex bg-red-50 p-4 rounded-full text-red-500">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800">Atención</h2>
                    <p class="text-sm text-red-600 font-medium"><?php echo isset($error) ? $error : "Local no encontrado."; ?></p>
                    <div class="pt-4">
                        <a href="../AreaDeAbrirPuerta/local.php" class="inline-block w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-xl transition duration-200 text-center">
                            Regresar a Locales
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tarjeta de Éxito de Apertura -->
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center space-y-6 w-full">

                    <!-- Icono de éxito animado o destacado -->
                    <div class="inline-flex bg-green-50 p-4 rounded-full text-green-600 shadow-inner">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <!-- Mensaje de confirmación -->
                    <div class="space-y-2">
                        <h1 class="text-2xl font-extrabold text-gray-800">
                            ¡Puerta Accionada!
                        </h1>
                        <p class="text-gray-600 text-base font-semibold">
                            <?php echo htmlspecialchars($local['nombre_local']); ?>
                        </p>
                        <p class="text-gray-400 text-xs">
                            <?php echo htmlspecialchars($local['direccion_local']); ?>
                        </p>
                    </div>

                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-blue-700 text-xs font-medium">
                        La señal de apertura ha sido enviada correctamente al dispositivo del local.
                    </div>

                    <!-- Botones de navegación -->
                    <div class="space-y-3 pt-2">
                        <a href="local.php?id_local=<?php echo $id_local; ?>" 
                           class="w-full inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition duration-200 shadow text-center">
                            Volver al Local
                        </a>
                        <a href="abrir_puerta.php" 
                           class="w-full inline-block bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-xl transition duration-200 text-center">
                            Elegir Otro Local
                        </a>
                    </div>

                </div>
            <?php endif; ?>

        </main>

        <!-- Pie de página simple -->
        <footer class="py-6 text-center text-xs text-gray-400">
            Panel de Anfitrión &copy; 2026
        </footer>

    </body>
</html>