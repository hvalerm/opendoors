<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/aperturas.php';

if ($argc > 2 || ($argc === 2 && $argv[1] !== '--una-vez')) {
    fwrite(STDERR, "Uso: php procesar_aperturas.php [--una-vez]\n");
    exit(1);
}

try {
    do {
        $procesadas = procesarAperturasPendientes(getOpenDoorsPDO());
        if ($argc === 2) {
            echo "Solicitudes finalizadas: $procesadas\n";
            break;
        }
        if ($procesadas < 100) {
            sleep(1);
        }
    } while (true);
} catch (PDOException $e) {
    error_log('Error al procesar aperturas pendientes: ' . $e->getMessage());
    exit(1);
}
