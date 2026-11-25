<?php
require_once __DIR__ . '/../../../util/verificar.php';
require_once __DIR__ . '/../../../util/db.php';
require_once __DIR__ . '/../Nucleo/util/db.php';

function empresaConfiguracionLocales(): ?int
{
    $idEmpresa = (int) $_SESSION['empresa_id'];
    $stmt = getPDO()->prepare(
        'SELECT m.nombreModulo FROM empresas_modulos em
         INNER JOIN modulos m ON m.idModulo = em.idModulo
         WHERE em.idEmpresa = ? AND em.habilitado = 1'
    );
    $stmt->execute([$idEmpresa]);
    return in_array('OpenDoors', $stmt->fetchAll(PDO::FETCH_COLUMN), true) ? $idEmpresa : null;
}

function datosLocalesConfiguracion(PDO $pdo, int $idEmpresa, ?int $idLocal = null): array
{
    $sql = 'SELECT l.idLocal, l.nombreLocal, l.direccionLocal, l.zonaHoraria, e.nombreEmpresa, l.eliminado
            FROM locales l
            INNER JOIN empresas e ON e.idEmpresa = l.idEmpresa
            WHERE l.idEmpresa = ?';
    if ($idLocal !== null) {
        $stmt = $pdo->prepare($sql . ' AND l.idLocal = ?');
        $stmt->execute([$idEmpresa, $idLocal]);
    } else {
        $stmt = $pdo->prepare($sql . ' ORDER BY l.nombreLocal, l.idLocal');
        $stmt->execute([$idEmpresa]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
