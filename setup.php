<?php
require 'db.php'; // Incluye la conexión a Aiven

$sql = "
CREATE TABLE IF NOT EXISTS Location (
    id_loc INT AUTO_INCREMENT PRIMARY KEY,
    name_loc VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS Board (
    id_boa INT AUTO_INCREMENT PRIMARY KEY,
    name_boa VARCHAR(255),
    description_boa TEXT
);

CREATE TABLE IF NOT EXISTS Location_Board (
    id_loc_boa INT AUTO_INCREMENT PRIMARY KEY,
    id_loc INT,
    id_boa INT,
    FOREIGN KEY (id_loc) REFERENCES Location(id_loc),
    FOREIGN KEY (id_boa) REFERENCES Board(id_boa)
);

CREATE TABLE IF NOT EXISTS Action (
    id_act INT AUTO_INCREMENT PRIMARY KEY,
    name_act VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS AccountType (
    id_accTyp INT AUTO_INCREMENT PRIMARY KEY,
    name_accTyp VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS Account (
    user_acc VARCHAR(50) PRIMARY KEY,
    pass_acc VARCHAR(255),
    id_accTyp INT,
    FOREIGN KEY (id_accTyp) REFERENCES AccountType(id_accTyp)
);

CREATE TABLE IF NOT EXISTS Client (
    Id_cli INT AUTO_INCREMENT PRIMARY KEY,
    Id_acc VARCHAR(50),
    Name_cli VARCHAR(100),
    Lastname_Cli VARCHAR(100),
    Dni_cli VARCHAR(20),
    FOREIGN KEY (Id_acc) REFERENCES Account(user_acc)
);

CREATE TABLE IF NOT EXISTS BoardAction (
    id_boa INT,
    id_loc INT,
    activate TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id_boa, id_loc),
    FOREIGN KEY (id_boa) REFERENCES Board(id_boa),
    FOREIGN KEY (id_loc) REFERENCES Location(id_loc)
);

CREATE TABLE IF NOT EXISTS Programming (
    Id_pro INT AUTO_INCREMENT PRIMARY KEY,
    Id_cli INT,
    Id_loc_boa INT,
    Datetime_start DATETIME,
    Datetime_end DATETIME,
    FOREIGN KEY (Id_cli) REFERENCES Client(Id_cli),
    FOREIGN KEY (Id_loc_boa) REFERENCES Location_Board(id_loc_boa)
);

CREATE TABLE IF NOT EXISTS Log (
    Id_log INT AUTO_INCREMENT PRIMARY KEY,
    Id_act INT,
    Id_acc VARCHAR(50),
    Id_loc_boa INT,
    FOREIGN KEY (Id_act) REFERENCES Action(id_act),
    FOREIGN KEY (Id_acc) REFERENCES Account(user_acc),
    FOREIGN KEY (Id_loc_boa) REFERENCES Location_Board(id_loc_boa)
);
";

try {
    $pdo->exec($sql);
    echo "<h2 style='color:green;'>¡Todas las tablas se han creado exitosamente en Aiven!</h2>";
    echo "<p>Ya puedes eliminar este archivo <code>setup.php</code> del servidor por seguridad.</p>";
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error al crear las tablas:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>