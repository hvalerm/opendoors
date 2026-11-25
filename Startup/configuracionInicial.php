<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($idEmpresa) || !is_int($idEmpresa) || $idEmpresa <= 0) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

require_once __DIR__ . '/../util/BaseDatos/db.php';
require_once __DIR__ . '/../util/Seguridad/verificar.php';
require_once __DIR__ . '/../util/Cuentas/recuperar_comun.php';
iniciarSesionSegura();
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT idEmpresa, duenoEmpresa, inicio
         FROM empresas WHERE idEmpresa = ?'
    );
    $stmt->execute([$idEmpresa]);
    $empresa = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Error en Startup/configuracionInicial: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar la configuracion inicial. Intentalo mas tarde.');
}

if (!$empresa || (int) $empresa['inicio'] !== 1) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

$nombreAnfitrion = trim($empresa['duenoEmpresa']);
if ($nombreAnfitrion === '' || !mb_check_encoding($nombreAnfitrion, 'UTF-8')
    || mb_strlen($nombreAnfitrion, 'UTF-8') > 100) {
    error_log('Nombre de dueño no válido en Startup/configuracionInicial para la empresa ' . $idEmpresa);
    http_response_code(500);
    exit('El nombre del anfitrion no es valido. Contacta al administrador.');
}

if (empty($_SESSION['startup_csrf'])) {
    $_SESSION['startup_csrf'] = bin2hex(random_bytes(32));
}

