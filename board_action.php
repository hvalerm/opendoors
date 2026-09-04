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

// 2. Capturar y validar IDs de la URL
$id_boa = isset($_GET['id_boa']) ? intval($_GET['id_boa']) : 0;
$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;

if ($id_boa <= 0 || $id_loc <= 0) {
    header("Location: dashboard.php");
    exit;
}

$mensaje = '';
$tipo_alerta = '';

// 3. Procesar la acción cuando se presiona el botón (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nuevo_estado = isset($_POST['activate']) ? intval($_POST['activate']) : 0;
    
    try {
        // Verificar si existe el registro en BoardAction para esta clave compuesta
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
        $stmtCheck->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe > 0) {
            $stmtUpdate = $pdo->prepare("UPDATE BoardAction SET activate = :activate WHERE id_boa = :id_boa AND id_loc = :id_loc");
            $stmtUpdate->execute([
                'activate' => $nuevo_estado, 
                'id_boa' => $id_boa, 
                'id_loc' => $id_loc
            ]);
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO BoardAction (id_boa, id_loc, activate) VALUES (:id_boa, :id_loc, :activate)");
            $stmtInsert->execute([
                'id_boa' => $id_boa, 
                'id_loc' => $id_loc, 
                'activate' => $nuevo_estado
            ]);
        }

        // Registrar en el Log de auditoría
        $stmtLb = $pdo->prepare("SELECT id_loc_boa FROM Location_Board WHERE id_loc = :id_loc AND id_boa = :id_boa");
        $stmtLb->execute(['id_loc' => $id_loc, 'id_boa' => $id_boa]);
        $id_loc_boa = $stmtLb->fetchColumn();

        if ($id_loc_boa) {
            $id_act = 1; 
            $stmtLog = $pdo->prepare("INSERT INTO Log (Id_act, Id_acc, Id_loc_boa) VALUES (:id_act, :id_acc, :id_loc_boa)");
            $stmtLog->execute([
                'id_act' => $id_act,
                'id_acc' => $_SESSION['user_acc'],
                'id_loc_boa' => $id_loc_boa
            ]);
        }

        $mensaje = $nuevo_estado == 1 ? "Puerta abierta correctamente. Monitoreando estado..." : "Puerta cerrada correctamente.";
        $tipo_alerta = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar la base de datos.";
        $tipo_alerta = "error";
    }
}

// 4. Consultar datos actuales
$nombre_board = "Sensor";
$nombre_ubicacion = "Ubicación";
$estado_actual = 0;
$es_puerta = ($id_boa == 1);

try {
    $stmtB = $pdo->prepare("SELECT name_boa FROM Board WHERE id_boa = :id_boa");
    $stmtB->execute(['id_boa' => $id_boa]);
    if ($b = $stmtB->fetch(PDO::FETCH_ASSOC)) {
        $nombre_board = $b['name_boa'];
    }

    $stmtL = $pdo->prepare("SELECT name_loc FROM Location WHERE id_loc = :id_loc");
    $stmtL->execute(['id_loc' => $id_loc]);
    if ($l = $stmtL->fetch(PDO::FETCH_ASSOC)) {
        $nombre_ubicacion = $l['name_loc'];
    }

    $stmtBa = $pdo->prepare("SELECT activate FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
    $stmtBa->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
    if ($ba = $stmtBa->fetch(PDO::FETCH_ASSOC)) {
        $estado_actual = intval($ba['activate']);
    }
} catch (PDOException $e) {
    // Error silencioso
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar <?php echo htmlspecialchars($nombre_board); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Script de escucha (Polling): Revisa el estado cada 3 segundos si la puerta está abierta -->
    <?php if ($estado_actual == 1): ?>
    <script>
        setInterval(function() {
            // Hacemos una petición invisible al servidor para consultar el estado actual
            fetch('check_status.php?id_boa=<?php echo $id_boa; ?>&id_loc=<?php echo $id_loc; ?>')
                .then(response => response.json())
                .then(data => {
                    // Si el estado en la BD cambió a 0, recargamos la página para mostrar el nuevo estado
                    if (data.activate == 0) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error al comprobar el estado:', error));
        }, 3000); // Revisa cada 3000 milisegundos (3 segundos)
    </script>
    <?php endif; ?>
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Barra de Navegación -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-4">
                    <a href="location_details.php?id_loc=<?php echo $id_loc; ?>" class="text-gray-500 hover:text-blue-600 transition-colors flex items-center group">
                        <svg class="w-5 h-5 mr-1 transform group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Volver a Sensores
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
    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            
            <div class="bg-gradient-to-r <?php echo $es_puerta ? 'from-amber-500 to-amber-600' : 'from-emerald-500 to-emerald-600'; ?> px-8 py-6 text-white flex justify-between items-center">
                <div>
                    <span class="text-xs uppercase tracking-wider font-semibold opacity-80"><?php echo htmlspecialchars($nombre_ubicacion); ?></span>
                    <h1 class="text-3xl font-extrabold mt-1"><?php echo htmlspecialchars($nombre_board); ?></h1>
                </div>
                <?php if ($estado_actual == 1): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/20 text-white animate-pulse">
                        <span class="w-2 h-2 mr-1.5 bg-green-300 rounded-full"></span> Escuchando cambios...
                    </span>
                <?php endif; ?>
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="mx-8 mt-6 p-4 rounded-lg <?php echo $tipo_alerta == 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <div class="p-8">
                
                <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Estado actual del dispositivo en esta localidad</p>
                        <p class="text-2xl font-bold mt-1 <?php echo $estado_actual == 1 ? 'text-green-600' : 'text-gray-700'; ?>">
                            <?php if ($es_puerta): ?>
                                <?php echo $estado_actual == 1 ? '🔓 Puerta Abierta' : '🔒 Puerta Cerrada'; ?>
                            <?php else: ?>
                                <?php echo $estado_actual == 1 ? '🟢 Activado' : '⚪ Desactivado'; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="w-12 h-12 rounded-full flex items-center justify-center <?php echo $estado_actual == 1 ? 'bg-green-100 text-green-600 animate-pulse' : 'bg-gray-200 text-gray-500'; ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <?php if ($estado_actual == 0): ?>
                        <input type="hidden" name="activate" value="1">
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-6 rounded-xl shadow-md transition-all duration-200 flex items-center justify-center text-lg">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                            <?php echo $es_puerta ? 'Abrir Puerta Principal' : 'Activar Dispositivo'; ?>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="activate" value="0">
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-4 px-6 rounded-xl shadow-md transition-all duration-200 flex items-center justify-center text-lg">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            <?php echo $es_puerta ? 'Cerrar Puerta Principal' : 'Desactivar Dispositivo'; ?>
                        </button>
                    <?php endif; ?>
                </form>

            </div>
        </div>
    </main>

</body>
</html>