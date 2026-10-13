<?php
require '../../db.php';
$rol_pagina = 3;
require '../../validarAcceso.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $query = "SELECT l.nombre_local, lp.consultado, NOW() AS fecha_actual
              FROM Local_Placa lp
              INNER JOIN Local l ON lp.id_local = l.id_local
              WHERE lp.id_placa = 1
              ORDER BY l.nombre_local ASC";
    $stmt = $pdo->query($query);
    $sensores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sensores as &$sensor) {
        $consultado = $sensor['consultado'];
        $fechaActual = strtotime($sensor['fecha_actual']);
        $fechaConsulta = $consultado !== null ? strtotime($consultado) : false;

        $sensor['estado'] = $fechaConsulta === false || ($fechaActual - $fechaConsulta > 5)
            ? 'Inhabilitado'
            : 'Habilitado';
        unset($sensor['fecha_actual']);
    }
    unset($sensor);

    echo json_encode($sensores, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo cargar la lista de sensores.'], JSON_UNESCAPED_UNICODE);
}