$h = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$paisesTelefono = [
    'AR' => ['Argentina', '+54'],
    'BO' => ['Bolivia', '+591'],
    'BR' => ['Brasil', '+55'],
    'CA' => ['Canadá', '+1'],
    'CL' => ['Chile', '+56'],
    'CO' => ['Colombia', '+57'],
    'CR' => ['Costa Rica', '+506'],
    'CU' => ['Cuba', '+53'],
    'EC' => ['Ecuador', '+593'],
    'SV' => ['El Salvador', '+503'],
    'ES' => ['España', '+34'],
    'US' => ['Estados Unidos', '+1'],
    'GT' => ['Guatemala', '+502'],
    'HN' => ['Honduras', '+504'],
    'MX' => ['México', '+52'],
    'NI' => ['Nicaragua', '+505'],
    'PA' => ['Panamá', '+507'],
    'PY' => ['Paraguay', '+595'],
    'PE' => ['Perú', '+51'],
    'DO' => ['República Dominicana', '+1'],
    'UY' => ['Uruguay', '+598'],
    'VE' => ['Venezuela', '+58'],
];
$datos = [
    'nombreEmpresa' => '',
    'paisTelefono' => '',
    'telefonoContacto' => '',
    'correoCuenta' => '',
    'ingreso' => 'manual',
];
$prefijoOtro = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entradaValida = true;
    foreach ($datos as $campo => $valor) {
        if (!isset($_POST[$campo]) || !is_string($_POST[$campo]) || !mb_check_encoding($_POST[$campo], 'UTF-8')) {
            $entradaValida = false;
        } else {
            $datos[$campo] = trim($_POST[$campo]);
        }
    }
    if (isset($_POST['prefijoOtro'])) {
        if (!is_string($_POST['prefijoOtro']) || !mb_check_encoding($_POST['prefijoOtro'], 'UTF-8')) {
            $entradaValida = false;
        } else {
            $prefijoOtro = trim($_POST['prefijoOtro']);
        }
    }
    $prefijo = $datos['paisTelefono'] === 'otro'
        ? $prefijoOtro
        : ($paisesTelefono[$datos['paisTelefono']][1] ?? '');
    $telefonoCompleto = $prefijo . ' ' . $datos['telefonoContacto'];
    $password = $_POST['pwd'] ?? null;
    $csrf = $_POST['csrf'] ?? null;
    if (!is_string($csrf) || !hash_equals($_SESSION['startup_csrf'], $csrf)) {
        http_response_code(403);
        $error = 'Solicitud no valida. Recarga la pagina e intentalo de nuevo.';
    } elseif (!$entradaValida || !is_string($password) || !mb_check_encoding($password, 'UTF-8')) {
        $error = 'Completa todos los campos con datos validos.';
    } elseif ($datos['nombreEmpresa'] === '' || mb_strlen($datos['nombreEmpresa'], 'UTF-8') > 50) {
        $error = 'El nombre de la empresa es obligatorio y admite hasta 50 caracteres.';
    } elseif (!preg_match('/^\+[1-9][0-9]{0,2}$/D', $prefijo)) {
        $error = 'Selecciona un pais o ingresa un prefijo internacional valido, por ejemplo +51.';
    } elseif (!preg_match('/^[0-9][0-9 ()-]{5,29}$/D', $datos['telefonoContacto'])
        || strlen($telefonoCompleto) > 30) {
        $error = 'Ingresa un telefono valido sin prefijo. El numero completo admite hasta 30 caracteres.';
    } elseif (strlen($datos['correoCuenta']) > 50 || !filter_var($datos['correoCuenta'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo valido de hasta 50 caracteres.';
    } elseif (mb_strlen($password, 'UTF-8') < REC_PASSWORD_MIN || strlen($password) > REC_PASSWORD_MAX) {
        $error = 'La contrasena debe tener al menos ' . REC_PASSWORD_MIN . ' caracteres y hasta ' . REC_PASSWORD_MAX . ' bytes.';
    } elseif (!in_array($datos['ingreso'], ['automatico', 'manual'], true)) {
        $error = 'Elige como deseas ingresar al sistema.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $pdo->beginTransaction();
            // Consumir el enlace y crear la cuenta deben confirmarse juntos.
            $stmt = $pdo->prepare(
                'UPDATE empresas SET nombreEmpresa = ?, telefonoContacto = ?, inicio = 0
                 WHERE idEmpresa = ? AND inicio = 1'
            );
            $stmt->execute([$datos['nombreEmpresa'], $telefonoCompleto, $idEmpresa]);
            if ($stmt->rowCount() !== 1) {
                $pdo->rollBack();
                http_response_code(404);
                exit('Solicitud no encontrada');
            }

            $stmt = $pdo->prepare('SELECT idCuenta FROM cuentas WHERE correoCuenta = ? LIMIT 1');
            $stmt->execute([$datos['correoCuenta']]);
            if ($stmt->fetch()) {
                throw new DomainException('Ese correo ya tiene una cuenta. Usa un correo diferente para el nuevo anfitrion.');
            }
            $pdo->prepare('INSERT INTO cuentas (nombreCuenta, correoCuenta, pwd) VALUES (?, ?, ?)')
                ->execute([$nombreAnfitrion, $datos['correoCuenta'], $hash]);
            $idCuenta = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO empresas_cuentas (idEmpresa, idCuenta, idCargo) VALUES (?, ?, 1)')
                ->execute([$idEmpresa, $idCuenta]);

            if ($datos['ingreso'] === 'automatico') {
                $vinculo = empresaUnicaCuenta($pdo, $idCuenta);
                if (new DateTime($vinculo['licencia_expira']) < new DateTime()) {
                    throw new DomainException('La licencia de la empresa ha expirado. Elige el ingreso manual para guardar los datos y consulta al administrador para renovar el acceso.');
                }
            }
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Error al guardar Startup/configuracionInicial: ' . $e->getMessage());
            http_response_code(500);
            $error = 'No se pudo guardar la configuracion. No se realizaron cambios. Intentalo mas tarde.';
        }

        if ($error === '') {
            $_SESSION = [];
            session_regenerate_id(true);
            if ($datos['ingreso'] === 'automatico') {
                $_SESSION['usuario_id'] = $idCuenta;
                $_SESSION['empresa_id'] = $idEmpresa;
                $_SESSION['nombre'] = $nombreAnfitrion;
                $_SESSION['ultima_actividad'] = time();
                $_SESSION['huella'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                header('Location: ../ModoAnfitrion/vistaAnfitrion', true, 303);
            } else {
                header('Location: ../login', true, 303);
            }
            exit;
        }
    }
    if ($error !== '' && http_response_code() === 200) {
        http_response_code(422);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configuraci&oacute;n inicial | <?= $h($nombreWeb) ?></title>
    <link rel="icon" type="image/png" href="../img/OpenDoors.png">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); padding: 20px; }
        .card { width: 100%; max-width: 680px; background: #fff; border-radius: 14px; padding: 32px 28px; box-shadow: 0 10px 30px rgba(0,0,0,.3); }
        .bienvenida { text-align: center; margin-bottom: 26px; }
        .saludo { font-size: 2.4rem; margin-bottom: 8px; }
        h1 { font-size: 1.6rem; color: #1b2a35; margin-bottom: 12px; }
        p { color: #526570; line-height: 1.5; }
        fieldset { border: 1px solid #dce5e9; border-radius: 10px; padding: 20px; margin-bottom: 22px; min-width: 0; }
        legend { font-weight: 700; color: #203a43; padding: 0 8px; }
        .field + .field { margin-top: 16px; }
        label { display: block; font-size: .9rem; font-weight: 600; color: #34444f; margin-bottom: 6px; }
        input:not([type=radio]), select { width: 100%; padding: 12px 14px; border: 1px solid #b9c9d1; border-radius: 8px; font: inherit; }
        input:focus-visible, select:focus-visible, button:focus-visible { outline: 3px solid #3a8fb7; outline-offset: 3px; }
        .ayuda { font-size: .85rem; margin-top: 7px; }
        .opcion { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; font-weight: 400; line-height: 1.4; }
        .opcion input { margin-top: 4px; flex-shrink: 0; }
        .error { background: #fdecea; color: #8e1b10; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        button { width: 100%; border: 0; border-radius: 9px; padding: 16px; background: linear-gradient(135deg, #22694a, #238456); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:hover { background: #1c6543; }
        @media (max-width: 480px) { body { padding: 12px; } .card { padding: 24px 18px; } fieldset { padding: 16px 12px; } }
    </style>
</head>
<body>
    <main class="card">
        <header class="bienvenida">
            <div class="saludo" aria-hidden="true">&#128075;</div>
            <h1>&iexcl;Qu&eacute; alegr&iacute;a tenerte aqu&iacute;, <?= $h($empresa['duenoEmpresa']) ?>!</h1>
            <p>Te damos la bienvenida a <?= $h($nombreWeb) ?>. Vamos a preparar tu empresa para que recibas a tus invitados con tranquilidad. &iexcl;Solo falta este peque&ntilde;o paso!</p>
        </header>
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= $h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="startup?id=<?= $h(rawurlencode($cifrado)) ?>">
            <input type="hidden" name="csrf" value="<?= $h($_SESSION['startup_csrf']) ?>">
            <fieldset>
                <legend>Datos de tu empresa</legend>
                <div class="field">
                    <label for="nombreEmpresa">Nombre de la empresa</label>
                    <input type="text" id="nombreEmpresa" name="nombreEmpresa" value="<?= $h($datos['nombreEmpresa']) ?>" maxlength="50" autocomplete="organization" required>
                </div>
                <div class="field">
                    <label for="paisTelefono">Pa&iacute;s del tel&eacute;fono</label>
                    <select id="paisTelefono" name="paisTelefono" autocomplete="country" required>
                        <option value="">Selecciona un pa&iacute;s</option>
                        <?php foreach ($paisesTelefono as $codigo => [$pais, $prefijoPais]): ?>
                            <option value="<?= $h($codigo) ?>" <?= $datos['paisTelefono'] === $codigo ? 'selected' : '' ?>><?= $h($pais . ' (' . $prefijoPais . ')') ?></option>
                        <?php endforeach; ?>
                        <option value="otro" <?= $datos['paisTelefono'] === 'otro' ? 'selected' : '' ?>>Otro prefijo</option>
                    </select>
                </div>
                <div class="field" id="campoPrefijoOtro">
                    <label for="prefijoOtro">Otro prefijo internacional</label>
                    <input type="tel" id="prefijoOtro" name="prefijoOtro" value="<?= $h($prefijoOtro) ?>" maxlength="4" pattern="\+[1-9][0-9]{0,2}" placeholder="+51" aria-describedby="ayudaPrefijo">
                    <p class="ayuda" id="ayudaPrefijo">Compl&eacute;talo solo si seleccionaste &laquo;Otro prefijo&raquo;.</p>
                </div>
                <div class="field">
                    <label for="telefonoContacto">Tel&eacute;fono de contacto</label>
                    <input type="tel" id="telefonoContacto" name="telefonoContacto" value="<?= $h($datos['telefonoContacto']) ?>" minlength="6" maxlength="30" pattern="[0-9][0-9 \(\)\-]{5,29}" autocomplete="tel-national" aria-describedby="ayudaTelefono" required>
                    <p class="ayuda" id="ayudaTelefono">Ingresa el n&uacute;mero sin el prefijo del pa&iacute;s, incluyendo el c&oacute;digo de &aacute;rea si corresponde.</p>
                </div>
            </fieldset>
            <fieldset>
                <legend>Datos del anfitri&oacute;n</legend>
                <div class="field">
                    <label for="nombreCuenta">Nombre del anfitri&oacute;n</label>
                    <input type="text" id="nombreCuenta" name="nombreCuenta" value="<?= $h($nombreAnfitrion) ?>" readonly>
                </div>
                <div class="field">
                    <label for="correoCuenta">Correo del anfitri&oacute;n</label>
                    <input type="email" id="correoCuenta" name="correoCuenta" value="<?= $h($datos['correoCuenta']) ?>" maxlength="50" autocomplete="username" required>
                </div>
                <div class="field">
                    <label for="pwd">Contrase&ntilde;a</label>
                    <input type="password" id="pwd" name="pwd" minlength="<?= REC_PASSWORD_MIN ?>" maxlength="<?= REC_PASSWORD_MAX ?>" autocomplete="new-password" aria-describedby="ayudaPassword" required>
                    <p class="ayuda" id="ayudaPassword">Usa al menos <?= REC_PASSWORD_MIN ?> caracteres. Tu contrase&ntilde;a se guardar&aacute; protegida.</p>
                </div>
            </fieldset>
            <fieldset>
                <legend>&iquest;C&oacute;mo quieres comenzar?</legend>
                <label class="opcion">
                    <input type="radio" name="ingreso" value="automatico" <?= $datos['ingreso'] === 'automatico' ? 'checked' : '' ?> required>
                    <span>Entrar autom&aacute;ticamente al panel, solo por esta vez.</span>
                </label>
                <label class="opcion">
                    <input type="radio" name="ingreso" value="manual" <?= $datos['ingreso'] === 'manual' ? 'checked' : '' ?> required>
                    <span>Ir al inicio de sesi&oacute;n e ingresar manualmente con mi nueva cuenta.</span>
                </label>
            </fieldset>
            <button type="submit">&#128640; &iexcl;Listo, vamos a comenzar!</button>
        </form>
    </main>
    <script>
        const paisTelefono = document.getElementById('paisTelefono');
        const campoPrefijoOtro = document.getElementById('campoPrefijoOtro');
        const prefijoOtro = document.getElementById('prefijoOtro');
        function actualizarPrefijoOtro() {
            const otro = paisTelefono.value === 'otro';
            campoPrefijoOtro.hidden = !otro;
            prefijoOtro.disabled = !otro;
            prefijoOtro.required = otro;
        }
        paisTelefono.addEventListener('change', actualizarPrefijoOtro);
        actualizarPrefijoOtro();
    </script>
</body>
</html>
