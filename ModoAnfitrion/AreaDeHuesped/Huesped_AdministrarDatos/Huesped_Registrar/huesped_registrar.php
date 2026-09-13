<?php
require '../../../../db.php';
require '../../../../validarAcceso.php';
$error = '';
$paises = $pdo->query('SELECT id_pais, pais FROM Pais ORDER BY pais')->fetchAll(PDO::FETCH_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $credencial = $_POST['credencial'] ?? '';
    $confirmacion = $_POST['credencial_confirmacion'] ?? '';
    $pais = trim($_POST['pais'] ?? '');
    if ($dni === '' || $nombre === '' || $apellido === '' || $correo === '' || $credencial === '' || $confirmacion === '' || $pais === '') {
        $error = 'Todos los campos son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } elseif ($credencial !== $confirmacion) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO Cuenta (correo_cuenta, credencial_cuenta, id_tipoCuenta) VALUES (:correo, :credencial, 1)');
            $stmt->execute([':correo' => $correo, ':credencial' => password_hash($credencial, PASSWORD_ARGON2ID)]);
            $id_cuenta = $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO Huesped (dni_huesped, nombre_huesped, apellido_huesped, id_cuenta, id_pais) VALUES (:dni, :nombre, :apellido, :id_cuenta, :pais)');
            $stmt->execute([':dni' => $dni, ':nombre' => $nombre, ':apellido' => $apellido, ':id_cuenta' => $id_cuenta, ':pais' => $pais]);
            $pdo->commit();
            header('Location: huesped_registrar.php?registro=exitoso');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getCode() === '23000' ? 'El correo o DNI ya está registrado.' : 'No se pudo registrar el huésped.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Registrar Huésped</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 min-h-screen flex flex-col m-0">
<div class="w-full"><?php require '../../../../barraNav.php'; ?></div>
<div class="w-full px-4 pt-4"><a href="../../area_huesped.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al área de huéspedes</a></div>
<main class="flex-grow flex items-center justify-center px-4 py-10"><section class="bg-white p-8 rounded-xl shadow-md max-w-lg w-full">
<h1 class="text-2xl font-bold text-gray-800 text-center">Registrar Huésped</h1>
<?php if (isset($_GET['registro'])): ?><p class="mt-4 bg-green-100 text-green-700 p-3 rounded-lg text-center">Huésped registrado correctamente.</p><?php endif; ?>
<?php if ($error !== ''): ?><p class="mt-4 bg-red-100 text-red-700 p-3 rounded-lg text-center"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="POST" class="space-y-4 mt-6"><input type="text" name="dni" maxlength="8" pattern="[0-9]{8}" placeholder="DNI" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><input type="text" name="nombre" placeholder="Nombre" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><input type="text" name="apellido" placeholder="Apellido" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><input type="email" name="correo" placeholder="Correo electrónico" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><input type="password" name="credencial" placeholder="Contraseña" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><input type="password" name="credencial_confirmacion" placeholder="Confirmar contraseña" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><select name="pais" required class="w-full border border-gray-300 rounded-lg px-4 py-2"><option value="" disabled selected>Selecciona el país</option><?php foreach ($paises as $pais): ?><option value="<?php echo (int) $pais['id_pais']; ?>"><?php echo htmlspecialchars($pais['pais']); ?></option><?php endforeach; ?></select><button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-3 px-6 rounded-lg">Registrar Huésped</button></form>
</section></main></body></html>
