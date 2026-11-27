-- Ejecutar una sola vez en la base de OpenDoors, con respaldo.
-- Primero resolver manualmente cualquier duplicado devuelto por:
-- SELECT idLocal, COUNT(*) AS dispositivos FROM microcontroladores
-- WHERE idLocal IS NOT NULL GROUP BY idLocal HAVING COUNT(*) > 1;
-- Si existen duplicados, el indice no se crea y no se modifica ningun registro.
ALTER TABLE microcontroladores
    ADD CONSTRAINT uq_microcontroladores_local UNIQUE (idLocal);
