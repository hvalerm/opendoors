<?php
require 'db.php'; // Usa la conexión segura a Aiven

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h2 style='color:blue;'>Tablas existentes en Aiven:</h2>";
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li><strong>" . htmlspecialchars($table) . "</strong></li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No se encontraron tablas en esta base de datos.</p>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error al consultar las tablas:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
