<?php

return [
    // =========================================================================
    // Broadcast shout (prefixed to every summary)
    // =========================================================================
    'shout' => [
        'FIM DO JOGO!!',
        'ACABOU!!',
        'NÃO HÁ MAIS TEMPO!!',
        'FINAL :en_venue!!',
        'TERMINOU!!',
        'APITO FINAL!!',
        'FIM DA PARTIDA!!',
        'JÁ NÃO HÁ MAIS!!',
    ],

    // =========================================================================
    // Opening sentences — League
    // =========================================================================
    'opening_home_win' => [
        ':el_home vence :en_venue frente :al_away (:score).',
        'Vitória :del_home :en_venue frente :al_away (:score).',
        ':el_home impõe-se :al_away :en_venue (:score).',
        'Vitória :del_home frente :al_away (:score).',
    ],
    // Variantes com conotação de casa — só usadas quando há vantagem do terreno
    'opening_home_win_home_only' => [
        'Os adeptos :del_home celebram a vitória contra :el_away (:score)',
    ],
    'opening_away_win' => [
        ':el_away vence :en_venue e leva o jogo (:score).',
        ':el_away leva a melhor :en_venue diante :el_home (:score).',
    ],
    // Variantes com conotação de casa — só usadas quando há vantagem do terreno
    'opening_away_win_home_only' => [
        'Vitória :del_away em casa :del_home (:score).',
    ],
    'opening_blowout' => [
        ':el_winner esmaga :al_loser :en_venue (:score) com uma grande exibição.',
        'Goleada :del_winner :en_venue frente :al_loser (:score), com uma grande exibição',
        'Tareia :del_winner :al_loser :en_venue (:score).',
        ':el_winner esmaga :al_loser (:score), que nunca teve hipóteses de levar o jogo',
        'Os adeptos :del_winner celebram a goleada frente :al_loser (:score).',
    ],
    'opening_draw' => [
        'Empate a :goals_each :en_venue entre :el_home e :el_away.',
        'Final :en_venue! :el_home não vai além do empate contra :el_away.',
        'Repartição de pontos :en_venue entre :el_home e :el_away (:score).',
        'Empate a :goals_each entre :el_home e :el_away onde nenhuma das equipas soube impor-se à outra',
        ':el_home e :el_away repartem os pontos e os golos (:score).',
    ],
    'opening_goalless' => [
        'Sem golos :en_venue entre :el_home e :el_away. Um empate a zero que não deixa nenhuma das equipas satisfeita.',
        'Empate sem golos :en_venue. Nem :el_home nem :el_away conseguem marcar num jogo em que houve de tudo, menos golos.',
        'A zero :en_venue. :el_home e :el_away repartem um ponto num jogo morno e sem golos.',
        'Sem golos entre :el_home e :el_away, num jogo que não será recordado pela emoção',
        'Empate sem golos: nem :el_home nem :el_away conseguem marcar.',
    ],
    'opening_narrow_win' => [
        ':el_winner leva a vitória pela margem mínima :en_venue (:score).',
        'Triunfo apertado :del_winner frente :al_loser :en_venue (:score).',
        ':el_winner sofre mas vence :en_venue frente :al_loser (:score).',
        ':el_winner leva a vitória pela margem mínima frente :al_loser (:score).',
        'Triunfo apertado :del_winner ante :el_loser (:score).',
    ],

    // =========================================================================
    // Opening sentences — Extra time & penalties (knockout)
    // =========================================================================
    'opening_extra_time' => [
        ':el_winner impõe-se no prolongamento :en_venue (:score).',
        'Precisou do prolongamento, mas :el_winner leva a vitória :en_venue (:score).',
        ':el_winner impõe-se no prolongamento (:score).',
        'Após o prolongamento, :el_winner leva a vitória (:score).',
    ],
    'opening_penalties' => [
        ':el_winner apura-se na marcação de grandes penalidades (:pen_score) após empatar :score_regular.',
        'As grandes penalidades decidem :en_venue. :el_winner apura-se (:pen_score).',
    ],

    // =========================================================================
    // Opening sentences — Cup-specific
    // =========================================================================
    'opening_cup_win' => [
        ':el_winner avança na :competition após impor-se :al_loser :en_venue (:score).',
        ':el_winner apura-se :en_venue frente :al_loser (:score).',
        ':el_loser fica eliminado :en_venue. :el_winner passa à ronda seguinte (:score).',
        ':el_winner avança na :competition após impor-se :al_loser (:score).',
        ':el_loser fica eliminado. :el_winner passa à ronda seguinte (:score).',
    ],
    'opening_cup_draw' => [
        'Empate :en_venue entre :el_home e :el_away (:score) na :competition.',
        ':el_home e :el_away empatam (:score) :en_venue pela :competition.',
        'Empate entre :el_home e :el_away (:score) na :competition.',
    ],

    // =========================================================================
    // Opening sentences — High stakes (semifinals, finals)
    // =========================================================================
    'opening_high_stakes_win' => [
        ':el_winner impõe-se :en_venue e avança na :competition! (:score)',
        'Enorme vitória :del_winner frente :al_loser :en_venue! (:score)',
        ':el_winner consegue! Vitória :en_venue frente :al_loser (:score).',
        ':el_winner impõe-se e avança na :competition! (:score)',
        'Enorme vitória :del_winner frente :al_loser! (:score)',
    ],
    'opening_high_stakes_champion' => [
        ':el_winner é campeão da :competition! Vitória na final :en_venue frente :al_loser (:score).',
        ':el_winner levanta o título da :competition após impor-se na final :al_loser (:score)!',
        'A :competition é :del_winner! Final resolvida :en_venue frente :al_loser (:score).',
    ],

    // =========================================================================
    // Goal narrative
    // =========================================================================
    'goals_one_team' => [
        ':scorers foram as marcadoras :del_team.',
        'Os golos :del_team foram assinados por :scorers.',
        ':el_team contou com os tentos de :scorers.',
    ],
    'goals_one_team_single_scorer' => [
        ':scorer marcou o único golo para :el_team.',
        ':scorer assinou o único tento :del_team.',
        'O golo :del_team foi assinado por :scorer.',
    ],
    'goals_team_fragment_single' => [
        ':scorer marcou para :el_team',
        ':scorer assinou o tento :del_team',
        'o golo :del_team foi obra de :scorer',
    ],
    'goals_team_fragment_multi' => [
        ':scorers fizeram os golos :del_team',
        ':scorers marcaram para :el_team',
        'os tentos :del_team foram assinados por :scorers',
    ],
    'goals_two_teams_join' => [
        ':a e :b.',
    ],
    'scorer_join_and' => 'e',

    // =========================================================================
    // Key moments
    // =========================================================================
    'comeback' => [
        ':el_winner deu a volta ao jogo após estar em desvantagem no marcador.',
        'Reviravolta :del_winner, que soube reagir após sofrer primeiro.',
        ':el_winner virou o marcador para levar a vitória.',
    ],
    'red_card_single' => [
        'A expulsão de :player (:minute\') marcou o rumo do encontro para :el_team.',
        'O jogo mudou com o vermelho a :player (:team) ao minuto :minute.',
    ],
    'red_cards_multiple' => [
        'As expulsões em :el_team condicionaram o resultado.',
        ':el_team ficou em inferioridade numérica após :count expulsões.',
    ],
    'dominant_first_half' => [
        'Os golos concentraram-se na primeira parte do jogo.',
        'Primeira parte intensa com todos os golos do encontro.',
    ],
    'dominant_second_half' => [
        'A emoção concentrou-se na segunda parte, onde chegaram os golos.',
        'O jogo abriu-se na segunda parte, com muitas oportunidades para ambas as equipas.',
    ],

    // =========================================================================
    // Form / streak (league only)
    // =========================================================================
    'form_losing_streak' => [
        ':el_team afunda-se após encadear :count derrotas consecutivas.',
        ':el_team continua sem conhecer a vitória após :count derrotas seguidas.',
        'Crise em :el_team, que soma :count derrotas consecutivas.',
    ],
    'form_winning_streak' => [
        ':el_team continua imparável e encadeia :count vitórias consecutivas.',
        'Sequência espetacular :del_team, que soma :count triunfos seguidos.',
        ':el_team não para de ganhar, já vão :count vitórias seguidas.',
    ],
    'form_winless' => [
        ':el_team continua sem ganhar, já vão :count jogos sem vitória.',
        ':el_team não levanta a cabeça e acumula :count encontros sem ganhar.',
    ],

    // =========================================================================
    // Color commentary (emotion, drama, opinion)
    // =========================================================================
    'last_minute_winner' => [
        'Golo de :player aos :minute\' para dar a vitória a :el_team in extremis!',
        ':player (:team) apareceu aos :minute\' para sentenciar quando tudo parecia perdido!',
        'Loucura nos descontos: :player assinou a vitória para :el_team aos :minute\'.',
    ],
    'last_minute_equalizer' => [
        'Golo de :player (:team) aos :minute\' para resgatar um ponto quando ninguém o esperava!',
        ':player (:team) igualou a contenda aos :minute\'. O empate foi cozinhado sobre a buzina.',
        'Empate agónico: :player marcou aos :minute\' para evitar a derrota de :el_team.',
    ],
    'hat_trick' => [
        'Hat-trick de :player! Exibição com :goals golos.',
        'Recital de :player (:team), que foi para casa com :goals golos.',
        ':player levou a bola para casa! :goals golos para emoldurar.',
    ],
    'upset' => [
        'Resultado inesperado para :del_loser! :el_winner surpreendeu e leva o jogo.',
        ':el_winner surpreende e derruba :al_loser com uma grande exibição coletiva.',
        ':el_winner virou os prognósticos do avesso e vence :en_venue.',
    ],
    'expected_win' => [
        ':el_winner não deu hipótese e demonstrou a sua superioridade em todos os momentos.',
        ':el_winner fez valer a sua superioridade diante :el_loser num jogo que não teve história.',
        ':el_winner derrotou :al_loser com facilidade :en_venue.',
    ],
    'high_scoring' => [
        'As avançadas estiveram certeiras num jogo onde vimos :total golos.',
        'Espetáculo para o adepto com :total golos. Isto é futebol!',
        'Jogo impressionante onde um total de :total golos subiram ao marcador',
    ],
    'few_chances' => [
        'Um jogo aborrecido :en_venue onde se viu pouco futebol e oportunidades de golo.',
        'Pouco futebol e menos perigo. Aos adeptos fez-se longo.',
        'Jogo cinzento em que mal houve oportunidades de golo.',
    ],

    // =========================================================================
    // Annotations
    // =========================================================================
    'penalty_goal_note' => 'de penálti',
    'own_goal_note' => 'na própria baliza',

    // =========================================================================
    // MVP closing
    // =========================================================================
    'mvp_closing' => [
        ':player (:team), nomeada MVP do jogo.',
        'Além disso, :player (:team) leva o MVP do encontro.',
        ':player (:team), eleita a melhor do jogo.',
        'MVP do jogo: :player (:team).',
    ],
];
