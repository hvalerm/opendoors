<?php
// Verificación de la sesión del usuario.
// Uso: require_once __DIR__ . '/../util/Seguridad/verificar.php'; requerirSesion();

const SESION_INACTIVIDAD_MAX = 1800; // 30 minutos
require_once __DIR__ . '/../Configuracion/DatosWeb.php';
define('URL_LOGIN', $urlLogin);
require_once __DIR__ . '/seguridad.php';

function empresasCuenta(PDO $pdo, int $idCuenta): array
{
    $stmt = $pdo->prepare(
        'SELECT ec.idEmpresa AS id_empresa, e.nombreEmpresa AS nombre_empresa,
                e.habilitado AS empresa_habilitada, c.habilitado AS cuenta_habilitada
         FROM empresas_cuentas ec
         INNER JOIN empresas e ON e.idEmpresa = ec.idEmpresa
         INNER JOIN cuentas c ON c.idCuenta = ec.idCuenta
         WHERE ec.idCuenta = ?
         ORDER BY e.nombreEmpresa, ec.idEmpresa'
    );
    $stmt->execute([$idCuenta]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function empresaUnicaCuenta(PDO $pdo, int $idCuenta): array
{
    $empresas = empresasCuenta($pdo, $idCuenta);
    if (count($empresas) > 1) {
        throw new DomainException('Se requiere una cuenta vinculada a una sola empresa para este registro.');
    }
    if (!$empresas) {
        throw new DomainException('La cuenta no tiene una empresa vinculada. Contacta al administrador.');
    }
    $empresa = $empresas[0];
    if (!$empresa['cuenta_habilitada'] || !$empresa['empresa_habilitada']) {
        throw new DomainException('La cuenta o su empresa está deshabilitada. Contacta al administrador.');
    }
    return $empresa;
}

function empresaVinculadaCuenta(PDO $pdo, int $idCuenta, int $idEmpresa): array
{
    foreach (empresasCuenta($pdo, $idCuenta) as $empresa) {
        if ((int) $empresa['id_empresa'] === $idEmpresa) {
            if (!$empresa['cuenta_habilitada'] || !$empresa['empresa_habilitada']) {
                throw new DomainException('La cuenta o su empresa está deshabilitada. Contacta al administrador.');
            }
            return $empresa;
        }
    }
    throw new DomainException('La cuenta no tiene esta empresa vinculada.');
}

function iniciarSesionSegura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => esHttps(),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function haySesionActiva(bool $registrarActividad = true): bool
{
    iniciarSesionSegura();

    if (empty($_SESSION['usuario_id'])) {
        return false;
    }

    $ahora = time();
    if (isset($_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > SESION_INACTIVIDAD_MAX) {
        cerrarSesion();
        return false;
    }
    if ($registrarActividad) {
        $_SESSION['ultima_actividad'] = $ahora;
    }

    // Evita el robo de sesión cambiando de navegador
    $huella = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!isset($_SESSION['huella'])) {
        $_SESSION['huella'] = $huella;
    } elseif (!hash_equals($_SESSION['huella'], $huella)) {
        cerrarSesion();
        return false;
    }

    require_once __DIR__ . '/../BaseDatos/db.php';
    try {
        $empresas = array_values(array_filter(empresasCuenta(getPDO(), (int) $_SESSION['usuario_id']),
            fn(array $vinculo): bool => (bool) $vinculo['cuenta_habilitada'] && (bool) $vinculo['empresa_habilitada']));
        if (!$empresas) {
            throw new DomainException('La cuenta no tiene una empresa vinculada y habilitada.');
        }
        $empresa = $empresas[0];
        foreach ($empresas as $vinculo) {
            if ((int) $vinculo['id_empresa'] === (int) ($_SESSION['empresa_id'] ?? 0)) {
                $empresa = $vinculo;
                break;
            }
        }
    } catch (DomainException $e) {
        error_log('Sesión invalidada: ' . $e->getMessage());
        cerrarSesion();
        return false;
    } catch (PDOException $e) {
        error_log('No se pudo validar la empresa de la sesión: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar la sesión. Inténtalo más tarde.');
    }
    $_SESSION['empresa_id'] = $empresa['id_empresa'];
    $_SESSION['empresa_nombre'] = $empresa['nombre_empresa'];
    return true;
}

function requerirSesion(): void
{
    if (!haySesionActiva()) {
        header('Location: ' . URL_LOGIN . '?expirada=1');
        exit;
    }
}

function usuarioActual(): ?array
{
    if (!haySesionActiva()) {
        return null;
    }
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['nombre'] ?? '',
    ];
}

function cerrarSesion(): void
{
    iniciarSesionSegura();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
