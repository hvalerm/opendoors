<?php
session_start();
require '../../db.php';
require '../../validarAcceso.php';

// 1. Obtener el id_local desde la URL (GET)
$id_local = isset($_GET['id_local']) ? intval($_GET['id_local']) : 0;

if ($id_local > 0) {
    try {
        // 2. Actualizar el campo accionar a 1 en la tabla Local_Placa para el local correspondiente
        $sql = "UPDATE Local_Placa SET accionar = 1 WHERE id_local = :id_local";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_local' => $id_local]);

        // Redirigir a accionar_puerta.php pasando el id_local
        header("Location: accionar_puerta.php?id_local=" . $id_local);
        exit;
    } catch (PDOException $e) {
        $error = "Error al actualizar la placa: " . $e->getMessage();
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
        <title>Abriendo Puerta...</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 flex items-center justify-center h-screen m-0">
        <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-sm text-center space-y-4">
            <?php if (isset($error)): ?>
                <p class="text-red-600 font-semibold"><?php echo $error; ?></p>
                <a href="local.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-lg text-sm">Volver</a>
            <?php else: ?>
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Enviando señal...</h2>
                <p class="text-sm text-gray-500">Por favor espere un momento.</p>
            <?php endif; ?>
        </div>
    </body>
</html>