<?php
require_once __DIR__ . '/../../../util/Seguridad/verificar.php';
require_once __DIR__ . '/../../../util/BaseDatos/db.php';
require_once __DIR__ . '/../../../util/Seguridad/modulos.php';
require_once __DIR__ . '/../Nucleo/util/db.php';

function empresaConfiguracionLocales(): ?int
{
    return (int) empresaOpenDoors()['id_empresa'];
}

function datosLocalesConfiguracion(PDO $pdo, int $idEmpresa, ?int $idLocal = null): array
{
    $sql = 'SELECT l.idLocal, l.nombreLocal, l.direccionLocal, l.zonaHoraria, l.eliminado
            FROM locales l
            WHERE l.idEmpresa = ?';
    if ($idLocal !== null) {
        $stmt = $pdo->prepare($sql . ' AND l.idLocal = ?');
        $stmt->execute([$idEmpresa, $idLocal]);
    } else {
        $stmt = $pdo->prepare($sql . ' ORDER BY l.nombreLocal, l.idLocal');
        $stmt->execute([$idEmpresa]);
    }
    $locales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($locales as &$local) {
        $local['nombreEmpresa'] = empresaOpenDoors()['nombre_empresa'];
    }
    return $locales;
}
