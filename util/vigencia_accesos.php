<?php

function ahoraVigenciaAccesos(): string
{
    return gmdate('Y-m-d H:i:s');
}

function mostrarFechaAcceso(string $utc, string $zona, string $formato = 'd/m/Y H:i'): string
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone($zona))->format($formato);
}

function fechaAccesoEnUtc(string $valor, string $zona): ?string
{
    $utc = new DateTimeZone('UTC');
    $formato = strlen($valor) === 16 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s';
    $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $valor, $utc);
    if (!$fecha || $fecha->format($formato) !== $valor) {
        return null;
    }

    $zonaLocal = new DateTimeZone($zona);
    $hora = $fecha->getTimestamp();
    $candidatos = [];
    // Una hora civil puede no existir o repetirse al cambiar el horario de verano.
    foreach ($zonaLocal->getTransitions($hora - 86400, $hora + 86400) as $transicion) {
        $instante = $hora - $transicion['offset'];
        $candidato = $fecha->setTimestamp($instante);
        if ($candidato->setTimezone($zonaLocal)->format($formato) === $valor) {
            $candidatos[$instante] = $candidato->format('Y-m-d H:i:s');
        }
    }
    return count($candidatos) === 1 ? reset($candidatos) : null;
}
