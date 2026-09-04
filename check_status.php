<?php
require 'db.php';

$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;
$id_boa = isset($_GET['id_boa']) ? intval($_GET['id_boa']) : 0;

header('Content-Type: application/json');

if ($id_loc > 0 && $id_boa > 0) {
    try {
        $stmt = $pdo->prepare("SELECT activate FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
        $stmt->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode(["activate" => intval($row['activate'])]);
            exit;
        }
    } catch (PDOException $e) {
        // Error silencioso
    }
}

echo json_encode(["activate" => 0]);
?>
