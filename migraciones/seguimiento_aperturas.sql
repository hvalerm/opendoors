-- Ejecutar en la base opendoors antes de desplegar PHP, con respaldo y
-- la aplicacion en mantenimiento. Los campos cantidadAperturasExitosas y
-- cantidadAperturasFallidas deben existir (INT NOT NULL DEFAULT 0).
-- cantidadAperturas debe haberse renombrado conservando sus valores.
-- El trigger anterior de conteo debe eliminarse para no duplicar incrementos.
-- No se cambia aqui el esquema de empresas.
DROP TRIGGER IF EXISTS trg_after_update_accionar_local;

-- Un contador nuevo agregado sin DEFAULT puede contener NULL.
UPDATE microcontroladores
SET cantidadAperturasExitosas = COALESCE(cantidadAperturasExitosas, 0),
    cantidadAperturasFallidas = COALESCE(cantidadAperturasFallidas, 0)
WHERE cantidadAperturasExitosas IS NULL OR cantidadAperturasFallidas IS NULL;
ALTER TABLE microcontroladores
  MODIFY cantidadAperturasExitosas int NOT NULL DEFAULT 0,
  MODIFY cantidadAperturasFallidas int NOT NULL DEFAULT 0;

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

-- Las ordenes que ya estaban pendientes disponen de 30 segundos tras la carga.
INSERT INTO solicitudes_apertura (idSolicitud, idLocal, idEmpresa, estado, solicitadaEn, venceEn)
SELECT SHA2(CONCAT(UUID(), ':', idLocal, ':', idEmpresa), 256), idLocal, idEmpresa,
       'pendiente', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 SECOND)
FROM locales WHERE accionar = 1;
