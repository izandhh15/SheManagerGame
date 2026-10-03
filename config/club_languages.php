<?php

// Idioma de los comunicados oficiales de cada club en "Redes del club".
// Se resuelve por comunidad autónoma (config/team_regions.php), con
// excepciones explícitas por equipo. Resto del mundo: español.
//
// Códigos: es = español, ca = catalán, va = valenciano, gl = gallego.

return [
    // Región (team_regions.php) => idioma.
    'regions' => [
        'valencia' => 'va',
        'catalunya' => 'ca',
        'galicia' => 'gl',
    ],

    // Equipo => idioma. Tiene prioridad sobre la región.
    'teams' => [
        'ES' => [
            'Valencia CF Femenino' => 'va',
            'FC Barcelona' => 'ca',
            'RCD Espanyol' => 'ca',
            'FC Badalona Women' => 'ca',
            'Real Madrid CF' => 'es',
            'Dépor Abanca' => 'gl',
        ],
    ],

    'default' => 'es',
];
