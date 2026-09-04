<?php
require 'db.php'; // Usa la conexión segura a Aiven

$sql = "
-- 1. AccountType (Tipos de cuenta)
INSERT IGNORE INTO AccountType (name_accTyp) VALUES 
('Administrador'),
('Anfitrión'),
('Huésped'),
('Personal de Limpieza');

-- 2. Location (Sedes o ubicaciones)
INSERT IGNORE INTO Location (name_loc) VALUES 
('Musas'),
('Cecias'),
('Sumaran');

-- 3. Board (Sensor o hardware genérico)
INSERT IGNORE INTO Board (id_boa, name_boa, description_boa) 
VALUES (1, 'Sensor de Puerta Principal', 'Controlador/Sensor genérico para la apertura de puertas');

-- 4. Location_Board (Relación entre sedes y el sensor genérico)
INSERT IGNORE INTO Location_Board (id_loc, id_boa) VALUES 
(1, 1), 
(2, 1), 
(3, 1);

-- 5. Account (Cuentas de usuario con contraseña encriptada en SHA-512)
INSERT IGNORE INTO Account (user_acc, pass_acc, id_accTyp) 
VALUES ('hjvm', SHA2('nhss@1996', 512), 1);

INSERT IGNORE INTO Account (user_acc, pass_acc, id_accTyp) 
VALUES ('claudia', SHA2('clauss4488', 512), 2);

INSERT IGNORE INTO Account (user_acc, pass_acc, id_accTyp) 
VALUES ('marcelino', SHA2('marcelino', 512), 3);

INSERT IGNORE INTO Account (user_acc, pass_acc, id_accTyp) 
VALUES 
('diana', SHA2('diana', 512), 4),
('silvia', SHA2('silvia', 512), 4);

-- 6. BoardAction (Asignación de estado por cada combinación de sensor y sede)
INSERT IGNORE INTO BoardAction (id_boa, id_loc, activate)
SELECT b.id_boa, l.id_loc, 0
FROM Board b
CROSS JOIN Location l;
";

try {
    $pdo->exec($sql);
    echo "<h2 style='color:green;'>¡Los registros se han insertado exitosamente en Aiven!</h2>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error al insertar los registros:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
