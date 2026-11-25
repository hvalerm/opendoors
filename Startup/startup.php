<?php
require_once __DIR__ . '/../util/Seguridad/enc.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$cifrado = $_GET['id'] ?? '';
if (!is_string($cifrado) || !preg_match('/^[0-9A-Fa-f]{1000}$/D', $cifrado)) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

try {
    $textoDescifrado = CustomCryptographer::descifrarNumero($cifrado);
} catch (RuntimeException $e) {
    error_log('Error en Startup/startup: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la configuracion inicial. Intentalo mas tarde.');
} catch (Exception $e) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

$idEmpresa = ctype_digit($textoDescifrado)
    ? filter_var(ltrim($textoDescifrado, '0'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ])
    : false;
if ($idEmpresa === false) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

// El ID se transmite solo por inclusion, nunca desde un parametro sin cifrar.
require __DIR__ . '/configuracionInicial.php';
