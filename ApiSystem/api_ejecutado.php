<?php
require '../db.php';

$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;
$id_pla = isset($_GET['id_pla']) ? intval($_GET['id_pla']) : 0;

if ($id_loc > 0 && $id_pla > 0) {
    try {
        $sql = "UPDATE Local_Placa SET ejecutado = NOW() WHERE id_placa = :id_pla AND id_local = :id_loc";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'id_pla' => $id_pla,
            'id_loc' => $id_loc
        ]);
    } catch (PDOException $e) {
        // Error silencioso
    }
}

?>