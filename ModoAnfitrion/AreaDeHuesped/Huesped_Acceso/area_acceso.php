<?php
require '../../../db.php';
$rol_pagina = 3;
require '../../../validarAcceso.php';

$error = '';
$exito = isset($_GET['programado']);
$huespedes = [];
$locales = [];

try {
	$stmt = $pdo->query('SELECT H.id_huesped, H.dni_huesped, H.nombre_huesped, H.apellido_huesped, C.correo_cuenta
						 FROM Huesped H
						 INNER JOIN Cuenta C ON C.id_cuenta = H.id_cuenta
						 WHERE C.id_EstadoCuenta = 1
						 ORDER BY H.apellido_huesped, H.nombre_huesped');
	$huespedes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $pdo->query('SELECT id_local, nombre_local, direccion_local FROM Local ORDER BY nombre_local');
	$locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
	$error = 'No se pudieron cargar los huéspedes o locales.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id_huesped = (int) ($_POST['id_huesped'] ?? 0);
	$id_local = (int) ($_POST['id_local'] ?? 0);
	$fecha_inicio = $_POST['fecha_inicio'] ?? '';
	$hora_inicio = $_POST['hora_inicio'] ?? '';
	$fecha_fin = $_POST['fecha_fin'] ?? '';
	$hora_fin = $_POST['hora_fin'] ?? '';

	$datetime_inicio = "$fecha_inicio $hora_inicio:00";
	$datetime_fin = "$fecha_fin $hora_fin:00";

	if ($id_huesped <= 0 || $id_local <= 0 || $fecha_inicio === '' || $hora_inicio === '' || $fecha_fin === '' || $hora_fin === '') {
		$error = 'Todos los campos son obligatorios.';
	} elseif (strtotime($datetime_inicio) === false || strtotime($datetime_fin) === false || strtotime($datetime_fin) <= strtotime($datetime_inicio)) {
		$error = 'La fecha y hora de fin deben ser posteriores al inicio.';
	} else {
		try {
			$stmt = $pdo->prepare('INSERT INTO Acceso (id_huesped, id_local, datetime_inicio, datetime_fin, ejecutado)
								   VALUES (:id_huesped, :id_local, :datetime_inicio, :datetime_fin, 0)');
			$stmt->execute([
				':id_huesped' => $id_huesped,
				':id_local' => $id_local,
				':datetime_inicio' => $datetime_inicio,
				':datetime_fin' => $datetime_fin
			]);
			header('Location: area_acceso.php?programado=1');
			exit;
		} catch (PDOException $exception) {
			$error = $exception->getCode() === '23000'
				? 'El acceso indicado ya existe o contiene datos duplicados.'
				: 'No se pudo programar el acceso.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="es">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Programar Acceso</title>
		<script src="https://cdn.tailwindcss.com"></script>
	</head>
	<body class="bg-gray-50 min-h-screen flex flex-col">
		<div class="w-full">
			<?php require '../../../barraNav.php'; ?>
		</div>
		<div class="w-full px-4 pt-4">
			<a href="../../../vistaAnfitrion.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg">&larr; Regresar al panel anfitrión</a>
		</div>

		<main class="max-w-5xl mx-auto w-full px-4 py-10">
			<section class="bg-white rounded-xl shadow-md border border-gray-100 p-8">
				<h1 class="text-3xl font-bold text-gray-800 text-center">Programar Acceso</h1>
				<?php if ($exito): ?><p class="mt-5 bg-green-100 text-green-700 p-3 rounded-lg text-center">Acceso programado correctamente.</p><?php endif; ?>
				<?php if ($error !== ''): ?><p class="mt-5 bg-red-100 text-red-700 p-3 rounded-lg text-center"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

				<form method="POST" class="mt-8 space-y-8">
					<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
						<div>
							<h2 class="text-lg font-semibold text-gray-800 mb-3">Seleccionar huésped</h2>
							<input type="search" id="filtro_huesped" placeholder="Buscar por nombre, apellido o DNI" class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-3">
							<select name="id_huesped" id="lista_huespedes" required size="6" class="w-full border border-gray-300 rounded-lg px-4 py-2">
								<?php foreach ($huespedes as $huesped): ?>
									<option value="<?php echo (int) $huesped['id_huesped']; ?>" data-busqueda="<?php echo htmlspecialchars(strtolower($huesped['nombre_huesped'] . ' ' . $huesped['apellido_huesped'] . ' ' . $huesped['dni_huesped'])); ?>">
										<?php echo htmlspecialchars($huesped['nombre_huesped'] . ' ' . $huesped['apellido_huesped'] . ' - DNI: ' . $huesped['dni_huesped']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div>
							<h2 class="text-lg font-semibold text-gray-800 mb-3">Seleccionar local</h2>
							<input type="search" id="filtro_local" placeholder="Buscar por nombre del local" class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-3">
							<select name="id_local" id="lista_locales" required size="6" class="w-full border border-gray-300 rounded-lg px-4 py-2">
								<?php foreach ($locales as $local): ?>
									<option value="<?php echo (int) $local['id_local']; ?>" data-busqueda="<?php echo htmlspecialchars(strtolower($local['nombre_local'])); ?>">
										<?php echo htmlspecialchars($local['nombre_local'] . ' - ' . $local['direccion_local']); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>

					<div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-gray-200 pt-6">
						<fieldset class="border border-gray-200 rounded-lg p-4">
							<legend class="px-2 font-semibold text-gray-800">Inicio</legend>
							<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
								<label class="text-sm text-gray-600">Fecha<input type="date" name="fecha_inicio" required class="block w-full border border-gray-300 rounded-lg px-3 py-2 mt-1"></label>
								<label class="text-sm text-gray-600">Hora<input type="time" name="hora_inicio" required class="block w-full border border-gray-300 rounded-lg px-3 py-2 mt-1"></label>
							</div>
						</fieldset>
						<fieldset class="border border-gray-200 rounded-lg p-4">
							<legend class="px-2 font-semibold text-gray-800">Fin</legend>
							<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-2">
								<label class="text-sm text-gray-600">Fecha<input type="date" name="fecha_fin" required class="block w-full border border-gray-300 rounded-lg px-3 py-2 mt-1"></label>
								<label class="text-sm text-gray-600">Hora<input type="time" name="hora_fin" required class="block w-full border border-gray-300 rounded-lg px-3 py-2 mt-1"></label>
							</div>
						</fieldset>
					</div>

					<button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg shadow">Programar acceso</button>
				</form>
			</section>
		</main>
		<script>
			function filtrarOpciones(inputId, selectId) {
				const filtro = document.getElementById(inputId).value.toLowerCase().trim();
				document.querySelectorAll(`#${selectId} option`).forEach((opcion) => {
					opcion.hidden = !opcion.dataset.busqueda.includes(filtro);
				});
			}

			document.getElementById('filtro_huesped').addEventListener('input', () => filtrarOpciones('filtro_huesped', 'lista_huespedes'));
			document.getElementById('filtro_local').addEventListener('input', () => filtrarOpciones('filtro_local', 'lista_locales'));
		</script>
	</body>
</html>