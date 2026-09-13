<?php
require '../../../db.php';
require '../../../validarAcceso.php';
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Administrar Estancia de Huéspedes</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex flex-col m-0">
        <div class="w-full">
            <?php require '../../../barraNav.php'; ?>
        </div>
        <div class="w-full px-4 pt-4">
            <a href="../area_huesped.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al área de huéspedes</a>
        </div>
        <main class="flex-grow flex items-center justify-center px-4 py-10">
            <section class="w-full max-w-lg mx-auto flex flex-col items-center space-y-6">
                <h1 class="text-3xl font-extrabold text-gray-800 tracking-wide text-center">ESTANCIAS DE HUÉSPEDES</h1>
                <div class="flex flex-col space-y-3 w-full">
                    <a href="EnCurso/huesped_enCurso.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg border border-gray-300 shadow-sm text-center">Huesped - En Curso</a>
                    <a href="Terminado/huesped_terminado.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg border border-gray-300 shadow-sm text-center">Huesped - Terminado</a>
                    <a href="PorIngresar/huesped_porIngresar.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg border border-gray-300 shadow-sm text-center">Huesped - Por Ingresar</a>
                    <a href="../Huesped_Antecedentes/huesped_antecedentes.php" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-4 rounded-lg border border-gray-300 shadow-sm text-center">Huesped - Antecedentes</a>
                </div>
            </section>
        </main>
    </body>
</html>
