<?php
require 'db.php';

$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 3;
$id_boa = isset($_GET['id_boa']) ? intval($_GET['id_boa']) : 1;

header('Content-Type: application/json');

if ($id_loc > 0 && $id_boa > 0) {
    try {
        // 1. Consultar el estado actual
        $stmt = $pdo->prepare("SELECT activate FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
        $stmt->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && intval($row['activate']) == 1) {
            // 2. Responder al ESP32 que debe abrir la puerta
            echo json_encode(["activate" => 1]);
            
            // 3. Forzar de inmediato el reseteo a 0 para que no se quede encendido
            $update = $pdo->prepare("UPDATE BoardAction SET activate = 0 WHERE id_boa = :id_boa AND id_loc = :id_loc");
            $update->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
            exit;
        }
    } catch (PDOException $e) {
        // Error silencioso para no romper la respuesta del ESP32
    }
}

// Si no está en 1 o hubo algún detalle, responde 0 por defecto
echo json_encode(["activate" => 0]);
?>
