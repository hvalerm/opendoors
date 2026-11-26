-- Para una base creada con el opendoors.sql anterior a esta adaptacion.
-- Ejecutar una sola vez, con respaldo y la aplicacion detenida. El DDL no es
-- transaccional: ante un error, detenerse y revisar el estado antes de seguir.
-- El contador anterior incluia cualquier cambio de accionar de 1 a 0.
DROP TRIGGER IF EXISTS trg_after_update_accionar_local;
DROP TRIGGER IF EXISTS trg_after_update_cantidadAperturas_microcontrolador;

ALTER TABLE microcontroladores
  CHANGE cantidadAperturas cantidadAperturasExitosas int NOT NULL DEFAULT 0,
  ADD cantidadAperturasFallidas int NOT NULL DEFAULT 0;

CREATE TABLE solicitudes_apertura (
  idSolicitud char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  idLocal int NOT NULL,
  idEmpresa int NOT NULL,
  estado enum('pendiente', 'exitosa', 'fallida', 'cancelada') NOT NULL,
  solicitadaEn datetime NOT NULL,
  venceEn datetime NOT NULL,
  finalizadaEn datetime NULL,
  KEY ix_solicitudes_estado_vencimiento (estado, venceEn),
  KEY ix_solicitudes_local (idLocal, idEmpresa, estado),
  FOREIGN KEY (idLocal, idEmpresa) REFERENCES locales (idLocal, idEmpresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO solicitudes_apertura (idSolicitud, idLocal, idEmpresa, estado, solicitadaEn, venceEn)
SELECT SHA2(CONCAT(UUID(), ':', idLocal, ':', idEmpresa), 256), idLocal, idEmpresa,
       'pendiente', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 SECOND)
FROM locales WHERE accionar = 1;

DELIMITER $$
CREATE TRIGGER trg_after_update_accionar_local
AFTER UPDATE ON locales FOR EACH ROW
BEGIN
    IF OLD.accionar <> NEW.accionar THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (NEW.idLocal, NEW.idEmpresa,
            CONCAT('[UPDATE] Modificación en estado Accionar. Anterior: ',
                   OLD.accionar, ' | Nuevo: ', NEW.accionar));
    END IF;
END$$

CREATE TRIGGER trg_after_update_cantidadAperturas_microcontrolador
AFTER UPDATE ON microcontroladores FOR EACH ROW
BEGIN
    IF OLD.cantidadAperturasExitosas <> NEW.cantidadAperturasExitosas THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (NEW.idMicrocontrolador, NEW.idLocal, NEW.idEmpresa,
            CONCAT('[UPDATE] Modificación en cantidadAperturasExitosas. Anterior: ',
                   OLD.cantidadAperturasExitosas, ' | Nuevo: ', NEW.cantidadAperturasExitosas));
    END IF;
    IF OLD.cantidadAperturasFallidas <> NEW.cantidadAperturasFallidas THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (NEW.idMicrocontrolador, NEW.idLocal, NEW.idEmpresa,
            CONCAT('[UPDATE] Modificación en cantidadAperturasFallidas. Anterior: ',
                   OLD.cantidadAperturasFallidas, ' | Nuevo: ', NEW.cantidadAperturasFallidas));
    END IF;
END$$
DELIMITER ;
