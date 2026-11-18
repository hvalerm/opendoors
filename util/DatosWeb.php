<?php

$nombreWeb = 'OpenDoors';

// URL publica de esta instalacion, sin barra final. En XAMPP: http://localhost/OpenDoors_V2
// Los enlaces compartidos, las llamadas de acceso y el inicio de sesion usan esta URL.
$urlBase = 'https://p-lzag.onrender.com/';

// Rutas relativas a la URL base, sin barra inicial.
// Deben coincidir con las rutas publicadas por el servidor; cambiarlas no mueve los archivos.
$rutaApi = 'ApiSystem';
$rutaApiBoard = $rutaApi . '/api_board.php';
$rutaApiOrdenApertura = $rutaApi . '/ordenDeApertura_porLinkAcceso.php';
$rutaAccesoPuerta = 'accesoPuerta.php';

$urlBase = rtrim($urlBase, '/');
$urlApi = $urlBase . '/' . $rutaApi;
$urlApiBoard = $urlBase . '/' . $rutaApiBoard;
$urlApiOrdenApertura = $urlBase . '/' . $rutaApiOrdenApertura;
$urlAccesoPuerta = $urlBase . '/' . $rutaAccesoPuerta;
$urlLogin = $urlBase . '/login.php';