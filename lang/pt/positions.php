<?php

return [
    // Player position display names (13 canonical positions)
    'goalkeeper' => 'Guarda-redes',
    'centre_back' => 'Central',
    'left_back' => 'Lateral Esquerda',
    'right_back' => 'Lateral Direita',
    'defensive_midfield' => 'Média Defensiva',
    'central_midfield' => 'Média Centro',
    'attacking_midfield' => 'Média Ofensiva',
    'left_midfield' => 'Média Esquerda',
    'right_midfield' => 'Média Direita',
    'left_winger' => 'Extrema Esquerda',
    'right_winger' => 'Extrema Direita',
    'centre_forward' => 'Ponta de Lança',
    'second_striker' => 'Segunda Avançada',

    // Generic source-data positions (não caem no MC por defeito)
    'defender' => 'Defesa',
    'midfielder' => 'Média',
    'forward' => 'Avançada',
    'striker' => 'Ponta de Lança',

    // Player position abbreviations
    'goalkeeper_abbr' => 'GR',
    'centre_back_abbr' => 'DC',
    'left_back_abbr' => 'DE',
    'right_back_abbr' => 'DD',
    'defensive_midfield_abbr' => 'MCD',
    'central_midfield_abbr' => 'MC',
    'attacking_midfield_abbr' => 'MOC',
    'left_midfield_abbr' => 'ME',
    'right_midfield_abbr' => 'MD',
    'left_winger_abbr' => 'EE',
    'right_winger_abbr' => 'ED',
    'centre_forward_abbr' => 'PL',
    'second_striker_abbr' => 'SA',

    'defender_abbr' => 'DF',
    'midfielder_abbr' => 'M',
    'forward_abbr' => 'AV',
    'striker_abbr' => 'PL',

    // Labels with abbreviation (for scouting dropdown)
    'goalkeeper_label' => 'Guarda-redes (GR)',
    'centre_back_label' => 'Central (DC)',
    'left_back_label' => 'Lateral Esquerda (DE)',
    'right_back_label' => 'Lateral Direita (DD)',
    'defensive_midfield_label' => 'Média Defensiva (MCD)',
    'central_midfield_label' => 'Média Centro (MC)',
    'attacking_midfield_label' => 'Média Ofensiva (MOC)',
    'left_midfield_label' => 'Média Esquerda (ME)',
    'right_midfield_label' => 'Média Direita (MD)',
    'left_winger_label' => 'Extrema Esquerda (EE)',
    'right_winger_label' => 'Extrema Direita (ED)',
    'centre_forward_label' => 'Ponta de Lança (PL)',
    'second_striker_label' => 'Segunda Avançada (SA)',

    // Position group abbreviations (squad footer badges)
    'group_goalkeeper_abbr' => 'GR',
    'group_defender_abbr' => 'DF',
    'group_midfielder_abbr' => 'M',
    'group_forward_abbr' => 'AV',

    // Scout filter groups
    'any_defender' => 'Qualquer Defesa (DC, DD, DE)',
    'any_midfielder' => 'Qualquer Média (MCD, MC, MOC, MD, ME)',
    'any_forward' => 'Qualquer Avançada (EE, ED, PL, SA)',
];
