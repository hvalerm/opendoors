<?php
//Registra huesped - no necesita session_start()
require '../../db.php';
$error = $error ?? '';
$dni = $dni ?? '';
$nombre = $nombre ?? '';
$apellido = $apellido ?? '';
$correo = $correo ?? '';
$pais_seleccionado = $pais_seleccionado ?? '';
//Registrar huesped - no necesita validar porque no ha iniciado sesion todavia

try {
    $stmt = $pdo->query("select id_pais, pais from Pais");
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $resultados = [];
    $error = "No se pudieron cargar los países.";
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Huésped</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">
    <a href="area_anfitrion.php" class="absolute top-4 left-4 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al área de anfitriones</a>

    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl border border-gray-700 max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-100">Registro de Anfitrión</h1>
        <?php if ($error !== ''): ?>
            <p class="mb-4 bg-red-100 text-red-700 p-3 rounded-lg text-center"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form action="procesar_registro.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">DNI</label>
                <input type="text" name="dni" value="<?php echo htmlspecialchars($dni); ?>" maxlength="8" pattern="[0-9]{8}" placeholder="Ej. 12345678" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nombre</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>" placeholder="Nombre" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Apellido</label>
                <input type="text" name="apellido" value="<?php echo htmlspecialchars($apellido); ?>" placeholder="Apellido" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Correo electrónico</label>
                <input type="email" name="correo" value="<?php echo htmlspecialchars($correo); ?>" placeholder="correo@ejemplo.com" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Contraseña</label>
                <input type="password" name="credencial1" placeholder="••••••••" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Confirmar contraseña</label>
                <input type="password" name="credencial2" placeholder="••••••••" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">País</label>
                <select name="pais" required
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
                    <?php
                    if (isset($resultados)):
                        echo '<option value="" disabled ' . ($pais_seleccionado === '' ? 'selected' : '') . '>Selecciona tu país</option>';

                        foreach ($resultados as $r):
                    ?>
                            <option value="<?php echo intval($r['id_pais']); ?>" <?php echo (string) $r['id_pais'] === $pais_seleccionado ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r['pais']); ?>
                            </option>
                    <?php endforeach;
                    else:
                        echo '<option value="" disabled selected>No hay países disponibles</option>';
                    endif;
                    ?>

                </select>
            </div>

            <button type="submit"
                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-lg cursor-pointer mt-6">
                Completar Registro
            </button>

            <!-- Ir a iniciar sesión -->
            <a href="../../login.php" class="block w-full text-center text-gray-400 hover:text-emerald-400 text-sm font-medium transition duration-200 mt-4">
                ¿Ya tienes una cuenta? Iniciar Sesión
            </a>
        </form>

    </div>

</body>

</html>