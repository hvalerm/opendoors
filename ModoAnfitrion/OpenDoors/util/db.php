<?php
require_once __DIR__ . '/../../../util/conexion_mysql.php';

define('DB_NAME_OPENDOORS', getenv('DB_NAME_OPENDOORS'));

function getOpenDoorsPDO(): PDO
{
    static $pdo = null;
    return $pdo ??= crearConexionPDO(DB_NAME_OPENDOORS, '+00:00');
}

function getOpenDoorsMySQLi(): mysqli
{
    static $mysqli = null;
    return $mysqli ??= crearConexionMySQLi(DB_NAME_OPENDOORS, '+00:00');
}
