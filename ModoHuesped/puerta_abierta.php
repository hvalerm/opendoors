<?php
session_start();
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $idlocal = $_GET['id_local'];
    $idplaca = $_GET['id_placa'];

    $sql = "select accionar from Local_Placa where id_local = :idlocal and id_placa=:idplaca and accionar = 1";
    while (true) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':idlocal' => $idlocal,
            ':idplaca' => $idplaca
        ]);

        $c = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($c) {
            
        } else {
            break;
        }
    }
    ?>   

    <!DOCTYPE html>
    <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Puerta Abierta</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <meta http-equiv="refresh" content="3;url=vistaHuesped.php">
        </head>
        <body class="bg-gray-50 flex items-center justify-center h-screen m-0">

            <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-sm text-center space-y-4">
                <!-- Icono de éxito (Check) -->
                <div class="flex justify-center">
                    <div class="rounded-full h-12 w-12 bg-green-100 flex items-center justify-center text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>

                <h2 class="text-xl font-bold text-gray-800">¡Puerta abierta!</h2>
                <p class="text-sm text-gray-500">La acción se ha completado con éxito.</p>
            </div>

        </body>
    </html>


    <?php
}
?>
