<?php

//Direccion inicial
$retroceso="../";
$retrocesos = "";

while(!file_exists($retrocesos . "index.php")) {
    $retrocesos .= "../";
}

//Declaracion de variables

$login_path = $retrocesos . "login.php";
$acceso_denegado_path = $retrocesos . "acceso_denegado.php";





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
    session_start();

    //Mensaje especifico deacuerdo al caso
    $_SESSION['error_mensaje'] = "Acceso denegado: no hay sesión activa.";
    $_SESSION['error_tipo'] = "no_sesion_activa";
    header('Location: ' . $acceso_denegado_path);
    exit;
}

$mi_rol = $_SESSION['id_tipoCuenta'];
//----------------------
// 1. Proteger la ruta: si no hay sesión activa, regresa al login
if (!isset($_SESSION['correo_cuenta'])) {
    //Mensaje especifico deacuerdo al caso
    session_destroy();
    session_start();
    //Mensaje especifico deacuerdo al caso
    $_SESSION['error_mensaje'] = "Acceso denegado: no hay sesión activa.";
    $_SESSION['error_tipo'] = "no_sesion_activa";
    header("Location: " . $acceso_denegado_path);
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
    session_destroy();
    session_start();
    //Mensaje especifico deacuerdo al caso
    $_SESSION['error_mensaje'] = "Acceso denegado: rol no permitido.";
    $_SESSION['error_tipo'] = "rol_no_permitido";
    header("Location: " . $acceso_denegado_path);
    exit;
}




// 4. Verificar si el rol actual tiene acceso a la página
if (!in_array($rol_pagina, $roles_permitidos)) {
    session_destroy();
    session_start();
    //Mensaje especifico deacuerdo al caso
    $_SESSION['error_mensaje'] = "Acceso denegado: rol no permitido para esta página.";
    $_SESSION['error_tipo'] = "rol_no_permitido";
    header("Location: " . $acceso_denegado_path);
    exit;
}
