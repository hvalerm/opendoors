<?php
require '../../db.php';
$rol_pagina = 4;
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
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4">
        <a href="../vistaAdministrador.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al panel administrador</a>
    </div>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-8 border-b border-gray-200 pb-5">
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Ubicaciones Disponibles</h1>
            <p class="text-gray-500 mt-2 text-lg">Selecciona una ubicación para abrir su puerta.</p>
        </div>
        <?php if ($error !== ''): ?><div class="bg-red-50 border-l-4 border-red-500 p-4 mb-8"><p class="text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p></div><?php endif; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php if (!$localizaciones): ?><div class="col-span-full text-center py-16 bg-white rounded-xl border-2 border-dashed border-gray-300"><p class="text-gray-500 font-medium">No hay ubicaciones registradas.</p></div><?php else: foreach ($localizaciones as $local): ?>
                <a href="local.php?id_local=<?php echo (int) $local['id_local']; ?>" class="group relative block bg-white border border-gray-200 rounded-2xl p-6 py-8 shadow-sm hover:shadow-xl hover:-translate-y-1 hover:border-blue-400 transition-all duration-300 flex flex-col items-center justify-center text-center overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div class="relative z-10 bg-blue-50 group-hover:bg-blue-100 p-3 rounded-full mb-3 transition-colors duration-300">
                        <svg class="w-8 h-8 text-blue-500 group-hover:text-blue-600 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <span class="relative z-10 text-lg font-bold text-gray-800 group-hover:text-blue-800 transition-colors duration-300 mb-1"><?php echo htmlspecialchars($local['nombre_local']); ?></span>
                    <span class="relative z-10 text-sm font-medium text-gray-500 group-hover:text-blue-600 transition-colors duration-300"><?php echo htmlspecialchars($local['direccion_local']); ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </main>
</body>
</html>
