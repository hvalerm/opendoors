<?php
session_start();

// 1. Proteger la ruta: si no hay sesión activa, regresa al login
if (!isset($_SESSION['user_acc'])) {
    header("Location: login.php");
    exit;
}

// 2. Control de Roles (RBAC) ajustado
// 1 = Administrador, 2 = Anfitrión, 4 = Personal de Limpieza
$roles_permitidos = [1, 2, 4]; 
$mi_rol = $_SESSION['id_accTyp'];

// Validamos si el rol actual está en la lista de permitidos
if (!in_array($mi_rol, $roles_permitidos)) {
    // Si es Huésped (ID 3), lo mandamos a su propia vista
    if ($mi_rol == 3) {
        header("Location: vista_huesped.php");
    } else {
        // Para cualquier otro rol desconocido
        header("Location: no_autorizado.php"); 
    }
    exit;
}

require 'db.php';

// 3. Obtener las ubicaciones de la base de datos
$ubicaciones = [];
try {
    // Consultamos la tabla Location ordenada alfabéticamente
    $stmt = $pdo->query("SELECT id_loc, name_loc FROM Location ORDER BY name_loc ASC");
    $ubicaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- Barra de Navegación -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex-shrink-0 flex items-center">
                    <!-- Icono o Logo del sistema -->
                    <span class="text-xl font-bold text-blue-600 tracking-wide">MiSistema</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 hidden sm:inline-block">
                        Hola, <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user_acc']); ?></span>
                    </span>
                    <a href="logout.php" class="text-sm font-medium text-red-600 hover:text-red-800 transition-colors bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg">
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

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
        <?php endif; ?>

        <!-- Cuadrícula de Botones (Locations) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            
            <?php if (empty($ubicaciones) && !isset($error)): ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300">
                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <p class="text-gray-500 font-medium">No hay ubicaciones registradas en la base de datos.</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($ubicaciones as $loc): ?>
                    <!-- Enlace que redirige a los detalles pasando el ID_LOC -->
                    <a href="location_details.php?id_loc=<?php echo $loc['id_loc']; ?>" 
                       class="group relative block bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 hover:border-blue-400 transition-all duration-300 flex flex-col items-center justify-center text-center h-40 overflow-hidden">
                        
                        <!-- Fondo decorativo sutil en el hover -->
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                        <!-- Icono SVG -->
                        <div class="relative z-10 bg-blue-50 group-hover:bg-blue-100 p-3 rounded-full mb-3 transition-colors duration-300">
                            <svg class="w-8 h-8 text-blue-500 group-hover:text-blue-600 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>

                        <!-- Texto de la Ubicación -->
                        <span class="relative z-10 text-xl font-semibold text-gray-800 group-hover:text-blue-800 transition-colors duration-300">
                            <?php echo htmlspecialchars($loc['name_loc']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>