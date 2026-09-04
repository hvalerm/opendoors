<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a InShop</title>
</head>
<body>
    <div style="text-align: center; margin-top: 50px;">
        <h1>Bienvenido a nuestro sistema</h1>
        <p>Para continuar, por favor ingresa con tu cuenta o regístrate.</p>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Si ya está logueado, mostramos el botón al dashboard -->
            <a href="dashboard.php">Ir a mi Panel</a>
        <?php else: ?>
            <!-- Si no, mostramos las opciones de acceso -->
            <a href="login.php">Iniciar Sesión</a> | 
            <a href="register.php">Registrarse</a>
        <?php endif; ?>
    </div>
</body>
</html>