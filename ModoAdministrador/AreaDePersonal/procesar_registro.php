<?php
require '../../db.php';
require '../../validarAcceso.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registrarPersonal.php');
    exit;
}

$dni = trim($_POST['dni'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$credencial = $_POST['credencial'] ?? '';
$credencial_confirmacion = $_POST['credencial_confirmacion'] ?? '';

if (empty($dni) || empty($nombre) || empty($apellido) || empty($correo) || empty($credencial) || empty($credencial_confirmacion)) {
    die('Error: Todos los campos son obligatorios.');
}

if ($credencial !== $credencial_confirmacion) {
    die('Error: Las contraseñas no coinciden.');
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    die('Error: El formato del correo electrónico no es válido.');
}

try {
    $pdo->beginTransaction();

    $credencial_hash = password_hash($credencial, PASSWORD_ARGON2ID);
    $sql = "INSERT INTO Cuenta (correo_cuenta, credencial_cuenta, id_tipoCuenta)
            VALUES (:correo, :credencial, 2)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':correo' => $correo,
        ':credencial' => $credencial_hash
    ]);

    $id_cuenta = $pdo->lastInsertId();

    $sql = "INSERT INTO Personal (dni_personal, nombre_personal, apellido_personal, id_cuenta)
            VALUES (:dni, :nombre, :apellido, :id_cuenta)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dni' => $dni,
        ':nombre' => $nombre,
        ':apellido' => $apellido,
        ':id_cuenta' => $id_cuenta
    ]);

    $pdo->commit();
    header('Location: registrarPersonal.php?registro=exitoso');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getCode() === '23000') {
        die('Error: El correo o DNI ya está registrado.');
    }

    die('Error al registrar el personal.');
}
