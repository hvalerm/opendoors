<?php
session_start();

// 1. Validar Sesión y Rol
if (!isset($_SESSION['user_acc'])) {
    header("Location: login.php");
    exit;
}

$roles_permitidos = [1, 2, 4]; 
$mi_rol = $_SESSION['id_accTyp'];

if (!in_array($mi_rol, $roles_permitidos)) {
    if ($mi_rol == 3) {
        header("Location: vista_huesped.php");
    } else {
        header("Location: no_autorizado.php"); 
    }
    exit;
}

require 'db.php';

// 2. Capturar y validar el ID de la ubicación de la URL
$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;

if ($id_loc <= 0) {
    header("Location: dashboard.php");
    exit;
}

// 3. Consultar los datos de la Ubicación y sus Tableros/Sensores
$nombre_ubicacion = "Ubicación Desconocida";
$boards = [];

try {
    $stmtLoc = $pdo->prepare("SELECT name_loc FROM Location WHERE id_loc = :id");
    $stmtLoc->execute(['id' => $id_loc]);
    $locData = $stmtLoc->fetch(PDO::FETCH_ASSOC);
    
    if ($locData) {
        $nombre_ubicacion = $locData['name_loc'];
    }

    $sqlBoards = "
        SELECT 
            b.id_boa, 
            b.name_boa, 
            b.description_boa 
        FROM Board b
        INNER JOIN Location_Board lb ON b.id_boa = lb.id_boa
        WHERE lb.id_loc = :id_loc
        ORDER BY b.name_boa ASC
    ";
    
    $stmtBoards = $pdo->prepare($sqlBoards);
    $stmtBoards->execute(['id_loc' => $id_loc]);
    $boards = $stmtBoards->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar los datos de los sensores.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensores - <?php echo htmlspecialchars($nombre_ubicacion); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Barra de Navegación -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-500 hover:text-blue-600 transition-colors flex items-center group">
                        <svg class="w-5 h-5 mr-1 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Volver
                    </a>
                    <span class="h-6 w-px bg-gray-300"></span>
                    <span class="text-xl font-bold text-blue-600 tracking-wide">MiSistema</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 hidden sm:inline-block">
                        Usuario: <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user_acc']); ?></span>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="mb-8 border-b border-gray-200 pb-5">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                Tableros en <span class="text-blue-600"><?php echo htmlspecialchars($nombre_ubicacion); ?></span>
            </h1>
            <p class="text-gray-500 mt-2 text-lg">Selecciona un sensor o tablero para realizar la acción correspondiente.</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8 rounded-r-lg shadow-sm">
                <p class="text-red-700 font-medium"><?php echo $error; ?></p>
            </div>
        <?php endif; ?>

        <!-- Cuadrícula de Sensores (Boards) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            
            <?php if (empty($boards) && !isset($error)): ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300">
                    <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    <p class="text-gray-500 font-medium text-lg">No hay sensores vinculados a esta ubicación.</p>
                </div>
            <?php else: ?>
                
                <?php foreach ($boards as $board): ?>
                    <!-- Verificamos si es el sensor con ID 1 -->
                    <?php $es_puerta = ($board['id_boa'] == 1); ?>

                    <a href="board_action.php?id_boa=<?php echo $board['id_boa']; ?>&id_loc=<?php echo $id_loc; ?>" 
                       class="group bg-white border border-gray-200 rounded-2xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 <?php echo $es_puerta ? 'hover:border-amber-400' : 'hover:border-emerald-400'; ?> transition-all duration-300 flex flex-col h-full relative overflow-hidden">
                        
                        <!-- Línea decorativa superior condicional -->
                        <div class="absolute top-0 left-0 w-full h-1 <?php echo $es_puerta ? 'bg-amber-400' : 'bg-emerald-400'; ?> transform scale-x-0 group-hover:scale-x-100 transition-transform duration-300 origin-left"></div>

                        <div class="flex items-start justify-between mb-4">
                            <!-- Icono dinámico según el ID -->
                            <div class="<?php echo $es_puerta ? 'bg-amber-50 text-amber-600 group-hover:bg-amber-100 group-hover:text-amber-700' : 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100 group-hover:text-emerald-700'; ?> p-3 rounded-xl transition-colors">
                                <?php if ($es_puerta): ?>
                                    <!-- Icono de Puerta -->
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                <?php else: ?>
                                    <!-- Icono de Rayo por defecto -->
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                <?php endif; ?>
                            </div>
                            
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 <?php echo $es_puerta ? 'group-hover:bg-amber-100 group-hover:text-amber-800' : 'group-hover:bg-emerald-100 group-hover:text-emerald-800'; ?> transition-colors">
                                ID: <?php echo $board['id_boa']; ?>
                            </span>
                        </div>

                        <!-- Nombre del Sensor -->
                        <h3 class="text-xl font-bold text-gray-900 <?php echo $es_puerta ? 'group-hover:text-amber-700' : 'group-hover:text-emerald-700'; ?> transition-colors mb-2">
                            <?php echo htmlspecialchars($board['name_boa']); ?>
                        </h3>
                        
                        <!-- Descripción -->
                        <p class="text-gray-500 text-sm flex-grow">
                            <?php 
                                echo !empty($board['description_boa']) 
                                    ? htmlspecialchars($board['description_boa']) 
                                    : 'Sin descripción adicional.'; 
                            ?>
                        </p>
                        
                        <!-- Texto del botón condicional -->
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center text-sm font-semibold <?php echo $es_puerta ? 'text-amber-600 group-hover:text-amber-700' : 'text-emerald-600 group-hover:text-emerald-700'; ?>">
                            <?php echo $es_puerta ? 'Abrir puerta principal' : 'Gestionar Sensor'; ?>
                            <svg class="w-4 h-4 ml-1 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </div>
                    </a>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </main>

</body>
</html>