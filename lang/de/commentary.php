<?php

return [
    'atmosphere_shot_on_target' => [
        '!Schuss von :player (:team)! Die Torhüterin fängt sicher',
        ':player (:team) versucht es von außerhalb des Strafraums. Gute Parade',
        'Abschluss von :player (:team), der die Torhüterin zum Eingreifen zwingt',
        '!Die hatte :el_team! Schuss von :player, den die Torhüterin abwehrt',
        ':player (:team) schießt aufs Tor, aber die Torhüterin stand gut',
        '!Achtung, der Schuss von :player (:team)! Wahnsinnsparade der Torhüterin',
    ],
    'atmosphere_shot_off_target' => [
        '!Uhhh! Schuss daneben von :player (:team)',
        ':player (:team) versucht es, aber der Ball geht daneben',
        'Hoher Abschluss von :player (:team), der geht über die Latte',
        ':player (:team) schießt von der Strafraumgrenze... knapp vorbei',
        ':player (:team) versucht es aus der Distanz, findet aber das Tor nicht',
        '!Fast! :player (:team) ist nah dran, aber der Ball geht ins Aus',
    ],
    'atmosphere_foul' => [
        'Foul von :player (:team), der Schiedsrichter zögert nicht',
        ':player (:team) stoppt mit einem Foul einen vielversprechenden Angriff :del_opponent',
        'Foulspiel von :player (:team) im Mittelfeld',
        ':player (:team) kommt zu spät und begeht ein Foul',
        'Der Schiedsrichter pfeift Foul von :player (:team) nach einem Gerangel',
        'Foul von :player (:team) im Angriff, Ballbesitz wechselt',
    ],
    'contextual_draw_open' => [
        'Ausgeglichenes Spiel im :venue, keines der beiden Teams kann sich durchsetzen',
        'Kein klarer Favorit in der Partie zwischen :el_home und :el_away',
        'Ausgeglichenes Spiel im :venue, mit Chancen auf beiden Seiten',
        'Torloses Remis im :venue, beide Teams belauern sich vorsichtig',
        'Maximale Ausgeglichenheit zwischen :el_home und :el_away, die sich gegenseitig neutralisieren',
    ],
    'contextual_draw_with_goals' => [
        'Offenes und unterhaltsames Spiel im :venue, mit Toren auf beiden Seiten',
        'Unentschieden, das dem Gesehenen entspricht, mit Chancen für beide',
        'Gleichstand auf der Anzeigetafel und im Spiel zwischen :el_home und :el_away',
        'Ständiges Hin und Her im :venue, beide Abwehrreihen leiden',
        'Spektakel für die Fans im :venue, Tore auf beiden Seiten verteilt',
    ],
    'contextual_home_leading' => [
        ':el_home kontrolliert Spiel und Ergebnis im :venue',
        'Klare Dominanz :del_home, das die Partie nach Belieben steuert',
        'Gutes Spiel :del_home, das in Führung liegt',
        ':el_home liegt im :venue bequem in Führung',
    ],
    // Variantes con connotación de localía — solo se usan cuando hay ventaja de campo
    'contextual_home_leading_home_only' => [
        'Die Gastgeberinnen machen bisher alles richtig, :el_home verdient in Führung',
    ],
    'contextual_away_leading' => [
        ':el_away spielt stark im :venue',
        'Schwierig wird es für :al_home, das im Rückstand liegt',
    ],
    // Variantes con connotación de localía — solo se usan cuando hay ventaja de campo
    'contextual_away_leading_home_only' => [
        ':el_away überrascht :al_home im eigenen Stadion',
        ':el_away bringt das Publikum im :venue mit einer starken Leistung zum Schweigen',
        'Starke Auswärtsleistung, :el_away führt das Spiel in der Fremde',
    ],
    'contextual_home_dominant' => [
        ':el_home drückt beharrlich, sammelt gefährliche Angriffe',
        'Druck :del_home, das mit viel Intensität das Tor sucht',
        'Viel :home in diesen Minuten, mit ständigen Vorstößen in den gegnerischen Strafraum',
        ':el_home schnürt :al_away im eigenen Strafraum ein, Angriffswelle um Angriffswelle',
    ],
    // Variantes con connotación de localía — solo se usan cuando hay ventaja de campo
    'contextual_home_dominant_home_only' => [
        'Die Gäste kommen nicht aus der eigenen Hälfte, :el_home drückt ohne Pause',
    ],
    'contextual_away_dominant' => [
        'Frühes Pressing :del_away, das den Spielaufbau :del_home erschweren will',
        ':el_away droht bei jedem Konter mit Gefahr',
        'Viel Intensität :del_away, das in diesen Minuten das Spiel bestimmt',
        ':el_away gibt den Ton an, :el_home findet nicht ins Spiel',
        'Absolute Kontrolle :del_away, das Ballbesitz und Territorium dominiert',
    ],
    'contextual_tight_game' => [
        'Sehr zerfahrenes Spiel im Mittelfeld, mit wenigen klaren Chancen',
        'Viel Intensität und wenig Klarheit, der Ball erreicht keine Torhüterin gefährlich',
        'Geschlossenes Spiel im :venue, mit mehr Kampf als Fußball',
        'Keines der beiden traut sich nach vorne, sehr vorsichtige Begegnung im :venue',
        'Schlacht im Mittelfeld, die Chancen glänzen durch Abwesenheit',
    ],
    'contextual_end_losing' => [
        'Die Zeit läuft und dieses Ergebnis reicht :al_trailing nicht, das reagieren muss',
        'Die Minuten schwinden für :el_trailing, das es sehr schwer hat',
        ':el_trailing wirft alles nach vorne, aber der Unterschied scheint unüberwindbar',
        'Ohne Ideen und ohne Zeit, :el_trailing hat einen Berg zu erklimmen',
        'Verzweiflung bei :el_trailing, während die Uhr herunterläuft',
    ],
    'contextual_end_losing_by_one' => [
        'Die Zeit läuft gegen :del_trailing, das den Ausgleich mit mehr Herz als Kopf sucht',
        ':el_trailing entgleitet der Ausgleich, wenige Minuten bleiben im :venue',
        'Kann :el_trailing das benötigte Tor finden? Die Zeit ist nicht auf seiner Seite',
        ':el_trailing drückt und drückt, aber der Ausgleich will nicht fallen',
        'Alles nach vorne :del_trailing, ein Tor würde alles ändern',
    ],
    'contextual_end_winning' => [
        ':el_leading kontrolliert die Schlussminuten der Partie ohne zu leiden',
        ':el_leading verwaltet die letzten Minuten der Begegnung in aller Ruhe',
        'Es riecht schon nach Sieg für :el_leading im :venue',
        ':el_leading kühlt das Spiel ab, hält den Ball in aller Ruhe',
        'Arbeit fast getan für :el_leading, das das bessere Team war',
    ],
    'contextual_end_draw' => [
        'Letzte Minuten und Punkteteilung, wenn kein Schlussspurt mehr kommt',
        'Das Spiel endet im :venue mit einem Remis',
        'Unentschieden im :venue, das keines der beiden so recht zufriedenstellt',
        'Es steuert auf ein Remis im :venue zu, keines findet das Siegtor',
        'Ein Punkt für jedes scheint das Endergebnis im :venue zu sein',
    ],
    'contextual_end_draw_knockout' => [
        'Das Ende der regulären Spielzeit im :venue naht und das Duell ist weiter offen',
        'Remis im :venue, das riecht nach Verlängerung',
        'Keines findet das entscheidende Tor, die Verlängerung rückt immer näher',
        'Die Minuten schwinden im :venue mit dem Unentschieden, das die Frage nicht löst',
        'Letzter Anstoß, um die Verlängerung zu vermeiden, keines will die Qual verlängern',
    ],
    'contextual_second_half_start' => [
        'Die zweite Hälfte beginnt im :venue beim Stand von :score',
        'Zurück in Aktion im :venue. :score zur Pause',
        'Die Teams kommen zurück auf den Rasen im :venue. :score der Spielstand',
        'Die zweite Hälfte beginnt im :venue, :score zur Halbzeit',
        'Das Spiel wird im :venue fortgesetzt mit :score auf der Anzeigetafel',
    ],
    'contextual_away_fans' => [
        'Die mitgereisten Fans :del_away feuern ihr Team im :venue von der Tribüne an',
        'Die Anhänger :del_away machen sich im :venue bemerkbar',
        'Die Auswärtsfans :del_away hören nicht auf zu singen im :venue',
        'Tolle Stimmung auf der Gästetribüne, die Anhänger :del_away pushen die ihren',
        'Spektakuläre Unterstützung der mitgereisten Fans :del_away heute',
    ],
    'contextual_home_fans' => [
        'Das :venue tobt vor Begeisterung, die Fans :del_home peitschen die ihren nach vorne',
        'Das Publikum im :venue steht in diesen Minuten hinter seinem Team',
        'Riesige Stimmung im :venue, die Fans :del_home sind voll dabei',
        'Das :venue bebt, die Tribüne feuert :al_home ununterbrochen an',
        'Die Fans :del_home sind heute die zwölfte Spielerin im :venue',
    ],
    // Prefijo de goles — se antepone a cada narración de gol para darle énfasis
    'goal_prefix' => [
        '!Tor :del_team!',
        '!TOR :del_team!',
        '!TRAUMTOR :del_team!',
        '!TOOOOOR :del_team!',
        '!TOOR :del_team!',
        '!Tor :del_team!',
        '!Trifft :el_team!',
        '!Netzt ein :el_team!',
        '!Was für ein Tor :del_team!',
        '!Traumtor :del_team!',
    ],
    'goal_assisted' => [
        'Flanke in den Strafraum und :player steht frei zum Kopfballtor',
        ':player nimmt am Elfmeterpunkt an, kontrolliert und vollendet mit Klasse',
        'Steilpass für :player, sie steht frei vor der Torhüterin und bleibt eiskalt',
        '!Was für ein Kombinationsspiel :del_team! :player vollendet mit feinem Touch',
        'Unwiderstehlicher Kopfball von :player an den zweiten Pfosten. Unhaltbar für die Torhüterin',
        'Maßflanke von der Seite und :player köpft nach Belieben ein',
        'Tödlicher Konter :del_team. :player vollendet eiskalt gegen die herauslaufende Torhüterin',
        'Doppelpass an der Strafraumgrenze und :player schiebt aus dem Fünfmeterraum ein',
        ':player kommt der Abwehr zuvor und schießt volley ins Netz',
        'Großer Assist und :player muss nur noch einschieben. Sie bleibt fehlerfrei',
        'Cleverer Laufweg von :player, die frei annimmt und den Ball über die Torhüterin lupft',
        ':player ist vor der Abwehr am Ball und schiebt ihn am ersten Pfosten ein',
        ':player trifft volley spektakulär, der Ball schlägt wie eine Bombe ein',
        'Ball ins Zentrum und :player trifft mit der Pike zum Tor',
    ],
    'goal_solo' => [
        '!Traumtor von :player! Sie lässt die Torhüterin aussteigen und schiebt rechts ein',
        ':player stellt die Abwehr, legt sich den Ball zurecht und hämmert ihn in den Winkel',
        '!Hammer von :player von außerhalb des Strafraums! Traumtor',
        ':player nimmt den Abpraller und schickt ihn ins Netz',
        'Sololauf von :player, die zwei Gegnerinnen stehen lässt und überlegt vollendet',
        '!Was für ein Traumtor von :player! Schuss mit Effet von der Strafraumgrenze, der im Winkel einschlägt',
        ':player nutzt einen Abwehrfehler und überwindet die Torhüterin mit einem flachen Schuss',
        'Distanzschuss von :player, der abgefälscht wird und die Torhüterin überrascht',
        'Direkter Freistoß von :player, der über die Mauer fliegt und neben dem Pfosten einschlägt',
        ':player zieht im Strafraum nach innen, findet die Lücke und schießt überlegt. Tor!',
        '!Sie nagelt ihn rein, :player! Direktabnahme von der Strafraumgrenze',
        'Ballgewinn und :player zögert nicht, vollendet mit einem unhaltbaren platzierten Schuss',
        ':player zaubert ein Solo-Traumtor von der Strafraumgrenze',
    ],

    // Narrativas tácticas — generadas según las configuraciones tácticas
    'tactical_high_press_working' => [
        ':user presst mit wilder Intensität und schnürt den Spielaufbau :del_opp ein',
        'Das hohe Pressing :del_user erobert den Ball in gefährlichen Zonen',
        ':user presst ohne Pause — :opp kommt kaum aus der eigenen Hälfte',
    ],
    'tactical_high_press_fading' => [
        'Die Intensität des Pressings :del_user beginnt zu sinken. Die Beine werden schwer',
        ':user kann dieses Anfangstempo nicht halten — :opp findet mehr Räume',
        'Man merkt die Müdigkeit. Das Pressing :del_user verliert an Biss',
    ],
    'tactical_high_press_exhausted' => [
        ':user wirkt erschöpft. Das hohe Pressing fordert in der Schlussphase seinen Tribut',
        'Die Kräfte schwinden bei :user — dieses aggressive Pressing rächt sich',
        ':opp wittert die Müdigkeit :del_user und stürmt mit Selbstvertrauen nach vorne',
    ],
    'tactical_opp_press_fading' => [
        'Das hohe Pressing :del_opp verliert an Kraft — :user sollte mehr Raum finden',
        'Das Pressing :del_opp ist nicht mehr wie zuvor. Die Räume öffnen sich',
    ],
    'tactical_opp_exhausted' => [
        ':opp wirkt platt nach all dem Pressing. :user kann die müden Beine ausnutzen',
        'Das hohe Pressing hat :al_opp ausgelaugt — man merkt, dass sie auf dem Zahnfleisch gehen',
    ],
    'tactical_low_block_wall' => [
        ':user steht kompakt und tief und erschwert :del_opp das Spiel enorm',
        'Eine disziplinierte Abwehrmauer :del_user. :opp findet keinen Weg hinein',
        ':user verteidigt mit vielen Spielerinnen und lässt :al_opp keine klare Chance',
    ],
    'tactical_low_block_fresh' => [
        'Die konservative Ausrichtung :del_user zahlt sich aus — die Spielerinnen wirken noch frisch',
        'Hohe Energiewerte bei :user dank der defensiven Disziplin',
    ],
    'tactical_possession_control' => [
        ':user kontrolliert das Tempo, lässt den Ball geduldig laufen und sucht Lücken',
        'Dominanter Ballbesitz :del_user — :opp läuft nur hinterher',
        ':user lässt den Ball gut laufen und diktiert das Tempo des Spiels',
    ],
    'tactical_possession_frustrated' => [
        ':user dominiert den Ballbesitz, findet aber keinen Weg durch den tiefen Block :del_opp',
        'Viel Ballbesitz für :user, aber der tiefe Block :del_opp frustriert jeden Angriff',
    ],
    'tactical_counter_waiting' => [
        ':user lauert geduckt, bereit, jederzeit zum Konter zu starten',
        ':user überlässt das Territorium — und will in der Umschaltphase zuschlagen',
        'Geduldige Defensive :del_user, bereit zuzuschlagen, wenn sich die Chance bietet',
    ],
    'tactical_counter_exploiting' => [
        ':user nutzt den Raum hinter der hohen Linie :del_opp mit tödlichen Kontern',
        'Die aggressive Ausrichtung :del_opp hinterlässt Lücken — :user bestraft sie im Konter',
    ],
    'tactical_direct_play' => [
        ':user überspringt das Mittelfeld mit langen Bällen und hält :opp in Alarmbereitschaft',
        'Direktes Spiel :del_user — ohne Schnörkel, langer Ball auf die Stürmerinnen',
    ],
    'tactical_direct_bypassing_press' => [
        'Das direkte Spiel :del_user fliegt über das hohe Pressing :del_opp hinweg — die langen Bälle finden ihr Ziel',
        'Das Pressing :del_opp wird durch die langen Bälle :del_user ausgehebelt',
    ],
    'goal_penalty' => [
        '!Elfmeter! :player (:team) tritt entschlossen an und trifft. Keine Chance für die Torhüterin',
        ':player (:team) legt sich den Ball zurecht, läuft an und hämmert ihn in den Winkel. Elfmetertor!',
        'Elfmeter für :el_team. :player nimmt Anlauf und schickt die Torhüterin mit einem platzierten Schuss in die falsche Ecke',
        ':player (:team) übernimmt die Verantwortung vom Punkt und bleibt fehlerfrei. Unwiderstehlich',
        '!Elfmetertor! :player (:team) schießt in die Mitte, während die Torhüterin in die Ecke fliegt',
        'Strafstoß für :el_team. :player wartet die Torhüterin ab, sieht sie sich bewegen und legt den Ball in die andere Ecke',
    ],
    // Sabor táctico en los goles
    'goal_counter_attack' => [
        '!Tödlicher Konter! :player vollendet nach einem verheerenden Konter von :team',
        '!Klinisch im Konter! :player trifft nach einem blitzschnellen Vorstoß von :team',
        '!Kontertor! :team schaltet blitzschnell um und :player vollendet',
    ],
    'goal_possession' => [
        ':team lässt den Ball geduldig laufen, bis :player die Lücke findet. Ballbesitz wie aus dem Lehrbuch',
        'Geduldiger Aufbau von :team und :player wählt den perfekten Moment zum Abschluss',
        'Kombinationsspiel :del_team — :player setzt den Schlusspunkt',
    ],
    'goal_direct' => [
        '!Langer Ball und :player ist da, um für :team zu vollenden!',
        ':team spielt direkt und es funktioniert — :player kontrolliert und vollendet',
        '!Direktes Spiel pur! Der lange Ball findet :player, die eiskalt bleibt',
    ],

    // Anuncio del tiempo añadido. El cliente elige la variante singular o
    // plural según los minutos (:minutes) para evitar "1 minutos".
    'stoppage_announcement_singular' => [
        'Der Schiedsrichter gibt :minutes Minute Nachspielzeit',
        'Der vierte Offizielle zeigt :minutes Minute Nachspielzeit an',
        ':minutes Minute Nachspielzeit',
        'Nur :minutes Minute Nachspielzeit',
    ],
    'stoppage_announcement_plural' => [
        'Der Schiedsrichter gibt :minutes Minuten Nachspielzeit',
        'Der vierte Offizielle zeigt :minutes Minuten Nachspielzeit an',
        ':minutes Minuten Nachspielzeit',
        'Nachspielzeit: :minutes Minuten',
        ':minutes Minuten werden angehängt',
        '!:minutes Minuten Nachspielzeit! Es ist noch Zeit',
    ],
];
