<?php
// Ejecutar: php tests\startup.php. Usa SQLite y un servidor HTTP temporales, sin BD real.
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/util/DatosWeb.php';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opendoors_startup_' . bin2hex(random_bytes(6));
mkdir($tmp);
mkdir($tmp . '/Startup');
mkdir($tmp . '/util');
mkdir($tmp . '/sessions');
$checks = 0;
$server = null;
$pdo = null;

function comprobarStartup(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

function cifrarStartup(string $value): string
{
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($value, 'aes-256-cbc',
        hash('sha256', 'clave-exclusiva-startup-test', true), OPENSSL_RAW_DATA, $iv);
    $package = $iv . pack('v', strlen($cipher)) . $cipher;
    return bin2hex($package . random_bytes(500 - strlen($package)));
}

try {
    foreach (['Startup/startup.php', 'Startup/configuracionInicial.php', 'accesoPuerta.php', 'util/enc.php',
        'util/verificar.php', 'util/seguridad.php', 'util/recuperar_comun.php', 'util/DatosWeb.php', 'util/contacto.php',
        'util/api_dispositivo.php', 'ApiSystem/api_board.php', 'ApiSystem/api_microcontrolador.php',
        'ApiSystem/api_restore.php', 'ApiSystem/ordenDeApertura_porLinkAcceso.php',
        'ModoAnfitrion/vistaAnfitrion.php', 'ModoAnfitrion/OpenDoors/puertaAccionada.php',
        'ModoAnfitrion/OpenDoors/Microcontroladores/verEstado.php',
        'ModoAnfitrion/OpenDoors/Microcontroladores/verActividad.php',
        'ModoAnfitrion/OpenDoors/Accesos/CrearAccesoLocal.php',
        'ModoAnfitrion/OpenDoors/Accesos/vetanaDelAcceso.php',
        'ModoAnfitrion/OpenDoors/Accesos/listarAccesos.php',
        'ModoAnfitrion/OpenDoors/Accesos/busquedaAccesoRevocar.php',
        'ModoAnfitrion/OpenDoors/Accesos/revocarAcceso.php'] as $file) {
        if (!is_dir(dirname($tmp . '/' . $file))) {
            mkdir(dirname($tmp . '/' . $file), 0777, true);
        }
        copy($root . '/' . $file, $tmp . '/' . $file);
    }
    $datosWeb = preg_replace('/^\$urlBase\s*=\s*[^;]+;/m',
        "\$urlBase = 'https://puertas.example.test/instalacion/';",
        file_get_contents($tmp . '/util/DatosWeb.php'), 1);
    file_put_contents($tmp . '/util/DatosWeb.php', str_replace(
        [var_export($nombreWeb, true), var_export($rutaApi, true), var_export($rutaAccesoPuerta, true)],
        ["'Puertas & <Prueba>'", "'servicios'", "'entrada'"],
        $datosWeb
    ));
    $vistaDispositivos = $tmp . '/ModoAnfitrion/OpenDoors/Microcontroladores/verEstado.php';
    file_put_contents($vistaDispositivos, str_replace(
        'TIMESTAMPDIFF(SECOND, m.ultimaConsulta, UTC_TIMESTAMP())',
        'CAST(strftime("%s", "now") - strftime("%s", m.ultimaConsulta) AS INTEGER)',
        file_get_contents($vistaDispositivos)
    ));
    $vistaAccesos = $tmp . '/ModoAnfitrion/OpenDoors/Accesos/busquedaAccesoRevocar.php';
    file_put_contents($vistaAccesos, str_replace('NOW()', 'datetime("now")', file_get_contents($vistaAccesos)));
    $vistaListado = $tmp . '/ModoAnfitrion/OpenDoors/Accesos/listarAccesos.php';
    file_put_contents($vistaListado, str_replace('NOW()', 'datetime("now")', file_get_contents($vistaListado)));
    $apiDispositivo = $tmp . '/util/api_dispositivo.php';
    file_put_contents($apiDispositivo, str_replace(
        ['FOR UPDATE', 'UTC_TIMESTAMP()'], ['', 'CURRENT_TIMESTAMP'], file_get_contents($apiDispositivo)
    ));
    file_put_contents($tmp . '/util/db.php', '<?php
require_once __DIR__ . "/seguridad.php";
function getPDO(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO("sqlite:" . __DIR__ . "/../test.sqlite", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON");
    }
    return $pdo;
}');
    copy(__DIR__ . '/router.php', $tmp . '/routerRutas.php');
    file_put_contents($tmp . '/router.php', '<?php
putenv("OPENDOORS_ENC_KEY=clave-exclusiva-startup-test");
if (($_SERVER["HTTP_X_STARTUP_TEST_MODE"] ?? "") === "sin-clave") putenv("OPENDOORS_ENC_KEY");
return require __DIR__ . "/routerRutas.php";
');
    file_put_contents($tmp . '/sesion.php', '<?php
require __DIR__ . "/util/verificar.php";
iniciarSesionSegura();
header("Content-Type: application/json");
echo json_encode($_SESSION);
');
    file_put_contents($tmp . '/protegida.php', '<?php
require __DIR__ . "/util/verificar.php";
requerirSesion();
');
    file_put_contents($tmp . '/recuperacion.php', '<?php
require __DIR__ . "/util/recuperar_comun.php";
recInicio("Recuperar", "Prueba");
recFin();
');
    $pdo = new PDO('sqlite:' . $tmp . '/test.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE empresas (idEmpresa INTEGER PRIMARY KEY, nombreEmpresa TEXT,
        duenoEmpresa TEXT, telefonoContacto TEXT, inicio INTEGER, habilitado INTEGER DEFAULT 1,
        licenciaExpira TEXT DEFAULT "2099-12-31 00:00:00")');
    $pdo->exec("INSERT INTO empresas (idEmpresa, nombreEmpresa, duenoEmpresa, telefonoContacto, inicio) VALUES
        (1, '<Empresa & nueva>', '<Dueno & cliente>', '+51000000000', 1),
        (2, 'Configurada', 'Dueno', '+51000000000', 0),
        (3, 'Automatica', 'Dueno', '+51000000000', 1),
        (4, 'Fallo', 'Dueno', '+51000000000', 1),
        (5, 'Vencida', 'Dueno', '+51000000000', 1),
        (6, 'Deshabilitada', 'Dueno', '+51000000000', 1)");
    $pdo->exec("UPDATE empresas SET licenciaExpira = '2000-01-01 00:00:00' WHERE idEmpresa = 5");
    $pdo->exec('UPDATE empresas SET habilitado = 0 WHERE idEmpresa = 6');
    $pdo->exec('CREATE TABLE cuentas (idCuenta INTEGER PRIMARY KEY AUTOINCREMENT, nombreCuenta TEXT,
        correoCuenta TEXT, pwd TEXT, habilitado INTEGER DEFAULT 1)');
    $pdo->exec('CREATE TABLE cargos (idCargo INTEGER PRIMARY KEY)');
    $pdo->exec('INSERT INTO cargos VALUES (1)');
    $pdo->exec('CREATE TABLE empresas_cuentas (idEmpresa INTEGER REFERENCES empresas(idEmpresa),
        idCuenta INTEGER REFERENCES cuentas(idCuenta), idCargo INTEGER REFERENCES cargos(idCargo),
        PRIMARY KEY (idEmpresa, idCuenta))');
    $pdo->exec('CREATE TABLE modulos (idModulo INTEGER PRIMARY KEY, nombreModulo TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE empresas_modulos (idEmpresa INTEGER REFERENCES empresas(idEmpresa),
        idModulo INTEGER REFERENCES modulos(idModulo), PRIMARY KEY (idEmpresa, idModulo))');
    $pdo->exec("INSERT INTO modulos VALUES (1, 'OpenDoors'), (2, 'GESTALO'),
        (3, 'Otro & <modulo>')");
    $pdo->exec('INSERT INTO empresas_modulos VALUES (1, 1), (1, 2)');
    $pdo->exec('CREATE TABLE locales (idLocal INTEGER PRIMARY KEY, idEmpresa INTEGER,
        nombreLocal TEXT, direccionLocal TEXT, eliminado INTEGER DEFAULT 0, habilitado INTEGER DEFAULT 1,
        accionar INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE soporte (idSoporte INTEGER PRIMARY KEY, telefonoContacto TEXT)');
    $pdo->exec('CREATE TABLE microcontroladores (idMicrocontrolador INTEGER PRIMARY KEY, idLocal INTEGER,
        idEmpresa INTEGER, ultimaConsulta TEXT, ultimaApertura TEXT, habilitado INTEGER, cantidadAperturas INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE accesos (idAcceso INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE, idLocal INTEGER, idEmpresa INTEGER,
        activoDesde TEXT, activoHasta TEXT, ejecutado INTEGER DEFAULT 0, habilitado INTEGER DEFAULT 1)');
    $pdo->exec("INSERT INTO locales (idLocal, idEmpresa, nombreLocal, direccionLocal)
        VALUES (1, 1, 'Local de prueba', 'Direccion de prueba')");
    $pdo->exec("INSERT INTO accesos (token, idLocal, idEmpresa, activoDesde, activoHasta)
        VALUES ('TokenPrueba', 1, 1, '2000-01-01 00:00:00', '2099-12-31 23:59:59')");

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($socket === false) {
        throw new RuntimeException($errstr);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'session.use_strict_mode=1',
        '-d', 'session.save_path=' . $tmp . '/sessions', '-S', $address, '-t', $tmp, $tmp . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $tmp . '/php.log', 'a'],
            2 => ['file', $tmp . '/php.log', 'a']], $pipes, $tmp);
    if (!is_resource($server)) {
        throw new RuntimeException('No se pudo iniciar PHP de pruebas.');
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
    comprobarStartup($ready, 'El servidor de pruebas no responde.');
    $cookies = [];
    $request = function (array $get, string $entry = 'Startup/startup', string $mode = '',
        ?array $post = null) use ($address, &$cookies): array {
        $headers = ['User-Agent: OpenDoorsStartupTest', 'X-Startup-Test-Mode: ' . $mode];
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
        $body = file_get_contents('http://' . $address . '/' . $entry . '?' . http_build_query($get), false, $context);
        if ($body === false) {
            throw new RuntimeException('Fallo HTTP de pruebas.');
        }
        $status = 0;
        $location = '';
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\S+ (\d+)/', $header, $m)) $status = (int) $m[1];
            if (preg_match('/^Location:\s*(.*)/i', $header, $m)) $location = $m[1];
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $m)) $cookies[$m[1]] = $m[2];
        }
        comprobarStartup(!preg_match('/(Warning:|Fatal error:|SQLSTATE|Undefined array key)/', $body),
            'Error PHP/SQL en la respuesta.');
        return ['status' => $status, 'location' => $location, 'body' => $body];
    };
    $csrf = function (array $response): string {
        if (!preg_match('/name="csrf" value="([^"]+)"/', $response['body'], $m)) {
            throw new RuntimeException('No se encontro el CSRF.');
        }
        return $m[1];
    };
    $get = ['id' => cifrarStartup('1')];
    $post = [
        'nombreEmpresa' => 'Empresa actualizada', 'paisTelefono' => 'PE', 'telefonoContacto' => '999 888 777',
        'nombreCuenta' => 'Nombre manipulado', 'correoCuenta' => 'anfitrion@example.test',
        'pwd' => 'ClaveSegura123!', 'ingreso' => 'manual',
    ];
    $response = $request([], 'protegida.php');
    comprobarStartup($response['status'] === 302
        && $response['location'] === 'https://puertas.example.test/instalacion/login?expirada=1',
        'El inicio de sesion no usa la URL base configurada.');
    $response = $request(['token' => 'TokenPrueba'], 'accesoPuerta');
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], '<title>Acceso | Puertas &amp; &lt;Prueba&gt;</title>')
        && str_contains($response['body'], 'action="/instalacion/servicios/ordenDeApertura_porLinkAcceso"')
        && str_contains($response['body'], 'new URL(window.location.href)')
        && str_contains($response['body'], "urlValidacion.search = '';")
        && str_contains($response['body'], 'fetch(urlValidacion,'),
        'El acceso publico no aplica el nombre o las rutas configuradas en el mismo sitio.');
    $response = $request([], 'accesoPuerta.php', '', ['accion' => 'validar', 'token' => 'TokenPrueba']);
    comprobarStartup(json_decode($response['body'], true) === ['valido' => true, 'mensaje' => ''],
        'La validacion no reconoce un acceso vigente.');
    $pdo->exec("UPDATE accesos SET ejecutado = 1 WHERE token = 'TokenPrueba'");
    $response = $request([], 'accesoPuerta.php', '', ['accion' => 'validar', 'token' => 'TokenPrueba']);
    comprobarStartup(json_decode($response['body'], true) === ['valido' => false, 'mensaje' => 'Acceso usado.'],
        'La validacion no detecta un acceso ya usado.');
    $pdo->exec("UPDATE accesos SET ejecutado = 0 WHERE token = 'TokenPrueba'");
    $response = $request([], 'ApiSystem/ordenDeApertura_porLinkAcceso', '',
        ['token' => 'TokenPrueba', 'confirmar' => '1']);
    comprobarStartup($response['status'] === 303 && str_contains($response['location'], 'confirmacion='),
        'No genera la confirmacion de apertura para el huesped.');
    $confirmacion = [];
    parse_str((string) parse_url($response['location'], PHP_URL_QUERY), $confirmacion);
    comprobarStartup(str_starts_with($response['location'], '../ModoAnfitrion/OpenDoors/puertaAccionada?'),
        'La confirmacion se redirige a una ruta con extension.');
    $response = $request($confirmacion, 'ModoAnfitrion/OpenDoors/puertaAccionada');
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], 'id="cerrarVentana" hidden')
        && str_contains($response['body'], 'id="avisoCierre" role="status" hidden'),
        'La confirmacion del huesped no ofrece el cierre inicialmente oculto.');
    $response = $request(['confirmacion' => 'invalida'], 'ModoAnfitrion/OpenDoors/puertaAccionada.php');
    comprobarStartup(!str_contains($response['body'], 'id="cerrarVentana"'),
        'Una confirmacion invalida muestra el boton de cierre.');
    $pdo->exec('INSERT INTO microcontroladores (idMicrocontrolador, idLocal, idEmpresa, habilitado) VALUES (10, 1, 1, 1)');
    $credencial = ['cifrado' => cifrarStartup('10')];
    $consulta = 'ApiSystem/api_microcontrolador';
    $restore = 'ApiSystem/api_restore';
    $orden = fn() => (int) $pdo->query('SELECT accionar FROM locales WHERE idLocal = 1')->fetchColumn();
    $ultimaConsulta = fn() => $pdo->query('SELECT ultimaConsulta FROM microcontroladores WHERE idMicrocontrolador = 10')->fetchColumn();
    $response = $request($credencial, $consulta);
    comprobarStartup($response['status'] === 200 && json_decode($response['body'], true) === ['ok' => true, 'accionar' => 1]
        && $orden() === 1
        && abs(time() - strtotime($ultimaConsulta() . ' UTC')) <= 5,
        'La consulta consume la orden o no guarda la ultima consulta en UTC.');
    $response = $request($credencial, $consulta);
    comprobarStartup(json_decode($response['body'], true) === ['ok' => true, 'accionar' => 1],
        'Una segunda consulta debe seguir mostrando la orden pendiente.');
    $pdo->exec("UPDATE microcontroladores SET habilitado = 0, ultimaConsulta = '2020-01-01 00:00:00' WHERE idMicrocontrolador = 10");
    comprobarStartup($request([], $restore, post: $credencial)['status'] === 403 && $orden() === 1,
        'Un dispositivo deshabilitado pudo restablecer la orden.');
    $pdo->exec('UPDATE microcontroladores SET habilitado = 1 WHERE idMicrocontrolador = 10');
    comprobarStartup($request($credencial, $restore)['status'] === 405 && $orden() === 1,
        'El restablecimiento no debe aceptar GET.');
    $response = $request([], $restore, post: $credencial);
    comprobarStartup($response['status'] === 200 && json_decode($response['body'], true) === ['ok' => true, 'accionar' => 0]
        && $orden() === 0 && $ultimaConsulta() === '2020-01-01 00:00:00',
        'Restablecer no limpia la orden o modifica la ultima consulta.');
    $pdo->exec('UPDATE locales SET accionar = 1 WHERE idLocal = 1');
    $response = $request($credencial, 'ApiSystem/api_board.php');
    comprobarStartup(json_decode($response['body'], true) === ['ok' => true, 'accionar' => 1] && $orden() === 0,
        'La API anterior ya no consume la orden al consultarla.');
    unset($orden, $ultimaConsulta);
    $pdo->exec('DELETE FROM microcontroladores WHERE idMicrocontrolador = 10');
    $pdo->exec("UPDATE accesos SET ejecutado = 0 WHERE token = 'TokenPrueba'");
    $pdo->exec('UPDATE locales SET accionar = 0 WHERE idLocal = 1');
    $response = $request([], 'recuperacion.php');
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], '<title>Recuperar | Puertas &amp; &lt;Prueba&gt;</title>'),
        'La pantalla comun de recuperacion no usa el nombre configurado.');
    foreach ([
        [], ['id' => ['1']], ['id' => '1'], ['id' => str_repeat('g', 1000)],
        ['id' => str_repeat('0', 1000)], ['id' => cifrarStartup('abc')],
        ['id' => cifrarStartup('0')], ['id' => cifrarStartup('2147483648')],
        ['id' => cifrarStartup('1abc')], ['id' => cifrarStartup('2')], ['id' => cifrarStartup('999')],
    ] as $invalid) {
        $response = $request($invalid);
        comprobarStartup($response['status'] === 404 && $response['body'] === 'Solicitud no encontrada',
            'No rechaza una solicitud invalida o una empresa sin inicio pendiente.');
    }
    $response = $request($get);
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], '&lt;Dueno &amp; cliente&gt;')
        && str_contains($response['body'], 'name="nombreCuenta" value="&lt;Dueno &amp; cliente&gt;" readonly')
        && str_contains($response['body'], 'bienvenida a Puertas &amp; &lt;Prueba&gt;.')
        && str_contains($response['body'], 'value="manual" checked'),
        'No muestra la bienvenida, el dueno escapado o la eleccion de ingreso.');
    comprobarStartup(str_contains($response['body'], 'name="nombreEmpresa" value=""')
        && str_contains($response['body'], 'name="telefonoContacto" value=""')
        && str_contains($response['body'], 'name="paisTelefono"')
        && str_contains($response['body'], 'Perú (+51)')
        && str_contains($response['body'], 'value="otro"')
        && !str_contains($response['body'], '&lt;Empresa &amp; nueva&gt;')
        && !str_contains($response['body'], '+51000000000'),
        'El formulario inicial no debe cargar el nombre ni el telefono registrados.');
    foreach (['   ', str_repeat('a', 101)] as $nombreInvalido) {
        $stmt = $pdo->prepare('UPDATE empresas SET duenoEmpresa = ? WHERE idEmpresa = 4');
        $stmt->execute([$nombreInvalido]);
        $fallo = $request(['id' => cifrarStartup('4')]);
        comprobarStartup($fallo['status'] === 500
            && str_contains($fallo['body'], 'Contacta al administrador.'),
            'No informa que el nombre del dueno registrado es invalido.');
    }
    $stmt = null;
    $pdo->exec("UPDATE empresas SET duenoEmpresa = 'Dueno' WHERE idEmpresa = 4");
    $post['csrf'] = $csrf($response);
    comprobarStartup((int) $pdo->query('SELECT inicio FROM empresas WHERE idEmpresa = 1')->fetchColumn() === 1,
        'La consulta no debe completar la configuracion.');
    comprobarStartup($request($get, post: array_replace($post, ['csrf' => 'incorrecto']))['status'] === 403,
        'No rechaza un CSRF incorrecto.');
    foreach ([
        ['nombreEmpresa' => ''], ['nombreEmpresa' => str_repeat('a', 51)],
        ['paisTelefono' => ''], ['paisTelefono' => 'XX'], ['paisTelefono' => ['PE']],
        ['paisTelefono' => 'otro'], ['paisTelefono' => 'otro', 'prefijoOtro' => ['+51']],
        ['paisTelefono' => 'otro', 'prefijoOtro' => '51'],
        ['paisTelefono' => 'otro', 'prefijoOtro' => '+012'], ['paisTelefono' => 'otro', 'prefijoOtro' => '+1234'],
        ['telefonoContacto' => 'abc'], ['telefonoContacto' => '+51 999 888 777'],
        ['telefonoContacto' => str_repeat('9', 28)], ['correoCuenta' => 'no-es-correo'],
        ['correoCuenta' => str_repeat('a', 40) . '@example.test'],
        ['pwd' => 'corta'], ['pwd' => str_repeat('a', 201)], ['pwd' => ['invalido']],
        ['ingreso' => 'otro'],
    ] as $invalid) {
        $response = $request($get, post: array_replace($post, $invalid));
        comprobarStartup($response['status'] === 422, 'Acepta datos de formulario invalidos.');
        comprobarStartup(!str_contains($response['body'], 'value="ClaveSegura123!"'),
            'Vuelve a mostrar la contrasena en el formulario.');
    }
    comprobarStartup(str_contains($response['body'], 'name="nombreEmpresa" value="Empresa actualizada"')
        && str_contains($response['body'], 'name="telefonoContacto" value="999 888 777"')
        && str_contains($response['body'], 'value="PE" selected'),
        'Un error de validacion debe conservar el nombre, el pais y el telefono ingresados.');
    $response = $request($get, post: array_replace($post, [
        'paisTelefono' => 'otro', 'prefijoOtro' => '+44', 'nombreEmpresa' => '',
    ]));
    comprobarStartup($response['status'] === 422
        && str_contains($response['body'], 'value="otro" selected')
        && str_contains($response['body'], 'name="prefijoOtro" value="+44"')
        && str_contains($response['body'], 'name="nombreCuenta" value="&lt;Dueno &amp; cliente&gt;" readonly'),
        'Un error de validacion debe conservar el prefijo personalizado.');
    comprobarStartup((int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn() === 0,
        'Los formularios invalidos no deben crear cuentas.');
    $cookiesAnteriores = $cookies;
    $cookies = [];
    comprobarStartup($request($get, post: $post)['status'] === 403,
        'Acepta un CSRF procedente de otra sesion.');
    $cookies = $cookiesAnteriores;
    $response = $request($get, post: $post);
    comprobarStartup($response['status'] === 303 && $response['location'] === '../login',
        'No dirige al login cuando se elige el ingreso manual.');
    $empresa = $pdo->query('SELECT * FROM empresas WHERE idEmpresa = 1')->fetch(PDO::FETCH_ASSOC);
    $cuenta = $pdo->query('SELECT * FROM cuentas')->fetch(PDO::FETCH_ASSOC);
    comprobarStartup($empresa['nombreEmpresa'] === $post['nombreEmpresa']
        && $empresa['telefonoContacto'] === '+51 ' . $post['telefonoContacto'] && (int) $empresa['inicio'] === 0,
        'No actualiza la empresa y consume el enlace.');
    comprobarStartup($cuenta['nombreCuenta'] === '<Dueno & cliente>'
        && $cuenta['correoCuenta'] === $post['correoCuenta']
        && $cuenta['pwd'] !== $post['pwd'] && password_verify($post['pwd'], $cuenta['pwd']),
        'No crea la cuenta con una contrasena protegida.');
    $vinculo = $pdo->query('SELECT * FROM empresas_cuentas')->fetch(PDO::FETCH_ASSOC);
    comprobarStartup((int) $vinculo['idEmpresa'] === 1
        && (int) $vinculo['idCuenta'] === (int) $cuenta['idCuenta'] && (int) $vinculo['idCargo'] === 1,
        'No vincula la cuenta con la empresa y el cargo 1.');
    $sesion = json_decode($request([], 'sesion.php')['body'], true);
    comprobarStartup(!isset($sesion['usuario_id']), 'El ingreso manual inicia sesion automaticamente.');
    comprobarStartup($request($get, post: $post)['status'] === 404
        && (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn() === 1,
        'Reutilizar el enlace crea mas cuentas.');

    $getAuto = ['id' => cifrarStartup('3')];
    $post['csrf'] = $csrf($request($getAuto));
    $response = $request($getAuto, post: $post);
    comprobarStartup($response['status'] === 422
        && str_contains($response['body'], 'Ese correo ya tiene una cuenta'),
        'Permite crear un correo duplicado.');
    comprobarStartup((int) $pdo->query('SELECT inicio FROM empresas WHERE idEmpresa = 3')->fetchColumn() === 1,
        'Un correo duplicado deja la empresa configurada.');
    $post['correoCuenta'] = 'automatico@example.test';
    $post['ingreso'] = 'automatico';
    $post['paisTelefono'] = 'otro';
    $post['prefijoOtro'] = '+44';
    unset($post['nombreCuenta']);
    $idSesionAnterior = $cookies['PHPSESSID'];
    $response = $request($getAuto, post: $post);
    comprobarStartup($response['status'] === 303 && $response['location'] === '../ModoAnfitrion/vistaAnfitrion',
        'No dirige al panel cuando se elige ingreso automatico.');
    comprobarStartup($pdo->query('SELECT telefonoContacto FROM empresas WHERE idEmpresa = 3')->fetchColumn() === '+44 999 888 777',
        'No guarda el telefono con el prefijo personalizado.');
    $sesion = json_decode($request([], 'sesion.php')['body'], true);
    comprobarStartup((int) $sesion['empresa_id'] === 3 && $sesion['nombre'] === 'Dueno'
        && (int) $sesion['usuario_id'] > 0 && isset($sesion['ultima_actividad'])
        && $sesion['huella'] === hash('sha256', 'OpenDoorsStartupTest')
        && $cookies['PHPSESSID'] !== $idSesionAnterior && !isset($sesion['startup_csrf']),
        'No establece una sesion segura para el nuevo anfitrion.');

    $pdo->exec("INSERT INTO locales (idLocal, idEmpresa, nombreLocal, direccionLocal)
        VALUES (3, 3, 'Local del anfitrion', 'Direccion del anfitrion')");
    $vistaDispositivos = 'ModoAnfitrion/OpenDoors/Microcontroladores/verEstado';
    $response = $request([], $vistaDispositivos);
    comprobarStartup(str_contains($response['body'], 'Tu empresa no tiene dispositivos de apertura registrados.'),
        'No informa cuando la empresa no tiene dispositivos.');
    $pdo->exec("INSERT INTO locales (idLocal, idEmpresa, nombreLocal, direccionLocal)
        VALUES (4, 3, 'Local <especial>', 'Direccion')");
    $pdo->exec("INSERT INTO microcontroladores VALUES
        (101, 3, 3, datetime('now'), '2025-01-02 03:04:05', 1, 27),
        (102, 4, 3, NULL, NULL, 0, 0),
        (103, 1, 1, datetime('now'), NULL, 1, 5)");
    $actividad = $request(['embed' => '1'], 'ModoAnfitrion/OpenDoors/Microcontroladores/verActividad');
    preg_match_all('~<article class="tarjeta">(.*?)</article>~s', $actividad['body'], $tarjetas);
    comprobarStartup($actividad['status'] === 200 && count($tarjetas[1]) === 2
        && !str_contains($actividad['body'], '<table')
        && str_contains($tarjetas[1][0], 'Microcontrolador #101')
        && str_contains($tarjetas[1][0], '<dt>Local</dt><dd>Local del anfitrion</dd>')
        && str_contains($tarjetas[1][0], '<dt>Dirección</dt><dd>Direccion del anfitrion</dd>')
        && str_contains($tarjetas[1][0], '<dt>Veces ejecutado</dt><dd>27</dd>')
        && str_contains($tarjetas[1][1], 'Microcontrolador #102')
        && str_contains($tarjetas[1][1], '<dt>Veces ejecutado</dt><dd>0</dd>'),
        'La actividad no muestra una tarjeta con los datos correspondientes a cada dispositivo de la empresa.');
    $response = $request([], $vistaDispositivos);
    comprobarStartup($response['status'] === 200
        && substr_count($response['body'], '<article class="tarjeta') === 2
        && str_contains($response['body'], 'data-col="0">101</span>')
        && str_contains($response['body'], 'data-col="0">102</span>')
        && !str_contains($response['body'], 'data-col="0">103</span>')
        && str_contains($response['body'], 'Local &lt;especial&gt;')
        && str_contains($response['body'], 'data-col="5">Activo</dd>')
        && str_contains($response['body'], 'data-col="5">Inactivo</dd>')
        && str_contains($response['body'], 'data-col="6">Sí</dd>')
        && str_contains($response['body'], 'data-col="6">No</dd>')
        && str_contains($response['body'], '02/01/2025 03:04:05')
        && str_contains($response['body'], '<dt>Última apertura</dt><dd>—</dd>'),
        'Las tarjetas no muestran correctamente los dispositivos de la empresa.');
    $sinSesion = $cookies;
    $cookies = [];
    comprobarStartup($request(['datos' => '1'], $vistaDispositivos)['status'] === 401,
        'La actualizacion automatica no debe devolver datos sin sesion.');
    $cookies = $sinSesion;
    $pdo->exec("UPDATE microcontroladores SET ultimaConsulta = '2025-01-02 12:00:00' WHERE idMicrocontrolador = 101");
    $pdo->exec("UPDATE microcontroladores SET ultimaConsulta = datetime('now', '+5 hours') WHERE idMicrocontrolador = 102");
    $actualizacion = $request(['datos' => '1'], $vistaDispositivos);
    $fragmento = json_decode($actualizacion['body'], true)['tarjetas'] ?? '';
    comprobarStartup($actualizacion['status'] === 200
        && substr_count($fragmento, 'data-col="5">Inactivo</dd>') === 2
        && str_contains($fragmento, 'datetime="2025-01-02T12:00:00Z">2025-01-02 12:00:00 UTC</time>')
        && !str_contains($fragmento, '<!DOCTYPE')
        && !str_contains($fragmento, 'data-col="0">103</span>'),
        'La actualizacion no refleja la desconexion o devuelve contenido ajeno a las tarjetas de la empresa.');
    $pdo->exec("UPDATE microcontroladores SET ultimaConsulta = datetime('now') WHERE idMicrocontrolador = 102");
    $actualizacion = $request(['datos' => '1'], $vistaDispositivos);
    $fragmento = json_decode($actualizacion['body'], true)['tarjetas'] ?? '';
    comprobarStartup($actualizacion['status'] === 200
        && substr_count($fragmento, 'data-col="5">Activo</dd>') === 1,
        'La actualizacion no refleja la reconexion del dispositivo.');
    $panel = 'ModoAnfitrion/vistaAnfitrion';
    $response = $request([], $panel);
    comprobarStartup($response['status'] === 200
        && !str_contains($response['body'], '<span class="label">Puertas &amp; &lt;Prueba&gt;</span>')
        && !str_contains($response['body'], '<span class="label">GESTALO</span>')
        && str_contains($response['body'], '<span class="label">Soporte</span>'),
        'Muestra modulos de otra empresa o esconde soporte sin modulos asignados.');
    $config = strpos($response['body'], 'href="?seccion=configuracion-cuenta"');
    $logout = strpos($response['body'], 'action="../logout"');
    comprobarStartup($config !== false && $logout !== false && $config < $logout,
        'La configuracion de cuenta no aparece antes de cerrar sesion.');
    $response = $request(['seccion' => 'configuracion-cuenta'], $panel);
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], '<h2>Configuración de cuenta</h2>'),
        'El enlace no abre la seccion de configuracion de cuenta.');
    $pdo->exec('INSERT INTO empresas_modulos VALUES (3, 2), (3, 3)');
    $response = $request([], $panel);
    comprobarStartup(str_contains($response['body'], '<span class="label">GESTALO</span>')
        && str_contains($response['body'], '<span class="label">Otro &amp; &lt;modulo&gt;</span>')
        && !str_contains($response['body'], '<span class="label">Puertas &amp; &lt;Prueba&gt;</span>'),
        'No muestra exclusivamente los modulos asignados o no escapa sus nombres.');
    $response = $request(['seccion' => 'modulo-2'], $panel);
    comprobarStartup(str_contains($response['body'], '<h2>GESTALO</h2>')
        && str_contains($response['body'], 'Sección en construcción.'),
        'El modulo sin pagina no muestra su seccion en construccion.');
    $response = $request(['seccion' => 'puertas-prueba/dashboard'], $panel);
    comprobarStartup(!str_contains($response['body'], '<iframe'),
        'Muestra una seccion del menu OpenDoors sin el modulo asignado.');
    $pdo->exec('INSERT INTO empresas_modulos VALUES (3, 1)');
    $response = $request([], $panel);
    $posOpenDoors = strpos($response['body'], '<span class="label">Puertas &amp; &lt;Prueba&gt;</span>');
    $posGestalo = strpos($response['body'], '<span class="label">GESTALO</span>');
    comprobarStartup($posOpenDoors !== false && $posGestalo !== false && $posOpenDoors < $posGestalo
        && str_contains($response['body'], '<span class="label">Dashboard</span>')
        && str_contains($response['body'], '<span class="label">Listar (y editar)</span>')
        && str_contains($response['body'], '<span class="label">Habilitar/Deshabilitar</span>')
        && str_contains($response['body'], '<span class="label">Local del anfitrion</span>'),
        'No muestra OpenDoors con sus opciones antes de los demas modulos.');
    $response = $request(['seccion' => 'puertas-prueba/accesos/revocar'], $panel);
    comprobarStartup(str_contains($response['body'], 'OpenDoors/Accesos/busquedaAccesoRevocar?embed=1'),
        'El menu de habilitacion no conserva la ruta de la seccion.');
    $response = $request(['seccion' => 'puertas-prueba/accesos/listar'], $panel);
    comprobarStartup(str_contains($response['body'], 'OpenDoors/Accesos/listarAccesos?embed=1'),
        'El menu de listado no conserva la ruta de la seccion.');
    $response = $request(['seccion' => 'puertas-prueba/dashboard'], $panel);
    comprobarStartup(str_contains($response['body'], 'OpenDoors/Dashboard/resumen?embed=1'),
        'La seccion de OpenDoors asignada no carga su pagina.');
    $pdo->exec('DELETE FROM empresas_modulos WHERE idEmpresa = 3 AND idModulo IN (1, 2)');
    $response = $request(['seccion' => 'modulo-2'], $panel);
    comprobarStartup(!str_contains($response['body'], '<span class="label">GESTALO</span>')
        && !str_contains($response['body'], '<span class="label">Puertas &amp; &lt;Prueba&gt;</span>')
        && !str_contains($response['body'], '<h2>GESTALO</h2>')
        && str_contains($response['body'], '<span class="label">Otro &amp; &lt;modulo&gt;</span>'),
        'La revocacion de modulos no se refleja inmediatamente.');
    $pdo->exec('DROP TABLE empresas_modulos');
    $response = $request([], $panel);
    comprobarStartup($response['status'] === 503
        && str_contains($response['body'], 'No se pudieron cargar los módulos.'),
        'Un error al consultar los modulos no debe ocultarse como una lista vacia.');

    $crearAcceso = 'ModoAnfitrion/OpenDoors/Accesos/CrearAccesoLocal';
    $response = $request(['id' => '3'], $crearAcceso);
    $response = $request(['id' => '3'], $crearAcceso, post: [
        'csrf' => $csrf($response),
        'activo_desde' => date('Y-m-d\TH:i', time() - 3600),
        'activo_hasta' => date('Y-m-d\TH:i', time() + 86400),
    ]);
    $nuevoAcceso = $pdo->query('SELECT idAcceso, token FROM accesos WHERE idEmpresa = 3')->fetch(PDO::FETCH_ASSOC);
    comprobarStartup($response['status'] === 200 && $nuevoAcceso !== false,
        'No crea el acceso de prueba.');
    $enlaceEsperado = 'https://puertas.example.test/instalacion/entrada?token=' . $nuevoAcceso['token'];
    comprobarStartup(str_contains($response['body'], $enlaceEsperado)
        && str_contains($response['body'], rawurlencode($enlaceEsperado)),
        'El enlace creado o compartido por WhatsApp no usa la configuracion central.');
    comprobarStartup(!str_contains($response['body'], '>' . $nuevoAcceso['token'] . '</div>')
        && str_contains($response['body'], 'id="btnCopiar"')
        && str_contains($response['body'], 'id="linkAcceso">' . $enlaceEsperado . '</div>'),
        'La confirmacion muestra el token separado o no permite copiar el enlace.');
    $listado = $request([], 'ModoAnfitrion/OpenDoors/Accesos/listarAccesos');
    $instruccion = strpos($listado['body'], 'Seleccione un acceso para editarlo');
    $tabla = strpos($listado['body'], '<table>');
    comprobarStartup($instruccion !== false && $tabla !== false && $instruccion < $tabla
        && str_contains($listado['body'], 'vetanaDelAcceso?id=' . $nuevoAcceso['idAcceso']),
        'El listado no indica como editar un acceso antes de la tabla.');
    $response = $request(['id' => (string) $nuevoAcceso['idAcceso']],
        'ModoAnfitrion/OpenDoors/Accesos/vetanaDelAcceso.php');
    comprobarStartup($response['status'] === 200
        && str_contains($response['body'], $enlaceEsperado)
        && str_contains($response['body'], rawurlencode($enlaceEsperado)),
        'El detalle del acceso no usa el enlace configurado.');
    comprobarStartup(str_contains($response['body'], 'Deshabilitar acceso'),
        'El detalle no muestra la accion de deshabilitar.');
    $idAcceso = (int) $nuevoAcceso['idAcceso'];
    $pdo->exec("INSERT INTO accesos (token, idLocal, idEmpresa, activoDesde, activoHasta, habilitado)
        VALUES ('TokenDeshabilitado', 3, 3, '2000-01-01 00:00:00', '2099-12-31 23:59:59', 0)");
    $idDeshabilitado = (int) $pdo->lastInsertId();
    $lista = 'ModoAnfitrion/OpenDoors/Accesos/busquedaAccesoRevocar';
    $response = $request(['embed' => '1'], $lista);
    comprobarStartup(str_contains($response['body'], 'Habilitar/Deshabilitar accesos')
        && str_contains($response['body'], "revocarAcceso?id=$idAcceso&amp;origen=busqueda&amp;embed=1\">Deshabilitar</a>")
        && str_contains($response['body'], "revocarAcceso?id=$idDeshabilitado&amp;origen=busqueda&amp;embed=1\">Habilitar</a>"),
        'La lista no muestra las acciones correspondientes al estado del acceso.');
    $confirmar = 'ModoAnfitrion/OpenDoors/Accesos/revocarAcceso';
    foreach ([[$idAcceso, 0, 'Deshabilitar'], [$idDeshabilitado, 1, 'Habilitar']] as [$id, $esperado, $accion]) {
        $response = $request(['id' => (string) $id, 'origen' => 'busqueda', 'embed' => '1'], $confirmar);
        comprobarStartup(str_contains($response['body'], "$accion acceso</button>"),
            'La confirmacion muestra una accion incorrecta.');
        $response = $request([], $confirmar, post: [
            'id' => (string) $id, 'origen' => 'busqueda', 'embed' => '1', 'csrf' => $csrf($response),
        ]);
        comprobarStartup($response['status'] === 302 && $response['location'] === 'busquedaAccesoRevocar?embed=1'
            && (int) $pdo->query("SELECT habilitado FROM accesos WHERE idAcceso = $id")->fetchColumn() === $esperado,
            'La accion no actualiza el estado o no vuelve a la lista.');
    }

    foreach ([5, 6] as $idEmpresa) {
        $getInhabilitada = ['id' => cifrarStartup((string) $idEmpresa)];
        $post['csrf'] = $csrf($request($getInhabilitada));
        $post['correoCuenta'] = "empresa$idEmpresa@example.test";
        $response = $request($getInhabilitada, post: $post);
        comprobarStartup($response['status'] === 422
            && (int) $pdo->query("SELECT inicio FROM empresas WHERE idEmpresa = $idEmpresa")->fetchColumn() === 1
            && (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn() === 2,
            'El ingreso automatico ignora la licencia o el estado de la empresa.');
    }
    $getFallo = ['id' => cifrarStartup('4')];
    $post['csrf'] = $csrf($request($getFallo));
    $post['correoCuenta'] = 'fallo@example.test';
    $post['ingreso'] = 'manual';
    $pdo->exec("CREATE TRIGGER fallo_vinculo BEFORE INSERT ON empresas_cuentas
        BEGIN SELECT RAISE(ABORT, 'Fallo de vinculacion de prueba'); END");
    $response = $request($getFallo, post: $post);
    comprobarStartup($response['status'] === 500
        && (int) $pdo->query('SELECT COUNT(*) FROM cuentas')->fetchColumn() === 2
        && $pdo->query('SELECT nombreEmpresa FROM empresas WHERE idEmpresa = 4')->fetchColumn() === 'Fallo'
        && (int) $pdo->query('SELECT inicio FROM empresas WHERE idEmpresa = 4')->fetchColumn() === 1,
        'El fallo de vinculacion no revierte todos los cambios.');
    $pdo->exec('DROP TRIGGER fallo_vinculo');
    $response = $request($getFallo, post: $post);
    comprobarStartup($response['status'] === 303 && $response['location'] === '../login',
        'No permite reintentar el guardado despues de un fallo.');
    $sesion = json_decode($request([], 'sesion.php')['body'], true);
    comprobarStartup(!isset($sesion['usuario_id']),
        'El ingreso manual conserva la sesion de otra cuenta.');
    comprobarStartup($request(['id' => '1'], 'Startup/configuracionInicial.php')['status'] === 404,
        'Permite acceder directamente con un ID sin cifrar.');
    comprobarStartup($request($getFallo, mode: 'sin-clave')['status'] === 500,
        'Oculta la falta de configuracion de cifrado.');
    echo "OK: $checks comprobaciones.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    $pdo = null;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($tmp);
}
