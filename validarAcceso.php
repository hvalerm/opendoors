<?php

//Declaracion de variables

if (!isset($_SESSION['id_tipoCuenta'])) {

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: https://opendoors.onrender.com/login.php');
    exit;
}

$mi_rol = $_SESSION['id_tipoCuenta'];
//----------------------
// 1. Proteger la ruta: si no hay sesión activa, regresa al login
if (!isset($_SESSION['correo_cuenta'])) {
    header("Location: login.php");
    exit;
}

// 2. Encontrar a que roles puedo acceder
switch ($mi_rol) {
    case 1:
        $roles_permitidos = [1];
        break;
    case 2:
        $roles_permitidos = [2];
        break;
    case 3:
        $roles_permitidos = [2, 3];
        break;
    case 4:
        $roles_permitidos = [2, 3, 4];
        break;
}

// 3. Verifica que no haya error
if (empty($roles_permitidos)) {
    header("Location: acceso_denegado.php");
    exit;
}

// 4. Verificar si el rol actual tiene acceso a la página
if (!in_array($rol_pagina, $roles_permitidos)) {
    header("Location: acceso_denegado.php");
    exit;
}
