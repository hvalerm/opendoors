<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso denegado</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
        <main class="w-full max-w-lg">
            <section class="bg-white border border-gray-200 rounded-2xl shadow-lg p-8 sm:p-10 text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86l-7.1 12.28A2 2 0 004.92 19h14.16a2 2 0 001.73-2.86l-7.1-12.28a2 2 0 00-3.42 0z"></path>
                    </svg>
                </div>

                <p class="text-sm font-semibold uppercase tracking-widest text-red-600">Acceso restringido</p>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900">Acceso denegado</h1>
                <p class="mt-4 text-base leading-7 text-gray-600">
                    OCURRIO UN PROBLEMA!!!: No cuenta con los permisos necesarios para consultar esta sección. Por motivos de seguridad, cierre la sesión y vuelva a iniciar sesión con una cuenta autorizada.
                </p>

                <a href="https://opendoors.onrender.com/logout.php" class="mt-8 inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Volver a iniciar sesión
                </a>
            </section>
        </main>
    </body>
</html>
