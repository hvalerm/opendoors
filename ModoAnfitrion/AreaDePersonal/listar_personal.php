<?php
session_start();
require '../../db.php';
require '../../validarAcceso.php';

// 1. Validar Sesión y Rol de Anfitrión (asumiendo que id_tipoCuenta para anfitrión es diferente o validas tu sesión)
if (!isset($_SESSION['correo_cuenta'])) {
    header("Location: ../../login.php");
    exit;
}

// 2. Procesar el cambio de estado si se envió el formulario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cuenta_accion'], $_POST['nuevo_estado'])) {
    $id_cuenta_accion = $_POST['id_cuenta_accion'];
    $nuevo_estado = $_POST['nuevo_estado']; // Vendrá 1 (Habilitado) o 2 (Inhabilitado)

    $sql_update = "UPDATE Cuenta SET id_EstadoCuenta = :nuevo_estado WHERE id_cuenta = :id_cuenta";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':nuevo_estado' => $nuevo_estado,
        ':id_cuenta' => $id_cuenta_accion
    ]);

    // Recargar la página para limpiar el POST y mostrar el cambio reflejado
    header("Location: listar_personal.php");
    exit;
}

// 3. Capturar término de búsqueda si existe
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// 4. Consulta SQL para listar al personal de limpieza filtrando opcionalmente por nombre o apellido
// (Asumimos que el personal de limpieza tiene un id_tipoCuenta específico, por ejemplo, limpieza = 2, o puedes ajustar el WHERE según tu lógica)
$sql = "SELECT C.id_cuenta, C.correo_cuenta, C.id_EstadoCuenta, P.nombre_personal, P.apellido_personal 
        FROM Cuenta C 
        INNER JOIN Personal P ON C.id_cuenta = P.id_cuenta 
        WHERE (P.nombre_personal LIKE :busqueda OR P.apellido_personal LIKE :busqueda)";

$stmt = $pdo->prepare($sql);
$stmt->execute([':busqueda' => '%' . $busqueda . '%']);
$personales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Personal de Limpieza</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen py-10 px-4">

        <div class="max-w-4xl mx-auto space-y-6">

            <!-- Cabecera -->
            <div class="flex flex-col md:flex-row justify-between items-center bg-white p-6 rounded-xl shadow-md border border-gray-100 gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Personal de Limpieza</h1>

                <!-- Formulario de Buscador -->
                <form method="GET" class="flex w-full md:w-auto gap-2">
                    <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por nombre o apellido..." 
                           class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 w-full md:w-64">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition duration-200 shadow">
                        Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="listar_personal.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-3 py-2 rounded-lg text-sm flex items-center justify-center transition duration-200">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Resultados -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 text-sm uppercase tracking-wider">
                                <th class="py-3 px-6">Nombre y Apellido</th>
                                <th class="py-3 px-6">Correo</th>
                                <th class="py-3 px-6 text-center">Estado</th>
                                <th class="py-3 px-6 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                            <?php if (count($personales) > 0): ?>
                                <?php foreach ($personales as $row): ?>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-gray-900">
                                            <?php echo htmlspecialchars($row['nombre_personal'] . ' ' . $row['apellido_personal']); ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php echo htmlspecialchars($row['correo_cuenta']); ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <?php if ($row['id_EstadoCuenta'] == 1): ?>
                                                <span class="bg-green-100 text-green-700 font-semibold px-3 py-1 rounded-full text-xs">Habilitado</span>
                                            <?php else: ?>
                                                <span class="bg-red-100 text-red-700 font-semibold px-3 py-1 rounded-full text-xs">Inhabilitado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="id_cuenta_accion" value="<?php echo $row['id_cuenta']; ?>">
                                                <?php if ($row['id_EstadoCuenta'] == 1): ?>
                                                    <!-- Si está habilitado, el botón permite pasar a inhabilitado (estado 2) -->
                                                    <input type="hidden" name="nuevo_estado" value="2">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs transition duration-200 shadow">
                                                        Inhabilitar
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Si está inhabilitado, el botón permite pasar a habilitado (estado 1) -->
                                                    <input type="hidden" name="nuevo_estado" value="1">
                                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs transition duration-200 shadow">
                                                        Habilitar
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-gray-500">
                                        No se encontró personal de limpieza registrado.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </body>
</html>