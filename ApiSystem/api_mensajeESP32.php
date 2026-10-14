<?php
require '../db.php';

$id_loc = intval($_GET['id_loc'] ?? 0);
$id_pla = intval($_GET['id_pla'] ?? 0);
$msg    = $_GET['msg'] ?? "";

if ($id_loc > 0 && $id_pla > 0 && $msg !== "") {
    try {
        $sql = "INSERT INTO Mensajes_Locales_Placas (id_local, id_placa, mensaje) VALUES (:id_loc, :id_pla, :msg)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'id_pla' => $id_pla,
            'id_loc' => $id_loc,
            'msg' => $msg
        ]);
    } catch (PDOException $e) {
        // Error silencioso
    }
}

?>

