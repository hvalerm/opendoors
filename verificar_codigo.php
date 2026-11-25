<?php
require_once __DIR__ . '/util/Seguridad/verificar.php';
require_once __DIR__ . '/util/BaseDatos/db.php';
require_once __DIR__ . '/util/Cuentas/recuperar_comun.php';

iniciarSesionSegura();
if (haySesionActiva()) {
    recRedirigir('ModoAnfitrion/vistaAnfitrion');
}
$correo = (string) ($_SESSION['rec_correo'] ?? '');
if ($correo === '') {
    recRedirigir('recuperar_password');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = preg_replace('/\s+/', '', (string) ($_POST['codigo'] ?? ''));

    if (!recCsrfValido()) {
        $error = 'Solicitud no válida. Recarga la página e inténtalo de nuevo.';
    } elseif (!preg_match('/^[0-9]{10}$/', $codigo)) {
        $error = 'El código tiene 10 dígitos.';
    } else {
        try {
            $pdo = getPDO();
            $q = $pdo->prepare(
                'SELECT c.idCuenta FROM cuentas c
                 WHERE c.correoCuenta = ? AND c.habilitado = 1
                   AND EXISTS (SELECT 1 FROM empresas_cuentas ec WHERE ec.idCuenta = c.idCuenta)'
            );
            $q->execute([$correo]);
            $idCuenta = $q->fetchColumn();

            // Cada intento se cuenta antes de comprobar el código, así no se puede adivinar en paralelo
            $cuenta = false;
            if ($idCuenta !== false) {
                $u = $pdo->prepare(
                    'UPDATE recuperacion_password SET intentos = intentos + 1
                     WHERE idCuenta = ? AND verificado = 0 AND expira > NOW() AND intentos < ' . REC_MAX_INTENTOS
                );
                $u->execute([$idCuenta]);
                $cuenta = $u->rowCount() === 1;
            }

            if (!$cuenta) {
                if ($idCuenta !== false) {
                    $pdo->prepare('DELETE FROM recuperacion_password WHERE idCuenta = ? AND verificado = 0')->execute([$idCuenta]);
                }
                recRedirigir('recuperar_password?vencido=1');
            }

            $q = $pdo->prepare('SELECT codigoHash AS codigo_hash, intentos FROM recuperacion_password WHERE idCuenta = ?');
            $q->execute([$idCuenta]);
            $fila = $q->fetch();

            if ($fila && password_verify($codigo, $fila['codigo_hash'])) {
                $pdo->prepare(
                    'UPDATE recuperacion_password SET verificado = 1, expira = DATE_ADD(NOW(), INTERVAL ' . REC_VERIFICADO_MINUTOS . ' MINUTE)
                     WHERE idCuenta = ?'
                )->execute([$idCuenta]);
                session_regenerate_id(true);
                $_SESSION['rec_cuenta'] = (int) $idCuenta;
                recRedirigir('nueva_password');
            }

            $quedan = max(REC_MAX_INTENTOS - (int) ($fila['intentos'] ?? REC_MAX_INTENTOS), 0);
            if ($quedan === 0) {
                $pdo->prepare('DELETE FROM recuperacion_password WHERE idCuenta = ?')->execute([$idCuenta]);
                recRedirigir('recuperar_password?vencido=1');
            }
            $error = 'Código incorrecto. Te ' . ($quedan === 1 ? 'queda 1 intento.' : 'quedan ' . $quedan . ' intentos.');
        } catch (PDOException $e) {
            error_log('Error en verificar_codigo: ' . $e->getMessage());
            $error = 'No se pudo verificar el código. Inténtalo más tarde.';
        }
    }
}

recInicio('Ingresa el código', 'Si el correo está registrado, enviamos un código de 10 dígitos. Es válido por ' . REC_CODIGO_MINUTOS . ' minutos.', $error);
?>
        <form method="post" action="verificar_codigo" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= recEscapar(recCsrf()) ?>">
            <div class="field">
                <label for="codigo">Código de 10 dígitos</label>
                <input type="text" class="codigo" id="codigo" name="codigo" inputmode="numeric" pattern="[0-9 ]{10,14}" maxlength="14" required autofocus autocomplete="one-time-code">
            </div>
            <button type="submit" class="submit">Verificar código</button>
        </form>
        <a class="volver" href="recuperar_password">¿No llegó? Solicitar otro código</a>
<?php recFin();
