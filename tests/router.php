<?php
// El servidor integrado de PHP no interpreta .htaccess.
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($ruta) && preg_match('~^/(?:vendor(?:/|$)|composer\.(?:json|lock)$)~iD', $ruta)) {
    http_response_code(403);
    return true;
}
if (is_string($ruta) && preg_match('~^/(?:ModoAnfitrion/OpenDoors/util|Modulos/[^/]+/(?:(?:menuLateral|guia)\.php|Nucleo/util)|Modulos/README\.txt)(?:/|$)~iD', $ruta)) {
    http_response_code(403);
    return true;
}
if (is_string($ruta) && preg_match('~^/ModoAnfitrion/OpenDoors/(puertaAccionada|Dashboard/resumen|Locales/localSeleccionado|Microcontroladores/(?:verEstado|verActividad)|Accesos/(?:CrearAccesoLocal|SeleccionarLocalAcceso|busquedaAccesoRevocar|listarAccesos|localSeleccionado|revocarAcceso|vetanaDelAcceso))(?:\.php)?$~D', $ruta, $m)) {
    require $_SERVER['DOCUMENT_ROOT'] . '/Modulos/OpenDoors/Nucleo/' . $m[1] . '.php';
    return true;
}
if (is_string($ruta) && preg_match('~^/ModoAnfitrion/Configuracion/OpenDoors/((?:locales|configuracionLocal|RestaurarInformacion)\.php|locales\.css)$~D', $ruta, $m)) {
    $archivo = $_SERVER['DOCUMENT_ROOT'] . '/Modulos/OpenDoors/Configuracion/' . $m[1];
    if (str_ends_with($archivo, '.css')) {
        header('Content-Type: text/css');
        readfile($archivo);
    } else {
        require $archivo;
    }
    return true;
}
if (is_string($ruta) && preg_match('~^/(?:[A-Za-z0-9_]+/)*[A-Za-z0-9_]+$~D', $ruta)) {
    $script = $_SERVER['DOCUMENT_ROOT'] . $ruta . '.php';
    if (is_file($script)) {
        require $script;
        return true;
    }
}
return false;
