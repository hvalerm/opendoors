<?php
require '../../db.php';
require '../../validarAcceso.php';

$id_local = (int) ($_GET['id_local'] ?? 0);
$local = null;
$error = '';
if ($id_local > 0) {
    try {
        $stmt = $pdo->prepare('SELECT nombre_local, direccion_local FROM Local WHERE id_local = :id_local');
        $stmt->execute([':id_local' => $id_local]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$local) $error = 'Local no encontrado.';
    } catch (PDOException $e) {
        $error = 'No se pudo cargar la información del local.';
    }
} else {
    $error = 'ID de local no válido.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Puerta accionada</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 min-h-screen flex flex-col"><div class="w-full"><?php require '../../barraNav.php'; ?></div><div class="max-w-md mx-auto w-full px-4 pt-4"><a href="abrir_puerta.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar a ubicaciones</a></div>
<main class="max-w-md mx-auto px-4 py-16 w-full flex-grow"><div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center space-y-6">
<?php if ($error !== ''): ?><h1 class="text-xl font-bold text-gray-800">Atención</h1><p class="text-red-600 font-medium"><?php echo htmlspecialchars($error); ?></p><a href="abrir_puerta.php" class="w-full inline-block bg-gray-100 text-gray-700 font-semibold py-3 px-6 rounded-xl">Regresar a ubicaciones</a>
<?php else: ?><div class="text-green-600 text-5xl">&#10003;</div><h1 class="text-2xl font-extrabold text-gray-800">¡Puerta accionada!</h1><p class="text-gray-600 font-semibold"><?php echo htmlspecialchars($local['nombre_local']); ?></p><p class="text-gray-400 text-sm"><?php echo htmlspecialchars($local['direccion_local']); ?></p><p class="bg-blue-50 text-blue-700 rounded-xl p-4 text-sm">La señal de apertura ha sido enviada correctamente.</p><a href="local.php?id_local=<?php echo $id_local; ?>" class="w-full inline-block bg-blue-600 text-white font-semibold py-3 px-6 rounded-xl">Volver al local</a><a href="abrir_puerta.php" class="w-full inline-block bg-gray-100 text-gray-700 font-semibold py-3 px-6 rounded-xl">Elegir otra ubicación</a><?php endif; ?>
</div></main></body></html>
