<?php
require '../../db.php';
require '../../validarAcceso.php';
$error = $error ?? '';
$dni = $dni ?? '';
$nombre = $nombre ?? '';
$apellido = $apellido ?? '';
$correo = $correo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registro de Personal</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body.bg-gray-900 > div.w-full > nav {
                background-color: transparent;
                border-color: rgba(255, 255, 255, 0.15);
                box-shadow: none;
            }

            body.bg-gray-900 > div.w-full > nav .text-gray-600 {
                color: rgb(209 213 219);
            }

            body.bg-gray-900 > div.w-full > nav .text-gray-800 {
                color: rgb(255 255 255);
            }
        </style>
    </head>
    <body class="bg-gray-900 text-white min-h-screen flex flex-col items-center p-4">
        <div class="w-full">
            <?php require '../../barraNav.php'; ?>
        </div>
        <div class="w-full px-4 pt-4">
            <a href="area_personal.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al área de personal</a>
        </div>

        <div class="bg-gray-800 p-8 rounded-xl shadow-2xl border border-gray-700 max-w-md w-full mt-4">
            <h1 class="text-2xl font-bold mb-6 text-center text-gray-100">Registro de Personal</h1>
            <?php if ($error !== ''): ?>
                <p class="mb-4 bg-red-100 text-red-700 p-3 rounded-lg text-center"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <?php if (isset($_GET['registro'])): ?>
                <p class="mb-4 bg-green-100 text-green-700 p-3 rounded-lg text-center">Personal registrado correctamente.</p>
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
                    <input type="password" name="credencial" placeholder="Contraseña" required
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Confirmar contraseña</label>
                    <input type="password" name="credencial_confirmacion" placeholder="Confirmar contraseña" required
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-lg cursor-pointer mt-6">
                    Completar Registro
                </button>
            </form>
        </div>
    </body>
</html>
