<?php
return static function (array $modulo, string $nombreWeb): array {
    $iconoModulo = 'M3 3h8v8H3zm10 0h8v8h-8zM3 13h8v8H3zm10 0h8v8h-8z';
    $iconoGestalo = 'M2 4h2v9h7V8h7a4 4 0 0 1 4 4v8h-2v-3H4v3H2zM5 10a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z';
    return [
        'principal' => [$modulo['nombreModulo'], $iconoModulo, [], null, 'modulo-' . (int) $modulo['idModulo']],
        'configuracion' => [$modulo['nombreModulo'], [
            ['Lugares', null, null, 'locales', 'M3 10h18v11H3zm2-6h14v4H5zm4 9h6v8H9z'],
        ], null, 'gestalo', $iconoGestalo],
    ];
};
