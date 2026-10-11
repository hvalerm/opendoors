<?php
require '../db.php';

$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;
$id_pla = isset($_GET['id_pla']) ? intval($_GET['id_pla']) : 0;

header('Content-Type: application/json');

if ($id_loc > 0 && $id_pla > 0) {
    try {
        $sql = "SELECT accionar FROM Local_Placa WHERE id_placa = :id_pla AND id_local = :id_loc";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_pla' => $id_pla, 'id_loc' => $id_loc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Si está en 1, respondemos JSON y lo actualizamos a 0 de inmediato
            echo json_encode(["accionar" => 1]);
            
            $update = $pdo->prepare("UPDATE Local_Placa SET accionar = 0 WHERE id_placa = :id_pla AND id_local = :id_loc");
            $update->execute(['id_pla' => $id_pla, 'id_loc' => $id_loc]);
            exit;
        }
    } catch (PDOException $e) {
        // Error silencioso
    }
}

echo json_encode(["accionar" => 0]);
?>
