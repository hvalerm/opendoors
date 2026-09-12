<?php

$modo;
$correo_cuenta = $_SERVER['correo_cuenta'] ?? '';
$nombre_usuario = "nombre";
$apellido_usuario = "apellido";


    if ($mi_rol >= 1 && $mi_rol <= 4) {

        //Establece el tipo de modo (Huesped, Personal, Anfitrion o Administrador)
        //Y Estructura la consulta sql para extraer datos
        switch ($mi_rol) {
            case 1:
                $modo = "HUESPED";
                $sql = "select H.nombre_huesped as nombre, H.apellido_huesped as apellido from Huesped H inner join Cuenta C on H.id_cuenta = C.id_cuenta where C.correo_cuenta = :correo_cuenta";
                break;

            case 2:
                $modo = "PERSONAL";
                $sql = "select P.nombre_personal as nombre, P.apellido_personal as apellido from Personal P inner join Cuenta C on P.id_cuenta = C.id_cuenta where C.correo_cuenta = :correo_cuenta";
                break;

            case 3:
                $modo = "ANFITRION";
                $sql = "";
                break;

            case 4:
                $modo = "ADMINISTRADOR";
                $sql = "";
                break;
        }

        //Inicia la extracción de datos como nombre y apellido
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':correo_cuenta' => $correo_cuenta]);
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($datos) {
            $nombre_usuario = $datos['nombre'];
            $apellido_usuario = $datos['apellido'];
        }
    } else {
        $modo = "DESCONOCIDO (ERROR)";
    }

?>


<!-- Barra de Navegación -->
<nav class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">
            <div class="flex-shrink-0 flex items-center">
                <!-- Icono o Logo del sistema -->
                <span class="text-xl font-bold text-blue-600 tracking-wide"><?php echo $modo; ?></span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 hidden sm:inline-block">
                    Hola, <span class="font-semibold text-gray-800"><?php echo $nombre_usuario . " " . $apellido_usuario; ?></span>
                </span>
                <a href="../logout.php" class="text-sm font-medium text-red-600 hover:text-red-800 transition-colors bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</nav>
