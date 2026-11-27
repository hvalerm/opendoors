<?php
return static function (array $modulo, string $nombreWeb): array {
    require_once __DIR__ . '/Nucleo/util/db.php';
    $idEmpresa = (int) ($modulo['idEmpresa'] ?? $_SESSION['empresa_id']);
    $stmt = getOpenDoorsPDO()->prepare(
        'SELECT idLocal, nombreLocal FROM locales
         WHERE idEmpresa = ? AND eliminado = 0 ORDER BY nombreLocal, idLocal'
    );
    $stmt->execute([$idEmpresa]);
    $lugares = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $local) {
        $lugares[] = [$local['nombreLocal'], null, 'OpenDoors/Locales/localSeleccionado?id=' . $local['idLocal'], 'local-' . $local['idLocal']];
    }

    $icono = 'M5 3h14v18H5zm9 8a1 1 0 1 0 0 2 1 1 0 0 0 0-2z';
    $iconoLugares = 'M3 10h18v11H3zm2-6h14v4H5zm4 9h6v8H9z';
    return [
        'prioridad' => 0,
        'principal' => [$nombreWeb, $icono, [
            ['Dashboard', null, 'OpenDoors/Dashboard/resumen', 'dashboard', 'M3 3h8v10H3zm10 0h8v6h-8zM3 15h8v6H3zm10-4h8v10h-8z'],
            ['Lugares', $lugares, null, 'locales', $iconoLugares, 'Sin lugares',
                !empty($modulo['multiempresa']) ? 'opendoors-lugares-' . $idEmpresa : 'opendoors-lugares'],
            ['Accesos', [
                ['Crear', null, 'OpenDoors/Accesos/SeleccionarLocalAcceso'], ['Listar (y editar)', null, 'OpenDoors/Accesos/listarAccesos', 'listar'], ['Habilitar/Deshabilitar', null, 'OpenDoors/Accesos/busquedaAccesoRevocar', 'revocar'],
            ], null, 'accesos', 'M12.65 10A6 6 0 1 0 12.65 14H17v4h4v-4h2v-4zM7 14a2 2 0 1 1 0-4 2 2 0 0 1 0 4z'],
            ['Dispositivos', [
                ['Ver estado', null, 'OpenDoors/Microcontroladores/verEstado'], ['Ver actividad', null, 'OpenDoors/Microcontroladores/verActividad'],
            ], null, 'dispositivos-de-apertura', 'M6 6h12v12H6zM9 9v6h6V9zM7 3h2v3H7zm4 0h2v3h-2zm4 0h2v3h-2zM7 18h2v3H7zm4 0h2v3h-2zm4 0h2v3h-2z'],
        ]],
        'configuracion' => [$modulo['nombreModulo'], [
            ['Lugares', null, 'Configuracion/OpenDoors/locales.php', 'locales', $iconoLugares],
            ['Restaurar información', null, 'Configuracion/OpenDoors/RestaurarInformacion.php', 'restaurar-informacion',
                'M12 5a7 7 0 1 1-6.7 9H3.2A9 9 0 1 0 5 6.4V3H3v7h7V8H6.3A7 7 0 0 1 12 5z'],
        ], null, 'opendoors', $icono],
        'script' => '../Modulos/OpenDoors/menuLateral.js',
        'guia' => __DIR__ . '/guia.php',
    ];
};
