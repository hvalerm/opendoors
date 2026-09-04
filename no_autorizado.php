<?php
session_start();
if (!isset($_SESSION['user_acc'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-10 rounded-xl shadow-lg max-w-md text-center border-t-4 border-red-500">
        
        <svg class="w-16 h-16 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>

        <h2 class="text-2xl font-bold text-gray-800 mb-2">Acceso Restringido</h2>
        <p class="text-gray-600 mb-6">Tu tipo de cuenta no tiene los permisos necesarios para ver las ubicaciones del sistema.</p>
        
        <a href="logout.php" class="inline-block bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-6 rounded-lg transition-colors duration-200">
            Cerrar Sesión y Volver
        </a>
    </div>
</body>
</html>