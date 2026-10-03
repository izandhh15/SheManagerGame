<?php

return [
    // Transfer messages
    'transfer_complete' => 'Transfer abgeschlossen! :player ist zu deinem Kader gestoßen.',
    'transfer_agreed' => ':message Der Transfer wird abgeschlossen, wenn das :window-Fenster öffnet.',
    'bid_exceeds_budget' => 'Das Angebot übersteigt dein Transferbudget.',
    'player_listed' => ':player zum Verkauf angeboten. Angebote können nach dem nächsten Spieltag eintreffen.',
    'player_unlisted' => ':player von der Transferliste genommen.',
    'cannot_sell_same_window' => ':player kann nicht verkauft werden — sie wurde kürzlich verpflichtet und kann noch nicht transferiert werden.',
    'offer_rejected' => 'Angebot :team_de abgelehnt.',
    'cannot_reject_release_clause_offer' => 'Du kannst dieses Angebot nicht ablehnen — es erreicht die Ausstiegsklausel von :player, daher ist der Verkauf obligatorisch.',
    'offer_accepted_sale' => ':player verkauft :team_a für :fee.',
    'offer_accepted_pre_contract' => 'Deal abgeschlossen! :player wechselt für :fee zu :team, wenn das :window-Fenster öffnet.',
    'offer_accepted_intra_window' => 'Deal abgeschlossen! :player geht :team_a für :fee nach dem nächsten Spiel.',

    // Free agent signing
    'free_agent_signed' => ':player hat als vereinslose Spielerin bei deinem Team unterschrieben!',
    'free_agent_agreed' => 'Deal abgeschlossen! :player stößt nach dem nächsten Spiel als vereinslose Spielerin dazu.',
    'not_free_agent' => 'Diese Spielerin ist nicht vereinslos.',
    'free_agent_reputation_too_low' => 'Diese Spielerin hat kein Interesse, für einen Club mit deinem Ruf zu spielen.',
    'transfer_window_closed' => 'Das Transferfenster ist geschlossen.',
    'wage_budget_exceeded' => 'Die Verpflichtung dieser Spielerin würde dein Gehaltsbudget überschreiten.',
    'signing_exceeds_salary_cap' => 'Die Verpflichtung von :player für :wage/Jahr würde deine Gehaltssumme auf :total erhöhen, über deiner Gehaltsobergrenze von :cap. Gib :shortfall frei, indem du zuerst Spielerinnen verkaufst.',
    'salary_cap_locked' => 'Du bist über deiner Gehaltsobergrenze. Verkaufe Spielerinnen, um wieder unter die Grenze zu kommen, bevor du verpflichtest oder verlängerst.',
    'pre_contract_exceeds_salary_cap' => 'Die Verpflichtung von :player für :wage/Jahr würde deine Gehaltssumme der nächsten Saison auf :total erhöhen, über deiner Gehaltsobergrenze von :cap. Dir fehlen :shortfall.',

    // Bid/loan submission confirmations
    'bid_already_exists' => 'Du hast bereits ein offenes Angebot für diese Spielerin.',
    'loan_request_submitted' => 'Deine Leihanfrage für :player wurde gesendet. Du erhältst bald eine Antwort.',

    // Loan messages
    'loan_agreed' => ':message Die Leihe beginnt, wenn das :window-Fenster öffnet.',
    'loan_in_complete' => ':message Die Leihe ist bereits aktiv.',
    'already_on_loan' => ':player ist bereits verliehen.',
    'loan_search_started' => 'Die Zielsuche für :player wurde gestartet. Du wirst benachrichtigt, wenn ein Club gefunden wird.',
    'loan_search_active' => ':player hat bereits eine aktive Leihsuche.',
    'loan_search_cancelled' => 'Die Leihsuche für :player wurde abgebrochen.',
    'loan_offer_accepted' => ':player verliehen :team_a.',
    'loan_offer_accepted_pre_window' => ':player wird :team_a verliehen, wenn das :window-Fenster öffnet.',
    'loan_offer_agreed_intra_window' => ':player wird :team_a nach dem nächsten Spiel verliehen.',

    // Contract messages
    'renewal_agreed' => ':player hat einer Verlängerung um :years Jahre für :wage/Jahr zugestimmt (gültig ab der nächsten Saison).',
    'renewal_failed' => 'Die Verlängerung konnte nicht verarbeitet werden.',
    'renewal_declined' => 'Du hast beschlossen, :player nicht zu verlängern. Sie geht am Saisonende.',
    'renewal_reconsidered' => 'Du hast die Verlängerung von :player überdacht.',
    'cannot_renew' => 'Diese Spielerin kann kein Verlängerungsangebot erhalten.',
    'renewal_invalid_offer' => 'Das Angebot muss größer als null sein.',

    // Pre-contract messages
    'pre_contract_accepted' => ':player hat dein Vorvertragsangebot angenommen! Sie stößt am Saisonende zu deinem Team.',
    'pre_contract_rejected' => ':player hat dein Vorvertragsangebot abgelehnt. Versuche, die Gehaltskonditionen zu verbessern.',
    'pre_contract_not_available' => 'Vorvertragsangebote sind nur zwischen Januar und Mai verfügbar.',
    'player_not_expiring' => 'Diese Spielerin ist nicht in ihrem letzten Vertragsjahr.',
    'pre_contract_submitted' => 'Vorvertragsangebot gesendet. Die Spielerin antwortet in den nächsten Tagen.',
    'pre_contract_result_accepted' => ':player hat dein Vorvertragsangebot angenommen!',
    'pre_contract_result_rejected' => ':player hat dein Vorvertragsangebot abgelehnt.',

    // Scout messages
    'scout_search_started' => 'Der Scout hat die Suche gestartet.',
    'scout_already_searching' => 'Du hast bereits eine aktive Suche. Brich sie zuerst ab oder warte auf die Ergebnisse.',
    'scout_search_cancelled' => 'Scout-Suche abgebrochen.',
    'scout_search_deleted' => 'Suche gelöscht.',
    'scout_search_limit' => 'Du hast das Suchlimit erreicht (maximal :max). Lösche eine alte Suche, um eine neue zu starten.',

    // Shortlist messages
    'shortlist_added' => ':player zu deiner Beobachtungsliste hinzugefügt.',
    'shortlist_removed' => ':player von deiner Beobachtungsliste entfernt.',
    'shortlist_full' => 'Deine Beobachtungsliste ist voll (maximal :max Spielerinnen).',

    // Budget messages
    'budget_saved' => 'Budgetzuweisung gespeichert.',
    'budget_no_projections' => 'Keine Finanzprognosen gefunden.',

    // Stadium / abonos
    'season_tickets_saved' => 'Dauerkartenpreise gespeichert.',
    'season_tickets_locked' => 'Die Dauerkartenpreise sind für diese Saison bereits gesperrt.',

    // Season messages
    'budget_exceeds_surplus' => 'Die Gesamtzuweisung übersteigt den verfügbaren Überschuss.',
    'budget_minimum_tier' => 'Alle Infrastruktur-Bereiche müssen mindestens Stufe 1 sein.',

    // Infrastructure upgrades
    'infrastructure_upgraded' => ':area auf Stufe :tier verbessert.',
    'infrastructure_upgrade_invalid_area' => 'Ungültiger Infrastruktur-Bereich.',
    'infrastructure_upgrade_not_higher' => 'Die Zielstufe muss höher als die aktuelle sein.',
    'infrastructure_upgrade_max_tier' => 'Die maximale Stufe ist 4.',
    'infrastructure_upgrade_insufficient_budget' => 'Unzureichendes Transferbudget. Die Verbesserung kostet :cost.',
    'investment_downgrade_not_lower' => 'Wähle eine Stufe unter der aktuellen.',
    'investment_saved' => 'Plan gespeichert.',
    'investment_locked_no_edit' => 'Die Saison läuft — du kannst jederzeit weiter verbessern, aber der Plan kann nicht mehr frei neu zugewiesen werden.',
    'investment_downgrade_staged' => 'Reduzierung geplant — wirkt in der nächsten Saison.',
    'investment_downgrade_cleared' => 'Geplante Reduzierung abgebrochen.',

    // Onboarding
    'welcome_to_team' => 'Willkommen :team_a! Deine Saison wartet auf dich.',

    // Season
    'season_not_complete' => 'Eine neue Saison kann nicht gestartet werden — die aktuelle Saison ist nicht beendet.',

    // Academy
    'academy_player_promoted' => ':player wurde in die erste Mannschaft befördert.',
    'academy_player_dismissed' => ':player wurde aus der Akademie entlassen.',
    'academy_player_loaned' => ':player wurde verliehen.',
    'academy_must_decide_21' => 'Spielerinnen ab 21 Jahren werden automatisch in die erste Mannschaft befördert.',

    // Reserve team (filial)
    'reserve_player_called_up' => ':player wurde in die erste Mannschaft berufen.',
    'reserve_player_sent_back' => ':player ist zur zweiten Mannschaft zurückgekehrt.',
    'reserve_player_call_up_blocked_full' => 'Der Kader der ersten Mannschaft ist voll. Gib eine Rückennummer frei, bevor du weitere Spielerinnen hochziehst.',
    'reserve_player_call_up_blocked' => 'Diese Spielerin kann nicht berufen werden.',
    'player_sent_down_to_reserve' => ':player wurde zur zweiten Mannschaft geschickt.',
    'send_down_not_allowed' => 'Diese Spielerin kann nicht zur zweiten Mannschaft geschickt werden.',
    'reserve_move_blocked_by_deal' => ':player hat einen vereinbarten Transfer oder Vorvertrag und kann das Team nicht wechseln, bis er abgeschlossen ist.',
    'send_down_squad_too_small' => 'Kann nicht zur zweiten Mannschaft geschickt werden — die erste Mannschaft muss mindestens :min Spielerinnen haben.',
    'send_down_position_minimum' => 'Kann nicht zur zweiten Mannschaft geschickt werden — die erste Mannschaft braucht mindestens :min :group.',
    'reserve_player_promoted' => ':player ist in die erste Mannschaft aufgestiegen.',

    // Player release messages
    'player_released' => ':player wurde freigestellt. Gezahlte Abfindung: :severance.',
    'release_not_your_player' => 'Du kannst nur Spielerinnen deines eigenen Teams freistellen.',
    'release_on_loan' => 'Eine verliehene Spielerin kann nicht freigestellt werden.',
    'release_has_agreed_transfer' => 'Eine Spielerin mit vereinbartem Transfer kann nicht freigestellt werden.',
    'release_has_pre_contract' => 'Eine Spielerin mit unterschriebenem Vorvertrag kann nicht freigestellt werden.',
    'release_squad_too_small' => 'Kann nicht freigestellt werden — dein Kader muss mindestens :min Spielerinnen haben.',
    'release_position_minimum' => 'Kann nicht freigestellt werden — du brauchst mindestens :min :group.',

    // Rescisión de mutuo acuerdo
    'mutual_termination_completed' => 'Einvernehmliche Vertragsauflösung mit :player abgeschlossen. Abfindung: :amount.',
    'severance_invalid_method' => 'Ungültige Zahlungsweise.',
    'severance_loan_active' => 'Du hast bereits ein aktives Darlehen. Du kannst kein weiteres aufnehmen.',
    'severance_loan_unavailable' => 'Das Darlehen kann derzeit nicht beantragt werden.',

    // Squad-minimum guards on promote / demote / list / accept
    'promote_squad_too_small' => 'Kann nicht hochgezogen werden — die zweite Mannschaft muss mindestens :min Spielerinnen haben.',
    'promote_position_minimum' => 'Kann nicht hochgezogen werden — die zweite Mannschaft braucht mindestens :min :group.',
    'demote_squad_too_small' => 'Kann nicht zur zweiten Mannschaft geschickt werden — die erste Mannschaft muss mindestens :min Spielerinnen haben.',
    'demote_position_minimum' => 'Kann nicht zur zweiten Mannschaft geschickt werden — die erste Mannschaft braucht mindestens :min :group.',
    'list_for_sale_squad_too_small' => 'Kann nicht zum Verkauf angeboten werden — dein Kader muss mindestens :min Spielerinnen haben.',
    'list_for_sale_position_minimum' => 'Kann nicht zum Verkauf angeboten werden — du brauchst mindestens :min :group.',
    'list_for_loan_squad_too_small' => 'Kann nicht verliehen werden — dein Kader muss mindestens :min Spielerinnen haben.',
    'list_for_loan_position_minimum' => 'Kann nicht verliehen werden — du brauchst mindestens :min :group.',
    'accept_offer_squad_too_small' => 'Das Angebot kann nicht angenommen werden — dein Kader muss mindestens :min Spielerinnen haben.',
    'accept_offer_position_minimum' => 'Das Angebot kann nicht angenommen werden — du brauchst mindestens :min :group.',
    'accept_loan_squad_too_small' => 'Die Leihe kann nicht angenommen werden — dein Kader muss mindestens :min Spielerinnen haben.',
    'accept_loan_position_minimum' => 'Die Leihe kann nicht angenommen werden — du brauchst mindestens :min :group.',

    'cannot_loan_free_agent' => 'Eine vereinslose Spielerin kann nicht verliehen werden. Verpflichte sie direkt.',

    // Pending actions
    'action_required' => 'Es gibt offene Aktionen, die du lösen musst, bevor du fortfährst.',
    'action_required_short' => 'Aktion erforderlich',

    // Tactical presets
    'preset_saved' => 'Taktik gespeichert.',
    'preset_updated' => 'Taktik aktualisiert.',
    'preset_deleted' => 'Taktik gelöscht.',
    'preset_limit_reached' => 'Maximal 3 gespeicherte Taktiken erreicht.',

    // Game management
    'game_deleted' => 'Das Spiel wird gelöscht.',
    'game_limit_reached' => 'Du hast das Maximum von 5 Spielen erreicht. Lösche eines, um ein neues zu erstellen.',
    'career_mode_requires_invite' => 'Club Manager und Pro Manager erfordern eine Einladung. Spiel die Weltmeisterschaft kostenlos!',
    'tournament_mode_requires_access' => 'Der Turniermodus erfordert Zugang. Kontaktiere einen Administrator, um zu starten.',
    'invalid_pro_manager_team' => 'Wähle einen der angezeigten Clubs — Pro Manager beginnt in der Primera Federación.',
    'invalid_academy_club' => 'Der gewählte Akademie-Club ist ungültig.',
    'club_has_no_filial' => 'Dieser Club hat keine verfügbare zweite Mannschaft.',
    'team_has_no_competition_link' => 'Dieses Team ist mit keinem Wettbewerb verknüpft: Der Spielstand kann nicht erstellt werden.',
    'team_squad_too_small' => 'Dieses Team hat nur :count Spielerinnen im Kader (mindestens :minimum): Der Spielstand kann nicht erstellt werden.',
    'cannot_apply_to_own_club' => 'Du kannst dich nicht bei deinem eigenen Club bewerben.',

    // Pre-match confirmation
    'pre_match_title' => 'Spielvorschau',
    'pre_match_no_lineup' => 'Du hast keine Aufstellung festgelegt.',
    'pre_match_incomplete' => 'Deine Aufstellung hat weniger als 11 Spielerinnen.',
    'pre_match_unavailable_injured' => 'Du hast eine verletzte Spielerin in deiner Aufstellung.',
    'pre_match_unavailable_suspended' => 'Du hast eine gesperrte Spielerin in deiner Aufstellung.',
    'pre_match_unavailable_multiple' => 'Du hast nicht verfügbare Spielerinnen in deiner Aufstellung.',
    'pre_match_auto_explanation' => 'Wenn du nichts änderst, wählt dein Trainerstab die beste Aufstellung aus den verfügbaren Spielerinnen.',
    'pre_match_warning_title' => 'Deine Aufstellung braucht Aufmerksamkeit',
    'pre_match_play' => 'Spiel spielen',
    'pre_match_continue' => 'Weiter',
    'pre_match_edit_lineup' => 'Aufstellung bearbeiten',
    'pre_match_reason_injured' => 'verletzt',
    'pre_match_reason_suspended' => 'gesperrt',
    'pre_match_starting_xi' => 'Startelf',
    'pre_match_no_lineup_set' => 'Aufstellung nicht festgelegt',
    'pre_match_auto_lineup' => 'Den Trainerstab die Aufstellung automatisch anpassen lassen, wenn Spielerinnen nicht verfügbar sind.',
    'pre_match_auto_select_done' => 'Die beste Aufstellung aus den verfügbaren Spielerinnen wurde automatisch gewählt.',

    // Matchday advance
    'advance_failed' => 'Fehler beim Vorrücken des Spieltags. Versuche es erneut.',

    // Fast mode
    'fast_mode_enabled' => 'Schnellmodus aktiviert. Dein Co-Trainer übernimmt das Team.',
    'fast_mode_disabled' => 'Schnellmodus deaktiviert. Du hast wieder die Kontrolle.',
    'fast_mode_action_required' => 'Eine Aktion erfordert deine Aufmerksamkeit. Verlasse den Schnellmodus, um sie zu lösen.',
    'fast_mode_blocked_live_match' => 'Beende das laufende Spiel, bevor du den Schnellmodus aktivierst.',
    'fast_mode_blocked_tournament' => 'Der Schnellmodus ist im Turniermodus nicht verfügbar.',
    'fast_mode_advance_failed_retry' => 'Der Spieltag konnte nicht simuliert werden. Versuche es erneut.',

    // Budget loan messages
    'budget_loan_approved' => 'Darlehen von :amount genehmigt und zu deinem Transferbudget hinzugefügt.',
    'loan_not_available' => 'Ein Budgetdarlehen ist derzeit nicht verfügbar.',
    'loan_below_minimum' => 'Der Darlehensbetrag liegt unter dem Minimum.',
    'loan_exceeds_maximum' => 'Der Darlehensbetrag übersteigt das erlaubte Maximum.',

    'stadium_supplementary_committed' => 'Bau gestartet: :seats Zusatztribünenplätze sind in 30 Tagen fertig.',
    'stadium_stand_expansion_committed' => 'Tribünenerweiterung genehmigt: :seats neue feste Plätze sind in der nächsten Saison fertig.',
    'stadium_rebuild_committed' => 'Stadionumbau genehmigt. Neue Zielkapazität: :capacity.',
    'stadium_active_project_exists' => 'Du hast bereits ein laufendes Projekt. Warte, bis es fertig ist, bevor du ein neues startest.',
    'stadium_supplementary_too_few_seats' => 'Du musst mindestens einen Zusatztribünenplatz hinzufügen.',
    'stadium_supplementary_exceeds_cap' => 'Überschreitet die erlaubte Obergrenze für Zusatztribünen.',
    'stadium_stand_expansion_too_few_seats' => 'Die Tribünenerweiterung erreicht nicht das geforderte Mindestmaß an Plätzen.',
    'stadium_stand_expansion_exceeds_cap' => 'Die Tribünenerweiterung überschreitet die maximal erlaubte Größe.',
    'stadium_rebuild_reputation_too_low' => 'Dein Ruf erlaubt noch keinen kompletten Stadionumbau.',
    'stadium_rebuild_must_be_larger' => 'Die Zielkapazität muss größer als die aktuelle sein.',
    'stadium_rebuild_exceeds_max_capacity' => 'Die Zielkapazität überschreitet die Obergrenze, die dein Ruf und deine Einnahmen finanzieren können.',
    'stadium_invalid_financing' => 'Ungültige Finanzierung.',
    'stadium_insufficient_budget' => 'Du hast nicht genug Budget, um das Projekt bar zu zahlen.',
    'stadium_loan_exceeds_cap' => 'Das beantragte Darlehen überschreitet die von der Bank genehmigte Obergrenze.',
    'stadium_uefa_upgrade_committed' => 'UEFA-Upgrade gestartet: Das Stadion erreicht in der nächsten Saison die Kategorie :level.',
    'stadium_uefa_already_max' => 'Dein Stadion ist bereits in der höchsten UEFA-Kategorie.',
    'stadium_uefa_capacity_floor' => 'Die aktuelle Kapazität erreicht nicht das Mindestmaß, das die nächste UEFA-Kategorie fordert.',
    'stadium_uefa_no_base_level' => 'Deinem Stadion ist keine UEFA-Kategorie zugewiesen. Erweitere zuerst die Kapazität.',

    'naming_rights_accepted' => 'Namensrechte-Vertrag mit :sponsor unterschrieben. Das Stadion wurde umbenannt.',
    'stadium_renamed' => 'Stadion in :name umbenannt.',
    'naming_rights_window_closed' => 'Die Stadionidentität kann nur in der Vorbereitung geändert werden, bis zum ersten Ligaspiel.',
    'naming_rights_deal_active' => 'Es gibt bereits einen aktiven Namensrechte-Vertrag: Der Sponsor besitzt den Stadionnamen bis zum Ablauf.',
    'naming_rights_offer_unavailable' => 'Dieses Namensrechte-Angebot ist nicht mehr verfügbar.',
    'stadium_already_renamed' => 'Das Stadion wurde in dieser Saison bereits umbenannt.',
    'naming_rights_search_complete' => '{0}Die Agentur hat keine neuen Sponsoren gefunden.|{1}Die Agentur hat :count Sponsoringangebot gebracht.|[2,*]Die Agentur hat :count Sponsoringangebote gebracht.',
    'naming_rights_search_cooldown' => 'Deine Handelsagentur sondiert noch den Markt. Warte ein paar Tage, bevor du erneut suchst.',
    'naming_rights_search_unaffordable' => 'Du hast kein Budget für die Provision der Handelsagentur.',
    'naming_rights_board_full' => 'Du hast bereits die maximale Anzahl an Angeboten auf dem Tisch. Nimm eines an oder lehne sie ab, bevor du weiter suchst.',

    // i18n-b review: poach youth player + generic error flashes
    'poach_not_enough_budget' => 'Nicht genug Budget (:fee€ nötig).',
    'poach_player_gone' => ':name ist nicht mehr verfügbar.',
    'poach_refused' => ':team weigert sich, über :name zu verhandeln. Der Versuch hat :cost€ an Scouting gekostet.',
    'poach_success' => ':name schließt sich deiner Akademie an!',
    'season_summary_load_error' => 'Die Saisonübersicht konnte nicht geladen werden. Bitte versuche es erneut.',
    'new_season_start_error' => 'Die neue Saison konnte nicht gestartet werden. Bitte versuche es erneut.',
    'lineup_confirmed' => 'Aufstellung bestätigt! Klicke auf Weiter, um das Spiel zu starten.',

    // i18n-b review: poach youth player social buzz
    'poach_buzz' => '🚨 :team \'stiehlt\' das Talent :player (:potential Pot.) aus einer rivalisierenden Akademie.',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'naming_rights_offer_rejected' => 'Angebot von :sponsor verworfen. Die werden es nie erfahren.',
    'sponsor_deal_accepted' => 'Deal! :sponsor sponsert :slot. Zeit zum Kassieren.',
    'sponsor_deal_rejected' => 'Angebot von :sponsor verworfen. Weiter geht\'s.',
    'sponsor_offer_unavailable' => 'Dieses Sponsoringangebot ist nicht mehr verfügbar.',
    'sponsor_deal_active' => 'Du hast bereits einen aktiven Sponsor auf diesem Platz. Warte, bis der Vertrag ausläuft.',
];
