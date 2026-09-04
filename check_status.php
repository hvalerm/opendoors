<?php
require 'db.php';

$id_boa = isset($_GET['id_boa']) ? intval($_GET['id_boa']) : 0;
$id_loc = isset($_GET['id_loc']) ? intval($_GET['id_loc']) : 0;

header('Content-Type: application/json');

$activate = 0;

if ($id_boa > 0 && $id_loc > 0) {
    try {
        $stmt = $pdo->prepare("SELECT activate FROM BoardAction WHERE id_boa = :id_boa AND id_loc = :id_loc");
        $stmt->execute(['id_boa' => $id_boa, 'id_loc' => $id_loc]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $activate = intval($row['activate']);
        }
    } catch (PDOException $e) {
        // En caso de error devolvemos 0
    }
}

echo json_encode(['activate' => $activate]);
?>
