<?php
session_start();
require '../../db.php';
require '../../validarAcceso.php';
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
        <title>ANFITRION - Personal</title>
        <!-- Script de Tailwind CSS -->
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body>

        <div class="w-full max-w-xs mx-auto flex flex-col items-center space-y-6">
            <!-- Título ANFITRION en grande -->
            <h1 class="text-3xl font-extrabold text-gray-800 tracking-wide text-center">
                ANFITRION (Personal)
            </h1>
            
            <!-- Registrar Personal -->
            <a href="registrarPersonal.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                Registrar Personal
            </a>

            <!-- Inhabilitar Personal -->
            <a href="listar_personal.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg transition duration-200 border border-gray-300 shadow-sm text-center">
                Inhabilitar/Habilitar Personal
            </a>
        </div>


    </body>
</html>
