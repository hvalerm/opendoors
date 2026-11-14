<?php
function obtenerTelefonoSoporte(PDO $pdo): ?string
{
    $telefono = $pdo->query('SELECT telefonoContacto FROM soporte WHERE idSoporte = 1')->fetchColumn();
    return normalizarTelefonoContacto($telefono);
}

function normalizarTelefonoContacto(mixed $telefono): ?string
{
    if (!is_string($telefono)) {
        return null;
    }

    $digitos = preg_replace('/\D+/', '', $telefono);
    return is_string($digitos) && $digitos !== '' && $digitos !== '51000000000' ? $digitos : null;
}
