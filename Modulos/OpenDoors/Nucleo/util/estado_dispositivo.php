<?php

function dispositivoActivo(mixed $segundosSinConsulta): bool
{
    return $segundosSinConsulta !== null
        && (int) $segundosSinConsulta >= 0
        && (int) $segundosSinConsulta <= 15;
}

function mensajeEstadoDispositivo(string $estado): string
{
    return match ($estado) {
        'activo' => '',
        'sin_dispositivo' => 'Este lugar no tiene un dispositivo de apertura asociado.',
        'multiples' => 'Este lugar tiene más de un dispositivo asociado. Contacta a soporte para corregirlo.',
        'deshabilitado' => 'El dispositivo de apertura está deshabilitado.',
        'inactivo' => 'El dispositivo de apertura está inactivo. Verifica que esté conectado.',
        default => throw new LogicException('Estado de dispositivo no válido.'),
    };
}

function estadoDispositivoLocal(PDO $pdo, int $idLocal, int $idEmpresa, bool $bloquear = false): string
{
    $stmt = $pdo->prepare(
        'SELECT m.habilitado, TIMESTAMPDIFF(SECOND, m.ultimaConsulta, UTC_TIMESTAMP()) AS segundos_sin_consulta
         FROM microcontroladores m
         WHERE m.idLocal = ? AND m.idEmpresa = ?' . ($bloquear ? ' FOR UPDATE' : '')
    );
    $stmt->execute([$idLocal, $idEmpresa]);
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$dispositivos) {
        return 'sin_dispositivo';
    }
    if (count($dispositivos) !== 1) {
        return 'multiples';
    }
    if (!(int) $dispositivos[0]['habilitado']) {
        return 'deshabilitado';
    }
    return dispositivoActivo($dispositivos[0]['segundos_sin_consulta']) ? 'activo' : 'inactivo';
}
