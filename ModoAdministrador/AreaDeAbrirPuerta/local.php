<?php
require '../../db.php';
require '../../validarAcceso.php';

$id_local = (int) ($_GET['id_local'] ?? 0);
$local = null;
$error = '';
if ($id_local > 0) {
    try {
        $stmt = $pdo->prepare('SELECT id_local, nombre_local, direccion_local FROM Local WHERE id_local = :id_local');
        $stmt->execute([':id_local' => $id_local]);
        $local = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $error = 'Hubo un problema al cargar los datos del local.';
    }
} else {
    $error = 'El local especificado no es válido.';
}
if (!$local && $error === '') $error = 'El local especificado no existe.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Local</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php require '../../barraNav.php'; ?>
    <main class="max-w-md mx-auto px-4 py-10 w-full flex-grow">
        <?php if ($error !== ''): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg"><p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p><a href="abrir_puerta.php" class="inline-block mt-4 bg-gray-200 px-4 py-2 rounded-lg">Volver a ubicaciones</a></div>
        <?php else: ?>
            <div class="bg-white border border-gray-200 rounded-2xl p-8 shadow-sm text-center space-y-6">
                <h1 class="text-2xl font-extrabold text-gray-800"><?php echo htmlspecialchars($local['nombre_local']); ?></h1>
                <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($local['direccion_local']); ?></p>
                <a href="api_abrir_puerta.php?id_local=<?php echo (int) $local['id_local']; ?>" class="w-full inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl shadow text-center">Abrir Puerta</a>
                <a href="abrir_puerta.php" class="w-full inline-block bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 px-6 rounded-xl text-center">Elegir otra ubicación</a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
