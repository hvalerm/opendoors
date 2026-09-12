<?php
session_start();
require '../../db.php';
require '../../validarAcceso.php';
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Huéspedes</title>
        <!-- Script de Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col justify-between m-0">

        <!-- Contenedor superior: Barra de navegación -->
        <div class="w-full">
            <?php require '../../barraNav.php'; ?>
        </div>

        <!-- Contenedor central con el título y los botones -->
        <div class="flex-grow flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-sm mx-auto flex flex-col items-center space-y-6">
                <!-- Título principal -->
                <h1 class="text-3xl font-extrabold text-gray-800 tracking-wide text-center">
                    HUÉSPEDES
                </h1>

                <!-- Contenedor de accesos apilados -->
                <div class="flex flex-col space-y-3 w-full">
                    <!-- Huéspedes - En estancia -->
                    <a href="Curso/huesped_curso.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huéspedes - Estancia en curso
                    </a>

                    <!-- Huéspedes - Estancia finalizada -->
                    <a href="Finalizada/huesped_finalizada.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huéspedes - Estancia finalizada
                    </a>

                    <!-- Huéspedes - Estancia por iniciar -->
                    <a href="PorIniciar/huesped_por_iniciar.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huéspedes - Estancia por iniciar
                    </a>

                    <!-- Huéspedes - Antecedentes -->
                    <a href="Antecedentes/huesped_antecedentes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                        Huéspedes - Antecedentes
                    </a>
                </div>
            </div>
        </div>

        <!-- Espaciador inferior opcional para balancear la página -->
        <div></div>

    </body>
</html>