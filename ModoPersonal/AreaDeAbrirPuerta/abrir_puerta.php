<?php
session_start();
require '../../db.php';
require '../../validarAcceso.php';

// 1. Obtener las ubicaciones de la base de datos
$localizaciones = [];
try {
    // Consultamos la tabla Local ordenada alfabéticamente
    $stmt = $pdo->query("SELECT id_local, nombre_local, direccion_local FROM Local ORDER BY nombre_local ASC");
    $localizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Hubo un problema al cargar las ubicaciones.";
}
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Panel de Control - Ubicaciones</title>
        <!-- Importamos Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen">

        <?php require '../../barraNav.php'; ?>

        <!-- Contenido Principal -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

            <!-- Encabezado de Sección -->
            <div class="mb-8 border-b border-gray-200 pb-5">
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Ubicaciones Disponibles</h1>
                <p class="text-gray-500 mt-2 text-lg">Selecciona una ubicación para gestionar sus áreas o programaciones.</p>
            </div>

            <!-- Manejo de Errores de Base de Datos -->
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 rounded-r-lg shadow-sm">
                    <p class="text-red-700 font-medium"><?php echo $error; ?></p>
                </div>
                <?php die(); ?>
            <?php endif; ?>

            <!-- Cuadrícula de Botones (Locations) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                <?php if (empty($localizaciones)): ?>
                    <div class="col-span-full flex flex-col items-center justify-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300">
                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <p class="text-gray-500 font-medium">No hay ubicaciones registradas en la base de datos.</p>
                    </div>
                <?php else: ?>

                    <?php foreach ($localizaciones as $local): ?>



                        <!-- Enlace con altura flexible (se remueve h-40 y se usa py-8 para adaptarse al contenido) -->
                        <a href="local.php?id_local=<?php echo $local['id_local']; ?>" 
                           class="group relative block bg-white border border-gray-200 rounded-2xl p-6 py-8 shadow-sm hover:shadow-xl hover:-translate-y-1 hover:border-blue-400 transition-all duration-300 flex flex-col items-center justify-center text-center overflow-hidden">

                            <!-- Fondo decorativo sutil en el hover -->
                            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                            <!-- Icono SVG -->
                            <div class="relative z-10 bg-blue-50 group-hover:bg-blue-100 p-3 rounded-full mb-3 transition-colors duration-300">
                                <svg class="w-8 h-8 text-blue-500 group-hover:text-blue-600 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>

                            <!-- Nombre del Local -->
                            <span class="relative z-10 text-lg font-bold text-gray-800 group-hover:text-blue-800 transition-colors duration-300 mb-1">
                                <?php echo htmlspecialchars($local['nombre_local']); ?>
                            </span>

                            <!-- Dirección del Local (con texto más legible y adaptado) -->
                            <span class="relative z-10 text-sm font-medium text-gray-500 group-hover:text-blue-600 transition-colors duration-300">
                                <?php echo htmlspecialchars($local['direccion_local']); ?>
                            </span>

                        </a>


                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </main>

    </body>
</html>