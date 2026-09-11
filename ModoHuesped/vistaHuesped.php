<?php
session_start();
require '../db.php';

// 1. Validar Sesión y Rol
if (!isset($_SESSION['correo_cuenta'])) {

    header("Location: ../login.php");
    exit;
}

$correo_cuenta = $_SESSION['correo_cuenta'];

//2. Verifica que haya una tipo de cuenta en la sesion
if (!isset($_SESSION['id_tipoCuenta'])) {
    echo "No Hay tipoCuenta asignado";
    die();
}

//3. Se especifica el tipo de cuenta permitido
$id_tipoCuenta_permitido = 1;

//4. Se valida que este formulario se cargue si es un usuario permitido
if ($_SESSION['id_tipoCuenta'] != $id_tipoCuenta_permitido) {
    echo "Usted no es un usario permitido para entrada.";
    die();

    exit;
}

//  -------------- FIN VALIDACIONES --------------
//select H.id_huesped from Cuenta C inner join Huesped H on C.id_cuenta = H.id_cuenta inner join Acceso A on A.id_huesped = H.id_huesped ;


$sql = "SELECT L.nombre_local, L.direccion_local, C.correo_cuenta, H.nombre_huesped, H.apellido_huesped, A.id_acceso, A.id_local, A.datetime_inicio, A.datetime_fin 
        FROM Cuenta C 
        INNER JOIN Huesped H ON C.id_cuenta = H.id_cuenta 
        INNER JOIN Acceso A ON A.id_huesped = H.id_huesped 
        INNER JOIN Local L ON L.id_local = A.id_local 
        WHERE C.correo_cuenta = :correo_cuenta 
          AND A.ejecutado = 0 
          AND NOW() BETWEEN A.datetime_inicio AND A.datetime_fin;";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':correo_cuenta' => $correo_cuenta
]);

$c = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Area de Acceso</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 flex items-center justify-center h-screen m-0">

        <?php
        if ($c) {
            ?>
            <div class="w-full max-w-lg mx-auto flex flex-col items-center space-y-6">
                <!-- Título principal fijo arriba -->
                <h2 class="text-2xl font-bold text-blue-600 text-center">Información de Acceso</h2>
                
                <?php
                foreach ($c as $row) {
                    ?>
                    <!-- Contenedor de cada formulario, uno debajo de otro -->
                    <div class="bg-white p-6 rounded-xl shadow-md w-full border border-gray-100">
                        <form method="POST" class="space-y-4">

                            <input type="hidden" name="id_acceso" value="<?php echo $row['id_acceso']; ?>">
                            <input type="hidden" name="id_local" value="<?php echo $row['id_local']; ?>">

                            <div class="space-y-2 text-left">
                                <!-- Datos Huesped -->
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="font-bold text-gray-900">Huésped:</span> 
                                    <span class="text-gray-600"><?php echo $row['nombre_huesped'] . " " . $row['apellido_huesped'] . " - " . $row['correo_cuenta']; ?></span>
                                </label>

                                <!-- Datos Local -->
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="font-bold text-gray-900">Local:</span> 
                                    <span class="text-gray-600"><?php echo $row['nombre_local'] . " - " . $row['direccion_local']; ?></span>
                                </label>

                                <!-- Datos de Horario -->
                                <label class="block text-sm font-medium text-gray-700">
                                    <span class="font-bold text-gray-900">Horario de Acceso:</span> 
                                    <span class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs font-semibold mt-1">
                                        <?php echo $row['datetime_inicio'] . " <-> " . $row['datetime_fin']; ?>
                                    </span>
                                </label>
                            </div>

                            <button type="submit" name="ver_datos" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 shadow">
                                ABRIR PUERTA
                            </button>
                        </form>
                    </div>
                    <?php
                }
                ?>
            </div>
            <?php
        } else {
            ?>
            <div class="mensaje-container">
                <h2>Acceso Restringido</h2>
                <p>Usted ya no tiene accesos disponibles. Cualquier inconveneinte contactarse con el Anfitrion</p>
            </div>

            <a href="tel:993044808" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 shadow text-center">
                Llamar al 993 044 808
            </a>
            <?php
        }





//FIN VALIDACIONES
        ?>
    </body>