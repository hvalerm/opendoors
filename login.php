<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
   
    //1. Datos ingresados
    $correo_cuenta = trim($_POST['correo_cuenta']);
    $credencial_cuenta = trim($_POST['credencial_cuenta']);

    //2. Encuentra credencial encriptada y lo asigna en variable $credencia_hash
    $sql = "select credencial_cuenta from Cuenta where correo_cuenta = :correo_cuenta";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':correo_cuenta' => $correo_cuenta
    ]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    $credencia_hash = $c['credencial_cuenta'];

    
    
    //3. Verifica la credencial 
    if (password_verify($credencial_cuenta, $credencia_hash)) {
        
        
        
        
        
        //Encontrar la id_cuenta de la cuenta
        $sql = "SELECT id_tipoCuenta FROM Cuenta WHERE correo_cuenta = :correo_cuenta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':correo_cuenta' => $correo_cuenta
        ]);
        //asigna el resultado de la consulta
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        
        
        $id_tipoCuenta = $c['id_tipoCuenta'];
        //Asignamos los datos a GLOBAL
        $_SESSION['correo_cuenta'] = $correo_cuenta;
        $_SESSION['id_tipoCuenta'] = $id_tipoCuenta;
        
 
        //Redirije a la pagina adecuada
        switch ($id_tipoCuenta) {
            //Huesped
            case 1:
                header("Location: ModoHuesped/vistaHuesped.php");
                break;

            //Personal
            case 2:
                header("Location: ModoPersonal/vistaPersonal.php");
                break;

            //Anfitrion
            case 3:
                header("Location: ModoAnfitrion/vistaAnfitrion.php");
                break;

            //Administrador
            case 4:
                header("Location: ModoAdministrador/vistaAdministrador.php");
                break;

            default:
                $error = "No detecta";
                break;
        }
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