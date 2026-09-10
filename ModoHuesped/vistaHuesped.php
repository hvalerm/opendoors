<?php
session_start();

// 1. Validar Sesión y Rol
if (!isset($_SESSION['user_acc'])) {
    header("Location: ../login.php");
    exit;
}

$rol_permitido = 3; 
$mi_rol = $_SESSION['id_accTyp'];

if ($mi_rol != $rol_permitido){
    header("Location: ../login.php");
    exit;
}

//FIN VALIDACIONES



require 'db.php';




?>