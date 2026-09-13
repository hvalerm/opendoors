<?php
require '../../db.php';
require '../../validarAcceso.php';

if (!isset($_SESSION['correo_cuenta'])) {
    header("Location: ../../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cuenta_accion'], $_POST['nuevo_estado'])) {
    $id_cuenta_accion = $_POST['id_cuenta_accion'];
    $nuevo_estado = $_POST['nuevo_estado'];

    $sql_update = "UPDATE Cuenta SET id_EstadoCuenta = :nuevo_estado WHERE id_cuenta = :id_cuenta AND id_tipoCuenta = 3";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':nuevo_estado' => $nuevo_estado,
        ':id_cuenta' => $id_cuenta_accion
    ]);

    header("Location: listarAnfitrion.php");
    exit;
}

$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

$sql = "SELECT id_cuenta, correo_cuenta, id_EstadoCuenta
        FROM Cuenta
        WHERE id_tipoCuenta = 3 AND correo_cuenta LIKE :busqueda
        ORDER BY correo_cuenta";

$stmt = $pdo->prepare($sql);
$stmt->execute([':busqueda' => '%' . $busqueda . '%']);
$anfitriones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Anfitriones</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen px-4">
        <div class="w-full">
            <?php require '../../barraNav.php'; ?>
        </div>

        <div class="max-w-4xl mx-auto space-y-6 py-10">
            <div class="flex flex-col md:flex-row justify-between items-center bg-white p-6 rounded-xl shadow-md border border-gray-100 gap-4">
                <h1 class="text-2xl font-bold text-gray-800">Anfitriones</h1>

                <form method="GET" class="flex w-full md:w-auto gap-2">
                    <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por correo..."
                           class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-blue-500 w-full md:w-64">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition duration-200 shadow">
                        Buscar
                    </button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="listarAnfitrion.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold px-3 py-2 rounded-lg text-sm flex items-center justify-center transition duration-200">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 text-sm uppercase tracking-wider">
                                <th class="py-3 px-6">Correo</th>
                                <th class="py-3 px-6 text-center">Estado</th>
                                <th class="py-3 px-6 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm text-gray-600">
                            <?php if (count($anfitriones) > 0): ?>
                                <?php foreach ($anfitriones as $row): ?>
                                    <tr>
                                        <td class="py-4 px-6 font-medium text-gray-900">
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
                                                <input type="hidden" name="id_cuenta_accion" value="<?php echo intval($row['id_cuenta']); ?>">
                                                <?php if ($row['id_EstadoCuenta'] == 1): ?>
                                                    <input type="hidden" name="nuevo_estado" value="2">
                                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold py-1.5 px-4 rounded-lg text-xs transition duration-200 shadow">
                                                        Inhabilitar
                                                    </button>
                                                <?php else: ?>
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
                                    <td colspan="3" class="py-6 text-center text-gray-500">
                                        No se encontraron anfitriones registrados.
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
