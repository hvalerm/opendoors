<?php
require '../../../../db.php';
$rol_pagina = 3;
require '../../../../validarAcceso.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Huéspedes En Curso</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <div class="w-full"><?php require '../../../../barraNav.php'; ?></div>
    <div class="w-full px-4 pt-4"><a href="../huesped_administrarEstancia.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar a estancias</a></div>
    <main class="flex-grow flex items-center justify-center px-4 py-10"><section class="bg-white p-8 rounded-xl shadow-md max-w-lg w-full text-center"><h1 class="text-2xl font-bold text-gray-800">Huesped - En Curso</h1><p class="text-gray-600 mt-4">Aquí se mostrarán los huéspedes con una estancia en curso.</p></section></main>
</body>
</html>
