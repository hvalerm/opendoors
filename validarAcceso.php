<?php

//Declaracion de variables
$mi_rol = $_SESSION['id_tipoCuenta'];
//----------------------


if ($debug == 0)://Si el modo debug esta desactivado, prosigue en la verificacion

// 1. Proteger la ruta: si no hay sesión activa, regresa al login
    if (!isset($_SESSION['correo_cuenta'])) {
        header("Location: login.php");
        exit;
    }

// 2. Redireccion a pagina adecuada
    $roles_permitidos_huesped=[1];
    $roles_permitidos_personal=[2];
    $roles_permitidos_anfitrion=[2,3];
    $roles_permitidos_adminitrador=[2,3,4];
    
    if (in_array($mi_rol, $roles_permitidos_huesped)) {// Si es Huésped (ID 1), lo mandamos a su propia vista
        header("Location: ../ModoHuesped/vistaHuesped.php");
    } else if (in_array($mi_rol, $roles_permitidos_personal)) {// Si es Personal (ID 2), lo mandamos a su propia vista
        header("Location: ../ModoPersonal/vistaPersonal.php");
    } else if (in_array($mi_rol, $roles_permitidos_anfitrion)) {// Si es Anfitrion (ID 3), lo mandamos a su propia vista
        header("Location: ../ModoAnfitrion/vistaAnfitrion.php");
    } else if (in_array($mi_rol, $roles_permitidos_adminitrador)) {// Si es Administrador (ID 4), lo mandamos a su propia vista
        header("Location: ../ModoAdministrador/vistaAdministrador.php");
    } else {// Para cualquier otro rol desconocido
        header("Location: no_autorizado.php");
    }

endif;