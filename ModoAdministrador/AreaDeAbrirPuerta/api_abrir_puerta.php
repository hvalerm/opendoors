<?php
require '../../db.php';
require '../../validarAcceso.php';

$id_local = (int) ($_GET['id_local'] ?? 0);
if ($id_local <= 0) {
    header('Location: abrir_puerta.php');
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE Local_Placa SET accionar = 1 WHERE id_local = :id_local');
    $stmt->execute([':id_local' => $id_local]);
    header('Location: accionar_puerta.php?id_local=' . $id_local);
    exit;
} catch (PDOException $e) {
    $error = 'No se pudo enviar la señal de apertura.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Error al abrir puerta</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4"><div class="bg-white p-8 rounded-xl shadow-md text-center max-w-sm w-full"><p class="text-red-600 font-semibold"><?php echo htmlspecialchars($error); ?></p><a href="local.php?id_local=<?php echo $id_local; ?>" class="inline-block mt-4 bg-gray-200 px-4 py-2 rounded-lg">Volver</a></div></body>
</html>
