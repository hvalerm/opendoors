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

// 2. Redireccion a pagina adecuada
$roles_permitidos_huesped = [1];
$roles_permitidos_personal = [2];
$roles_permitidos_anfitrion = [2, 3];
$roles_permitidos_adminitrador = [2, 3, 4];


