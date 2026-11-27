<?php
require_once __DIR__ . '/../Configuracion/DatosWeb.php';

function opcionesSmtp(): array
{
    $host = getenv('SMTP_HOST');
    $puerto = getenv('SMTP_PORT');
    $seguridad = getenv('SMTP_SECURITY');
    return [
        'host' => $host === false ? 'smtp.gmail.com' : $host,
        'puerto' => $puerto === false ? '465' : $puerto,
        'seguridad' => $seguridad === false ? 'ssl' : $seguridad,
        'usuario' => getenv('SMTP_USER'),
        'clave' => getenv('SMTP_PASSWORD'),
    ];
}

function smtpConfigurado(): bool
{
    $opciones = opcionesSmtp();
    $valido = is_string($opciones['usuario']) && filter_var($opciones['usuario'], FILTER_VALIDATE_EMAIL) !== false
        && is_string($opciones['clave']) && $opciones['clave'] !== ''
        && preg_match('/^[a-zA-Z0-9.-]+$/D', $opciones['host']) === 1
        && filter_var($opciones['puerto'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false
        && in_array($opciones['seguridad'], ['ssl', 'tls'], true)
        && extension_loaded('openssl')
        && is_file(__DIR__ . '/../../vendor/autoload.php');
    if (!$valido) {
        return false;
    }
    require_once __DIR__ . '/../../vendor/autoload.php';
    return class_exists(PHPMailer\PHPMailer\PHPMailer::class);
}

function enviarCorreo(string $para, string $asunto, string $texto): bool
{
    global $nombreWeb;
    if (!smtpConfigurado() || !filter_var($para, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $asunto . $nombreWeb)) {
        error_log('SMTP: configuración o datos de correo no válidos.');
        return false;
    }

    $opciones = opcionesSmtp();
    $correo = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $correo->isSMTP();
        $correo->Host = $opciones['host'];
        $correo->Port = (int) $opciones['puerto'];
        $correo->SMTPSecure = $opciones['seguridad'] === 'tls'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $correo->SMTPAuth = true;
        $correo->Username = $opciones['usuario'];
        $correo->Password = $opciones['clave'];
        $correo->Timeout = 10;
        $correo->CharSet = 'UTF-8';
        $correo->setFrom($correo->Username, $nombreWeb);
        $correo->addAddress($para);
        $correo->Subject = $asunto;
        $correo->Body = $texto;
        return $correo->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('SMTP: no se pudo enviar el correo. Revisa la conexión, TLS y las credenciales.');
        return false;
    }
}
