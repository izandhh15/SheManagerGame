<?php

return [
    // =========================================================================
    // Broadcast shout (prefixed to every summary)
    // =========================================================================
    'shout' => [
        'FIN DU MATCH !!',
        'C\'EST TERMINÉ !!',
        'PLUS DE TEMPS !!',
        'FIN :en_venue !!',
        'C\'EST FINI !!',
        'COUP DE SIFFLET FINAL !!',
        'FIN DE LA PARTIE !!',
        'IL N\'Y A PLUS RIEN !!',
    ],

    // =========================================================================
    // Opening sentences — League
    // =========================================================================
    'opening_home_win' => [
        ':el_home gagne :en_venue face à :al_away (:score).',
        'Victoire :del_home :en_venue face à :al_away (:score).',
        ':el_home s\'impose face à :al_away :en_venue (:score).',
        'Victoire :del_home face à :al_away (:score).',
    ],
    // Variantes connotées domicile — utilisées uniquement en cas d'avantage du terrain
    'opening_home_win_home_only' => [
        'Les supporters :del_home célèbrent la victoire contre :el_away (:score)',
    ],
    'opening_away_win' => [
        ':el_away gagne :en_venue et remporte le match (:score).',
        ':el_away fait coup double :en_venue face à :el_home (:score).',
    ],
    // Variantes connotées domicile — utilisées uniquement en cas d'avantage du terrain
    'opening_away_win_home_only' => [
        'Victoire :del_away sur la pelouse :del_home (:score).',
    ],
    'opening_blowout' => [
        ':el_winner écrase :al_loser :en_venue (:score) en faisant un grand match.',
        'Carton :del_winner :en_venue face à :al_loser (:score), qui signe une grande performance',
        'Correction :del_winner face à :al_loser :en_venue (:score).',
        ':el_winner écrase :al_loser (:score), qui n\'a jamais eu d\'option pour remporter le match',
        'Les supporters :del_winner célèbrent le carton face à :al_loser (:score).',
    ],
    'opening_draw' => [
        'Match nul :goals_each :en_venue entre :el_home et :el_away.',
        'Fin :en_venue ! :el_home ne peut pas faire mieux que le nul contre :el_away.',
        'Partage des points :en_venue entre :el_home et :el_away (:score).',
        'Match nul à :goals_each entre :el_home et :el_away où aucune des deux équipes n\'a su prendre le dessus sur l\'autre',
        ':el_home et :el_away se partagent les points et les buts (:score).',
    ],
    'opening_goalless' => [
        'Pas de buts :en_venue entre :el_home et :el_away. Un nul vierge qui ne satisfait aucune des deux équipes.',
        'Match nul sans but :en_venue. Ni :el_home ni :el_away ne parviennent à marquer dans un match où il y a eu de tout, sauf des buts.',
        'Zéro :en_venue. :el_home et :el_away se partagent un point dans un match terne et sans buts.',
        'Pas de buts entre :el_home et :el_away, dans un match dont on ne se souviendra pas pour son émotion',
        'Match nul sans but : ni :el_home ni :el_away ne parviennent à marquer.',
    ],
    'opening_narrow_win' => [
        ':el_winner remporte la victoire sur la plus petite des marges :en_venue (:score).',
        'Succès étriqué :del_winner face à :al_loser :en_venue (:score).',
        ':el_winner souffre mais gagne :en_venue face à :al_loser (:score).',
        ':el_winner remporte la victoire sur la plus petite des marges face à :al_loser (:score).',
        'Succès étriqué :del_winner face à :el_loser (:score).',
    ],

    // =========================================================================
    // Opening sentences — Extra time & penalties (knockout)
    // =========================================================================
    'opening_extra_time' => [
        ':el_winner s\'impose en prolongation :en_venue (:score).',
        'Il a fallu la prolongation, mais :el_winner remporte la victoire :en_venue (:score).',
        ':el_winner s\'impose en prolongation (:score).',
        'Après la prolongation, :el_winner remporte la victoire (:score).',
    ],
    'opening_penalties' => [
        ':el_winner se qualifie aux tirs au but (:pen_score) après le nul :score_regular.',
        'Les penaltys décident :en_venue. :el_winner se qualifie (:pen_score).',
    ],

    // =========================================================================
    // Opening sentences — Cup-specific
    // =========================================================================
    'opening_cup_win' => [
        ':el_winner avance en :competition après s\'être imposé face à :al_loser :en_venue (:score).',
        ':el_winner se qualifie :en_venue face à :al_loser (:score).',
        ':el_loser est éliminé :en_venue. :el_winner passe le tour (:score).',
        ':el_winner avance en :competition après s\'être imposé face à :al_loser (:score).',
        ':el_loser est éliminé. :el_winner passe le tour (:score).',
    ],
    'opening_cup_draw' => [
        'Match nul :en_venue entre :el_home et :el_away (:score) en :competition.',
        ':el_home et :el_away se neutralisent (:score) :en_venue pour la :competition.',
        'Match nul entre :el_home et :el_away (:score) en :competition.',
    ],

    // =========================================================================
    // Opening sentences — High stakes (semifinals, finals)
    // =========================================================================
    'opening_high_stakes_win' => [
        ':el_winner s\'impose :en_venue et avance en :competition ! (:score)',
        'Énorme victoire :del_winner face à :al_loser :en_venue ! (:score)',
        ':el_winner y arrive ! Victoire :en_venue face à :al_loser (:score).',
        ':el_winner s\'impose et avance en :competition ! (:score)',
        'Énorme victoire :del_winner face à :al_loser ! (:score)',
    ],
    'opening_high_stakes_champion' => [
        ':el_winner est champion de la :competition ! Victoire en finale :en_venue face à :al_loser (:score).',
        ':el_winner soulève le titre de la :competition après s\'être imposé en finale face à :al_loser (:score) !',
        'La :competition est pour :del_winner ! Finale résolue :en_venue face à :al_loser (:score).',
    ],

    // =========================================================================
    // Goal narrative
    // =========================================================================
    'goals_one_team' => [
        ':scorers ont été les buteuses :del_team.',
        'Les buts :del_team ont été signés :scorers.',
        ':el_team a compté sur les réalisations de :scorers.',
    ],
    'goals_one_team_single_scorer' => [
        ':scorer a marqué le seul but pour :el_team.',
        ':scorer a signé l\'unique but :del_team.',
        'Le but :del_team a été signé :scorer.',
    ],
    'goals_team_fragment_single' => [
        ':scorer a marqué pour :el_team',
        ':scorer a signé le but :del_team',
        'le but :del_team a été l\'œuvre de :scorer',
    ],
    'goals_team_fragment_multi' => [
        ':scorers ont inscrit les buts :del_team',
        ':scorers ont marqué pour :el_team',
        'les buts :del_team ont été signés :scorers',
    ],
    'goals_two_teams_join' => [
        ':a et :b.',
    ],
    'scorer_join_and' => 'et',

    // =========================================================================
    // Key moments
    // =========================================================================
    'comeback' => [
        ':el_winner a remonté le match après avoir été mené au score.',
        'Remontée :del_winner, qui a su se reprendre après avoir encaissé le premier.',
        ':el_winner a renversé le score pour remporter la victoire.',
    ],
    'red_card_single' => [
        'L\'expulsion de :player (:minute\') a marqué le cours de la rencontre pour :el_team.',
        'Le match a basculé avec le rouge pour :player (:team) à la :minute.',
    ],
    'red_cards_multiple' => [
        'Les expulsions dans :el_team ont conditionné le résultat.',
        ':el_team s\'est retrouvé en infériorité numérique après :count expulsions.',
    ],
    'dominant_first_half' => [
        'Les buts se sont concentrés en première période du match.',
        'Première période intense avec tous les buts de la rencontre.',
    ],
    'dominant_second_half' => [
        'L\'émotion s\'est concentrée en seconde période, où sont arrivés les buts.',
        'Le match s\'est ouvert en seconde période, avec beaucoup d\'occasions pour les deux équipes.',
    ],

    // =========================================================================
    // Form / streak (league only)
    // =========================================================================
    'form_losing_streak' => [
        ':el_team s\'enfonce après avoir enchaîné :count défaites consécutives.',
        ':el_team ne connaît toujours pas la victoire après :count défaites de suite.',
        'Crise à :el_team, qui accumule :count défaites consécutives.',
    ],
    'form_winning_streak' => [
        ':el_team reste irrésistible et enchaîne :count victoires consécutives.',
        'Série spectaculaire :del_team, qui accumule :count succès de suite.',
        ':el_team n\'arrête pas de gagner, déjà :count victoires de suite.',
    ],
    'form_winless' => [
        ':el_team ne gagne toujours pas, déjà :count matchs sans victoire.',
        ':el_team ne redresse pas la tête et accumule :count rencontres sans gagner.',
    ],

    // =========================================================================
    // Color commentary (emotion, drama, opinion)
    // =========================================================================
    'last_minute_winner' => [
        'But de :player à la :minute\' pour donner la victoire à :el_team in extremis !',
        ':player (:team) est apparue à la :minute\' pour conclure alors que tout semblait perdu !',
        'Folie dans le temps additionnel : :player a signé la victoire pour :el_team à la :minute\'.',
    ],
    'last_minute_equalizer' => [
        'But de :player (:team) à la :minute\' pour arracher un point quand personne ne s\'y attendait !',
        ':player (:team) a égalisé à la :minute\'. Le nul s\'est joué sur le gong.',
        'Nul arraché : :player a marqué à la :minute\' pour éviter la défaite de :el_team.',
    ],
    'hat_trick' => [
        'Triplé de :player ! Exhibition avec :goals buts.',
        'Récital de :player (:team), qui est rentrée chez elle avec :goals buts.',
        ':player a ramené le ballon à la maison ! :goals buts à encadrer.',
    ],
    'upset' => [
        'Claque pour :del_loser ! :el_winner a créé la surprise et remporte le match.',
        ':el_winner crée la surprise et fait tomber :al_loser avec une grande performance collective.',
        ':el_winner a mis les pronostics sens dessus dessous et gagne :en_venue.',
    ],
    'expected_win' => [
        ':el_winner n\'a laissé aucune option et a démontré sa supériorité à tout moment.',
        ':el_winner a fait valoir sa supériorité face à :el_loser dans un match sans histoire.',
        ':el_winner a battu :al_loser avec facilité :en_venue.',
    ],
    'high_scoring' => [
        'Les attaquantes ont été adroites dans un match où nous avons vu :total buts.',
        'Spectacle pour le supporter avec :total buts. Ça, c\'est du football !',
        'Match impressionnant où un total de :total buts sont montés au tableau d\'affichage',
    ],
    'few_chances' => [
        'Un match ennuyeux :en_venue où l\'on a vu peu de jeu et d\'occasions de but.',
        'Peu de football et encore moins de danger. Long pour les supporters.',
        'Match gris où il n\'y a eu quasiment aucune occasion de but.',
    ],

    // =========================================================================
    // Annotations
    // =========================================================================
    'penalty_goal_note' => 'sur penalty',
    'own_goal_note' => 'contre son camp',

    // =========================================================================
    // MVP closing
    // =========================================================================
    'mvp_closing' => [
        ':player (:team), nommée MVP du match.',
        'En outre, :player (:team) remporte le MVP de la rencontre.',
        ':player (:team), élue meilleure joueuse du match.',
        'MVP du match : :player (:team).',
    ],
];
