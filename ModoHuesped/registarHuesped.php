<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Huésped</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center p-4">

    <div class="bg-gray-800 p-8 rounded-xl shadow-2xl border border-gray-700 max-w-md w-full">
        <h1 class="text-2xl font-bold mb-6 text-center text-gray-100">Registro de Huésped</h1>
        
        <form action="procesar_registro.php" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">DNI</label>
                <input type="text" name="dni" maxlength="8" pattern="[0-9]{8}" placeholder="Ej. 12345678" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Nombre</label>
                <input type="text" name="nombre" placeholder="Tu nombre" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Apellido</label>
                <input type="text" name="apellido" placeholder="Tu apellido" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Correo electrónico</label>
                <input type="email" name="correo" placeholder="correo@ejemplo.com" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">País</label>
                <select name="pais" required 
                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-emerald-500">
                    <option value="" disabled selected>Selecciona un país</option>
                    <option value="Perú">Perú</option>
                    <option value="Argentina">Argentina</option>
                    <option value="Bolivia">Bolivia</option>
                    <option value="Chile">Chile</option>
                    <option value="Colombia">Colombia</option>
                    <option value="Ecuador">Ecuador</option>
                    <option value="España">España</option>
                    <option value="México">México</option>
                    <option value="Estados Unidos">Estados Unidos</option>
                    <option value="Venezuela">Venezuela</option>
                </select>
            </div>

            <button type="submit" 
                class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-lg cursor-pointer mt-6">
                Completar Registro
            </button>
        </form>
    </div>

</body>
</html>