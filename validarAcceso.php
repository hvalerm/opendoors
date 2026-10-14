<?php

//no es necesario poner session_start porque se llama a este con un require que ya lo inició previamente
//Direccion inicial

$retrocesos = "";
while(!file_exists($retrocesos . "index.php")) {
    $retrocesos .= "../";
}

//Declaracion de variables
$mi_rol = $_SESSION['id_tipoCuenta'];
$login_path = $retrocesos . "login.php";
$acceso_denegado_path = $retrocesos . "acceso_denegado.php";



//Posible redireccion
// 1. Verificar si hay sesión activa
if (!isset($_SESSION['id_tipoCuenta'])) {
echo "entra a salida";
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
    echo "id_tipoCurnta: ".$_SESSION['id_tipoCuenta'];

    session_destroy();

    //Mensaje especifico deacuerdo al caso
    $error_mensaje = "Acceso denegado: no hay sesión activa.";
    $error_tipo = "No sesión activa";
    header('Location: ' . $acceso_denegado_path."?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}


//----------------------


// 2. Encontrar a que roles puedo acceder, de acuerdo al rol actual
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

//Posible redireccion
// 3. Verifica que no haya error, es decir, que existan roles permitidos para el usuario actual, y si no los hay, redirige al acceso denegado
if (empty($roles_permitidos)) {
    session_destroy();
    //Mensaje especifico deacuerdo al caso
    $error_mensaje = "Acceso denegado: rol no permitido.";
    $error_tipo = "Rol no permitido";
    header("Location: " . $acceso_denegado_path . "?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}



//Posible redireccion
// 4. Verificar si el rol actual tiene acceso a la página, y si no lo tiene, redirige al acceso denegado
if (!in_array($mi_rol, $roles_permitidos)) {
    session_destroy();
    //Mensaje especifico deacuerdo al caso
    $error_mensaje = "Acceso denegado: rol no permitido para esta página.";
    $error_tipo = "Rol no permitido";
    header("Location: " . $acceso_denegado_path . "?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}
