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
                        <th scope="col" class="px-6 py-4 font-semibold">Última consulta</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Última apertura</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody id="sensores-tabla" class="divide-y divide-white/70 text-sm">
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">Cargando sensores...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        const cuerpoTablaSensores = document.getElementById('sensores-tabla');
        let consultaEnCurso = false;

        async function actualizarSensores() {
            if (consultaEnCurso) {
                return;
            }

            consultaEnCurso = true;
            try {
                const respuesta = await fetch('api_estadoSensores.php', {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });

                if (!respuesta.ok) {
                    throw new Error('No se pudo cargar la lista de sensores.');
                }

                const sensores = await respuesta.json();
                cuerpoTablaSensores.replaceChildren();

                if (!sensores.length) {
                    const fila = document.createElement('tr');
                    const celda = document.createElement('td');
                    celda.colSpan = 4;
                    celda.className = 'px-6 py-8 text-center text-gray-500';
                    celda.textContent = 'No hay sensores registrados para este tipo.';
                    fila.appendChild(celda);
                    cuerpoTablaSensores.appendChild(fila);
                    return;
                }

                sensores.forEach((sensor) => {
                    const fila = document.createElement('tr');
                    fila.className = sensor.estado === 'Inhabilitado'
                        ? 'bg-red-100 text-red-950 hover:bg-red-200 transition-colors'
                        : 'bg-blue-100 text-blue-950 hover:bg-blue-200 transition-colors';

                    const local = document.createElement('td');
                    local.className = 'px-6 py-4 font-medium';
                    local.textContent = sensor.nombre_local;

                    const ultimaConsulta = document.createElement('td');
                    ultimaConsulta.className = 'px-6 py-4';
                    ultimaConsulta.textContent = sensor.consultado || 'Sin registro';

                    const ultimaApertura = document.createElement('td');
                    ultimaApertura.className = 'px-6 py-4';
                    ultimaApertura.textContent = sensor.ejecutado || 'Sin registro';

                    const estado = document.createElement('td');
                    estado.className = 'px-6 py-4 font-semibold';
                    estado.textContent = sensor.estado;

                    fila.append(local, ultimaConsulta, ultimaApertura, estado);
                    cuerpoTablaSensores.appendChild(fila);
                });
            } catch (error) {
                const fila = document.createElement('tr');
                const celda = document.createElement('td');
                celda.colSpan = 4;
                celda.className = 'bg-red-50 px-6 py-4 text-red-800';
                celda.textContent = 'No se pudo actualizar la lista de sensores.';
                fila.appendChild(celda);
                cuerpoTablaSensores.replaceChildren(fila);
            } finally {
                consultaEnCurso = false;
            }
        }

        actualizarSensores();
        window.setInterval(actualizarSensores, 1000);
    </script>

</body>

</html>