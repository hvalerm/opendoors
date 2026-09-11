<?php
/* * /
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
//$dbname = getenv('DB_NAME');
$dbname = 'opendoors';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

/**/

$host = 'mysql-25019e99-opendoors.j.aivencloud.com';
$port = '13195';
$dbname = 'opendoors';
$user = 'avnadmin';
$pass = 'AVNS_9BjfJpsOvEDvfcr0iuT';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Error de conexión remota: " . $e->getMessage());
}
?>