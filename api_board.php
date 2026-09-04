<?php
require 'db.php';

$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 3;
$id_boa = isset($_GET['id_boa']) ? intval($_GET['id_boa']) : 1;

header('Content-Type: application/json');

if ($id_loc > 0 && $id_boa > 0) {
    try {
        $stmt = $pdo->prepare("SELECT activate FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
        $stmt->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && intval($row['activate']) == 1) {
            // Si está en 1, respondemos JSON y lo actualizamos a 0 de inmediato
            echo json_encode(["activate" => 1]);
            
            $update = $pdo->prepare("UPDATE BoardAction SET activate = 0 WHERE id_boa = :id_boa AND id_loc = :id_loc");
            $update->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
            exit;
        }
    } catch (PDOException $e) {
        // Error silencioso para no romper la respuesta del ESP32
    }
}

echo json_encode(["activate" => 0]);
?>
