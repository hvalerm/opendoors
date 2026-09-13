<?php
require '../../../db.php';
$rol_pagina = 2;
require '../../../validarAcceso.php';
?>

<!DOCTYPE html>
<!--
Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHPWebPage.php to edit this template
-->
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body>
        <div class="w-full">
            <?php require '../../../barraNav.php'; ?>
        </div>
        <a href="../area_huesped.php" class="inline-block m-4 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al área de huéspedes</a>
        <?php
        // put your code here
        ?>
    </body>
</html>
