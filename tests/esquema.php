<?php
// Ejecutar: php tests\esquema.php [puerto MariaDB aislado; por defecto 13316].
// Requiere PHP con pdo_mysql, mysqli, openssl y mbstring, y un servidor local de pruebas
// con TLS y usuario root sin clave. Crea y elimina solo dos BD opendoors_test_* propias.
// Usa las conexiones reales de empresas y OpenDoors; no envia emails.
declare(strict_types=1);

date_default_timezone_set('America/Guayaquil');
$root = dirname(__DIR__);
$port = filter_var($argv[1] ?? '13316', FILTER_VALIDATE_INT);
if (!$port || $port < 1024 || $port > 65535) {
    throw new InvalidArgumentException('Puerto de pruebas no valido.');
}
$db = 'opendoors_test_' . bin2hex(random_bytes(6));
$dbPuertas = $db . '_modulo';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $db;
$pdo = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server = null;
$created = false;
$createdPuertas = false;
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
    $pdo->exec("CREATE DATABASE `$dbPuertas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $createdPuertas = true;
    foreach (['opendoors' => $dbPuertas, 'empresas' => $db] as $esquema => $destino) {
        $pdo->exec("USE `$destino`");
        $pdo->exec("SET time_zone = '" . ($esquema === 'empresas' ? '-05:00' : '+00:00') . "'");
        $delimiter = ';';
        $sql = '';
        foreach (file($root . DIRECTORY_SEPARATOR . 'bds' . DIRECTORY_SEPARATOR . $esquema . '.sql') as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)/', $line, $m)) {
                $delimiter = $m[1];
                continue;
            }
            if (preg_match('/^\s*(--|$|CREATE DATABASE|USE )/i', $line)) {
                continue;
            }
            $sql .= str_replace('opendoors.', "`$dbPuertas`.", $line);
            if (str_ends_with(rtrim($sql), $delimiter)) {
                $pdo->exec(substr(rtrim($sql), 0, -strlen($delimiter)));
                $sql = '';
            }
        }
        comprobar(trim($sql) === '', "El esquema $esquema no se importo completamente.");
    }
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => (string) $port, 'DB_USER' => 'root', 'DB_PASS' => '',
        'DB_NAME_EMPRESAS' => $db, 'DB_NAME_OPENDOORS' => $dbPuertas] as $nombre => $valor) {
        putenv("$nombre=$valor");
    }
    require_once $root . '/ModoAnfitrion/OpenDoors/util/db.php';
    comprobar(!function_exists('getPDO'), 'La conexion de OpenDoors depende de la conexion general.');
    $puertas = getOpenDoorsPDO();
    require_once $root . DIRECTORY_SEPARATOR . 'util' . DIRECTORY_SEPARATOR . 'db.php';
    $pdo = getPDO();
    $migracionAperturas = file($root . '/migraciones/seguimiento_aperturas.sql');
    $sqlAperturas = implode('', array_filter($migracionAperturas, fn($linea) => !str_starts_with($linea, '--')));
    foreach (array_filter(array_map('trim', explode(';', $sqlAperturas))) as $sentencia) {
        $puertas->exec($sentencia);
    }
    comprobar($pdo !== $puertas && $pdo === getPDO() && $puertas === getOpenDoorsPDO(),
        'Las conexiones no estan separadas o no se reutilizan.');
    comprobar($pdo->query('SELECT DATABASE()')->fetchColumn() === $db
        && $puertas->query('SELECT DATABASE()')->fetchColumn() === $dbPuertas,
        'Las conexiones no seleccionan los esquemas configurados.');
    comprobar($pdo->query('SELECT @@session.time_zone')->fetchColumn() === '-05:00'
        && $puertas->query('SELECT @@session.time_zone')->fetchColumn() === '+00:00',
        'Las conexiones no configuran las zonas horarias.');
    comprobar(getMySQLi()->query('SELECT DATABASE()')->fetch_row()[0] === $db
        && getOpenDoorsMySQLi()->query('SELECT DATABASE()')->fetch_row()[0] === $dbPuertas,
        'MySQLi no separa las bases.');
    comprobar(!$pdo->query("SHOW TABLES LIKE 'locales'")->fetchColumn()
        && !$puertas->query("SHOW TABLES LIKE 'cuentas'")->fetchColumn(),
        'Las tablas de cada modulo no estan aisladas.');
    $pdo->exec("INSERT INTO empresas (nombreEmpresa, duenoEmpresa, licenciaExpira, habilitado) VALUES
        ('EmpresaAlpha', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
        ('EmpresaBeta', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
        ('EmpresaInactiva', 'Pruebas', DATE_ADD(NOW(), INTERVAL 7 DAY), 0),
        ('EmpresaVencida', 'Pruebas', DATE_SUB(NOW(), INTERVAL 1 DAY), 1)");
    $insert = $pdo->prepare('INSERT INTO cuentas (correoCuenta, nombreCuenta, pwd, habilitado) VALUES (?, ?, ?, ?)');
    foreach (range(1, 8) as $id) {
        $insert->execute(["cuenta$id@example.test", "Cuenta$id", password_hash('Prueba12345', PASSWORD_DEFAULT), $id === 5 ? 0 : 1]);
    }
    $pdo->exec('INSERT INTO empresas_cuentas (idEmpresa, idCuenta, idCargo) VALUES
        (1,1,1), (2,2,1), (1,3,1), (2,3,1), (1,5,1), (3,6,1), (4,7,1), (1,8,1)');
    comprobar((int) $puertas->query('SELECT COUNT(*) FROM empresas')->fetchColumn() === 4,
        'El trigger no replica las empresas.');
    $pdo->beginTransaction();
    $pdo->exec("UPDATE empresas SET nombreEmpresa = 'CambioTemporal' WHERE idEmpresa = 1");
    comprobar($pdo->query("SELECT nombreEmpresa FROM `$dbPuertas`.empresas WHERE idEmpresa = 1")->fetchColumn() === 'CambioTemporal',
        'El trigger no sincroniza la actualizacion.');
    $pdo->rollBack();
    comprobar($puertas->query('SELECT nombreEmpresa FROM empresas WHERE idEmpresa = 1')->fetchColumn() === 'EmpresaAlpha',
        'El rollback no revierte la replica.');
    $puertas->exec("INSERT INTO locales (nombreLocal, direccionLocal, idEmpresa) VALUES
        ('LocalAlpha', 'DireccionAlpha', 1), ('LocalBeta', 'DireccionBeta', 2),
        ('LocalAlphaDos', 'Otra direccion', 1)");
    $puertas->exec('INSERT INTO microcontroladores (idLocal, idEmpresa) VALUES (1,1), (2,2)');
    comprobar(abs(time() - strtotime($puertas->query('SELECT ultimaConsulta FROM microcontroladores WHERE idMicrocontrolador = 1')->fetchColumn() . ' UTC')) < 10,
        'La fecha predeterminada del dispositivo no esta en UTC.');
    $insertarAcceso = $puertas->prepare('INSERT INTO accesos (activoDesde, activoHasta, idLocal, idEmpresa, token)
        VALUES (?, ?, ?, ?, ?)');
    comprobar($puertas->query('SELECT zonaHoraria FROM locales WHERE idLocal = 1')->fetchColumn() === 'America/Guayaquil',
        'El esquema nuevo no asigna la zona inicial.');
    $puertas->exec('ALTER TABLE locales DROP COLUMN zonaHoraria');
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
        $puertas->exec($sentencia);
    }
    // Simular que se retoma la migración después de confirmar la transacción.
    foreach (array_slice($sentencias, 1) as $sentencia) {
        $puertas->exec($sentencia);
    }
    $migrados = $puertas->query('SELECT activoDesde, activoHasta FROM accesos')->fetchAll();
    comprobar(count($migrados) === 3 && $migrados === array_fill(0, 3, ['activoDesde' => $desde, 'activoHasta' => $hasta]),
        'La migración no conserva la vigencia de todos los accesos o los convierte dos veces.');
    comprobar((int) $puertas->query("SELECT COUNT(*) FROM locales WHERE zonaHoraria = 'America/Guayaquil'")->fetchColumn() === 3,
        'La migración no asigna la zona inicial a todos los locales.');
    $puertas->exec("DELETE FROM accesos WHERE token = 'TokenAlphaDos'");
    $pdo->exec('UPDATE empresas_modulos SET habilitado = 1 WHERE idEmpresa = 1 AND idModulo = 1');

    mkdir($tmp);
    $app = $tmp . DIRECTORY_SEPARATOR . 'app';
    mkdir($app);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $normalized = str_replace('\\', '/', $relative);
        if ($file->getExtension() !== 'php' || str_starts_with($normalized, '.git/')
            || str_starts_with($normalized, 'tests/')) {
            continue;
        }
        $target = $app . DIRECTORY_SEPARATOR . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        copy($file->getPathname(), $target);
    }
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
    comprobar($login(8)['location'] === 'ModoAnfitrion/vistaAnfitrion'
        && str_contains($request('ModoAnfitrion/OpenDoors/Accesos/listarAccesos.php')['body'], 'LocalAlpha'),
        'Se confunde el ID de cuenta con el ID de empresa.');
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
        'ModoAnfitrion/Configuracion/OpenDoors/locales.php',
        'ModoAnfitrion/Configuracion/OpenDoors/configuracionLocal.php?id=1',
        'ModoAnfitrion/Configuracion/OpenDoors/RestaurarInformacion.php',
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
    comprobar((int) $puertas->query('SELECT COUNT(*) FROM accesos WHERE idEmpresa=1')->fetchColumn() === 2, 'No crea el acceso.');
    $edit = 'ModoAnfitrion/OpenDoors/Accesos/vetanaDelAcceso.php?id=1';
    $token = $csrf($request($edit));
    $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '3',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    comprobar((int) $puertas->query('SELECT idLocal FROM accesos WHERE idAcceso=1')->fetchColumn() === 3, 'No actualiza el acceso.');
    $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '1',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    $r = $request($edit, ['csrf' => $token, 'accion' => 'actualizar', 'id_local' => '2',
        'activo_desde' => date('Y-m-d\TH:i', time() - 1800), 'activo_hasta' => date('Y-m-d\TH:i', time() + 7200), 'habilitado' => '1']);
    comprobar(str_contains($r['body'], 'El local seleccionado no es válido.')
        && (int) $puertas->query('SELECT idLocal FROM accesos WHERE idAcceso=1')->fetchColumn() === 1,
        'Permite mover el acceso a otra empresa.');
    $revoke = 'ModoAnfitrion/OpenDoors/Accesos/revocarAcceso.php?id=1';
    $request($revoke, ['csrf' => $csrf($request($revoke))]);
    comprobar((int) $puertas->query('SELECT habilitado FROM accesos WHERE idAcceso=1')->fetchColumn() === 0, 'No revoca el acceso.');
    $r = $request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha']);
    comprobar($r['status'] === 403, 'Permite un acceso revocado.');
    $request($edit, ['csrf' => $token, 'accion' => 'toggle']);

    $r = $request('accesoPuerta.php', ['accion' => 'validar', 'token' => 'TokenAlpha']);
    comprobar(json_decode($r['body'], true)['valido'] === true, 'No valida el enlace publico.');
    $r = $request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha', 'confirmar' => '1']);
    comprobar($r['status'] === 303, 'No emite la orden por enlace.');
    $confirmation = $r['location'];
    comprobar((int) $puertas->query('SELECT ejecutado FROM accesos WHERE idAcceso=1')->fetchColumn() === 1, 'No consume el enlace.');
    comprobar((int) $puertas->query('SELECT cantidadAperturasExitosas FROM microcontroladores WHERE idMicrocontrolador=1')->fetchColumn() === 0,
        'Se contabiliza una apertura antes de que el dispositivo la confirme.');
    comprobar($request('ApiSystem/ordenDeApertura_porLinkAcceso.php', ['token' => 'TokenAlpha'])['status'] === 404, 'Reutiliza el enlace.');
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt('1', 'aes-256-cbc', hash('sha256', $env['OPENDOORS_ENC_KEY'], true), OPENSSL_RAW_DATA, $iv);
    $package = $iv . pack('v', strlen($encrypted)) . $encrypted;
    $cipher = bin2hex(str_pad($package, 500, "\0"));
    $r = $request('ApiSystem/api_board.php?cifrado=' . $cipher);
    comprobar(json_decode($r['body'], true) === ['ok' => true, 'accionar' => 1], 'El dispositivo no recibe la orden.');
    comprobar((int) $puertas->query('SELECT cantidadAperturasExitosas FROM microcontroladores WHERE idMicrocontrolador=1')->fetchColumn() === 1,
        'La API no contabiliza una unica apertura exitosa.');
    comprobar(abs(time() - strtotime($puertas->query('SELECT ultimaApertura FROM microcontroladores WHERE idMicrocontrolador = 1')->fetchColumn() . ' UTC')) < 10,
        'La confirmacion de apertura no guarda la hora en UTC.');
    $r = $request('ApiSystem/api_board.php?cifrado=' . $cipher);
    comprobar(json_decode($r['body'], true) === ['ok' => true, 'accionar' => 0], 'No limpia la orden de apertura.');
    $r = $request('ModoAnfitrion/OpenDoors/' . basename($confirmation) . '&estado=1');
    comprobar((json_decode($r['body'], true)['estado'] ?? '') === 'exitosa', 'No confirma la apertura del huesped.');
    $local = 'ModoAnfitrion/OpenDoors/Locales/localSeleccionado.php?id=1';
    $token = $csrf($request($local));
    comprobar($request($local, ['csrf' => $token, 'accion' => 'abrir_puerta'])['status'] === 302, 'No abre desde el panel.');
    $request($local, ['csrf' => $token, 'accion' => 'anular_apertura']);
    comprobar((int) $puertas->query('SELECT accionar FROM locales WHERE idLocal=1')->fetchColumn() === 0, 'No anula la apertura.');

    $configurar = 'ModoAnfitrion/Configuracion/OpenDoors/configuracionLocal.php?id=1';
    $r = $request($configurar, ['csrf' => $csrf($request($configurar)), 'nombreLocal' => 'LocalConfigurado',
        'direccionLocal' => 'DireccionConfigurada', 'zonaHoraria' => 'America/Lima', 'eliminado' => '0']);
    comprobar($r['status'] === 303
        && $puertas->query('SELECT nombreLocal FROM locales WHERE idLocal = 1')->fetchColumn() === 'LocalConfigurado',
        'La configuracion no escribe en OpenDoors.');
    $restaurar = 'ModoAnfitrion/Configuracion/OpenDoors/RestaurarInformacion.php';
    $r = $request($restaurar, ['csrf' => $csrf($request($restaurar)), 'alcance' => '1',
        'campos' => ['cantidadAperturasExitosas']]);
    comprobar($r['status'] === 303
        && (int) $puertas->query('SELECT cantidadAperturasExitosas FROM microcontroladores WHERE idMicrocontrolador = 1')->fetchColumn() === 0,
        'La restauracion no usa una transaccion en OpenDoors.');
    $pdo->exec('UPDATE empresas_modulos SET habilitado = 0 WHERE idEmpresa = 1 AND idModulo = 1');
    comprobar($request($configurar)['status'] === 403 && $request($restaurar)['status'] === 403
        && !str_contains($request('ModoAnfitrion/vistaAnfitrion.php')['body'], 'LocalConfigurado'),
        'La configuracion o el menu no consultan las asignaciones en empresas.');
    $pdo->exec('UPDATE empresas_modulos SET habilitado = 1 WHERE idEmpresa = 1 AND idModulo = 1');

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

    $pdo->exec("INSERT INTO empresas (duenoEmpresa) VALUES ('Anfitrion de prueba')");
    $idNuevaEmpresa = (int) $pdo->lastInsertId();
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt((string) $idNuevaEmpresa, 'aes-256-cbc',
        hash('sha256', $env['OPENDOORS_ENC_KEY'], true), OPENSSL_RAW_DATA, $iv);
    $package = $iv . pack('v', strlen($encrypted)) . $encrypted;
    $startup = 'Startup/startup.php?id=' . bin2hex(str_pad($package, 500, "\0"));
    $r = $request($startup, ['csrf' => $csrf($request($startup)), 'nombreEmpresa' => 'NuevaEmpresa',
        'paisTelefono' => 'PE', 'telefonoContacto' => '900000000', 'correoCuenta' => 'startup@example.test',
        'pwd' => 'PruebaStartup12345', 'ingreso' => 'automatico']);
    comprobar($r['status'] === 303 && $r['location'] === '../ModoAnfitrion/vistaAnfitrion',
        'Startup no guarda la configuracion en empresas.');
    $datosEmpresa = $pdo->query("SELECT nombreEmpresa, telefonoContacto, inicio FROM empresas WHERE idEmpresa = $idNuevaEmpresa")->fetch();
    comprobar($datosEmpresa === $puertas->query("SELECT nombreEmpresa, telefonoContacto, inicio FROM empresas WHERE idEmpresa = $idNuevaEmpresa")->fetch()
        && $datosEmpresa['nombreEmpresa'] === 'NuevaEmpresa' && (int) $datosEmpresa['inicio'] === 0,
        'Startup no conserva la sincronizacion de empresas dentro de su transaccion.');
    comprobar($request('ModoAnfitrion/vistaAnfitrion.php')['status'] === 200,
        'Una empresa sin OpenDoors asignado no puede entrar al sistema general.');

    comprobar(!preg_match('/(SQLSTATE|PHP Warning|PHP Fatal|PHP Notice)/', file_get_contents($log)), 'Hay errores en el log PHP.');
    echo "OK: $checks comprobaciones con empresas.sql, opendoors.sql y sus triggers.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if ($created) $pdo->exec("DROP DATABASE `$db`");
    if ($createdPuertas) $pdo->exec("DROP DATABASE `$dbPuertas`");
    if (is_dir($tmp)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) rmdir($file->getPathname());
            else unlink($file->getPathname());
        }
        rmdir($tmp);
    }
}
