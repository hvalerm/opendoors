<?php

$nombreWeb = 'OpenDoors';

// URL publica de esta instalacion, sin barra final. En XAMPP: http://localhost/OpenDoors_V2
// Los enlaces compartidos, las llamadas de acceso y el inicio de sesion usan esta URL.
$urlBase = 'https://p-lzag.onrender.com/';

// Rutas relativas a la URL base, sin barra inicial.
// Deben coincidir con las rutas publicadas por el servidor; cambiarlas no mueve los archivos.
$rutaApi = 'ApiSystem';
// Las URL nuevas no llevan extensión; las antiguas con .php siguen funcionando.
$rutaApiBoard = $rutaApi . '/api_board';
$rutaApiMicrocontrolador = $rutaApi . '/api_microcontrolador';
$rutaApiRestore = $rutaApi . '/api_restore';
$rutaApiOrdenApertura = $rutaApi . '/ordenDeApertura_porLinkAcceso';
$rutaAccesoPuerta = 'accesoPuerta';

$urlBase = rtrim($urlBase, '/');
$urlApi = $urlBase . '/' . $rutaApi;
$urlApiBoard = $urlBase . '/' . $rutaApiBoard;
$urlApiMicrocontrolador = $urlBase . '/' . $rutaApiMicrocontrolador;
$urlApiRestore = $urlBase . '/' . $rutaApiRestore;
$urlApiOrdenApertura = $urlBase . '/' . $rutaApiOrdenApertura;
$urlAccesoPuerta = $urlBase . '/' . $rutaAccesoPuerta;
$urlLogin = $urlBase . '/login';