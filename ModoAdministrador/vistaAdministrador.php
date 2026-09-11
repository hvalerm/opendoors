<?php
session_start();
require '../db.php';
require '../validarAcceso.php';

$modo='ADMINISTRADOR';
?>

<!DOCTYPE html>
<!--
Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHPWebPage.php to edit this template
-->
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php $modo ?></title>
        <!-- Tailwind CSS CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body>

        <div class="w-full max-w-xs mx-auto flex flex-col items-center space-y-6">
            <!-- Título ANFITRION en grande -->
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-wide text-center">
                <?php $modo ?>
            </h1>

            <!-- Contenedor de botones apilados con sus rutas específicas -->
            <div class="flex flex-col space-y-3 w-full">
                <!-- Abrir Puerta -->
                <a href="AreaDeAbrirPuerta/abrir_puerta.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow text-center">
                    Abrir Puerta
                </a>

                <!-- Área - Personal de Limpieza -->
                <a href="AreaDePersonal/area_limpieza.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                    Area - Personal de Limpieza
                </a>

                <!-- Área - Huésped -->
                <a href="AreaDeHuesped/area_huesped.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                    Area - Huesped
                </a>
            </div>
        </div>

    </body>
</html>
