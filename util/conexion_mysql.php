<?php
date_default_timezone_set('America/Guayaquil');
require_once __DIR__ . '/seguridad.php';

define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL_CA', __DIR__ . '/ca.pem');

function crearConexionPDO(string $nombre, string $zonaHoraria): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, $nombre, DB_CHARSET);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (is_file(DB_SSL_CA)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
    } else {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $options[PDO::MYSQL_ATTR_SSL_CA] = '';
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec('SET time_zone = ' . $pdo->quote($zonaHoraria));
    return $pdo;
}

function crearConexionMySQLi(string $nombre, string $zonaHoraria): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = mysqli_init();
    if (is_file(DB_SSL_CA)) {
        $mysqli->ssl_set(null, null, DB_SSL_CA, null, null);
        $flags = MYSQLI_CLIENT_SSL;
    } else {
        $flags = MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
    }
    $mysqli->real_connect(DB_HOST, DB_USER, DB_PASS, $nombre, (int) DB_PORT, null, $flags);
    $mysqli->set_charset(DB_CHARSET);
    $stmt = $mysqli->prepare('SET time_zone = ?');
    $stmt->bind_param('s', $zonaHoraria);
    $stmt->execute();
    $stmt->close();
    return $mysqli;
}
