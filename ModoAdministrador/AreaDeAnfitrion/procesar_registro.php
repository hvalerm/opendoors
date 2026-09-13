<?php
require '../../db.php';
$rol_pagina = 4;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $credencial1 = $_POST['credencial1'] ?? '';
    $credencial2 = $_POST['credencial2'] ?? '';
    $pais_seleccionado = trim($_POST['pais'] ?? '');

    if ($credencial1 !== $credencial2) {
        $error = 'Las contraseñas no coinciden.';
        include 'registrarAnfitrion.php';
        exit;
    }

    if (empty($dni) || empty($nombre) || empty($apellido) || empty($correo) || empty($credencial1) || empty($pais_seleccionado)) {
        $error = 'Todos los campos son obligatorios.';
        include 'registrarAnfitrion.php';
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo electrónico no es válido.';
        include 'registrarAnfitrion.php';
        exit;
    }

    try {
            // Encriptar la contraseña de forma segura utilizando Argon2id
            $credencial_hash = password_hash($credencial1, PASSWORD_ARGON2ID);

            $sql = "INSERT INTO Cuenta (correo_cuenta, credencial_cuenta, id_tipoCuenta)
            VALUES (:correo, :credencial_hash, 3)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':correo' => $correo,
                ':credencial_hash' => $credencial_hash
            ]);

            $sql = "SELECT id_cuenta FROM Cuenta where correo_cuenta=:correo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':correo' => $correo
            ]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $id_cuenta = $r['id_cuenta'];

            $sql = "INSERT INTO Anfitrion (dni_anfitrion, nombre_anfitrion, apellido_anfitrion, id_cuenta)
                VALUES (:dni, :nombre, :apellido, :idCuenta)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':dni' => $dni,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':idCuenta' => $id_cuenta
            ]);
            ?>


            <!DOCTYPE html>
            <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Registro Exitoso</title>
                    <script src="https://cdn.tailwindcss.com"></script>
                </head>
                <body class="bg-gray-100 flex items-center justify-center h-screen">

                    <div class="bg-white p-8 rounded-lg shadow-md text-center max-w-sm w-full">
                        <div class="mb-4 text-green-600 flex justify-center">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-800 mb-2">¡Registro exitoso!</h2>
                        <p class="text-gray-600 mb-6">Tu cuenta ha sido creada correctamente en el sistema!!!</p>

                        <a href="../../login.php" class="block w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                            Iniciar sesión
                        </a>
                    </div>

                </body>
            </html>

            <?php
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'El correo, DNI o anfitrión ya está registrado.';
            } else {
                $error = 'No se pudo registrar el anfitrión.';
            }
            include 'registrarAnfitrion.php';
            exit;
        }
} else {
    header('Location: registrarAnfitrion.php');
    exit;
}

