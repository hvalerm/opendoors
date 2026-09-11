<?php
session_start();
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_local = $_POST['id_local'];
    $id_placa = 1;
    $id_acceso = $_POST['id_acceso'];

    try {
        $sql = "UPDATE Local_Placa 
            SET accionar = 1 
            WHERE id_local = :id_local AND id_placa = :id_placa";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_local' => $id_local,
            ':id_placa' => $id_placa
        ]);

        //echo "El campo accionar se actualizó correctamente a 1.";
    } catch (PDOException $e) {
        echo "Error al actualizar: " . $e->getMessage();
    }


    // Cambiamos el estado de ejecutado a 1
    $sql = "UPDATE Acceso SET ejecutado = 1 WHERE id_acceso = :id_acceso";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_acceso' => $id_acceso]);
}//FIN IF POST
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Abriendo Puerta</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Opcional: Redirigir automáticamente después de 3 segundos -->
        <meta http-equiv="refresh" content="3;url=index.php">
    </head>
    <body class="bg-gray-50 flex items-center justify-center h-screen m-0">

        <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-sm text-center space-y-4">
            <!-- Icono de carga animado (Spinner) -->
            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-blue-600 border-t-transparent"></div>
            </div>

            <h2 class="text-xl font-bold text-gray-800">Abriendo puerta...</h2>
            <p class="text-sm text-gray-500">Por favor espere un momento.</p>
        </div>

    </body>
</html>