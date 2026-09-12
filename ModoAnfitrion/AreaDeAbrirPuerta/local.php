<?php
require '../../db.php';
require '../../validarAcceso.php';

// 1. Obtener el id_local de la URL (GET) usando id_local en lugar de id_loc
$id_local = isset($_GET['id_local']) ? intval($_GET['id_local']) : 0;

// 2. Consultar los datos específicos del local seleccionado
$local = null;
try {
    $stmt = $pdo->prepare("SELECT id_local, nombre_local, direccion_local FROM Local WHERE id_local = :id_local");
    $stmt->execute([':id_local' => $id_local]);
    $local = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Hubo un problema al cargar los datos del local.";
}

// Si no se encuentra el local, asignar mensaje de error
if (!$local) {
    $error = "El local especificado no existe o no es válido.";
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Detalle del Local - <?php echo $local ? htmlspecialchars($local['nombre_local']) : 'Error'; ?></title>
        <!-- Importamos Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col justify-between">

         <!-- Barra de Navegación -->
        <?php require '../../barraNav.php'; ?>

        <!-- Contenido Principal -->
        <main class="max-w-md mx-auto px-4 py-10 w-full">

            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm">
                    <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                </div>
            <?php else: ?>

                <!-- Tarjeta con Información del Local y Acceso -->
                <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center space-y-6">

                    <!-- Icono decorativo -->
                    <div class="inline-flex bg-blue-50 p-4 rounded-full text-blue-500">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>

                    <!-- Datos del Local -->
                    <div class="space-y-2">
                        <h1 class="text-2xl font-extrabold text-gray-800">
                            <?php echo htmlspecialchars($local['nombre_local']); ?>
                        </h1>
                        <p class="text-gray-500 text-sm font-medium">
                            <?php echo htmlspecialchars($local['direccion_local']); ?>
                        </p>
                    </div>

                    <hr class="border-gray-100">

                    <!-- Acceso para Abrir Puerta pasando id_local -->
                    <div>
                        <a href="api_abrir_puerta.php?id_local=<?php echo $local['id_local']; ?>" 
                           class="w-full inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition duration-200 shadow text-center">
                            Abrir Puerta
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