<?php
// El servidor integrado de PHP no interpreta .htaccess.
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($ruta) && preg_match('~^/(?:[A-Za-z0-9_]+/)*[A-Za-z0-9_]+$~D', $ruta)) {
    $script = $_SERVER['DOCUMENT_ROOT'] . $ruta . '.php';
    if (is_file($script)) {
        require $script;
        return true;
    }
}
return false;
