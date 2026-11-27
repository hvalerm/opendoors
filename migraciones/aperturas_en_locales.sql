-- Ejecutar una sola vez en la base de OpenDoors, con respaldo y la
-- aplicacion y el procesador anteriores detenidos. El DDL no es atomico.
-- Primero asegurarse de que no existan ordenes pendientes (accionar = 1).
-- Los resultados anteriores sin consultar no se transfieren.
DROP PROCEDURE IF EXISTS verificar_aperturas_en_locales;
DELIMITER $$
CREATE PROCEDURE verificar_aperturas_en_locales()
BEGIN
    IF EXISTS (SELECT 1 FROM locales WHERE accionar = 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay ordenes pendientes; resolverlas antes de migrar';
    END IF;
END$$
DELIMITER ;
CALL verificar_aperturas_en_locales();
DROP PROCEDURE verificar_aperturas_en_locales;

ALTER TABLE locales
    ADD aperturaId char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ADD aperturaEstado enum('pendiente', 'exitosa', 'fallida', 'cancelada') DEFAULT NULL,
    ADD aperturaVenceEn datetime DEFAULT NULL;
