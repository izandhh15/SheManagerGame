<?php

// Idioma de los comunicados oficiales de cada club en "Redes del club".
// Se resuelve por comunidad autónoma (config/team_regions.php), con
// excepciones explícitas por equipo. Resto del mundo: español.
//
// Resolución del conflicto de especificación (03-10-2026, QA fase 3 M9):
// vale ESTE comentario. Los clubes extranjeros (no mapeados) publican en
// español; no se usa inglés por defecto para ellos. El inglés de las
// plantillas solo aparece cuando el locale del SITIO es 'en', igual que
// para cualquier club español.
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
