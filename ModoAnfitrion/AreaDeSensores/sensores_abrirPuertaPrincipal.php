<?php
require '../../db.php';
$rol_pagina = 3;
require '../../validarAcceso.php';
$modo = 'ANFITRION';
/*
Esto solo es para ver el estado de los sensores de abrir puertas principales.

Teniendo en cuenta que:
- La placa de sensor de abrir puerta principal es el valor de "1" en tipo entero
- Existen varios locales que tienen esta placa sensor
- Cada placa - local esta registrado en una bd con una tabla llamada Local_Placa, y aqui tiene los campos de id, id_local, id_placa, accionar y consultado (datetime)

Algoritmo:
- Consultar las tablas Local_Placa y Local (con inner join) donde se recuperen los campos de nombre_local y consultado (datetime)
- Crear un table HTML para mostrar los datos de los sensores que contiene el nombre_local y su estado.
- iterar sobre los resultados de la consulta y llenar la tabla HTML con los datos de cada sensor.
    * Verificar si la diferencia entre la fecha y hora actual y el campo consultado (datetime) es mayor a un umbral determinado (por ejemplo, 15 segundos) para determinar si el sensor está activo o no, mostrando Habilitado o inhabilitado.

*/
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sensor de: Abrir Puerta Principal</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-gray-50">

    <?php require '../../barraNav.php'; ?>

    <main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
        <div class="mb-6 border-b border-gray-200 pb-5">
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">
                Sensor de: Abrir Puerta Principal
            </h1>
            <p class="mt-2 text-sm text-gray-600">Estado de conexión por local</p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
            <table class="w-full min-w-[32rem] border-collapse text-left">
                <thead class="bg-gray-800 text-sm uppercase tracking-wide text-white">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">Nombre del local</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/70 text-sm">

                    <?php
                    try {
                        $query = "SELECT l.nombre_local, lp.consultado FROM Local_Placa lp INNER JOIN Local l ON lp.id_local = l.id_local WHERE lp.id_placa = 1";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        $query2 = "SELECT NOW() AS fecha_actual";
                        $stmt = $pdo->prepare($query2);
                        $stmt->execute();
                        $result2 = $stmt->fetch(PDO::FETCH_ASSOC);
                        $ahora = strtotime($result2['fecha_actual']);
                        $umbral = 15; // segundos

                        foreach ($result as $fila) {
                            if ($fila['consultado'] === null) {
                                $estado = 'Inhabilitado';
                            } else {
                                $consultado = strtotime($fila['consultado']);
                                $estado = ($ahora - $consultado > $umbral) ? 'Inhabilitado' : 'Habilitado';
                            }

                            $claseFila = $estado === 'Inhabilitado'
                                ? 'bg-red-100 text-red-950 hover:bg-red-200'
                                : 'bg-blue-100 text-blue-950 hover:bg-blue-200';

                    ?>
                            <tr class="<?php echo $claseFila; ?> transition-colors">
                                <td class="px-6 py-4 font-medium">
                                    <?php echo htmlspecialchars($fila['nombre_local'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 font-semibold">
                                    <?php echo htmlspecialchars($estado, ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                    <?php

                            // Cada $fila es un array asociativo con los datos de un registro
                            // Los datos ya se están mostrando en la tabla HTML, por lo que este bloque es redundante y puede eliminarse.
                        }
                    } catch (Exception $e) {
                        echo '<tr><td colspan="2" class="bg-red-50 px-6 py-4 text-red-800">No se pudo cargar la lista de sensores.</td></tr>';
                    }

                    if (isset($result) && empty($result)) {
                        echo '<tr><td colspan="2" class="px-6 py-8 text-center text-gray-500">No hay sensores registrados para este tipo.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </main>

</body>

</html>