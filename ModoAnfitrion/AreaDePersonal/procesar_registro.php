<?php
require '../../db.php';
require '../../validarAcceso.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar los datos del formulario
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $credencial = $_POST['credencial'] ?? '';

    // Validar que ningún campo esté vacío
    if (empty($dni) || empty($nombre) || empty($apellido) || empty($correo) || empty($credencial)) {
        die("Error: Todos los campos son obligatorios.");
    }

    // Validar formato del correo electrónico
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        die("Error: El formato del correo electrónico no es válido.");
    }

    try {
        // Encriptar la contraseña de forma segura utilizando Argon2id
        $credencial_hash = password_hash($credencial, PASSWORD_ARGON2ID);

        $sql = "INSERT INTO Cuenta (correo_cuenta, credencial_cuenta, id_tipoCuenta)
            VALUES (:correo, :credencial_encriptada, 2)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':correo' => $correo,
            ':credencial_encriptada' => $credencial_hash
        ]);

        //Obtiene la id_cuenta de la cuenta recien registrada
        $sql = "SELECT id_cuenta FROM Cuenta where correo_cuenta=:correo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':correo' => $correo
        ]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_cuenta = $r['id_cuenta'];

        //Agrega el los datos del personal
        $sql = "INSERT INTO Personal (dni_personal, nombre_personal, apellido_personal, id_cuenta)
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

                    <a href="../vistaAnfitrion.php" class="block w-full bg-blue-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200">
                        Regresar al panel
                    </a>
                </div>

            </body>
        </html>

        <?php
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            ?>
            <!DOCTYPE html>
            <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Correo ya registrado</title>
                    <script src="https://cdn.tailwindcss.com"></script>
                </head>
                <body class="bg-gray-100 flex items-center justify-center h-screen">

                    <div class="bg-white p-8 rounded-lg shadow-md text-center max-w-sm w-full">
                        <div class="mb-4 text-amber-500 flex justify-center">
                            <svg class="w-16 h-16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>

                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Correo ya registrado</h2>
                        <p class="text-gray-600 mb-6">El correo electrónico ingresado ya se encuentra asociado a otra cuenta en el sistema.</p>

                        <a href="javascript:history.back()" class="block w-full bg-gray-600 text-white font-semibold py-2 px-4 rounded-md hover:bg-gray-700 transition duration-200">
                            Regresar
                        </a>
                    </div>

                </body>
            </html>
            <?php
        } else {
            echo "ERROR: " . $e->getMessage();
        }
    }
}
?>