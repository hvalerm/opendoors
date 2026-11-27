<?php
require_once __DIR__ . '/../BaseDatos/db.php';

function moduloDisponible(int $idEmpresa, string $nombreModulo): bool
{
    $stmt = getPDO()->prepare(
        'SELECT m.nombreModulo FROM empresas_modulos em
         INNER JOIN modulos m ON m.idModulo = em.idModulo
         WHERE em.idEmpresa = ? AND em.habilitado = 1 AND DATE(em.licenciaExpira) >= CURRENT_DATE'
    );
    $stmt->execute([$idEmpresa]);
    return in_array($nombreModulo, $stmt->fetchAll(PDO::FETCH_COLUMN), true);
}

function requerirModulo(string $nombreModulo, ?int $idEmpresa = null): void
{
    try {
        if (!moduloDisponible($idEmpresa ?? (int) $_SESSION['empresa_id'], $nombreModulo)) {
            http_response_code(403);
            exit('El módulo no está habilitado o su licencia ha vencido.');
        }
    } catch (PDOException $e) {
        error_log('No se pudo validar la licencia del módulo: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar el módulo. Inténtalo más tarde.');
    }
}

function empresaModulo(string $nombreModulo): array
{
    $parametro = $_GET['empresa'] ?? $_POST['empresa'] ?? $_SESSION['empresa_id'];
    $idEmpresa = filter_var($parametro, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idEmpresa || (isset($_GET['empresa'], $_POST['empresa'])
        && (!is_scalar($_POST['empresa']) || (string) $_GET['empresa'] !== (string) $_POST['empresa']))) {
        http_response_code(400);
        exit('Empresa no válida.');
    }
    try {
        $empresa = empresaVinculadaCuenta(getPDO(), (int) $_SESSION['usuario_id'], $idEmpresa);
        requerirModulo($nombreModulo, $idEmpresa);
        return $empresa;
    } catch (DomainException $e) {
        http_response_code(403);
        exit('No tienes acceso a esta empresa.');
    } catch (PDOException $e) {
        error_log('No se pudo validar la empresa del módulo: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar la empresa. Inténtalo más tarde.');
    }
}

function empresaOpenDoors(): array
{
    return empresaModulo('OpenDoors');
}

function urlEmpresaOpenDoors(string $url, int $idEmpresa): string
{
    return isset($_GET['empresa']) || isset($_POST['empresa'])
        ? $url . (str_contains($url, '?') ? '&' : '?') . 'empresa=' . $idEmpresa
        : $url;
}
