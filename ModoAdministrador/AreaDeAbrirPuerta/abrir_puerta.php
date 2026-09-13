<?php
require '../../db.php';
require '../../validarAcceso.php';

$error = '';
try {
    $stmt = $pdo->query('SELECT id_local, nombre_local, direccion_local FROM Local ORDER BY nombre_local ASC');
    $localizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $localizaciones = [];
    $error = 'Hubo un problema al cargar las ubicaciones.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Ubicaciones</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php require '../../barraNav.php'; ?>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8 border-b border-gray-200 pb-5">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Ubicaciones Disponibles</h1>
            <p class="text-gray-500 mt-2 text-lg">Selecciona una ubicación para abrir su puerta.</p>
        </div>
        <?php if ($error !== ''): ?><div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8"><p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php if (!$localizaciones): ?><div class="col-span-full text-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300"><p class="text-gray-500 font-medium">No hay ubicaciones registradas.</p></div><?php else: foreach ($localizaciones as $local): ?>
                <a href="local.php?id_local=<?php echo (int) $local['id_local']; ?>" class="group block bg-white border border-gray-200 rounded-2xl p-6 py-8 shadow-sm hover:shadow-xl hover:border-blue-400 transition-all text-center">
                    <span class="block text-lg font-bold text-gray-800 group-hover:text-blue-800 mb-1"><?php echo htmlspecialchars($local['nombre_local']); ?></span>
                    <span class="block text-sm font-medium text-gray-500"><?php echo htmlspecialchars($local['direccion_local']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </main>
</body>
</html>
