<?php

// No es necesario poner session_start porque se llama a este con un require que ya lo inició previamente
// Dirección inicial

$retrocesos = "";
while(!file_exists($retrocesos . "index.php")) {
    $retrocesos .= "../";
}

// Declaración de variables
$mi_rol = $_SESSION['id_tipoCuenta'] ?? null;
$login_path = $retrocesos . "login.php";
$acceso_denegado_path = $retrocesos . "acceso_denegado.php";

// Posible redirección
// 1. Verificar si hay sesión activa
if (!isset($_SESSION['id_tipoCuenta'])) {
    // Se eliminaron los 'echo' para evitar romper el envío de cabeceras (headers)
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

    // Mensaje específico de acuerdo al caso
    $error_mensaje = "Acceso denegado: no hay sesión activa.";
    $error_tipo = "No sesión activa";
    header('Location: ' . $acceso_denegado_path . "?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}

// ----------------------

// Inicializamos el arreglo por seguridad
$roles_permitidos = [];

// 2. Encontrar a qué roles puedo acceder, de acuerdo al rol actual
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

// Posible redirección
// 3. Verifica que no haya error, es decir, que existan roles permitidos para el usuario actual
if (empty($roles_permitidos)) {
    session_destroy();
    $error_mensaje = "Acceso denegado: rol no permitido.";
    $error_tipo = "Rol no permitido";
    header("Location: " . $acceso_denegado_path . "?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}

// Posible redirección
// 4. Verificar si el rol actual tiene acceso a la página
if (!in_array($mi_rol, $roles_permitidos)) {
    session_destroy();
    $error_mensaje = "Acceso denegado: rol no permitido para esta página.";
    $error_tipo = "Rol no permitido";
    header("Location: " . $acceso_denegado_path . "?error_mensaje=" . urlencode($error_mensaje) . "&error_tipo=" . urlencode($error_tipo));
    exit;
}
