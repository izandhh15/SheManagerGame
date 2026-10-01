<?php

/**
 * Academy nicknames for the career mode.
 *
 * Maps a first-team club name (as stored in data/*/teams.json) to the
 * nickname of its youth academy. In academy career mode the user picks
 * the CLUB (shown with its academy nickname) and starts at the lowest
 * reserve team, working their way up.
 *
 * Keys should match the club name; matching is done case-insensitively
 * and also tries stripping common suffixes (CF, FC, etc.).
 */
return [
    // Spain
    'FC Barcelona' => 'La Masía',
    'Real Madrid CF' => 'La Fábrica',
    'Atlético de Madrid' => 'Academia Atlético',
    'Athletic Club' => 'Lezama',
    'Real Sociedad' => 'Zubieta',
    'Valencia CF' => 'Academia VCF',
    'Villarreal CF' => 'Cantera Grogueta',
    'Sevilla FC' => 'Cantera Sevillista',
    'Real Betis Balompié' => 'Cantera Bética',
    'RCD Espanyol' => 'Ciutat Esportiva Dani Jarque',
    'Deportivo ABANCA' => 'Abegondo',
    'Levante UD' => 'Cantera Granota',
    'Madrid CFF' => 'Cantera del Madrid CFF',
    'SD Eibar' => 'Cantera Armera',
    'CA Osasuna' => 'Tajonar',
    'Granada CF' => 'Cantera Nazarí',
    'Sporting de Huelva' => 'Cantera Sportinguista',
    'UD Tenerife' => 'Cantera Tinerfeña',
    'FC Levante Badalona' => 'Cantera Badalonina',

    // England
    'Arsenal' => 'Hale End',
    'Chelsea' => 'Cobham',
    'Manchester City' => 'City Football Academy',
    'Manchester United' => 'Carrington',
    'Liverpool' => 'The Academy',
    'Tottenham Hotspur' => 'Hotspur Way',
    'Everton' => 'Finch Farm',
    'Aston Villa' => 'Bodymoor Heath',
    'Brighton & Hove Albion' => 'American Express Elite Centre',
    'West Ham United' => 'Chadwell Heath',

    // Germany
    'FC Bayern München' => 'FC Bayern Campus',
    'VfL Wolfsburg' => 'Nachwuchsleistungszentrum',
    'Eintracht Frankfurt' => 'Riederwald',
    'Bayer 04 Leverkusen' => 'Kurtekotten',
    'Borussia Dortmund' => 'Hohenbuschei',
    'Turbine Potsdam' => 'Nachwuchsakademie',
    'SC Freiburg' => 'Schönbergstadion Nachwuchs',
    'TSG Hoffenheim' => 'Akademie Hoffenheim',
    'RB Leipzig' => 'RB Nachwuchsakademie',
    '1. FC Köln' => 'Geißbockheim',

    // France
    'Olympique Lyonnais' => 'Académie OL',
    'Paris Saint-Germain' => 'Campus PSG',
    'Paris FC' => 'Académie Paris FC',
    'Montpellier HSC' => 'Centre de Formation',
    'FC Girondins de Bordeaux' => 'Le Haillan',
    'AS Saint-Étienne' => 'L\'Étrat',
    'Dijon FCO' => 'Centre de Formation Dijonnais',
    'FC Nantes' => 'La Jonelière',
    'Stade de Reims' => 'Centre de Vie Raymond Kopa',

    // Italy
    'Juventus' => 'Juventus Academy',
    'Inter' => 'Inter Academy',
    'AC Milan' => 'Milan Academy',
    'AS Roma' => 'Roma Academy',
    'Fiorentina' => 'Viola Park',
    'Napoli' => 'Napoli Academy',
    'Sassuolo' => 'Mapei Football Center',
    'Lazio' => 'Lazio Academy',

    // Portugal
    'SL Benfica' => 'Caixa Futebol Campus',
    'Sporting CP' => 'Alcochete',
    'SC Braga' => 'Cidade Desportiva',
    'FC Porto' => 'Olival',

    // Netherlands
    'Ajax' => 'De Toekomst',
    'PSV' => 'De Herdgang',
    'FC Twente' => 'Tukkers Academy',
    'Feyenoord' => 'Varkenoord',

    // USA (NWSL academies are newer; use club + Academy)
    'OL Reign' => 'Reign Academy',
    'Portland Thorns' => 'Thorns Academy',
    'North Carolina Courage' => 'Courage Academy',
    'Washington Spirit' => 'Spirit Academy',

    // Mexico
    'Tigres UANL' => 'Cantera Felina',
    'Club América' => 'Cantera Azulcrema',
    'Chivas Guadalajara' => 'Cantera Rojiblanca',
    'Rayadas Monterrey' => 'Cantera Rayada',

    // Brazil
    'Corinthians' => 'Terrão',
    'Palmeiras' => 'Academia de Futebol',
    'São Paulo' => 'CFA Laudo Natel',
    'Flamengo' => 'Ninho do Urubu',
    'Santos' => 'CT Rei Pelé',

    // Argentina
    'Boca Juniors' => 'Casa Amarilla',
    'River Plate' => 'River Camp',
    'San Lorenzo' => 'Ciudad Deportiva',
    'Racing Club' => 'Tita Mattiussi',

    // Switzerland
    'FC Zürich' => 'Nachwuchs Campus',
    'Servette FCCF' => 'Académie Servettienne',
    'BSC Young Boys' => 'Nachwuchs YB',
];
