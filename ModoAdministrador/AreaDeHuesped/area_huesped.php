<?php
require '../../db.php';
require '../../validarAcceso.php';
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Huéspedes</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col m-0">
        <div class="w-full">
            <?php require '../../barraNav.php'; ?>
        </div>

        <main class="flex-grow flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-sm mx-auto flex flex-col items-center space-y-6">
                <h1 class="text-3xl font-extrabold text-gray-800 tracking-wide text-center">
                    HUÉSPEDES
                </h1>

                <div class="flex flex-col space-y-3 w-full">
                    <a href="Huesped_AdministrarDatos/huesped_administrarDatos.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huésped - Administrar datos
                    </a>

                    <a href="administrar_estancia.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huésped - Administrar Estancia
                    </a>

                    <a href="antecedentes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huésped - Antecedentes
                    </a>
                </div>
            </div>
        </main>
    </body>
</html>
