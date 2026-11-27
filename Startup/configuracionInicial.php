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
// Codigos geograficos basados en libphonenumber; nombres basados en Unicode CLDR.
// Fuentes y licencias: Startup/Licencias.
$paisesTelefono = [
    'AF' => ['Afganistán', '+93'],
    'AL' => ['Albania', '+355'],
    'DE' => ['Alemania', '+49'],
    'AD' => ['Andorra', '+376'],
    'AO' => ['Angola', '+244'],
    'AI' => ['Anguila', '+1'],
    'AG' => ['Antigua y Barbuda', '+1'],
    'SA' => ['Arabia Saudí', '+966'],
    'DZ' => ['Argelia', '+213'],
    'AR' => ['Argentina', '+54'],
    'AM' => ['Armenia', '+374'],
    'AW' => ['Aruba', '+297'],
    'AU' => ['Australia', '+61'],
    'AT' => ['Austria', '+43'],
    'AZ' => ['Azerbaiyán', '+994'],
    'BS' => ['Bahamas', '+1'],
    'BD' => ['Bangladés', '+880'],
    'BB' => ['Barbados', '+1'],
    'BH' => ['Baréin', '+973'],
    'BE' => ['Bélgica', '+32'],
    'BZ' => ['Belice', '+501'],
    'BJ' => ['Benín', '+229'],
    'BM' => ['Bermudas', '+1'],
    'BY' => ['Bielorrusia', '+375'],
    'BO' => ['Bolivia', '+591'],
    'BA' => ['Bosnia y Herzegovina', '+387'],
    'BW' => ['Botsuana', '+267'],
    'BR' => ['Brasil', '+55'],
    'BN' => ['Brunéi', '+673'],
    'BG' => ['Bulgaria', '+359'],
    'BF' => ['Burkina Faso', '+226'],
    'BI' => ['Burundi', '+257'],
    'BT' => ['Bután', '+975'],
    'CV' => ['Cabo Verde', '+238'],
    'KH' => ['Camboya', '+855'],
    'CM' => ['Camerún', '+237'],
    'CA' => ['Canadá', '+1'],
    'BQ' => ['Caribe neerlandés', '+599'],
    'QA' => ['Catar', '+974'],
    'TD' => ['Chad', '+235'],
    'CZ' => ['Chequia', '+420'],
    'CL' => ['Chile', '+56'],
    'CN' => ['China', '+86'],
    'CY' => ['Chipre', '+357'],
    'VA' => ['Ciudad del Vaticano', '+39'],
    'CO' => ['Colombia', '+57'],
    'KM' => ['Comoras', '+269'],
    'CG' => ['Congo', '+242'],
    'KP' => ['Corea del Norte', '+850'],
    'KR' => ['Corea del Sur', '+82'],
    'CR' => ['Costa Rica', '+506'],
    'CI' => ['Côte d’Ivoire', '+225'],
    'HR' => ['Croacia', '+385'],
    'CU' => ['Cuba', '+53'],
    'CW' => ['Curazao', '+599'],
    'DK' => ['Dinamarca', '+45'],
    'DM' => ['Dominica', '+1'],
    'EC' => ['Ecuador', '+593'],
    'EG' => ['Egipto', '+20'],
    'SV' => ['El Salvador', '+503'],
    'AE' => ['Emiratos Árabes Unidos', '+971'],
    'ER' => ['Eritrea', '+291'],
    'SK' => ['Eslovaquia', '+421'],
    'SI' => ['Eslovenia', '+386'],
    'ES' => ['España', '+34'],
    'US' => ['Estados Unidos', '+1'],
    'EE' => ['Estonia', '+372'],
    'SZ' => ['Esuatini', '+268'],
    'ET' => ['Etiopía', '+251'],
    'PH' => ['Filipinas', '+63'],
    'FI' => ['Finlandia', '+358'],
    'FJ' => ['Fiyi', '+679'],
    'FR' => ['Francia', '+33'],
    'GA' => ['Gabón', '+241'],
    'GM' => ['Gambia', '+220'],
    'GE' => ['Georgia', '+995'],
    'GH' => ['Ghana', '+233'],
    'GI' => ['Gibraltar', '+350'],
    'GD' => ['Granada', '+1'],
    'GR' => ['Grecia', '+30'],
    'GL' => ['Groenlandia', '+299'],
    'GP' => ['Guadalupe', '+590'],
    'GU' => ['Guam', '+1'],
    'GT' => ['Guatemala', '+502'],
    'GF' => ['Guayana Francesa', '+594'],
    'GG' => ['Guernesey', '+44'],
    'GN' => ['Guinea', '+224'],
    'GQ' => ['Guinea Ecuatorial', '+240'],
    'GW' => ['Guinea-Bisáu', '+245'],
    'GY' => ['Guyana', '+592'],
    'HT' => ['Haití', '+509'],
    'HN' => ['Honduras', '+504'],
    'HU' => ['Hungría', '+36'],
    'IN' => ['India', '+91'],
    'ID' => ['Indonesia', '+62'],
    'IQ' => ['Irak', '+964'],
    'IR' => ['Irán', '+98'],
    'IE' => ['Irlanda', '+353'],
    'AC' => ['Isla de la Ascensión', '+247'],
    'IM' => ['Isla de Man', '+44'],
    'CX' => ['Isla de Navidad', '+61'],
    'NF' => ['Isla Norfolk', '+672'],
    'IS' => ['Islandia', '+354'],
    'AX' => ['Islas Aland', '+358'],
    'KY' => ['Islas Caimán', '+1'],
    'CC' => ['Islas Cocos', '+61'],
    'CK' => ['Islas Cook', '+682'],
    'FO' => ['Islas Feroe', '+298'],
    'FK' => ['Islas Malvinas', '+500'],
    'MP' => ['Islas Marianas del Norte', '+1'],
    'MH' => ['Islas Marshall', '+692'],
    'SB' => ['Islas Salomón', '+677'],
    'TC' => ['Islas Turcas y Caicos', '+1'],
    'VG' => ['Islas Vírgenes Británicas', '+1'],
    'VI' => ['Islas Vírgenes de EE. UU.', '+1'],
    'IL' => ['Israel', '+972'],
    'IT' => ['Italia', '+39'],
    'JM' => ['Jamaica', '+1'],
    'JP' => ['Japón', '+81'],
    'JE' => ['Jersey', '+44'],
    'JO' => ['Jordania', '+962'],
    'KZ' => ['Kazajistán', '+7'],
    'KE' => ['Kenia', '+254'],
    'KG' => ['Kirguistán', '+996'],
    'KI' => ['Kiribati', '+686'],
    'XK' => ['Kosovo', '+383'],
    'KW' => ['Kuwait', '+965'],
    'LA' => ['Laos', '+856'],
    'LS' => ['Lesoto', '+266'],
    'LV' => ['Letonia', '+371'],
    'LB' => ['Líbano', '+961'],
    'LR' => ['Liberia', '+231'],
    'LY' => ['Libia', '+218'],
    'LI' => ['Liechtenstein', '+423'],
    'LT' => ['Lituania', '+370'],
    'LU' => ['Luxemburgo', '+352'],
    'MK' => ['Macedonia del Norte', '+389'],
    'MG' => ['Madagascar', '+261'],
    'MY' => ['Malasia', '+60'],
    'MW' => ['Malaui', '+265'],
    'MV' => ['Maldivas', '+960'],
    'ML' => ['Mali', '+223'],
    'MT' => ['Malta', '+356'],
    'MA' => ['Marruecos', '+212'],
    'MQ' => ['Martinica', '+596'],
    'MU' => ['Mauricio', '+230'],
    'MR' => ['Mauritania', '+222'],
    'YT' => ['Mayotte', '+262'],
    'MX' => ['México', '+52'],
    'FM' => ['Micronesia', '+691'],
    'MD' => ['Moldavia', '+373'],
    'MC' => ['Mónaco', '+377'],
    'MN' => ['Mongolia', '+976'],
    'ME' => ['Montenegro', '+382'],
    'MS' => ['Montserrat', '+1'],
    'MZ' => ['Mozambique', '+258'],
    'MM' => ['Myanmar (Birmania)', '+95'],
    'NA' => ['Namibia', '+264'],
    'NR' => ['Nauru', '+674'],
    'NP' => ['Nepal', '+977'],
    'NI' => ['Nicaragua', '+505'],
    'NE' => ['Níger', '+227'],
    'NG' => ['Nigeria', '+234'],
    'NU' => ['Niue', '+683'],
    'NO' => ['Noruega', '+47'],
    'NC' => ['Nueva Caledonia', '+687'],
    'NZ' => ['Nueva Zelanda', '+64'],
    'OM' => ['Omán', '+968'],
    'NL' => ['Países Bajos', '+31'],
    'PK' => ['Pakistán', '+92'],
    'PW' => ['Palaos', '+680'],
    'PA' => ['Panamá', '+507'],
    'PG' => ['Papúa Nueva Guinea', '+675'],
    'PY' => ['Paraguay', '+595'],
    'PE' => ['Perú', '+51'],
    'PF' => ['Polinesia Francesa', '+689'],
    'PL' => ['Polonia', '+48'],
    'PT' => ['Portugal', '+351'],
    'PR' => ['Puerto Rico', '+1'],
    'HK' => ['RAE de Hong Kong (China)', '+852'],
    'MO' => ['RAE de Macao (China)', '+853'],
    'GB' => ['Reino Unido', '+44'],
    'CF' => ['República Centroafricana', '+236'],
    'CD' => ['República Democrática del Congo', '+243'],
    'DO' => ['República Dominicana', '+1'],
    'RE' => ['Reunión', '+262'],
    'RW' => ['Ruanda', '+250'],
    'RO' => ['Rumanía', '+40'],
    'RU' => ['Rusia', '+7'],
    'EH' => ['Sáhara Occidental', '+212'],
    'WS' => ['Samoa', '+685'],
    'AS' => ['Samoa Americana', '+1'],
    'BL' => ['San Bartolomé', '+590'],
    'KN' => ['San Cristóbal y Nieves', '+1'],
    'SM' => ['San Marino', '+378'],
    'MF' => ['San Martín', '+590'],
    'PM' => ['San Pedro y Miquelón', '+508'],
    'VC' => ['San Vicente y las Granadinas', '+1'],
    'SH' => ['Santa Elena', '+290'],
    'LC' => ['Santa Lucía', '+1'],
    'ST' => ['Santo Tomé y Príncipe', '+239'],
    'SN' => ['Senegal', '+221'],
    'RS' => ['Serbia', '+381'],
    'SC' => ['Seychelles', '+248'],
    'SL' => ['Sierra Leona', '+232'],
    'SG' => ['Singapur', '+65'],
    'SX' => ['Sint Maarten', '+1'],
    'SY' => ['Siria', '+963'],
    'SO' => ['Somalia', '+252'],
    'LK' => ['Sri Lanka', '+94'],
    'ZA' => ['Sudáfrica', '+27'],
    'SD' => ['Sudán', '+249'],
    'SS' => ['Sudán del Sur', '+211'],
    'SE' => ['Suecia', '+46'],
    'CH' => ['Suiza', '+41'],
    'SR' => ['Surinam', '+597'],
    'SJ' => ['Svalbard y Jan Mayen', '+47'],
    'TH' => ['Tailandia', '+66'],
    'TW' => ['Taiwán', '+886'],
    'TZ' => ['Tanzania', '+255'],
    'TJ' => ['Tayikistán', '+992'],
    'IO' => ['Territorio Británico del Océano Índico', '+246'],
    'TF' => ['Territorios Australes Franceses', '+262'],
    'PS' => ['Territorios Palestinos', '+970'],
    'TL' => ['Timor-Leste', '+670'],
    'TG' => ['Togo', '+228'],
    'TK' => ['Tokelau', '+690'],
    'TO' => ['Tonga', '+676'],
    'TT' => ['Trinidad y Tobago', '+1'],
    'TA' => ['Tristán de Acuña', '+290'],
    'TN' => ['Túnez', '+216'],
    'TM' => ['Turkmenistán', '+993'],
    'TR' => ['Turquía', '+90'],
    'TV' => ['Tuvalu', '+688'],
    'UA' => ['Ucrania', '+380'],
    'UG' => ['Uganda', '+256'],
    'UY' => ['Uruguay', '+598'],
    'UZ' => ['Uzbekistán', '+998'],
    'VU' => ['Vanuatu', '+678'],
    'VE' => ['Venezuela', '+58'],
    'VN' => ['Vietnam', '+84'],
    'WF' => ['Wallis y Futuna', '+681'],
    'YE' => ['Yemen', '+967'],
    'DJ' => ['Yibuti', '+253'],
    'ZM' => ['Zambia', '+260'],
    'ZW' => ['Zimbabue', '+263'],
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
    } elseif (!preg_match('/^[0-9][0-9 ()-]{3,29}$/D', $datos['telefonoContacto'])
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
                    <label for="paisTelefono">Pa&iacute;s o territorio del tel&eacute;fono</label>
                    <select id="paisTelefono" name="paisTelefono" autocomplete="country" required>
                        <option value="">Selecciona un pa&iacute;s o territorio</option>
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
                    <input type="tel" id="telefonoContacto" name="telefonoContacto" value="<?= $h($datos['telefonoContacto']) ?>" minlength="4" maxlength="30" pattern="[0-9][0-9 \(\)\-]{3,29}" autocomplete="tel-national" aria-describedby="ayudaTelefono" required>
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
