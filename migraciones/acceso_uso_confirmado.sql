-- Ejecutar una sola vez en la base de OpenDoors, con respaldo y la
-- aplicacion detenida. El DDL no es atomico.
-- Resolver todas las ordenes pendientes antes de ejecutar la migracion.
-- Los accesos marcados como usados por la version anterior no se modifican:
-- no es posible determinar desde esos datos si la apertura tuvo exito.
DROP PROCEDURE IF EXISTS verificar_acceso_uso_confirmado;
DELIMITER $$
CREATE PROCEDURE verificar_acceso_uso_confirmado()
BEGIN
    IF EXISTS (SELECT 1 FROM locales WHERE accionar = 1 OR aperturaEstado = 'pendiente') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay ordenes pendientes; resolverlas antes de migrar';
    END IF;
END$$
DELIMITER ;
CALL verificar_acceso_uso_confirmado();
DROP PROCEDURE verificar_acceso_uso_confirmado;

ALTER TABLE locales
    ADD COLUMN aperturaIdAcceso int DEFAULT NULL,
    ADD UNIQUE KEY uq_locales_apertura_acceso (aperturaIdAcceso),
    ADD CONSTRAINT fk_locales_apertura_acceso FOREIGN KEY (aperturaIdAcceso)
        REFERENCES accesos (idAcceso) ON DELETE RESTRICT ON UPDATE RESTRICT;
