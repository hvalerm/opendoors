<?php
// Conexion del sistema general: cuentas, empresas, modulos y soporte.
require_once __DIR__ . '/conexion_mysql.php';

define('DB_NAME_EMPRESAS', getenv('DB_NAME_EMPRESAS') ?: 'empresas');

function getPDO(): PDO
{
    static $pdo = null;
    return $pdo ??= crearConexionPDO(DB_NAME_EMPRESAS, '-05:00');
}

function getMySQLi(): mysqli
{
    static $mysqli = null;
    return $mysqli ??= crearConexionMySQLi(DB_NAME_EMPRESAS, '-05:00');
}
