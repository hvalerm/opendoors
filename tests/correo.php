<?php
// php tests\correo.php. Simula SMTP local con TLS; no envia correo real.
declare(strict_types=1);
require_once dirname(__DIR__) . '/util/Correo/correo.php';

if (($argv[1] ?? '') === '--cliente') {
    echo enviarCorreo('destino@example.test', 'Código de recuperación', "Prueba de envío\nSegunda línea") ? 'OK' : 'ERROR';
    exit;
}

$checks = 0;
function comprobarCorreo(bool $condicion, string $mensaje): void
{
    global $checks;
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
    $checks++;
}

putenv('SMTP_USER');
putenv('SMTP_PASSWORD');
comprobarCorreo(!smtpConfigurado(), 'No debe estar configurado sin credenciales.');
putenv('SMTP_USER=emisor@example.test');
putenv('SMTP_PASSWORD=clave-de-prueba-' . bin2hex(random_bytes(8)));
putenv('SMTP_HOST=localhost');
putenv('SMTP_PORT=0');
comprobarCorreo(!smtpConfigurado(), 'No debe aceptar un puerto invalido.');
putenv('SMTP_PORT=465');
putenv('SMTP_SECURITY=sin-cifrado');
comprobarCorreo(!smtpConfigurado(), 'No debe permitir SMTP sin cifrado.');
putenv('SMTP_SECURITY=ssl');
comprobarCorreo(smtpConfigurado() && !enviarCorreo("destino@example.test\r\nBcc: intruso@example.test", 'Asunto', 'Texto')
    && !enviarCorreo('destino@example.test', "Asunto\r\nBcc: intruso@example.test", 'Texto'),
    'Debe rechazar destinatarios y asuntos con saltos de linea.');

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opendoors_smtp_' . bin2hex(random_bytes(8));
if (!mkdir($dir)) {
    throw new RuntimeException('No se pudo crear el directorio temporal SMTP.');
}
$config = $dir . DIRECTORY_SEPARATOR . 'openssl.cnf';
$certificado = $dir . DIRECTORY_SEPARATOR . 'cert.pem';
$clavePrivada = $dir . DIRECTORY_SEPARATOR . 'key.pem';
$archivos = [$config, $certificado, $clavePrivada];
try {
    file_put_contents($config, "[req]\ndistinguished_name=req_dn\nx509_extensions=v3_cert\n"
        . "[req_dn]\n[v3_cert]\nsubjectAltName=DNS:localhost\n"
        . "basicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\n");
    $opciones = ['config' => $config, 'private_key_bits' => 2048, 'digest_alg' => 'sha256',
        'x509_extensions' => 'v3_cert'];
    $key = openssl_pkey_new($opciones);
    if ($key === false || !openssl_pkey_export($key, $pem, null, $opciones)) {
        throw new RuntimeException('No se pudo crear la clave TLS de prueba.');
    }
    $csr = openssl_csr_new(['commonName' => 'localhost'], $key, $opciones);
    $cert = $csr === false ? false : openssl_csr_sign($csr, null, $key, 1, $opciones);
    if ($cert === false || !openssl_x509_export($cert, $certPem)) {
        throw new RuntimeException('No se pudo crear el certificado TLS de prueba.');
    }
    file_put_contents($clavePrivada, $pem);
    file_put_contents($certificado, $certPem);

    $probarServidor = function (string $seguridad, bool $certificadoConfiable, bool $rechazarClave = false) use ($certificado, $clavePrivada): array {
        $contexto = stream_context_create(['ssl' => [
            'local_cert' => $certificado, 'local_pk' => $clavePrivada,
        ]]);
        $servidor = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $contexto);
        if ($servidor === false) {
            throw new RuntimeException("No se pudo iniciar SMTP de prueba: $error");
        }
        $direccion = stream_socket_get_name($servidor, false);
        $puerto = (int) substr(strrchr($direccion, ':'), 1);
        putenv("SMTP_PORT=$puerto");
        putenv("SMTP_SECURITY=$seguridad");
        $comando = [PHP_BINARY];
        if ($certificadoConfiable) {
            array_push($comando, '-d', "openssl.cafile=$certificado");
        }
        array_push($comando, __FILE__, '--cliente');
        $proceso = proc_open($comando, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            fclose($servidor);
            throw new RuntimeException('No se pudo iniciar el cliente SMTP de prueba.');
        }
        fclose($pipes[0]);
        $comandos = [];
        $datos = '';
        $cliente = stream_socket_accept($servidor, 10);
        if ($cliente === false) {
            proc_terminate($proceso);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proceso);
            fclose($servidor);
            throw new RuntimeException('El cliente no conecto con el servidor SMTP de prueba.');
        }
        stream_set_timeout($cliente, 10);
        $cifrado = false;
        if ($seguridad === 'ssl') {
            $cifrado = @stream_socket_enable_crypto($cliente, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true;
        }
        if ($seguridad === 'tls' || $cifrado) {
            fwrite($cliente, "220 localhost SMTP de prueba\r\n");
            $autenticacion = 0;
            $recibiendoDatos = false;
            while (($linea = fgets($cliente)) !== false) {
                $linea = rtrim($linea, "\r\n");
                if ($recibiendoDatos) {
                    if ($linea === '.') {
                        $recibiendoDatos = false;
                        fwrite($cliente, "250 Mensaje aceptado\r\n");
                    } else {
                        $datos .= $linea . "\n";
                    }
                    continue;
                }
                if ($autenticacion > 0) {
                    $comandos[] = $linea;
                    if ($autenticacion === 1) {
                        $autenticacion = 2;
                        fwrite($cliente, "334 UGFzc3dvcmQ6\r\n");
                    } else {
                        $autenticacion = 0;
                        fwrite($cliente, $rechazarClave ? "535 Autenticacion rechazada\r\n" : "235 Autenticado\r\n");
                    }
                } elseif (str_starts_with($linea, 'EHLO ')) {
                    $comandos[] = $linea;
                    fwrite($cliente, "250-localhost\r\n" . (!$cifrado ? "250-STARTTLS\r\n" : '')
                        . "250-AUTH LOGIN\r\n250 SIZE 100000\r\n");
                } elseif ($linea === 'STARTTLS') {
                    $comandos[] = $linea;
                    fwrite($cliente, "220 Iniciar TLS\r\n");
                    $cifrado = @stream_socket_enable_crypto($cliente, true, STREAM_CRYPTO_METHOD_TLS_SERVER) === true;
                    if (!$cifrado) {
                        break;
                    }
                } elseif ($linea === 'AUTH LOGIN') {
                    $comandos[] = $linea;
                    $autenticacion = 1;
                    fwrite($cliente, "334 VXNlcm5hbWU6\r\n");
                } elseif (str_starts_with($linea, 'MAIL FROM:') || str_starts_with($linea, 'RCPT TO:')) {
                    $comandos[] = $linea;
                    fwrite($cliente, "250 Aceptado\r\n");
                } elseif ($linea === 'DATA') {
                    $comandos[] = $linea;
                    $recibiendoDatos = true;
                    fwrite($cliente, "354 Enviar mensaje\r\n");
                } elseif ($linea === 'QUIT') {
                    fwrite($cliente, "221 Adios\r\n");
                    break;
                } else {
                    fwrite($cliente, "500 Comando no esperado\r\n");
                }
            }
        }
        fclose($cliente);
        fclose($servidor);
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proceso) !== 0) {
            throw new RuntimeException("Fallo el cliente de prueba: $error");
        }
        return [$salida, $comandos, $datos];
    };

    foreach (['ssl', 'tls'] as $seguridad) {
        [$salida, $comandos, $datos] = $probarServidor($seguridad, true);
        preg_match('/^Subject: (.+)$/m', $datos, $asunto);
        comprobarCorreo($salida === 'OK'
            && in_array('AUTH LOGIN', $comandos, true)
            && in_array(base64_encode('emisor@example.test'), $comandos, true)
            && in_array(base64_encode(getenv('SMTP_PASSWORD')), $comandos, true)
            && in_array('MAIL FROM:<emisor@example.test>', $comandos, true)
            && in_array('RCPT TO:<destino@example.test>', $comandos, true)
            && isset($asunto[1]) && iconv_mime_decode($asunto[1], 0, 'UTF-8') === 'Código de recuperación'
            && str_contains($datos, 'Prueba de envío')
            && str_contains($datos, 'Content-Type: text/plain; charset=UTF-8')
            && ($seguridad !== 'tls' || in_array('STARTTLS', $comandos, true)),
            'El envio SMTP cifrado no contiene la autenticacion o el mensaje esperados.');
    }
    [$salida, $comandos] = $probarServidor('ssl', true, true);
    comprobarCorreo($salida === 'ERROR' && !in_array('DATA', $comandos, true),
        'Una autenticacion rechazada no debe enviar el mensaje.');
    [$salida, $comandos] = $probarServidor('ssl', false);
    comprobarCorreo($salida === 'ERROR' && !in_array('AUTH LOGIN', $comandos, true),
        'Un certificado no confiable no debe permitir el envio de credenciales.');
    [$salida, $comandos] = $probarServidor('tls', false);
    comprobarCorreo($salida === 'ERROR' && in_array('STARTTLS', $comandos, true)
        && !in_array('AUTH LOGIN', $comandos, true),
        'STARTTLS no debe enviar credenciales si el certificado no es confiable.');
    echo "OK: $checks comprobaciones SMTP locales.\n";
} finally {
    foreach ($archivos as $archivo) {
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
    rmdir($dir);
}
