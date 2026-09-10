<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $correo_cuenta = trim($_POST['correo_cuenta']);
    $credencial_cuenta = trim($_POST['credencial_cuenta']);

    // Generamos el hash SHA-512 en PHP con la contraseña ingresada
    $credencial_cuenta_hash = password_hash($credencial_cuenta, PASSWORD_ARGON2ID);

    // Consulta preparada contra la tabla Cuenta
    // Ahora comparamos también el hash de la contraseña directamente en la consulta (o en PHP)
    $stmt = $pdo->prepare("SELECT correo_cuenta, id_cuenta FROM Cuenta WHERE correo_cuenta = :correo AND credencial_cuenta = :credencial");
    $stmt->execute([
        ':correo' => $correo_cuenta,
        ':credencial' => $credencial_cuenta_hash // Pasamos el hash generado en PHP
    ]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si $user tiene datos, las credenciales coinciden
    if ($user) {
        $_SESSION['correo_cuenta'] = $user['correo_cuenta'];
        $_SESSION['id_cuenta'] = $user['id_cuenta'];
        
        switch ($_SESSION['id_cuenta']) {
            //Administrador
            case 1:
                header("Location: ModoAdministrador/vistaAdministrador.php");
                break;
            
            //Anfitrion
            case 2:
                header("Location: ModoAnfitrion/vistaAnfitrion.php");
                break;
            
            //Huesped
            case 3:
                header("Location: ModoHuesped/vistaHuesped.php");
                break;
            
            //Personal
            case 4:
                header("Location: ModoPersonal/vistaPersonal.php");
                break;

            
            default:
                $error = "No detecta";
                break;
        }
        exit;
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema</title>
    <!-- Importamos Tailwind CSS mediante CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <!-- Contenedor del Login -->
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md mx-4">
        
        <!-- Cabecera -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-gray-800">Bienvenido</h2>
            <p class="text-gray-500 mt-2 text-sm">Ingresa tus credenciales para continuar</p>
        </div>

        <!-- Alerta de Error -->
        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="login.php" class="space-y-6">
            
            <!-- Input Usuario -->
            <div>
                <label for="correo_cuenta" class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                <input type="text" id="correo_cuenta" name="correo_cuenta" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                       placeholder="Ingresa tu correo">
            </div>

            <!-- Input Contraseña -->
            <div>
                <label for="credencial_cuenta" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
                <input type="password" id="credencial_cuenta" name="credencial_cuenta" required 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
                       placeholder="••••••••">
            </div>

            <!-- Botón de Envío -->
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md transition-colors duration-200">
                Iniciar Sesión
            </button>
        </form>
        
        <!-- Enlace al Registro -->
        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                ¿No tienes una cuenta? 
                <a href="ModoHuesped/registarHuesped.php" class="font-semibold text-blue-600 hover:text-blue-800 transition-colors">
                    Regístrate aquí
                </a>
            </p>
        </div>
    </div>

</body>
</html>