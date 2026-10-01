<?php

return [
    // =========================================================================
    // Broadcast shout (prefixed to every summary)
    // =========================================================================
    'shout' => [
        'ABPFIFF!!',
        'SCHLUSS!!',
        'KEINE ZEIT MEHR!!',
        'FINAL :en_venue!!',
        'AUS!!',
        'SCHLUSSPFIFF!!',
        'SPIELENDE!!',
        'NICHTS GEHT MEHR!!',
    ],

    // =========================================================================
    // Opening sentences — League
    // =========================================================================
    'opening_home_win' => [
        ':el_home gewinnt :en_venue gegen :al_away (:score).',
        'Heimsieg :del_home :en_venue gegen :al_away (:score).',
        ':el_home setzt sich :en_venue gegen :al_away durch (:score).',
        'Sieg :del_home gegen :al_away (:score).',
    ],
    // Variantes con connotación de localía — solo se usan cuando hay ventaja de campo
    'opening_home_win_home_only' => [
        'Die Fans :del_home feiern den Sieg gegen :el_away (:score)',
    ],
    'opening_away_win' => [
        ':el_away gewinnt :en_venue und nimmt das Spiel mit (:score).',
        ':el_away nimmt :en_venue gegen :el_home den Sieg mit (:score).',
    ],
    // Variantes con connotación de localía — solo se usan cuando hay ventaja de campo
    'opening_away_win_home_only' => [
        'Auswärtssieg :del_away bei :del_home (:score).',
    ],
    'opening_blowout' => [
        ':el_winner überrollt :al_loser :en_venue (:score) mit einer starken Leistung.',
        'Kantersieg :del_winner :en_venue gegen :al_loser (:score) mit einer starken Leistung',
        'Klatsche :del_winner gegen :al_loser :en_venue (:score).',
        ':el_winner überrollt :al_loser (:score), die nie eine Chance hatte, das Spiel zu gewinnen',
        'Die Fans :del_winner feiern den Kantersieg gegen :al_loser (:score).',
    ],
    'opening_draw' => [
        'Unentschieden mit :goals_each :en_venue zwischen :el_home und :el_away.',
        'Schluss :en_venue! :el_home kommt gegen :el_away nicht über ein Remis hinaus.',
        'Punkteteilung :en_venue zwischen :el_home und :el_away (:score).',
        'Unentschieden mit :goals_each zwischen :el_home und :el_away, in einem Spiel, in dem kein Team das andere dominierte',
        ':el_home und :el_away teilen sich Punkte und Tore (:score).',
    ],
    'opening_goalless' => [
        'Torlos :en_venue zwischen :el_home und :el_away. Ein 0:0, das keines der beiden Teams zufriedenstellt.',
        'Torloses Remis :en_venue. Weder :el_home noch :el_away treffen in einem Spiel, in dem es alles gab – nur keine Tore.',
        'Nullnummer :en_venue. :el_home und :el_away teilen sich einen Punkt in einem faden Spiel ohne Tore.',
        'Torlos zwischen :el_home und :el_away, in einem Spiel, das nicht wegen seiner Spannung in Erinnerung bleiben wird',
        'Torloses Unentschieden: Weder :el_home noch :el_away treffen.',
    ],
    'opening_narrow_win' => [
        ':el_winner holt sich den knappen Sieg :en_venue (:score).',
        'Knappes Ergebnis :del_winner gegen :al_loser :en_venue (:score).',
        ':el_winner zittert sich durch, gewinnt aber :en_venue gegen :al_loser (:score).',
        ':el_winner holt sich den knappen Sieg gegen :al_loser (:score).',
        'Knapper Erfolg :del_winner gegen :el_loser (:score).',
    ],

    // =========================================================================
    // Opening sentences — Extra time & penalties (knockout)
    // =========================================================================
    'opening_extra_time' => [
        ':el_winner setzt sich in der Verlängerung :en_venue durch (:score).',
        'Es brauchte die Verlängerung, aber :el_winner holt sich den Sieg :en_venue (:score).',
        ':el_winner setzt sich in der Verlängerung durch (:score).',
        'Nach der Verlängerung holt sich :el_winner den Sieg (:score).',
    ],
    'opening_penalties' => [
        ':el_winner qualifiziert sich im Elfmeterschießen (:pen_score) nach :score_regular in der regulären Spielzeit.',
        'Das Elfmeterschießen entscheidet :en_venue. :el_winner qualifiziert sich (:pen_score).',
    ],

    // =========================================================================
    // Opening sentences — Cup-specific
    // =========================================================================
    'opening_cup_win' => [
        ':el_winner zieht in der :competition eine Runde weiter nach dem Sieg gegen :al_loser :en_venue (:score).',
        ':el_winner qualifiziert sich :en_venue gegen :al_loser (:score).',
        ':el_loser scheidet :en_venue aus. :el_winner zieht in die nächste Runde ein (:score).',
        ':el_winner zieht in der :competition eine Runde weiter nach dem Sieg gegen :al_loser (:score).',
        ':el_loser scheidet aus. :el_winner zieht in die nächste Runde ein (:score).',
    ],
    'opening_cup_draw' => [
        'Unentschieden :en_venue zwischen :el_home und :el_away (:score) in der :competition.',
        ':el_home und :el_away trennen sich (:score) :en_venue in der :competition unentschieden.',
        'Unentschieden zwischen :el_home und :el_away (:score) in der :competition.',
    ],

    // =========================================================================
    // Opening sentences — High stakes (semifinals, finals)
    // =========================================================================
    'opening_high_stakes_win' => [
        '!:el_winner setzt sich :en_venue durch und zieht in der :competition weiter! (:score)',
        '!Riesiger Sieg :del_winner gegen :al_loser :en_venue! (:score)',
        '!:el_winner schafft es! Sieg :en_venue gegen :al_loser (:score).',
        '!:el_winner setzt sich durch und zieht in der :competition weiter! (:score)',
        '!Riesiger Sieg :del_winner gegen :al_loser! (:score)',
    ],
    'opening_high_stakes_champion' => [
        '!:el_winner ist Meisterin der :competition! Finalsieg :en_venue gegen :al_loser (:score).',
        '!:el_winner hebt den Pokal der :competition nach dem Finalsieg gegen :al_loser (:score)!',
        '!Die :competition geht an :del_winner! Finale entschieden :en_venue gegen :al_loser (:score).',
    ],

    // =========================================================================
    // Goal narrative
    // =========================================================================
    'goals_one_team' => [
        ':scorers waren die Torschützinnen :del_team.',
        'Die Tore :del_team erzielten :scorers.',
        ':el_team konnte auf die Treffer von :scorers zählen.',
    ],
    'goals_one_team_single_scorer' => [
        ':scorer erzielte das einzige Tor für :el_team.',
        ':scorer erzielte den einzigen Treffer :del_team.',
        'Das Tor :del_team erzielte :scorer.',
    ],
    'goals_team_fragment_single' => [
        ':scorer traf für :el_team',
        ':scorer erzielte den Treffer :del_team',
        'das Tor :del_team erzielte :scorer',
    ],
    'goals_team_fragment_multi' => [
        ':scorers erzielten die Tore :del_team',
        ':scorers trafen für :el_team',
        'die Tore :del_team erzielten :scorers',
    ],
    'goals_two_teams_join' => [
        ':a und :b.',
    ],
    'scorer_join_and' => 'und',

    // =========================================================================
    // Key moments
    // =========================================================================
    'comeback' => [
        ':el_winner drehte das Spiel nach einem Rückstand.',
        'Comeback :del_winner, die sich nach dem Gegentreffer zurückkämpfte.',
        ':el_winner drehte den Spielstand und holte sich den Sieg.',
    ],
    'red_card_single' => [
        'Der Platzverweis für :player (:minute\') prägte den Spielverlauf für :el_team.',
        'Das Spiel kippte mit der Roten Karte für :player (:team) in der :minute. Minute.',
    ],
    'red_cards_multiple' => [
        'Die Platzverweise bei :el_team beeinflussten das Ergebnis.',
        ':el_team geriet nach :count Platzverweisen in Unterzahl.',
    ],
    'dominant_first_half' => [
        'Die Tore fielen in der ersten Halbzeit des Spiels.',
        'Intensive erste Hälfte mit allen Toren der Partie.',
    ],
    'dominant_second_half' => [
        'Die Spannung konzentrierte sich auf die zweite Hälfte, in der die Tore fielen.',
        'Das Spiel öffnete sich in der zweiten Hälfte, mit vielen Chancen für beide Teams.',
    ],

    // =========================================================================
    // Form / streak (league only)
    // =========================================================================
    'form_losing_streak' => [
        ':el_team taumelt nach :count Niederlagen in Folge.',
        ':el_team wartet weiter auf einen Sieg nach :count Pleiten in Serie.',
        'Krise bei :el_team, das :count Niederlagen in Folge kassiert hat.',
    ],
    'form_winning_streak' => [
        ':el_team bleibt unaufhaltsam und reiht :count Siege aneinander.',
        'Spektakuläre Serie :del_team, das :count Erfolge in Folge feiert.',
        ':el_team hört nicht auf zu gewinnen, bereits :count Siege in Serie.',
    ],
    'form_winless' => [
        ':el_team wartet weiter auf einen Sieg, bereits :count Spiele ohne Erfolg.',
        ':el_team kommt nicht aus dem Tief und sammelt :count sieglose Spiele.',
    ],

    // =========================================================================
    // Color commentary (emotion, drama, opinion)
    // =========================================================================
    'last_minute_winner' => [
        '!Tor von :player in der :minute.\' Minute, das :el_team den Sieg in letzter Sekunde sichert!',
        '!:player (:team) tauchte in der :minute.\' Minute auf und machte den Deckel drauf, als alles verloren schien!',
        'Wahnsinn in der Nachspielzeit: :player sicherte :el_team den Sieg in der :minute.\' Minute.',
    ],
    'last_minute_equalizer' => [
        '!Tor von :player (:team) in der :minute.\' Minute, das einen Punkt rettet, als niemand mehr damit rechnete!',
        ':player (:team) glich in der :minute.\' Minute aus. Der Ausgleich fiel mit dem Schlusspfiff.',
        'Qualvoller Ausgleich: :player traf in der :minute.\' Minute und bewahrte :el_team vor der Niederlage.',
    ],
    'hat_trick' => [
        '!Hattrick von :player! Glanzleistung mit :goals Toren.',
        'Gala von :player (:team), die mit :goals Toren nach Hause ging.',
        '!:player nahm den Ball mit nach Hause! :goals Tore zum Einrahmen.',
    ],
    'upset' => [
        '!Paukenschlag :del_loser! :el_winner sorgte für die Überraschung und gewinnt das Spiel.',
        ':el_winner sorgt für die Überraschung und schlägt :al_loser mit einer starken Kollektivleistung.',
        ':el_winner stellte die Prognosen auf den Kopf und gewinnt :en_venue.',
    ],
    'expected_win' => [
        ':el_winner ließ keine Zweifel aufkommen und zeigte durchgehend ihre Überlegenheit.',
        ':el_winner machte ihre Überlegenheit gegen :el_loser in einem Spiel ohne Geschichte geltend.',
        ':el_winner besiegte :al_loser mühelos :en_venue.',
    ],
    'high_scoring' => [
        'Die Angreiferinnen waren treffsicher in einem Spiel mit :total Toren.',
        'Spektakel für die Fans mit :total Toren. Das ist Fußball, Leute!',
        'Beeindruckendes Spiel, in dem insgesamt :total Tore fielen',
    ],
    'few_chances' => [
        'Ein langweiliges Spiel :en_venue mit wenig Spielfluss und Torchancen.',
        'Wenig Fußball und noch weniger Gefahr. Für die Fans zog es sich.',
        'Graues Spiel, in dem es kaum Torchancen gab.',
    ],

    // =========================================================================
    // Annotations
    // =========================================================================
    'penalty_goal_note' => 'per Elfmeter',
    'own_goal_note' => 'Eigentor',

    // =========================================================================
    // MVP closing
    // =========================================================================
    'mvp_closing' => [
        ':player (:team), als MVP des Spiels ausgezeichnet.',
        'Außerdem geht der MVP des Spiels an :player (:team).',
        ':player (:team), als Beste des Spiels gewählt.',
        'MVP des Spiels: :player (:team).',
    ],
];
