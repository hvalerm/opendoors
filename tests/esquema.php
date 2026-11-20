<?php
// Ejecutar: php tests\esquema.php [puerto MariaDB aislado; por defecto 13316].
// Requiere PHP con pdo_mysql, openssl y mbstring, y un servidor local de pruebas
// con usuario root sin clave. Crea y elimina solo una BD opendoors_test_* propia.
// La copia temporal sustituye conexion y correo: no usa util/db.php ni envia emails.
declare(strict_types=1);

date_default_timezone_set('America/Guayaquil');
$root = dirname(__DIR__);
$port = filter_var($argv[1] ?? '13316', FILTER_VALIDATE_INT);
if (!$port || $port < 1024 || $port > 65535) {
    throw new InvalidArgumentException('Puerto de pruebas no valido.');
}
$db = 'opendoors_test_' . bin2hex(random_bytes(6));
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db;
$pdo = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server = null;
$created = false;
$checks = 0;
function comprobar(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

try {
    $pdo->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo->exec("USE `$db`");
    $delimiter = ';';
    $sql = '';
    foreach (file($root . DIRECTORY_SEPARATOR . 'bd_ultimo.sql') as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)/', $line, $m)) {
            $delimiter = $m[1];
            continue;
        }
        if (preg_match('/^\s*(--|$)/', $line)) {
            continue;
        }
        $sql .= $line;
        if (str_ends_with(rtrim($sql), $delimiter)) {
            $pdo->exec(substr(rtrim($sql), 0, -strlen($delimiter)));
            $sql = '';
        }
    }
    comprobar(trim($sql) === '', 'El esquema no se importo completamente.');
    $pdo->exec("INSERT INTO cargos (nombreCargo) VALUES ('Anfitrion')");
    $pdo->exec("INSERT INTO empresas (nombreEmpresa, duenoEmpresa, licenciaExpira, habilitado) VALUES
        ('EmpresaAlpha', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
        ('EmpresaBeta', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
        ('EmpresaInactiva', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 0),
        ('EmpresaVencida', 'Pruebas', DATE_SUB(NOW(), INTERVAL 1 DAY), 1)");
    $insert = $pdo->prepare('INSERT INTO cuentas (correoCuenta, nombreCuenta, pwd, habilitado) VALUES (?, ?, ?, ?)');
    foreach (range(1, 7) as $id) {
        $insert->execute(["cuenta$id@example.test", "Cuenta$id", password_hash('Prueba12345', PASSWORD_DEFAULT), $id === 5 ? 0 : 1]);
    }
    $pdo->exec('INSERT INTO empresas_cuentas (idEmpresa, idCuenta, idCargo) VALUES
        (1,1,1), (2,2,1), (1,3,1), (2,3,1), (1,5,1), (3,6,1), (4,7,1)');
    $pdo->exec("INSERT INTO locales (nombreLocal, direccionLocal, idEmpresa) VALUES
        ('LocalAlpha', 'DireccionAlpha', 1), ('LocalBeta', 'DireccionBeta', 2),
        ('LocalAlphaDos', 'Otra direccion', 1)");
    $pdo->exec('INSERT INTO microcontroladores (idLocal, idEmpresa) VALUES (1,1), (2,2)');
    $insertarAcceso = $pdo->prepare('INSERT INTO accesos (activoDesde, activoHasta, idLocal, idEmpresa, token)
        VALUES (?, ?, ?, ?, ?)');
    comprobar($pdo->query('SELECT zonaHoraria FROM locales WHERE idLocal = 1')->fetchColumn() === 'America/Guayaquil',
        'El esquema nuevo no asigna la zona inicial.');
    $pdo->exec('ALTER TABLE locales DROP COLUMN zonaHoraria');
    $ahora = time();
    $desde = gmdate('Y-m-d H:i:s', $ahora - 3600);
    $hasta = gmdate('Y-m-d H:i:s', $ahora + 86400);
    $desdeAnterior = gmdate('Y-m-d H:i:s', $ahora - 3600 - 5 * 3600);
    $hastaAnterior = gmdate('Y-m-d H:i:s', $ahora + 86400 - 5 * 3600);
    $insertarAcceso->execute([$desdeAnterior, $hastaAnterior, 1, 1, 'TokenAlpha']);
    $insertarAcceso->execute([$desdeAnterior, $hastaAnterior, 2, 2, 'TokenBeta']);
    $insertarAcceso->execute([$desdeAnterior, $hastaAnterior, 1, 1, 'TokenAlphaDos']);
    unset($insertarAcceso);

    $migracion = file($root . DIRECTORY_SEPARATOR . 'migraciones' . DIRECTORY_SEPARATOR . 'vigencia_utc_por_local.sql');
    $sql = implode('', array_filter($migracion, fn($linea) => !str_starts_with($linea, '--')));
    $sentencias = array_values(array_filter(array_map('trim', explode(';', $sql))));
    foreach ($sentencias as $sentencia) {
        $pdo->exec($sentencia);
    }
    // Simular que se retoma la migración después de confirmar la transacción.
    foreach (array_slice($sentencias, 1) as $sentencia) {
        $pdo->exec($sentencia);
    }
    $migrados = $pdo->query('SELECT activoDesde, activoHasta FROM accesos')->fetchAll();
    comprobar(count($migrados) === 3 && $migrados === array_fill(0, 3, ['activoDesde' => $desde, 'activoHasta' => $hasta]),
        'La migración no conserva la vigencia de todos los accesos o los convierte dos veces.');
    comprobar((int) $pdo->query("SELECT COUNT(*) FROM locales WHERE zonaHoraria = 'America/Guayaquil'")->fetchColumn() === 3,
        'La migración no asigna la zona inicial a todos los locales.');
    $pdo->exec("DELETE FROM accesos WHERE token = 'TokenAlphaDos'");

    mkdir($tmp);
    $app = $tmp . DIRECTORY_SEPARATOR . 'app';
    mkdir($app);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $normalized = str_replace('\\', '/', $relative);
        if ($file->getExtension() !== 'php' || str_starts_with($normalized, '.git/')
            || str_starts_with($normalized, 'tests/') || $normalized === 'util/db.php') {
            continue;
        }
        $target = $app . DIRECTORY_SEPARATOR . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        copy($file->getPathname(), $target);
    }
    $connection = "<?php\nrequire_once __DIR__ . '/seguridad.php';\ndate_default_timezone_set('America/Guayaquil');\n"
        . "function getPDO(): PDO { static \$pdo; return \$pdo ??= new PDO("
        . var_export("mysql:host=127.0.0.1;port=$port;dbname=$db;charset=utf8mb4", true)
        . ", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]); }\n";
    file_put_contents($app . DIRECTORY_SEPARATOR . 'util' . DIRECTORY_SEPARATOR . 'db.php', $connection);
    $mailFile = $tmp . DIRECTORY_SEPARATOR . 'mail.txt';
    file_put_contents($app . DIRECTORY_SEPARATOR . 'util' . DIRECTORY_SEPARATOR . 'correo.php',
        "<?php\nfunction smtpConfigurado(): bool { return true; }\n"
        . 'function enviarCorreo($destino, $asunto, $texto): bool { file_put_contents('
        . var_export($mailFile, true) . ', $texto); return true; }');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($socket === false) {
        throw new RuntimeException($errstr);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $base = 'http://' . $address;
    $log = $tmp . DIRECTORY_SEPARATOR . 'php.log';
    $sessions = $tmp . DIRECTORY_SEPARATOR . 'sessions';
    mkdir($sessions);
    $env = getenv();
    $env['OPENDOORS_ENC_KEY'] = 'clave-exclusiva-de-pruebas';
    $server = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'session.save_path=' . $sessions,
        '-S', $address, '-t', $app, __DIR__ . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $app, $env);
    if (!is_resource($server)) {
        throw new RuntimeException('No se pudo iniciar el servidor PHP de pruebas.');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $errstr, 0.1);
        if ($probe !== false) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    comprobar($ready, 'El servidor de pruebas no responde.');

    $cookies = [];
    $request = function (string $path, ?array $post = null) use ($base, &$cookies): array {
        $headers = ['User-Agent: OpenDoorsSchemaTest'];
        if ($cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(
                fn($key, $value) => "$key=$value", array_keys($cookies), $cookies));
        }
        if ($post !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(['http' => [
            'method' => $post === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $post === null ? '' : http_build_query($post),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
        ]]);
        $body = file_get_contents($base . '/' . $path, false, $context);
        if ($body === false) {
            throw new RuntimeException("Fallo HTTP: $path");
        }
        $status = 0;
        $location = '';
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+ (\d+)/', $header, $m)) $status = (int) $m[1];
            if (preg_match('/^Location:\s*(.*)/i', $header, $m)) $location = $m[1];
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $m)) $cookies[$m[1]] = $m[2];
        }
        comprobar(!preg_match('/(Warning:|Fatal error:|SQLSTATE|Undefined array key)/', $body), "Error PHP/SQL en $path");
        return ['status' => $status, 'location' => $location, 'body' => $body];
    };
    $csrf = function (array $response): string {
        if (!preg_match('/name="csrf" value="([^"]+)"/', $response['body'], $m)) {
            throw new RuntimeException('No se encontro el CSRF.');
        }
        return $m[1];
    };
    $login = function (int $id, string $password = 'Prueba12345') use ($request, $csrf, &$cookies): array {
        $cookies = [];
        return $request('login.php', ['usuario' => "cuenta$id@example.test", 'password' => $password,
            'csrf' => $csrf($request('login.php'))]);
    };

    require_once $root . DIRECTORY_SEPARATOR . 'util' . DIRECTORY_SEPARATOR . 'intentos_login.php';
    comprobar(registrarFallo($pdo, 'fallo@example.test') === LOGIN_MAX_INTENTOS - 1, 'No registra el primer fallo.');
    for ($i = 1; $i < LOGIN_MAX_INTENTOS; $i++) registrarFallo($pdo, 'fallo@example.test');
    comprobar(segundosBloqueado($pdo, 'fallo@example.test') > 0, 'No bloquea los intentos de login.');
    limpiarIntentos($pdo, 'fallo@example.test');
    comprobar(segundosBloqueado($pdo, 'fallo@example.test') === 0, 'No limpia los intentos.');
    comprobar(str_contains($login(1, 'incorrecta')['body'], 'incorrectos'), 'No rechaza la clave incorrecta.');
    comprobar(str_contains($login(3)['body'], 'varias empresas'), 'No bloquea una cuenta multempresa.');
    comprobar(str_contains($login(4)['body'], 'no tiene una empresa'), 'No informa la falta de vinculo.');
    $r = $login(5);
    comprobar($r['status'] === 200 && str_contains($r['body'], 'incorrectos'), 'Permite la cuenta deshabilitada.');
    comprobar(str_contains($login(6)['body'], 'deshabilitada'), 'Permite la empresa deshabilitada.');
    comprobar($login(7)['location'] === 'licencia_expirada', 'No detecta la licencia vencida.');
    comprobar($login(1)['location'] === 'ModoAnfitrion/vistaAnfitrion', 'Fallo en login con vinculo unico.');

    $pages = [
        'ModoAnfitrion/vistaAnfitrion.php',
        'ModoAnfitrion/OpenDoors/Dashboard/resumen.php',
        'ModoAnfitrion/OpenDoors/Locales/localSeleccionado.php?id=1',
        'ModoAnfitrion/OpenDoors/Accesos/SeleccionarLocalAcceso.php',
        'ModoAnfitrion/OpenDoors/Accesos/localSeleccionado.php?id=1',
        'ModoAnfitrion/OpenDoors/Accesos/CrearAccesoLocal.php?id=1',
        'ModoAnfitrion/OpenDoors/Accesos/listarAccesos.php',
        'ModoAnfitrion/OpenDoors/Accesos/busquedaAccesoRevocar.php',
        'ModoAnfitrion/OpenDoors/Accesos/vetanaDelAcceso.php?id=1',
        'ModoAnfitrion/OpenDoors/Accesos/revocarAcceso.php?id=1',
        'ModoAnfitrion/OpenDoors/Microcontroladores/verEstado.php',
        'ModoAnfitrion/OpenDoors/Microcontroladores/verActividad.php',
        'ModoAnfitrion/OpenDoors/puertaAccionada.php?id=1',
    ];
    foreach ($pages as $page) {
        $r = $request($page);
        comprobar($r['status'] === 200 && str_contains($r['body'], 'LocalAlpha'), "No carga datos: $page");
        comprobar(!str_contains($r['body'], 'LocalBeta') && !str_contains($r['body'], 'EmpresaBeta'), "Mezcla empresas: $page");
    }
    foreach (['Locales/localSeleccionado.php?id=2', 'Accesos/CrearAccesoLocal.php?id=2',
        'Accesos/vetanaDelAcceso.php?id=2', 'Accesos/revocarAcceso.php?id=2', 'puertaAccionada.php?id=2'] as $page) {
        comprobar(!str_contains($request('ModoAnfitrion/OpenDoors/' . $page)['body'], 'LocalBeta'), "Permite un ID ajeno: $page");
    }
    $create = 'ModoAnfitrion/OpenDoors/Accesos/CrearAccesoLocal.php?id=1';
    $r = $request($create, ['csrf' => $csrf($request($create)),
        'activo_desde' => date('Y-m-d\TH:i', time() - 3600), 'activo_hasta' => date('Y-m-d\TH:i', time() + 86400)]);
    comprobar((int) $pdo->query('SELECT COUNT(*) FROM accesos WHERE idEmpresa=1')->fetchColumn() === 2, 'No crea el acceso.');
    $edit = 'ModoAnfitrion/OpenDoors/Accesos/vetanaDelAcceso.php?id=1';
    $token = $csrf($request($edit));
    $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '3',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    comprobar((int) $pdo->query('SELECT idLocal FROM accesos WHERE idAcceso=1')->fetchColumn() === 3, 'No actualiza el acceso.');
    $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '1',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    $r = $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '2',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    comprobar(str_contains($r['body'], 'El local seleccionado no es válido.')
        && (int) $pdo->query('SELECT idLocal FROM accesos WHERE idAcceso=1')->fetchColumn() === 1,
        'Permite mover el acceso a otra empresa.');
    $revoke = 'ModoAnfitrion/OpenDoors/Accesos/revocarAcceso.php?id=1';
    $request($revoke, ['csrf' => $csrf($request($revoke))]);
    comprobar((int) $pdo->query('SELECT habilitado FROM accesos WHERE idAcceso=1')->fetchColumn() === 0, 'No revoca el acceso.');
    $r = $request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha']);
    comprobar($r['status'] === 403, 'Permite un acceso revocado.');
    $request($edit, ['csrf' => $token, 'accion' => 'toggle']);

    $r = $request('accesoPuerta.php', ['accion' => 'validar', 'token' => 'TokenAlpha']);
    comprobar(json_decode($r['body'], true)['valido'] === true, 'No valida el enlace publico.');
    $r = $request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha', 'confirmar' => '1']);
    comprobar($r['status'] === 303, 'No emite la orden por enlace.');
    $confirmation = $r['location'];
    comprobar((int) $pdo->query('SELECT ejecutado FROM accesos WHERE idAcceso=1')->fetchColumn() === 1, 'No consume el enlace.');
    comprobar((int) $pdo->query('SELECT cantidadAperturas FROM microcontroladores WHERE idMicrocontrolador=1')->fetchColumn() === 1, 'El trigger no contabiliza una unica apertura.');
    comprobar($request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha'])['status'] === 404, 'Reutiliza el enlace.');
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt('1', 'aes-256-cbc', hash('sha256', $env['OPENDOORS_ENC_KEY'], true), OPENSSL_RAW_DATA, $iv);
    $package = $iv . pack('v', strlen($encrypted)) . $encrypted;
    $cipher = bin2hex(str_pad($package, 500, "\0"));
    $r = $request('ApiSystem/api_board.php?cifrado=' . $cipher);
    comprobar(json_decode($r['body'], true) === ['ok' => true, 'accionar' => 1], 'El dispositivo no recibe la orden.');
    comprobar((int) $pdo->query('SELECT cantidadAperturas FROM microcontroladores WHERE idMicrocontrolador=1')->fetchColumn() === 1, 'La API duplica el contador del trigger.');
    $r = $request('ApiSystem/api_board.php?cifrado=' . $cipher);
    comprobar(json_decode($r['body'], true) === ['ok' => true, 'accionar' => 0], 'No limpia la orden de apertura.');
    $r = $request('ModoAnfitrion/OpenDoors/' . basename($confirmation) . '&estado=1');
    comprobar(json_decode($r['body'], true) === ['accionar' => 0], 'No confirma la apertura del huesped.');
    $local = 'ModoAnfitrion/OpenDoors/Locales/localSeleccionado.php?id=1';
    $token = $csrf($request($local));
    comprobar($request($local, ['csrf' => $token, 'accion' => 'abrir_puerta'])['status'] === 302, 'No abre desde el panel.');
    $request($local, ['csrf' => $token, 'accion' => 'anular_apertura']);
    comprobar((int) $pdo->query('SELECT accionar FROM locales WHERE idLocal=1')->fetchColumn() === 0, 'No anula la apertura.');

    // Un segundo vinculo invalida tambien una sesion abierta, no solo el login.
    $pdo->exec('INSERT INTO empresas_cuentas (idEmpresa, idCuenta, idCargo) VALUES (2,1,1)');
    comprobar($request('ModoAnfitrion/OpenDoors/Dashboard/resumen.php')['status'] === 302, 'La sesion abierta admite multiples empresas.');
    $pdo->exec('DELETE FROM empresas_cuentas WHERE idEmpresa=2 AND idCuenta=1');

    comprobar($login(1)['status'] === 302, 'No restablece el ingreso con un solo vinculo.');
    $pdo->exec('DELETE FROM empresas_cuentas WHERE idEmpresa=1 AND idCuenta=1');
    comprobar($request('ModoAnfitrion/OpenDoors/Accesos/listarAccesos.php')['status'] === 302, 'Mantiene la sesion sin vinculo.');
    $pdo->exec('INSERT INTO empresas_cuentas (idEmpresa, idCuenta, idCargo) VALUES (1,1,1)');

    $cookies = [];
    $pdo->exec("INSERT INTO recuperacion_password (idCuenta, codigoHash, expira, ultimoEnvio)
        VALUES (1, 'codigo-anterior', DATE_SUB(NOW(), INTERVAL 1 MINUTE), DATE_SUB(NOW(), INTERVAL 2 MINUTE))");
    $r = $request('recuperar_password.php');
    $r = $request('recuperar_password.php', ['csrf' => $csrf($r), 'correo' => 'cuenta1@example.test']);
    comprobar($r['location'] === 'verificar_codigo', 'No inicia la recuperacion.');
    comprobar(preg_match('/\b(\d{10})\b/', file_get_contents($mailFile), $m) === 1, 'No genera el codigo.');
    $r = $request('verificar_codigo.php', ['csrf' => $csrf($request('verificar_codigo.php')), 'codigo' => $m[1]]);
    comprobar($r['location'] === 'nueva_password', 'No verifica el codigo.');
    $r = $request('nueva_password.php', ['csrf' => $csrf($request('nueva_password.php')),
        'password' => 'NuevaPrueba12345', 'password2' => 'NuevaPrueba12345']);
    comprobar($r['location'] === 'login?cambiada=1', 'No actualiza la clave.');
    comprobar($login(1, 'NuevaPrueba12345')['status'] === 302, 'No permite ingresar con la clave recuperada.');
    comprobar((int) $pdo->query('SELECT COUNT(*) FROM recuperacion_password')->fetchColumn() === 0, 'No consume el codigo.');
    comprobar((int) $pdo->query('SELECT COUNT(*) FROM logs_empresas_cuentas')->fetchColumn() > 0, 'No registra los vinculos.');
    comprobar(!preg_match('/(SQLSTATE|PHP Warning|PHP Fatal|PHP Notice)/', file_get_contents($log)), 'Hay errores en el log PHP.');
    echo "OK: $checks comprobaciones con bd_ultima.sql y sus triggers.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if ($created) $pdo->exec("DROP DATABASE `$db`");
    if (is_dir($tmp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($tmp);
    }
}
