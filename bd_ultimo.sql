-- =====================================================
-- OpenDoors_V2 - Estructura completa de la base de datos
-- 1) Tablas (ordenadas por dependencias)
-- 2) Triggers
-- =====================================================

-- MySQL no configura la zona horaria por esquema.
-- La aplicacion debe ejecutar este SET al abrir cada conexion.
SET SESSION time_zone = '-05:00';

-- ==================== TABLAS ====================

CREATE TABLE empresas (
  idEmpresa int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombreEmpresa varchar(50) NOT NULL DEFAULT 'SIN ASIGNAR',
  duenoEmpresa varchar(100) NOT NULL,
  telefonoContacto varchar(30) NOT NULL DEFAULT '+51000000000',
  licenciaExpira datetime NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
  inicio boolean NOT NULL DEFAULT 1,
  habilitado boolean NOT NULL DEFAULT 1,
  CONSTRAINT chk_empresas_inicio_boolean CHECK (inicio IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalogo de complementos disponibles para las empresas.
CREATE TABLE modulos (
  idModulo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombreModulo varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE empresas_modulos (
  idEmpresa int NOT NULL,
  idModulo int NOT NULL,
  habilitado boolean NOT NULL DEFAULT 0,
  PRIMARY KEY (idEmpresa, idModulo),
  KEY idModulo (idModulo),
  FOREIGN KEY (idEmpresa) REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (idModulo) REFERENCES modulos (idModulo) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cargos (
  idCargo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombreCargo varchar(100) NOT NULL,
  estado boolean NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locales (
  idLocal int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombreLocal varchar(50) NOT NULL,
  direccionLocal varchar(100) NOT NULL,
  zonaHoraria varchar(64) NOT NULL DEFAULT 'America/Guayaquil',
  idEmpresa int NOT NULL,
  accionar boolean NOT NULL DEFAULT 0,
  habilitado boolean NOT NULL DEFAULT 1,
  eliminado boolean NOT NULL DEFAULT 0,
  UNIQUE KEY uq_local_empresa (idLocal,idEmpresa),
  KEY idEmpresa (idEmpresa),
  FOREIGN KEY (idEmpresa) REFERENCES empresas (idEmpresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cuentas (
  idCuenta int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  correoCuenta varchar(50) NOT NULL,
  nombreCuenta varchar(100) NOT NULL,
  pwd varchar(255) NOT NULL,
  habilitado boolean NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Una cuenta puede pertenecer a varias empresas, con un cargo por empresa.
-- Crear primero la cuenta y luego sus vínculos, preferiblemente en una transacción.
-- Las altas/bajas sin vínculos no generan logs por empresa; las actualizaciones
-- sin vínculos se bloquean. Desvincular explícitamente antes de borrar los padres.
CREATE TABLE empresas_cuentas (
  idEmpresa int NOT NULL,
  idCuenta int NOT NULL,
  idCargo int NOT NULL,
  PRIMARY KEY (idEmpresa, idCuenta),
  KEY idCuenta (idCuenta),
  KEY idCargo (idCargo),
  FOREIGN KEY (idEmpresa) REFERENCES empresas (idEmpresa) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (idCuenta) REFERENCES cuentas (idCuenta) ON DELETE RESTRICT ON UPDATE RESTRICT,
  FOREIGN KEY (idCargo) REFERENCES cargos (idCargo) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accesos (
  idAcceso int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  -- La vigencia se guarda en UTC y se muestra en la zona del local.
  activoDesde datetime NOT NULL,
  activoHasta datetime NOT NULL,
  idLocal int NOT NULL,
  idEmpresa int NOT NULL,
  token varchar(250) NOT NULL UNIQUE,
  ejecutado boolean NOT NULL DEFAULT 0,
  habilitado boolean NOT NULL DEFAULT 1,
  KEY idLocal (idLocal,idEmpresa),
  FOREIGN KEY (idLocal, idEmpresa) REFERENCES locales (idLocal, idEmpresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE microcontroladores (
  idMicrocontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idLocal int NOT NULL,
  idEmpresa int NOT NULL,
  ultimaConsulta datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimaApertura datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cantidadAperturas int NOT NULL DEFAULT '0',
  habilitado boolean NOT NULL DEFAULT 1,
  KEY idLocal (idLocal,idEmpresa),
  FOREIGN KEY (idLocal, idEmpresa) REFERENCES locales (idLocal, idEmpresa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Control de intentos de login (protección contra fuerza bruta). Seguro de ejecutar más de una vez.
CREATE TABLE IF NOT EXISTS intentos_login (
  usuario varchar(255) NOT NULL PRIMARY KEY,
  intentos int NOT NULL DEFAULT 0,
  bloqueadoHasta datetime NULL DEFAULT NULL,
  ultimoIntento datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Códigos de recuperación de contraseña (10 dígitos, 5 minutos). Seguro de ejecutar más de una vez.
CREATE TABLE IF NOT EXISTS recuperacion_password (
  idCuenta int NOT NULL PRIMARY KEY,
  codigoHash varchar(255) NOT NULL,
  expira datetime NOT NULL,
  intentos int NOT NULL DEFAULT 0,
  verificado boolean NOT NULL DEFAULT 0,
  ultimoEnvio datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idCuenta) REFERENCES cuentas (idCuenta) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- soporte se crea vacía: permite una única inserción y luego solo actualizaciones.
-- Los triggers no bloquean TRUNCATE ni DROP: no otorgar esos permisos a la aplicación.
CREATE TABLE soporte (
  idSoporte tinyint NOT NULL DEFAULT 1 PRIMARY KEY,
  telefonoContacto varchar(50) NOT NULL DEFAULT '+51000000000',
  correoContacto varchar(50) NOT NULL DEFAULT 'correo@ejemplo.com',
  CONSTRAINT chk_soporte_registro_unico CHECK (idSoporte = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablas de logs

-- Auditoría global de soporte, independiente de logs_general y sin claves foráneas.
CREATE TABLE logs_soporte (
  idLogSoporte int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idSoporte tinyint NOT NULL,
  descripcionLogSoporte text NOT NULL,
  fechaRegistroLogSoporte datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_accesos (
  idLogAcceso int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idAcceso int NOT NULL,
  idEmpresa int NOT NULL,
  descripcionLogAcceso text NOT NULL,
  fechaRegistroLogAcceso datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_cuentas (
  idLogCuenta int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idCuenta int NOT NULL,
  idEmpresa int NOT NULL,
  descripcionLogCuenta text NOT NULL,
  fechaRegistroLogCuenta datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sin claves foráneas para conservar el historial tras eliminar el vínculo o sus padres.
CREATE TABLE logs_empresas_cuentas (
  idLogEmpresaCuenta int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idCuenta int NOT NULL,
  idCargo int NOT NULL,
  descripcionLogEmpresaCuenta text NOT NULL,
  fechaRegistroLogEmpresaCuenta datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sin claves foráneas para conservar el historial después de eliminar los registros.
CREATE TABLE logs_modulos (
  idLogModulo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idModulo int NOT NULL,
  descripcionLogModulo text NOT NULL,
  fechaRegistroLogModulo datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_empresas_modulos (
  idLogEmpresaModulo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idModulo int NOT NULL,
  descripcionLogEmpresaModulo text NOT NULL,
  fechaRegistroLogEmpresaModulo datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_empresas (
  idLogEmpresa int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  descripcionLogEmpresa text NOT NULL,
  fechaRegistroLogEmpresa datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_locales (
  idLogLocal int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idLocal int NOT NULL,
  idEmpresa int NOT NULL,
  descripcionLogLocal text NOT NULL,
  fechaRegistroLogLocal datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_cargos (
  idLogCargo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idCargo int NOT NULL,
  descripcionLogCargo text NOT NULL,
  fechaRegistroLogCargo datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_microcontroladores (
  idLogMicrocontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idMicrocontrolador int NOT NULL,
  idLocal int NOT NULL,
  idEmpresa int NOT NULL,
  descripcionLogMicrocontrolador text NOT NULL,
  fechaRegistroLogMicrocontrolador datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_general (
  idLogGeneral int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tablaAfectada varchar(64) NOT NULL,
  idRegistro int NOT NULL,
  idEmpresa int NOT NULL,
  tipoAccion varchar(50) NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablas de logs de acciones no permitidas.
-- En Aiven se usa InnoDB: si un trigger ejecuta SIGNAL, estos logs pueden revertirse
-- junto con la operación que los generó.

CREATE TABLE logs_acciones_no_permitidas_accesos (
  idLogAcceso int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idAcceso int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_cargos (
  idLogCargo int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idCargo int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_cuentas (
  idLogCuenta int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idCuenta int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_empresas (
  idLogEmpresa int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_locales (
  idLogLocal int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idLocal int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_microcontroladores (
  idLogMicrocontrolador int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idEmpresa int NOT NULL,
  idMicrocontrolador int NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs_acciones_no_permitidas_general (
  idLogGeneral int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tablaAfectada varchar(64) NOT NULL,
  idEmpresa int NOT NULL,
  idRegistro int NOT NULL,
  tipoAccion varchar(50) NOT NULL,
  descripcionLog text NOT NULL,
  fechaRegistro datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== TRIGGERS ====================
-- Las comparaciones BINARY detectan cambios de mayúsculas, acentos y espacios finales.

DELIMITER $$

-- --------------- Triggers base (empresas y logs -> logs_general) ---------------
CREATE TRIGGER trg_after_delete_empresa
AFTER DELETE ON empresas
FOR EACH ROW
BEGIN
    INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
    VALUES (
        OLD.idEmpresa,
        CONCAT('[DELETE] Se eliminó el registro de la empresa ID: ', OLD.idEmpresa)
    );
END$$

CREATE TRIGGER trg_after_insert_empresa
AFTER INSERT ON empresas
FOR EACH ROW
BEGIN
    INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
    VALUES (
        NEW.idEmpresa,
        CONCAT('[INSERT] Se creó una nueva empresa con ID: ', NEW.idEmpresa)
    );

    -- Crear los vínculos con los módulos disponibles, inicialmente deshabilitados.
    INSERT INTO empresas_modulos (idEmpresa, idModulo, habilitado)
    SELECT NEW.idEmpresa, idModulo, 0
    FROM modulos;
END$$

CREATE TRIGGER trg_after_update_habilitado_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_nombre_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF BINARY OLD.nombreEmpresa <> BINARY NEW.nombreEmpresa THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en nombre de empresa. Anterior: ',
                OLD.nombreEmpresa,
                ' | Nuevo: ',
                NEW.nombreEmpresa
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_dueno_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF BINARY OLD.duenoEmpresa <> BINARY NEW.duenoEmpresa THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en dueño de empresa. Anterior: ',
                OLD.duenoEmpresa,
                ' | Nuevo: ',
                NEW.duenoEmpresa
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_licencia_expira_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.licenciaExpira <> NEW.licenciaExpira THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en vencimiento de licencia. Anterior: ',
                OLD.licenciaExpira,
                ' | Nuevo: ',
                NEW.licenciaExpira
            )
        );
    END IF;
END$$

-- Una vez desactivado, inicio no puede volver a cambiar; los demás campos sí.
CREATE TRIGGER trg_before_update_inicio_empresa
BEFORE UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.inicio = 0 AND NOT (NEW.inicio <=> OLD.inicio) THEN
        INSERT INTO logs_acciones_no_permitidas_empresas (idEmpresa, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de inicio. Original: ',
                OLD.inicio,
                ' | Intentado: ',
                COALESCE(CAST(NEW.inicio AS CHAR), 'NULL')
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede modificar inicio una vez que su valor es 0.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_inicio_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.inicio <> NEW.inicio THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en inicio de empresa. Anterior: ',
                OLD.inicio,
                ' | Nuevo: ',
                NEW.inicio
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_telefonoContacto_empresa
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF BINARY OLD.telefonoContacto <> BINARY NEW.telefonoContacto THEN
        INSERT INTO logs_empresas (idEmpresa, descripcionLogEmpresa)
        VALUES (
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en teléfono de contacto. Anterior: ',
                OLD.telefonoContacto,
                ' | Nuevo: ',
                NEW.telefonoContacto
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_empresa_empresas
AFTER UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_empresas (idEmpresa, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Modificado: ',
                NEW.idEmpresa
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_no_permitida_id_empresa_empresas
BEFORE UPDATE ON empresas
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_empresas (idEmpresa, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Intentado: ',
                NEW.idEmpresa
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idEmpresa.';
    END IF;
END$$

CREATE TRIGGER trg_after_insert_logs_normales_general_accesos
AFTER INSERT ON logs_accesos
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog
    )
    VALUES (
        'Accesos',
        NEW.idAcceso,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogAcceso LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogAcceso LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogAcceso LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogAcceso
    );
END$$

CREATE TRIGGER trg_after_insert_logs_normales_general_cuentas
AFTER INSERT ON logs_cuentas
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog
    )
    VALUES (
        'Cuentas',
        NEW.idCuenta,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogCuenta LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogCuenta LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogCuenta LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogCuenta
    );
END$$

-- idRegistro identifica la cuenta; junto con idEmpresa identifica el vínculo.
CREATE TRIGGER trg_after_insert_logs_normales_general_empresas_cuentas
AFTER INSERT ON logs_empresas_cuentas
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog,
        fechaRegistro
    )
    VALUES (
        'Empresas_Cuentas',
        NEW.idCuenta,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogEmpresaCuenta LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogEmpresaCuenta LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogEmpresaCuenta LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogEmpresaCuenta,
        NEW.fechaRegistroLogEmpresaCuenta
    );
END$$

-- idRegistro identifica el módulo; junto con idEmpresa identifica el vínculo.
CREATE TRIGGER trg_after_insert_logs_normales_general_empresas_modulos
AFTER INSERT ON logs_empresas_modulos
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog,
        fechaRegistro
    )
    VALUES (
        'Empresas_Modulos',
        NEW.idModulo,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogEmpresaModulo LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogEmpresaModulo LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogEmpresaModulo LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogEmpresaModulo,
        NEW.fechaRegistroLogEmpresaModulo
    );
END$$

CREATE TRIGGER trg_after_insert_logs_normales_general_empresas
AFTER INSERT ON logs_empresas
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog
    )
    VALUES (
        'Empresas',
        NEW.idEmpresa,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogEmpresa LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogEmpresa LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogEmpresa LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogEmpresa
    );
END$$

CREATE TRIGGER trg_after_insert_logs_normales_general_locales
AFTER INSERT ON logs_locales
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog
    )
    VALUES (
        'Locales',
        NEW.idLocal,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogLocal LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogLocal LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogLocal LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogLocal
    );
END$$

-- --------------- Triggers de módulos y asignaciones ---------------
CREATE TRIGGER trg_after_insert_modulo
AFTER INSERT ON modulos
FOR EACH ROW
BEGIN
    INSERT INTO logs_modulos (idModulo, descripcionLogModulo)
    VALUES (
        NEW.idModulo,
        CONCAT('[INSERT] Se creó el módulo ID: ', NEW.idModulo, ' | Nombre: ', NEW.nombreModulo)
    );

    -- Completar también las relaciones faltantes de módulos anteriores.
    INSERT INTO empresas_modulos (idEmpresa, idModulo, habilitado)
    SELECT e.idEmpresa, m.idModulo, 0
    FROM empresas AS e
    CROSS JOIN modulos AS m
    WHERE NOT EXISTS (
        SELECT 1
        FROM empresas_modulos AS em
        WHERE em.idEmpresa = e.idEmpresa
          AND em.idModulo = m.idModulo
    );
END$$

CREATE TRIGGER trg_after_delete_modulo
AFTER DELETE ON modulos
FOR EACH ROW
INSERT INTO logs_modulos (idModulo, descripcionLogModulo)
VALUES (
    OLD.idModulo,
    CONCAT('[DELETE] Se eliminó el módulo ID: ', OLD.idModulo, ' | Nombre: ', OLD.nombreModulo)
)$$

CREATE TRIGGER trg_after_update_modulo
AFTER UPDATE ON modulos
FOR EACH ROW
INSERT INTO logs_modulos (idModulo, descripcionLogModulo)
SELECT
    NEW.idModulo,
    CONCAT('[UPDATE] Se modificó el módulo. ID anterior: ', OLD.idModulo,
           ' | ID nuevo: ', NEW.idModulo,
           ' | Nombre anterior: ', OLD.nombreModulo,
           ' | Nombre nuevo: ', NEW.nombreModulo)
FROM DUAL
WHERE OLD.idModulo <> NEW.idModulo OR BINARY OLD.nombreModulo <> BINARY NEW.nombreModulo$$

CREATE TRIGGER trg_after_insert_empresa_modulo
AFTER INSERT ON empresas_modulos
FOR EACH ROW
INSERT INTO logs_empresas_modulos (idEmpresa, idModulo, descripcionLogEmpresaModulo)
VALUES (
    NEW.idEmpresa,
    NEW.idModulo,
    CONCAT('[INSERT] Se asignó el módulo ID: ', NEW.idModulo, ' a la empresa ID: ', NEW.idEmpresa,
           ' | Habilitado: ', NEW.habilitado)
)$$

CREATE TRIGGER trg_after_delete_empresa_modulo
AFTER DELETE ON empresas_modulos
FOR EACH ROW
INSERT INTO logs_empresas_modulos (idEmpresa, idModulo, descripcionLogEmpresaModulo)
VALUES (
    OLD.idEmpresa,
    OLD.idModulo,
    CONCAT('[DELETE] Se desasignó el módulo ID: ', OLD.idModulo, ' de la empresa ID: ', OLD.idEmpresa,
           ' | Habilitado: ', OLD.habilitado)
)$$

CREATE TRIGGER trg_after_update_empresa_modulo
AFTER UPDATE ON empresas_modulos
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa OR OLD.idModulo <> NEW.idModulo THEN
        INSERT INTO logs_empresas_modulos (idEmpresa, idModulo, descripcionLogEmpresaModulo)
        VALUES
            (OLD.idEmpresa, OLD.idModulo,
             CONCAT('[UPDATE] Se retiró la asignación del módulo ID: ', OLD.idModulo,
                    ' para la empresa ID: ', OLD.idEmpresa, ' | Habilitado: ', OLD.habilitado)),
            (NEW.idEmpresa, NEW.idModulo,
             CONCAT('[UPDATE] Se agregó la asignación del módulo ID: ', NEW.idModulo,
                    ' para la empresa ID: ', NEW.idEmpresa, ' | Habilitado: ', NEW.habilitado));
    END IF;

    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_empresas_modulos (idEmpresa, idModulo, descripcionLogEmpresaModulo)
        VALUES (
            NEW.idEmpresa,
            NEW.idModulo,
            CONCAT('[UPDATE] Modificación en estado habilitado. Anterior: ', OLD.habilitado,
                   ' | Nuevo: ', NEW.habilitado)
        );
    END IF;
END$$

-- --------------- Triggers de cargos -> logs_cargos ---------------
CREATE TRIGGER trg_after_insert_cargo
AFTER INSERT ON cargos
FOR EACH ROW
INSERT INTO logs_cargos (idCargo, descripcionLogCargo)
VALUES (
    NEW.idCargo,
    CONCAT('[INSERT] Se creó un nuevo cargo con ID: ', NEW.idCargo)
)$$

CREATE TRIGGER trg_after_delete_cargo
AFTER DELETE ON cargos
FOR EACH ROW
INSERT INTO logs_cargos (idCargo, descripcionLogCargo)
VALUES (
    OLD.idCargo,
    CONCAT('[DELETE] Se eliminó el registro del cargo ID: ', OLD.idCargo)
)$$

CREATE TRIGGER trg_after_update_nombre_cargo
AFTER UPDATE ON cargos
FOR EACH ROW
INSERT INTO logs_cargos (idCargo, descripcionLogCargo)
SELECT
    NEW.idCargo,
    CONCAT('[UPDATE] Modificación en nombre de cargo. Anterior: ', OLD.nombreCargo, ' | Nuevo: ', NEW.nombreCargo)
FROM DUAL
WHERE BINARY OLD.nombreCargo <> BINARY NEW.nombreCargo$$

CREATE TRIGGER trg_after_update_estado_cargo
AFTER UPDATE ON cargos
FOR EACH ROW
INSERT INTO logs_cargos (idCargo, descripcionLogCargo)
SELECT
    NEW.idCargo,
    CONCAT('[UPDATE] Modificación en estado del cargo. Anterior: ', OLD.estado, ' | Nuevo: ', NEW.estado)
FROM DUAL
WHERE OLD.estado <> NEW.estado$$

CREATE TRIGGER trg_before_update_no_permitida_id_cargo_cargos
BEFORE UPDATE ON cargos
FOR EACH ROW
BEGIN
    IF OLD.idCargo <> NEW.idCargo THEN
        INSERT INTO logs_acciones_no_permitidas_cargos (idCargo, descripcionLog)
        VALUES (
            OLD.idCargo,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idCargo. Original: ',
                OLD.idCargo,
                ' | Intentado: ',
                NEW.idCargo
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idCargo.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_cargo_cargos
AFTER UPDATE ON cargos
FOR EACH ROW
BEGIN
    IF OLD.idCargo <> NEW.idCargo THEN
        INSERT INTO logs_acciones_no_permitidas_cargos (idCargo, descripcionLog)
        VALUES (
            OLD.idCargo,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idCargo. Original: ',
                OLD.idCargo,
                ' | Modificado: ',
                NEW.idCargo
            )
        );
    END IF;
END$$

-- --------------- logs_acciones_no_permitidas_* -> logs_acciones_no_permitidas_general ---------------
CREATE TRIGGER trg_after_insert_no_permitida_accesos
AFTER INSERT ON logs_acciones_no_permitidas_accesos
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tablaAfectada,
    idEmpresa,
    idRegistro,
    tipoAccion,
    descripcionLog,
    fechaRegistro
)
VALUES (
    'Accesos',
    NEW.idEmpresa,
    NEW.idAcceso,
    'ACCION_NO_PERMITIDA',
    NEW.descripcionLog,
    NEW.fechaRegistro
)$$

CREATE TRIGGER trg_after_insert_no_permitida_cuentas
AFTER INSERT ON logs_acciones_no_permitidas_cuentas
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tablaAfectada,
    idEmpresa,
    idRegistro,
    tipoAccion,
    descripcionLog,
    fechaRegistro
)
VALUES (
    'Cuentas',
    NEW.idEmpresa,
    NEW.idCuenta,
    'ACCION_NO_PERMITIDA',
    NEW.descripcionLog,
    NEW.fechaRegistro
)$$

CREATE TRIGGER trg_after_insert_no_permitida_empresas
AFTER INSERT ON logs_acciones_no_permitidas_empresas
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tablaAfectada,
    idEmpresa,
    idRegistro,
    tipoAccion,
    descripcionLog,
    fechaRegistro
)
VALUES (
    'Empresas',
    NEW.idEmpresa,
    NEW.idEmpresa,
    'ACCION_NO_PERMITIDA',
    NEW.descripcionLog,
    NEW.fechaRegistro
)$$

CREATE TRIGGER trg_after_insert_no_permitida_locales
AFTER INSERT ON logs_acciones_no_permitidas_locales
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tablaAfectada,
    idEmpresa,
    idRegistro,
    tipoAccion,
    descripcionLog,
    fechaRegistro
)
VALUES (
    'Locales',
    NEW.idEmpresa,
    NEW.idLocal,
    'ACCION_NO_PERMITIDA',
    NEW.descripcionLog,
    NEW.fechaRegistro
)$$

-- --------------- ACCESOS ---------------
CREATE TRIGGER trg_after_insert_acceso
AFTER INSERT ON accesos
FOR EACH ROW
BEGIN
    INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
    VALUES (
        NEW.idAcceso,
        NEW.idEmpresa,
        CONCAT('[INSERT] Se creó un nuevo acceso con ID: ', NEW.idAcceso)
    );
END$$

CREATE TRIGGER trg_after_delete_acceso
AFTER DELETE ON accesos
FOR EACH ROW
BEGIN
    INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
    VALUES (
        OLD.idAcceso,
        OLD.idEmpresa,
        CONCAT('[DELETE] Se eliminó el registro del acceso ID: ', OLD.idAcceso)
    );
END$$

CREATE TRIGGER trg_after_update_id_local_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.idLocal <> NEW.idLocal THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en el local del acceso. Anterior: ',
                OLD.idLocal,
                ' | Nuevo: ',
                NEW.idLocal
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_activo_desde_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.activoDesde <> NEW.activoDesde THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en activoDesde. Anterior: ',
                OLD.activoDesde,
                ' | Nuevo: ',
                NEW.activoDesde
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_activo_hasta_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.activoHasta <> NEW.activoHasta THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en activoHasta. Anterior: ',
                OLD.activoHasta,
                ' | Nuevo: ',
                NEW.activoHasta
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_token_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF BINARY OLD.token <> BINARY NEW.token THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            '[UPDATE] Modificación en el token del acceso.'
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_ejecutado_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.ejecutado <> NEW.ejecutado THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado Ejecutado. Anterior: ',
                OLD.ejecutado,
                ' | Nuevo: ',
                NEW.ejecutado
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_habilitado_acceso
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_accesos (idAcceso, idEmpresa, descripcionLogAcceso)
        VALUES (
            NEW.idAcceso,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        );
    END IF;
END$$

-- --------------- CUENTAS ---------------
CREATE TRIGGER trg_before_update_cuenta_sin_empresa
BEFORE UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM empresas_cuentas WHERE idCuenta = OLD.idCuenta
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede modificar una cuenta sin empresas vinculadas.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_nombre_cuenta
AFTER UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF BINARY OLD.nombreCuenta <> BINARY NEW.nombreCuenta THEN
        INSERT INTO logs_cuentas (idCuenta, idEmpresa, descripcionLogCuenta)
        SELECT
            NEW.idCuenta,
            ec.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en nombre de cuenta. Anterior: ',
                OLD.nombreCuenta,
                ' | Nuevo: ',
                NEW.nombreCuenta
            )
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = NEW.idCuenta;
    END IF;
END$$

CREATE TRIGGER trg_after_update_correo_cuenta
AFTER UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF BINARY OLD.correoCuenta <> BINARY NEW.correoCuenta THEN
        INSERT INTO logs_cuentas (idCuenta, idEmpresa, descripcionLogCuenta)
        SELECT
            NEW.idCuenta,
            ec.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en correo de cuenta. Anterior: ',
                OLD.correoCuenta,
                ' | Nuevo: ',
                NEW.correoCuenta
            )
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = NEW.idCuenta;
    END IF;
END$$

-- --------------- EMPRESAS_CUENTAS ---------------
CREATE TRIGGER trg_after_insert_empresa_cuenta
AFTER INSERT ON empresas_cuentas
FOR EACH ROW
BEGIN
    INSERT INTO logs_empresas_cuentas (idCuenta, idEmpresa, idCargo, descripcionLogEmpresaCuenta)
    VALUES (
        NEW.idCuenta,
        NEW.idEmpresa,
        NEW.idCargo,
        CONCAT('[INSERT] Se vinculó la cuenta ID: ', NEW.idCuenta,
               ' a la empresa ID: ', NEW.idEmpresa, ' | Cargo: ', NEW.idCargo)
    );
END$$

CREATE TRIGGER trg_after_delete_empresa_cuenta
AFTER DELETE ON empresas_cuentas
FOR EACH ROW
BEGIN
    INSERT INTO logs_empresas_cuentas (idCuenta, idEmpresa, idCargo, descripcionLogEmpresaCuenta)
    VALUES (
        OLD.idCuenta,
        OLD.idEmpresa,
        OLD.idCargo,
        CONCAT('[DELETE] Se desvinculó la cuenta ID: ', OLD.idCuenta,
               ' de la empresa ID: ', OLD.idEmpresa, ' | Cargo: ', OLD.idCargo)
    );
END$$

CREATE TRIGGER trg_before_update_identificadores_empresa_cuenta
BEFORE UPDATE ON empresas_cuentas
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa OR OLD.idCuenta <> NEW.idCuenta THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se pueden modificar los identificadores del vínculo; elimínelo y cree otro.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_cargo_empresa_cuenta
AFTER UPDATE ON empresas_cuentas
FOR EACH ROW
BEGIN
    IF OLD.idCargo <> NEW.idCargo THEN
        INSERT INTO logs_empresas_cuentas (idCuenta, idEmpresa, idCargo, descripcionLogEmpresaCuenta)
        VALUES (
            NEW.idCuenta,
            NEW.idEmpresa,
            NEW.idCargo,
            CONCAT(
                '[UPDATE] Modificación en cargo de cuenta. Anterior: ',
                OLD.idCargo,
                ' | Nuevo: ',
                NEW.idCargo
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_pwd_cuenta
AFTER UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF BINARY OLD.pwd <> BINARY NEW.pwd THEN
        INSERT INTO logs_cuentas (idCuenta, idEmpresa, descripcionLogCuenta)
        SELECT
            NEW.idCuenta,
            ec.idEmpresa,
            '[UPDATE] Modificación en la contraseña de la cuenta.'
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = NEW.idCuenta;
    END IF;
END$$

CREATE TRIGGER trg_after_update_habilitado_cuenta
AFTER UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_cuentas (idCuenta, idEmpresa, descripcionLogCuenta)
        SELECT
            NEW.idCuenta,
            ec.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = NEW.idCuenta;
    END IF;
END$$

-- --------------- LOCALES ---------------
CREATE TRIGGER trg_after_insert_local
AFTER INSERT ON locales
FOR EACH ROW
BEGIN
    INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
    VALUES (
        NEW.idLocal,
        NEW.idEmpresa,
        CONCAT('[INSERT] Se creó un nuevo local con ID: ', NEW.idLocal)
    );
END$$

CREATE TRIGGER trg_after_delete_local
AFTER DELETE ON locales
FOR EACH ROW
BEGIN
    INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
    VALUES (
        OLD.idLocal,
        OLD.idEmpresa,
        CONCAT('[DELETE] Se eliminó el registro del local ID: ', OLD.idLocal)
    );
END$$

CREATE TRIGGER trg_after_update_nombre_local
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF BINARY OLD.nombreLocal <> BINARY NEW.nombreLocal THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en nombre de local. Anterior: ',
                OLD.nombreLocal,
                ' | Nuevo: ',
                NEW.nombreLocal
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_direccion_local
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF BINARY OLD.direccionLocal <> BINARY NEW.direccionLocal THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en dirección de local. Anterior: ',
                OLD.direccionLocal,
                ' | Nuevo: ',
                NEW.direccionLocal
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_accionar_local
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.accionar <> NEW.accionar THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado Accionar. Anterior: ',
                OLD.accionar,
                ' | Nuevo: ',
                NEW.accionar
            )
        );
        IF OLD.accionar = 1 AND NEW.accionar = 0 THEN
            UPDATE microcontroladores
            SET cantidadAperturas = cantidadAperturas + 1,
                ultimaApertura = CURRENT_TIMESTAMP
            WHERE idLocal = NEW.idLocal
              AND idEmpresa = NEW.idEmpresa;
        END IF;
    END IF;
END$$

CREATE TRIGGER trg_after_update_habilitado_local
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_eliminado_local
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.eliminado <> NEW.eliminado THEN
        INSERT INTO logs_locales (idLocal, idEmpresa, descripcionLogLocal)
        VALUES (
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado Eliminado. Anterior: ',
                OLD.eliminado,
                ' | Nuevo: ',
                NEW.eliminado
            )
        );
    END IF;
END$$



-- =============== ACCIONES NO PERMITIDAS ===============


-- --------------- ACCIONES NO PERMITIDAS: ACCESOS ---------------
CREATE TRIGGER trg_before_update_no_permitida_id_acceso_accesos
BEFORE UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.idAcceso <> NEW.idAcceso THEN
        INSERT INTO logs_acciones_no_permitidas_accesos (idEmpresa, idAcceso, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idAcceso,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idAcceso. Original: ',
                OLD.idAcceso,
                ' | Intentado: ',
                NEW.idAcceso
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idAcceso.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_acceso_accesos
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.idAcceso <> NEW.idAcceso THEN
        INSERT INTO logs_acciones_no_permitidas_accesos (idEmpresa, idAcceso, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idAcceso,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idAcceso. Original: ',
                OLD.idAcceso,
                ' | Modificado: ',
                NEW.idAcceso
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_no_permitida_id_empresa_accesos
BEFORE UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_accesos (idEmpresa, idAcceso, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idAcceso,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Intentado: ',
                NEW.idEmpresa
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idEmpresa.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_empresa_accesos
AFTER UPDATE ON accesos
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_accesos (idEmpresa, idAcceso, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idAcceso,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Modificado: ',
                NEW.idEmpresa
            )
        );
    END IF;
END$$

-- --------------- ACCIONES NO PERMITIDAS: CUENTAS ---------------
CREATE TRIGGER trg_before_update_no_permitida_id_cuenta_cuentas
BEFORE UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF OLD.idCuenta <> NEW.idCuenta THEN
        INSERT INTO logs_acciones_no_permitidas_cuentas (idEmpresa, idCuenta, descripcionLog)
        SELECT
            ec.idEmpresa,
            OLD.idCuenta,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idCuenta. Original: ',
                OLD.idCuenta,
                ' | Intentado: ',
                NEW.idCuenta
            )
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = OLD.idCuenta;
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idCuenta.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_cuenta_cuentas
AFTER UPDATE ON cuentas
FOR EACH ROW
BEGIN
    IF OLD.idCuenta <> NEW.idCuenta THEN
        INSERT INTO logs_acciones_no_permitidas_cuentas (idEmpresa, idCuenta, descripcionLog)
        SELECT
            ec.idEmpresa,
            OLD.idCuenta,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idCuenta. Original: ',
                OLD.idCuenta,
                ' | Modificado: ',
                NEW.idCuenta
            )
        FROM empresas_cuentas ec
        WHERE ec.idCuenta = OLD.idCuenta;
    END IF;
END$$

-- --------------- ACCIONES NO PERMITIDAS: LOCALES ---------------
CREATE TRIGGER trg_before_update_no_permitida_id_local_locales
BEFORE UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.idLocal <> NEW.idLocal THEN
        INSERT INTO logs_acciones_no_permitidas_locales (idEmpresa, idLocal, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idLocal,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idLocal. Original: ',
                OLD.idLocal,
                ' | Intentado: ',
                NEW.idLocal
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idLocal.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_local_locales
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.idLocal <> NEW.idLocal THEN
        INSERT INTO logs_acciones_no_permitidas_locales (idEmpresa, idLocal, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idLocal,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idLocal. Original: ',
                OLD.idLocal,
                ' | Modificado: ',
                NEW.idLocal
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_no_permitida_id_empresa_locales
BEFORE UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_locales (idEmpresa, idLocal, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idLocal,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Intentado: ',
                NEW.idEmpresa
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idEmpresa.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_empresa_locales
AFTER UPDATE ON locales
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_locales (idEmpresa, idLocal, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idLocal,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Modificado: ',
                NEW.idEmpresa
            )
        );
    END IF;
END$$

-- --------------- LOGS A TABLAS GENERALES: MICROCONTROLADORES ---------------
CREATE TRIGGER trg_after_insert_logs_normales_general_microcontroladores
AFTER INSERT ON logs_microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_general (
        tablaAfectada,
        idRegistro,
        idEmpresa,
        tipoAccion,
        descripcionLog
    )
    VALUES (
        'Microcontroladores',
        NEW.idMicrocontrolador,
        NEW.idEmpresa,
        CASE
            WHEN NEW.descripcionLogMicrocontrolador LIKE '[INSERT]%' THEN 'INSERT'
            WHEN NEW.descripcionLogMicrocontrolador LIKE '[UPDATE]%' THEN 'UPDATE'
            WHEN NEW.descripcionLogMicrocontrolador LIKE '[DELETE]%' THEN 'DELETE'
            ELSE 'EVENTO'
        END,
        NEW.descripcionLogMicrocontrolador
    );
END$$

CREATE TRIGGER trg_after_insert_no_permitida_microcontroladores
AFTER INSERT ON logs_acciones_no_permitidas_microcontroladores
FOR EACH ROW
INSERT INTO logs_acciones_no_permitidas_general (
    tablaAfectada,
    idEmpresa,
    idRegistro,
    tipoAccion,
    descripcionLog,
    fechaRegistro
)
VALUES (
    'Microcontroladores',
    NEW.idEmpresa,
    NEW.idMicrocontrolador,
    'ACCION_NO_PERMITIDA',
    NEW.descripcionLog,
    NEW.fechaRegistro
)$$

-- --------------- MICROCONTROLADORES ---------------
CREATE TRIGGER trg_after_insert_microcontrolador
AFTER INSERT ON microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
    VALUES (
        NEW.idMicrocontrolador,
        NEW.idLocal,
        NEW.idEmpresa,
        CONCAT('[INSERT] Se creó un nuevo microcontrolador con ID: ', NEW.idMicrocontrolador)
    );
END$$

CREATE TRIGGER trg_after_delete_microcontrolador
AFTER DELETE ON microcontroladores
FOR EACH ROW
BEGIN
    INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
    VALUES (
        OLD.idMicrocontrolador,
        OLD.idLocal,
        OLD.idEmpresa,
        CONCAT('[DELETE] Se eliminó el registro del microcontrolador ID: ', OLD.idMicrocontrolador)
    );
END$$

CREATE TRIGGER trg_after_update_id_local_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.idLocal <> NEW.idLocal THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (
            NEW.idMicrocontrolador,
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en el local del microcontrolador. Anterior: ',
                OLD.idLocal,
                ' | Nuevo: ',
                NEW.idLocal
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_ultimaConsulta_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.ultimaConsulta <> NEW.ultimaConsulta THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (
            NEW.idMicrocontrolador,
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en ultimaConsulta. Anterior: ',
                OLD.ultimaConsulta,
                ' | Nuevo: ',
                NEW.ultimaConsulta
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_ultimaApertura_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.ultimaApertura <> NEW.ultimaApertura THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (
            NEW.idMicrocontrolador,
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en ultimaApertura. Anterior: ',
                OLD.ultimaApertura,
                ' | Nuevo: ',
                NEW.ultimaApertura
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_cantidadAperturas_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.cantidadAperturas <> NEW.cantidadAperturas THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (
            NEW.idMicrocontrolador,
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en cantidadAperturas. Anterior: ',
                OLD.cantidadAperturas,
                ' | Nuevo: ',
                NEW.cantidadAperturas
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_habilitado_microcontrolador
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.habilitado <> NEW.habilitado THEN
        INSERT INTO logs_microcontroladores (idMicrocontrolador, idLocal, idEmpresa, descripcionLogMicrocontrolador)
        VALUES (
            NEW.idMicrocontrolador,
            NEW.idLocal,
            NEW.idEmpresa,
            CONCAT(
                '[UPDATE] Modificación en estado habilitado. Anterior: ',
                OLD.habilitado,
                ' | Nuevo: ',
                NEW.habilitado
            )
        );
    END IF;
END$$

-- --------------- ACCIONES NO PERMITIDAS: MICROCONTROLADORES ---------------
CREATE TRIGGER trg_before_update_no_permitida_id_microcontrolador_micros
BEFORE UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.idMicrocontrolador <> NEW.idMicrocontrolador THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (idEmpresa, idMicrocontrolador, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idMicrocontrolador,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idMicrocontrolador. Original: ',
                OLD.idMicrocontrolador,
                ' | Intentado: ',
                NEW.idMicrocontrolador
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idMicrocontrolador.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_microcontrolador_micros
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.idMicrocontrolador <> NEW.idMicrocontrolador THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (idEmpresa, idMicrocontrolador, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idMicrocontrolador,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idMicrocontrolador. Original: ',
                OLD.idMicrocontrolador,
                ' | Modificado: ',
                NEW.idMicrocontrolador
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_no_permitida_id_empresa_micros
BEFORE UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (idEmpresa, idMicrocontrolador, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idMicrocontrolador,
            CONCAT(
                'Intento bloqueado: Modificación no permitida de idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Intentado: ',
                NEW.idEmpresa
            )
        );
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Acción no permitida: No se puede modificar el campo idEmpresa.';
    END IF;
END$$

CREATE TRIGGER trg_after_update_no_permitida_id_empresa_micros
AFTER UPDATE ON microcontroladores
FOR EACH ROW
BEGIN
    IF OLD.idEmpresa <> NEW.idEmpresa THEN
        INSERT INTO logs_acciones_no_permitidas_microcontroladores (idEmpresa, idMicrocontrolador, descripcionLog)
        VALUES (
            OLD.idEmpresa,
            OLD.idMicrocontrolador,
            CONCAT(
                'Modificación detectada (AFTER UPDATE) en idEmpresa. Original: ',
                OLD.idEmpresa,
                ' | Modificado: ',
                NEW.idEmpresa
            )
        );
    END IF;
END$$

-- --------------- SOPORTE ---------------
CREATE TRIGGER trg_before_insert_soporte
BEFORE INSERT ON soporte
FOR EACH ROW
BEGIN
    IF NEW.idSoporte IS NULL OR NEW.idSoporte <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El único registro de soporte debe tener idSoporte = 1.';
    END IF;
    IF EXISTS (SELECT 1 FROM soporte) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El registro de soporte ya existe; solo se permite actualizarlo.';
    END IF;
END$$

CREATE TRIGGER trg_after_insert_soporte
AFTER INSERT ON soporte
FOR EACH ROW
BEGIN
    INSERT INTO logs_soporte (idSoporte, descripcionLogSoporte)
    VALUES (
        NEW.idSoporte,
        CONCAT(
            '[INSERT] Se creó el registro de soporte. Teléfono: ',
            NEW.telefonoContacto,
            ' | Correo: ',
            NEW.correoContacto
        )
    );
END$$

CREATE TRIGGER trg_after_update_telefonoContacto_soporte
AFTER UPDATE ON soporte
FOR EACH ROW
BEGIN
    IF BINARY OLD.telefonoContacto <> BINARY NEW.telefonoContacto THEN
        INSERT INTO logs_soporte (idSoporte, descripcionLogSoporte)
        VALUES (
            NEW.idSoporte,
            CONCAT(
                '[UPDATE] Modificación en teléfono de soporte. Anterior: ',
                OLD.telefonoContacto,
                ' | Nuevo: ',
                NEW.telefonoContacto
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_after_update_correoContacto_soporte
AFTER UPDATE ON soporte
FOR EACH ROW
BEGIN
    IF BINARY OLD.correoContacto <> BINARY NEW.correoContacto THEN
        INSERT INTO logs_soporte (idSoporte, descripcionLogSoporte)
        VALUES (
            NEW.idSoporte,
            CONCAT(
                '[UPDATE] Modificación en correo de soporte. Anterior: ',
                OLD.correoContacto,
                ' | Nuevo: ',
                NEW.correoContacto
            )
        );
    END IF;
END$$

CREATE TRIGGER trg_before_update_soporte
BEFORE UPDATE ON soporte
FOR EACH ROW
BEGIN
    IF NEW.idSoporte <> OLD.idSoporte THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede modificar el identificador del registro de soporte.';
    END IF;
END$$

CREATE TRIGGER trg_before_delete_soporte
BEFORE DELETE ON soporte
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede eliminar el registro de soporte.';
END$$

-- --------------- LOGS_SOPORTE ---------------
CREATE TRIGGER trg_before_update_logs_soporte
BEFORE UPDATE ON logs_soporte
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se permite modificar el historial de soporte.';
END$$

CREATE TRIGGER trg_before_delete_logs_soporte
BEFORE DELETE ON logs_soporte
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se permite eliminar el historial de soporte.';
END$$

DELIMITER ;

INSERT INTO soporte (idSoporte, telefonoContacto, correoContacto)
VALUES (1, '+51920715669', 'hvalerm@gmail.com');

INSERT INTO cargos (nombreCargo)
VALUES
  ('Anfitrion'),
  ('Administrador'),
  ('Personal');

INSERT INTO modulos (nombreModulo)
VALUES ('OpenDoors'),
       ('GESTALO');
    