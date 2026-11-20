-- Solo para una BD existente, antes de desplegar el PHP nuevo.
-- Hacer un respaldo y poner la aplicación en mantenimiento hasta desplegar
-- el PHP nuevo. Confirmar que todas las vigencias se interpretaban en UTC-05:00.
-- Ejecutar con modo SQL estricto y detenerse ante cualquier error.
-- No importar bd_ultimo.sql sobre una BD existente.
ALTER TABLE locales ADD COLUMN zonaHoraria varchar(64) NULL AFTER direccionLocal;

-- Si falla después del primer ALTER, hacer ROLLBACK si sigue abierta la
-- transacción y retomar desde START TRANSACTION. NULL indica lo pendiente.
START TRANSACTION;
UPDATE accesos a
INNER JOIN locales l ON l.idLocal = a.idLocal AND l.idEmpresa = a.idEmpresa
SET a.activoDesde = DATE_ADD(a.activoDesde, INTERVAL 5 HOUR),
    a.activoHasta = DATE_ADD(a.activoHasta, INTERVAL 5 HOUR)
WHERE l.zonaHoraria IS NULL;

UPDATE locales SET zonaHoraria = 'America/Guayaquil' WHERE zonaHoraria IS NULL;
COMMIT;

ALTER TABLE locales MODIFY COLUMN zonaHoraria varchar(64) NOT NULL DEFAULT 'America/Guayaquil';
