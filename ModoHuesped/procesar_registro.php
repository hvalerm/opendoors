<?php
session_start();

require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Recoger y limpiar los datos del formulario
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $credencial = $_POST['credencial'] ?? '';
    $id_pais = trim($_POST['pais'] ?? '');
    
    
    
    // Validar que ningún campo esté vacío
    if (empty($dni) || empty($nombre) || empty($apellido) || empty($correo) || empty($password_plano) || empty($id_pais)) {
        die("Error: Todos los campos son obligatorios.");
    }
    
    // Validar formato del correo electrónico
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        die("Error: El formato del correo electrónico no es válido.");
    }
    
    // Encriptar la contraseña de forma segura utilizando Argon2id
    $password_hash = password_hash($password_plano, PASSWORD_ARGON2ID);
    
    $sql = "INSERT INTO Cuenta (correo_cuenta, credencial_cuenta, id_tipoCuenta)
        VALUES (:correo, :credencial, 1)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':correo' => $correo,
        ':credencial' => $credencial
    ]);
    
    
    $sql ="SELECT id_cuenta FROM Cuenta where correo_cuenta=:correo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':correo' => $correo
    ]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    $id_cuenta=$r['id_cuenta'];
    
    
    
    $sql = "INSERT INTO Huesped (dni_huesped, nombre_huesped, apellido_huesped, id_cuenta, id_pais)
            VALUES (:dni, :nombre, :apellido, :idCuenta, :idPais)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dni' => $dni,
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':idCuenta' => $id_cuenta,
        ':idPais' => $id_pais
    ]);
    
}