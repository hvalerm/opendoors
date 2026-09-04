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
            echo json_encode(["activate" => 1]);
            
            $update = $pdo->prepare("UPDATE BoardAction SET activate = 0 WHERE id_boa = :id_boa AND id_loc = :id_loc");
            $update->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
            exit;
        }
    } catch (PDOException $e) {
        // Muestra el error exacto en pantalla en lugar de ocultarlo
        echo json_encode(["error" => "Error en BD: " . $e->getMessage()]);
        exit;
    }
}

echo json_encode(["activate" => 0]);
?>
