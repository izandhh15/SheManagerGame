<?php

/**
 * Government venue offers (F5, 0.3.9): regional/national governments
 * occasionally offer national teams a stadium to play at, via in-game
 * mail. All government names are the real official ones; all stadiums
 * are real.
 *
 * Keyed by national-team country ISO code. Each entry lists governments
 * with the stadiums they can offer (stadium names must match
 * data/mens_stadiums.json).
 */
return [

    'ES' => [
        [
            'government' => 'Generalitat Valenciana',
            'stadiums' => [
                'Castalia',
                'José Rico Pérez',
                'Manuel Martínez Valero',
            ],
        ],
        [
            'government' => 'Generalitat de Catalunya',
            'stadiums' => [
                'Estadi Olímpic Lluís Companys',
                'RCDE Stadium',
                'Montilivi',
            ],
        ],
        [
            'government' => 'Gobierno de España',
            'stadiums' => [
                'La Cartuja',
                'Santiago Bernabéu',
                'Riyadh Air Metropolitano',
            ],
        ],
        [
            'government' => 'Junta de Andalucía',
            'stadiums' => [
                'La Cartuja',
                'Ramón Sánchez-Pizjuán',
                'Benito Villamarín',
            ],
        ],
    ],

    // Other national teams: their national government offers a venue.
    // Stadiums must exist in data/mens_stadiums.json.
    'FR' => [
        [
            'government' => 'Gouvernement français',
            'stadiums' => ['Parc des Princes'],
        ],
    ],
    'DE' => [
        [
            'government' => 'Bundesregierung',
            'stadiums' => ['Olympiastadion Berlin'],
        ],
    ],
    'IT' => [
        [
            'government' => 'Governo italiano',
            'stadiums' => ['Stadio Olimpico'],
        ],
    ],
    'EN' => [
        [
            'government' => 'UK Government',
            'stadiums' => ['Emirates Stadium'],
        ],
    ],
    'PT' => [
        [
            'government' => 'Governo de Portugal',
            'stadiums' => ['Estádio da Luz'],
        ],
    ],
    'NL' => [
        [
            'government' => 'Nederlandse regering',
            'stadiums' => ['Johan Cruijff Arena'],
        ],
    ],

];
