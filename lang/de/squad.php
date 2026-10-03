<?php

return [
    // Page title
    'squad' => 'Kader',
    'first_team' => 'Erste Mannschaft',
    'development' => 'Entwicklung',
    'stats' => 'Statistiken',

    // Position groups
    'goalkeepers' => 'Torhüterinnen',
    'defenders' => 'Verteidigerinnen',
    'midfielders' => 'Mittelfeldspielerinnen',
    'forwards' => 'Angreiferinnen',
    'goalkeepers_short' => 'TW',
    'defenders_short' => 'ABW',
    'midfielders_short' => 'MF',
    'forwards_short' => 'ANG',

    // Columns
    'years_abbr' => 'Jahre',
    'fitness' => 'FIT',
    'morale' => 'MOR',
    'overall' => 'Gesamt',
    'overall_short' => 'GES',
    'attack_short' => 'ANG',
    'defense_short' => 'ABW',
    'attack_xg_label' => 'Angriff xG',
    'defense_xg_label' => 'Abwehr xG',

    // Status labels
    'on_loan' => 'Ausgeliehen',
    'loaned_from' => 'Ausgeliehen von',
    'loaned_to' => 'Ausgeliehen an',
    'leaving_free' => 'Verlässt den Klub (ablösefrei)',
    'renewed' => 'Verlängert',
    'sale_agreed' => 'Verkauf vereinbart',
    'retiring' => 'Beendet Karriere',
    'listed' => 'Zum Verkauf',
    'list_for_sale' => 'Zum Verkauf anbieten',
    'unlist_from_sale' => 'Vom Verkauf nehmen',
    'loan_out' => 'Verleihen',
    'release_player' => 'Freigeben',
    'release_confirm_title' => 'Spielerin freigeben',
    'release_confirm_message' => 'Bist du dir sicher, dass du :player freigeben möchtest? Diese Aktion kann nicht rückgängig gemacht werden.',
    'release_severance_label' => 'Abfindungskosten',
    'release_remaining_contract' => 'Restvertrag',
    'release_years_remaining' => ':years Jahr(e)',
    'release_confirm_button' => 'Freigabe bestätigen',
    'mutual_terminate' => 'Vertrag einvernehmlich auflösen',
    'loan_searching' => 'Leihe-Ziel wird gesucht',
    'contract_expiring' => 'Vertrag läuft aus',

    // Summary
    'wage_bill' => 'Gehaltsbudget',
    'per_year' => '/Jahr',
    'avg_fitness' => 'Durchschn. Fitness',
    'avg_morale' => 'Durchschn. Moral',
    'low' => 'niedrig',

    // Contract management
    'free_transfer' => 'Ablösefrei',
    'let_go' => 'Ziehen lassen',
    'pre_contract_signed' => 'Vorvertrag unterschrieben',
    'new_wage_from_next' => 'Neues Gehalt ab nächster Saison',
    'has_pre_contract_offers' => 'Hat Vorvertragsangebote!',
    'renew' => 'Verlängern',
    'expires_in_days' => '{0}Läuft heute aus|{1}Läuft in :count Tag aus|[2,*]Läuft in :count Tagen aus',

    // Lineup validation
    'formation_position_mismatch' => 'Die Formation :formation erfordert :required :position, aber du hast :actual ausgewählt.',
    'player_not_available' => 'Eine oder mehrere ausgewählte Spielerinnen sind nicht verfügbar.',

    // Lineup
    'formation' => 'Formation',
    'mentality' => 'Mentalität',
    'auto_select' => 'Auto-Auswahl',
    'opponent' => 'Gegner',
    'need' => 'du brauchst',

    // Compatibility
    'natural' => 'Natürlich',
    'very_good' => 'Sehr gut',
    'good' => 'Gut',
    'okay' => 'Akzeptabel',
    'poor' => 'Schwach',
    'unsuitable' => 'Ungeeignet',


    // Lineup editor
    'pitch' => 'Spielfeld',

    // Opponent scout
    'injured' => 'verletzt',
    'suspended' => 'gesperrt',

    // Coach assistant
    'coach_recommendations' => 'Empfehlungen',
    'coach_no_tips' => 'Keine besonderen Empfehlungen für dieses Spiel.',
    'coach_defensive_recommended' => 'Stärkerer Gegner. Defensive Mentalität reduziert deren erwartete Tore um 30%.',
    'coach_attacking_recommended' => 'Du hast die Oberhand. Eine offensive Mentalität kann deine Tore maximieren.',
    'coach_risky_formation' => 'Deine offensive Formation gegen einen überlegenen Gegner gibt ihnen mehr Chancen. Erwäge eine defensivere.',
    'coach_home_advantage' => 'Ihr spielt zu Hause (+0.15 erwartete Tore).',
    'coach_critical_fitness' => ':names mit kritischer Energie (<50). 2x höheres Verletzungsrisiko. Erwäge Rotation.',
    'coach_low_fitness' => ':count Spielerin(nen) mit niedriger Energie (<70). Spielen schlechter und haben höheres Verletzungsrisiko.',
    'coach_low_morale' => ':count Spielerin(nen) mit niedriger Moral. Werden im Spiel schlechter performen.',
    'coach_bench_frustration' => ':count starke Spielerin(nen) ohne Spielzeit verlieren Moral. Rotiere, um sie zufrieden zu halten.',
    'coach_opponent_expected_label' => 'Erwartet',
    'coach_opponent_defensive_setup' => 'Gegner voraussichtlich mit :formation (:mentality). Erwäge einen offensiven Ansatz, um sie zu knacken.',
    'coach_opponent_attacking_setup' => 'Gegner voraussichtlich mit :formation (:mentality). Sie lassen Räume — eine solide Defensive kann das ausnutzen.',
    'coach_opponent_deep_block' => 'Gegner mit 5er-Kette. Breite und Geduld werden entscheidend sein.',
    'coach_out_of_position' => ':names außer Position. Werden im Spiel schlechter performen.',
    'mentality_defensive' => 'Defensiv',
    'mentality_balanced' => 'Ausgeglichen',
    'mentality_attacking' => 'Offensiv',

    // Unavailability reasons
    'suspended_matches' => 'Gesperrt (:count Spiel)|Gesperrt (:count Spiele)',
    'injured_generic' => 'Verletzt',
    'injury_return_date' => 'fällt aus bis :date',

    // Injury types
    'injury_muscle_fatigue' => 'Muskelermüdung',
    'injury_muscle_strain' => 'Muskelzerrung',
    'injury_calf_strain' => 'Wadenzerrung',
    'injury_ankle_sprain' => 'Verstauchung des Sprunggelenks',
    'injury_groin_strain' => 'Adduktorenzerrung',
    'injury_hamstring_tear' => 'Hinterer Oberschenkelriss',
    'injury_knee_contusion' => 'Knieprellung',
    'injury_metatarsal_fracture' => 'Mittelfußbruch',
    'injury_acl_tear' => 'Kreuzbandriss',
    'injury_achilles_rupture' => 'Achillessehnenriss',

    // Development page
    'ability' => 'Fähigkeit',
    'playing_time' => 'Minuten',
    'high_potential' => 'Hohes Potenzial',
    'growing' => 'Wachsend',
    'declining' => 'Nachlassend',
    'peak' => 'Auf dem Höhepunkt',
    'all' => 'Alle',
    'no_players_match_filter' => 'Keine Spielerin passt zum gewählten Filter.',
    'pot' => 'POT',
    'apps' => 'Sp',
    'projection' => 'Prognose',
    'potential' => 'Potenzial',
    'potential_range' => 'Potenzialspanne',
    'starter_bonus' => 'Stammplatz-Bonus',
    'needs_appearances' => 'Braucht :count+ Spiele für Stammplatz-Bonus',
    'qualifies_starter_bonus' => 'Qualifiziert für Stammplatz-Bonus (+50% Entwicklung)',

    // Stats page
    'goals' => 'T',
    'assists' => 'V',
    'goal_contributions' => 'T+V',
    'goals_per_game' => 'T/Sp',
    'own_goals' => 'ET',
    'yellow_cards' => 'GK',
    'red_cards' => 'RK',
    'clean_sheets' => 'ZU',
    'appearances' => 'Spiele',
    'bookings' => 'Verwarnungen',
    'click_to_sort' => 'Klicke auf die Spaltenüberschriften zum Sortieren',

    // Stats highlights
    'top_in_squad' => 'Kader-Bestwert',

    // Legend labels
    'legend_apps' => 'Spiele',
    'legend_goals' => 'Tore',
    'legend_assists' => 'Vorlagen',
    'legend_contributions' => 'Torbeteiligungen',
    'legend_own_goals' => 'Eigentore',
    'legend_mvp' => 'MVP-Auszeichnungen',
    'legend_clean_sheets' => 'Zu-Null-Spiele (nur TW)',

    // Squad number
    'assign_number' => 'Rückennummer vergeben',
    'number_taken' => 'Diese Nummer ist bereits vergeben',
    'number_updated' => 'Nummer aktualisiert',
    'number_invalid' => 'Die Nummer muss zwischen 1 und 99 liegen',

    // Player detail modal
    'abilities' => 'Fähigkeiten',
    'overall_full' => 'Gesamtwert',
    'fitness_full' => 'Energie',
    'morale_full' => 'Moral',
    'season_stats' => 'Saisonstatistiken',
    'clean_sheets_full' => 'Zu-Null-Spiele',
    'goals_conceded_full' => 'Gegentore',
    'discovered' => 'Entdeckt',
    'origin' => 'Herkunft',
    'joined' => 'Wechsel',
    'origin_academy' => 'Nachwuchs',
    'origin_free_agent' => 'Vereinslos',
    'precontract_banner_title' => 'Vorvertrag unterschrieben',
    'precontract_banner_body' => 'Noch nicht in deinem Kader — stößt zu Saisonbeginn :year ablösefrei dazu.',
    'career_history' => 'Karriereverlauf',
    'no_career_history' => 'Noch keine abgeschlossenen Saisons.',

    // Academy
    'academy' => 'Nachwuchsakademie',
    'promote_to_first_team' => 'In die erste Mannschaft hochziehen',
    'academy_tier' => 'Akademie-Level',
    'academy_players' => 'Spielerinnen',
    'no_academy_prospects' => 'Keine Nachwuchstalente verfügbar.',
    'academy_explanation' => 'Neue Talente kommen zu Beginn jeder Saison entsprechend deiner Akademie-Investition.',
    'academy_dismiss' => 'Entlassen',
    'academy_dismiss_confirm' => 'Bist du dir sicher? Die Spielerin wird endgültig entlassen.',
    'academy_dismiss_desc' => 'Die Spielerin wird endgültig aus dem Klub entlassen.',
    'academy_loan_out' => 'Verleihen',
    'academy_loan_desc' => 'Die Spielerin wird mit beschleunigter Entwicklung (1.5x) verliehen und kehrt am Saisonende zurück.',
    'academy_promote' => 'Hochziehen',
    'academy_promote_desc' => 'Die Spielerin stößt mit Profivertrag zur ersten Mannschaft.',
    'academy_on_loan' => 'Verliehen',
    'academy_seasons' => ':count Saison|:count Saisons',
    // Academy help text
    'academy_help_toggle' => 'Wie funktioniert die Akademie?',
    'academy_help_development' => 'Die Akademie funktioniert wie dein B-Team und bildet Spielerinnen auf Kaderniveau aus. Die Talente entwickeln sich im Laufe der Saison und können in die erste Mannschaft hochgezogen werden, wenn sie bereit sind.',
    'academy_help_actions_title' => 'Verfügbare Aktionen',
    'academy_help_promote' => 'Hochziehen — stößt dauerhaft mit Profivertrag zur ersten Mannschaft',
    'academy_help_loan' => 'Verleihen — entwickelt sich verliehen schneller und kehrt am Saisonende zurück',
    'academy_help_dismiss' => 'Entlassen — verlässt den Klub dauerhaft',
    'academy_help_age_rule' => 'Spielerinnen, die 21 werden, rücken zu Saisonbeginn automatisch in die erste Mannschaft auf.',

    'academy_tier_0' => 'Minimale Akademie',
    'academy_tier_1' => 'Basis-Akademie',
    'academy_tier_2' => 'Gute Akademie',
    'academy_tier_3' => 'Elite-Akademie',
    'academy_tier_4' => 'Weltklasse-Akademie',
    'academy_tier_unknown' => 'Unbekannt',

    // Reserve team (filial)
    'reserve_team' => 'Zweite Mannschaft',
    'reserve_squad' => 'Kader der Zweiten',
    'no_reserve_players' => 'Keine Spielerinnen in der Zweiten.',
    'call_up' => 'Hochziehen',
    'call_up_to_first_team' => 'In die erste Mannschaft hochziehen',
    'send_back' => 'Zurückschicken',
    'send_back_to_reserve' => 'Zurück zur Zweiten',
    'send_down_to_reserve' => 'Zur Zweiten schicken',
    'send_down_to_reserve_confirm' => 'Diese U23-Spielerin zur Zweiten schicken?',
    'called_up_indicator' => 'In der ersten Mannschaft',
    'homegrown_indicator' => 'Eigengewächs',
    'actions' => 'Aktionen',
    'reserve_help_toggle' => 'Wie funktioniert die Zweite?',
    'reserve_help_development' => 'Deine Zweite ist die offizielle Nachwuchsmannschaft — die Spielerinnen gehören zur Zweiten, nicht zur ersten Mannschaft. Jede Saison kommen neue Talente entsprechend deiner Akademie-Investition, die sich zusammen mit der restlichen Zweiten entwickeln.',
    'reserve_help_age_rule' => 'Spielerinnen, die 24 werden, rücken am Saisonende automatisch in die erste Mannschaft auf. Die erste Mannschaft kann jederzeit Spielerinnen aus der Zweiten hochziehen.',
    'reserve_help_actions_title' => 'Verfügbare Aktionen',
    'reserve_help_call_up' => 'Hochziehen — die Spielerin stößt auf Leihbasis zur ersten Mannschaft und kann Spiele für die erste Mannschaft bestreiten',
    'reserve_help_send_back' => 'Zurückschicken — schickt die hochgezogene Spielerin zurück zur Zweiten',

    // Lineup help text
    'lineup_help_toggle' => 'Wie funktioniert die Aufstellung?',
    'lineup_help_intro' => 'Wähle 11 Spielerinnen für jedes Spiel. Formation, Energie und Positionsverträglichkeit beeinflussen die Leistung.',
    'lineup_help_formation_title' => 'Formation und Mentalität',
    'lineup_help_formation_desc' => 'Die Formation bestimmt, welche Positionen auf dem Feld verfügbar sind. Spielerinnen spielen in ihrer natürlichen Position am besten.',
    'lineup_help_compatibility_natural' => 'Natürlich — die Spielerin ist auf ihrer besten Position, volle Leistung.',
    'lineup_help_compatibility_good' => 'Sehr gut — spielt ohne Abzug. Gut — 25% Abzug im Spiel.',
    'lineup_help_compatibility_poor' => 'Schwach / Ungeeignet — 25% Abzug. Wenn möglich vermeiden.',
    'lineup_help_mentality_desc' => 'Die Mentalität beeinflusst, wie offensiv oder defensiv dein Team spielt.',
    'lineup_help_condition_title' => 'Energie und Moral',
    'lineup_help_condition_desc' => 'Spielerinnen mit niedriger Energie oder Moral spielen schlechter. Rotiere den Kader, um alle frisch zu halten.',
    'lineup_help_fitness' => 'Die Energie sinkt während jedes Spiels und erholt sich zwischen den Spieltagen. Die Spielerinnen beginnen die Spiele mit ihrem aktuellen Energiewert — manage die Rotation, um sie frisch zu halten.',
    'lineup_help_morale' => 'Die Moral wird von Ergebnissen, Spielzeit und Vertragssituation beeinflusst.',
    'lineup_help_auto' => 'Nutze "Auto-Auswahl", damit das System die beste verfügbare Elf für deine Formation wählt.',

    // Squad selection (tournament onboarding)
    'squad_selection_title' => 'Wähle deinen Kader',
    'squad_selection_subtitle' => 'Wähle 26 Spielerinnen für das Turnier',
    'confirm_squad' => 'Bestätigen',
    'squad_confirmed' => 'Kader bestätigt!',
    'invalid_selection' => 'Ungültige Auswahl. Überprüfe die ausgewählten Spielerinnen.',
    'download_squad' => 'Kader herunterladen',
    'squad_list' => 'Aufgebot',
    'called_up_badge' => 'Nominiert',

    // Radar chart
    'radar_gk' => 'Tor',
    'radar_def' => 'Abwehr',
    'radar_mid' => 'Mittelfeld',
    'radar_att' => 'Angriff',
    'radar_fit' => 'Energie',
    'radar_mor' => 'Moral',
    'radar_overall' => 'Gesamt',

    // Registration
    'not_registered' => 'Nicht gemeldet',
    'too_many_first_team' => 'Maximal 25 Meldungen für die erste Mannschaft (Nummern 1–25).',
    'drag_to_assign' => 'Ziehe eine Spielerin hierher, um sie zuzuweisen',

    // Grid positioning
    'drag_or_tap' => 'Tippe auf ein Feld oder ziehe die Spielerin',
    'select_player_for_slot' => 'Wähle eine Spielerin aus der Liste',

    // Squad dashboard KPIs
    'squad_size' => 'Kader',
    'avg_age' => 'Durchschnittsalter',
    'condition' => 'Zustand',
    'squad_value' => 'Kaderwert',

    // View modes
    'tactical' => 'Taktisch',
    'planning' => 'Planung',
    'numbers' => 'Nummern',

    // Table headers
    'mvp' => 'MVP',
    'cards' => 'Karten',
    'avg_ovr' => 'Ø-Wert',

    // Filters
    'available' => 'Verfügbar',
    'unavailable' => 'Nicht verfügbar',
    'clear_filters' => 'Filter zurücksetzen',

    // Sidebar
    'squad_analysis' => 'Kaderanalyse',
    'alerts' => 'Warnungen',
    'position_depth' => 'Positionstiefe',
    'age_profile' => 'Altersstruktur',
    'contract_watch' => 'Verträge',
    'expiring_this_season' => 'Laufen diese Saison aus',
    'no_contract_issues' => 'Keine ausstehenden Verträge',
    'highest_earners' => 'Top-Verdienerinnen',

    // Tooltips
    'tooltip_fitness' => 'Durchschnittliche Energie — bestimmt die Anfangsenergie in Spielen und beeinflusst die Leistung',
    'tooltip_morale' => 'Durchschnittliche Moral — beeinflusst Motivation und Konstanz',
    'tooltip_avg_overall' => 'Durchschnittlicher Kaderwert',

    // Alerts
    'alert_many_injured' => ':count verletzte Spielerinnen — erwäge Rotation der Stammkräfte',
    'alert_low_morale' => ':count Spielerinnen mit niedriger Moral',
    'alert_low_fitness' => ':count Spielerinnen mit niedriger Energie',
    'alert_thin_position' => 'Nur :count Spielerin(nen) auf :position — dünne Besetzung',
    'alert_no_cover' => 'Keine Besetzung auf :position',
    'alert_no_natural_cover' => 'Keine natürliche :position — nur Teillösung verfügbar',
    'alert_window_closing' => 'Das Transferfenster schließt am :date',

    // Number grid
    'number_grid' => 'Rückennummern',
    'assigned' => 'Vergeben',
    'available_number' => 'Verfügbar',

    // Column headers (new design)
    'player' => 'Spielerin',
    'pos' => 'Pos',
    'players_count' => 'Spielerinnen',
    'dev_status_label' => 'Status',

    // Morale labels
    'morale_ecstatic' => 'Euphorisch',
    'morale_happy' => 'Zufrieden',
    'morale_content' => 'Neutral',
    'morale_frustrated' => 'Frustriert',
    'morale_unhappy' => 'Unzufrieden',

    // Lineup tabs & labels
    'tactics' => 'Taktik',
    'defensive_line' => 'Abwehrkette',
    'unsaved_changes' => 'Ungespeicherte Änderungen',

    // Lineup redesign
    'opponent_goal' => 'Gegnertor',
    'available_players' => 'Verfügbare Spielerinnen',
    'substitutes' => 'Ersatzbank',
    'lineup_overview' => 'Aufstellungsübersicht',

    // Tactical presets
    'presets' => 'Gespeichert',
    'save_preset' => 'Taktik speichern',
    'preset_name' => 'Name',
    'preset_name_placeholder' => 'Z. B.: Stammelf, Pokal, Ersatz...',
    'preset_apply_now' => 'Diese Taktik im nächsten Spiel verwenden',
    'save_and_confirm' => 'Speichern und bestätigen',
    'preset_delete_confirm' => 'Diese gespeicherte Taktik löschen?',
    'preset_overwrite_toggle' => 'Taktik überschreiben',
    'preset_replace_required_hint' => 'Du hast bereits drei gespeicherte Taktiken. Wähle, welche durch die aktuelle Aufstellung ersetzt wird.',

    // Dorsales
    'number' => 'Nummer',

    // Inscripción de plantilla
    'registration' => 'Meldung',
    'registration_title' => 'Kadermeldung',
    'registration_subtitle' => 'Nummern für die Saison vergeben',
    'first_team_slots' => 'Erste Mannschaft (1–25)',
    'academy_slots' => 'Akademie (26–99)',
    'unregistered_players' => 'Nicht gemeldet',
    'empty_slot' => 'Leer',
    'save_registration' => 'Speichern',
    'registration_saved' => 'Meldung gespeichert',
    'registered_count' => ':count gemeldet',
    'academy_age_limit' => 'Nur als U23 markierte Spielerinnen können mit Akademie-Nummer (26–99) gemeldet werden',
    'registration_rules_title' => 'Melderegeln',
    'registration_rule_first_team' => 'Spielerinnen der ersten Mannschaft tragen Nummern 1 bis 25.',
    'registration_rule_academy' => 'Akademie-Nummern (26–99) sind für als U23 markierte Spielerinnen reserviert (unter 24 am 1. Januar).',
    'registration_rule_u23_badge' => 'Als U23 markierte Spielerinnen sind die ganze Saison für eine Akademie-Nummer berechtigt, auch wenn sie mitten in der Saison 24 werden — die Berechtigung hängt vom Alter am 1. Januar ab.',
    'registration_rule_unregistered' => 'Nicht gemeldete Spielerinnen können nicht für Spiele nominiert werden.',
    'registration_readonly' => 'Du kannst Spielerinnen nur während der Transferfenster melden und Nummern ändern.',
    'u23_badge_label' => 'U23',
    'u23_badge_tooltip' => 'Berechtigt für Akademie-Nummer — unter 24 am 1. Januar der Saison.',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'academy_jewel' => 'Juwel',
    'academy_jewel_tooltip' => 'Juwel der Nachwuchsarbeit: eine 16-jährige Nachwuchsspielerin mit erstklassigem Potenzial.',

];
