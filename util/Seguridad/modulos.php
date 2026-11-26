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

function requerirModulo(string $nombreModulo): void
{
    try {
        if (!moduloDisponible((int) $_SESSION['empresa_id'], $nombreModulo)) {
            http_response_code(403);
            exit('El módulo no está habilitado o su licencia ha vencido.');
        }
    } catch (PDOException $e) {
        error_log('No se pudo validar la licencia del módulo: ' . $e->getMessage());
        http_response_code(503);
        exit('No se pudo validar el módulo. Inténtalo más tarde.');
    }
}
