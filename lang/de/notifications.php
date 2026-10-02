<?php

return [
    // Inbox
    'inbox' => 'Benachrichtigungen',
    'new' => 'neu',
    'all_caught_up' => 'Du bist auf dem Laufenden',

    // Department inbox tabs
    'dept_all' => 'Alle',
    'dept_sporting' => 'Trainerstab',
    'dept_transfers' => 'Sportdirektion',
    'dept_scouting' => 'Scouts',
    'dept_academy' => 'Akademie',
    'dept_board' => 'Vorstand',
    'dept_competition' => 'Wettbewerb',

    // Critical-alert popup (blocking, must-dismiss)
    'alert_heading' => 'Wichtiger Hinweis',
    'alerts_heading' => ':count wichtige Hinweise',
    'celebration_heading' => 'Herzlichen Glückwunsch!',
    'alert_dismiss' => 'Verwerfen',
    'dismiss_all' => 'Alle verwerfen',
    'alert_continue' => 'Weiter',
    'action_review_offer' => 'Angebot prüfen',
    'action_view_competition' => 'Wettbewerb ansehen',
    'action_view_details' => 'Details ansehen',

    // Injury types
    'injury_muscle_fatigue' => 'Muskelermüdung',
    'injury_muscle_strain' => 'Muskelzerrung',
    'injury_calf_strain' => 'Wadenzerrung',
    'injury_ankle_sprain' => 'Sprunggelenksverstauchung',
    'injury_groin_strain' => 'Leistenzerrung',
    'injury_hamstring_tear' => 'Oberschenkelriss',
    'injury_knee_contusion' => 'Knieprellung',
    'injury_metatarsal_fracture' => 'Mittelfußbruch',
    'injury_acl_tear' => 'Kreuzbandriss',
    'injury_achilles_rupture' => 'Achillessehnenriss',

    // Player injuries
    'player_injured_title' => ':player verletzt',
    'player_injured_message' => ':player hat sich :injury :location zugezogen.',
    'player_injured_message_with_date' => ':player hat sich :injury :location zugezogen. Ausfall bis :date.',
    'injury_location_match' => 'im Spiel',
    'injury_location_training' => 'im Training',

    // Player suspensions
    'player_suspended_title' => ':player gesperrt',
    'player_suspended_message' => ':player wurde wegen :reason für :matches Spiel gesperrt. Sie verpasst das nächste Spiel in der :competition.|:player wurde wegen :reason für :matches Spiele gesperrt. Sie verpasst das nächste Spiel in der :competition.',
    'reason_red_card' => 'einer Roten Karte',
    'reason_yellow_accumulation' => 'einer Gelbsperre',

    // Player recovery
    'player_recovered_title' => ':player genesen',
    'player_recovered_message' => ':player ist genesen und wieder einsatzbereit.',

    // Transfer offers
    'transfer_offer_title' => 'Kaufangebot für :player',
    'transfer_offer_message' => ':team_el hat :fee für die Spielerin geboten.',
    'free_transfer' => 'Ablösefreier Transfer',

    // Transfer complete
    'transfer_complete_incoming_title' => ':player verpflichtet',
    'transfer_complete_incoming_message' => ':player ist :team_de für :fee zu deinem Kader gestoßen.',
    'transfer_complete_outgoing_title' => ':player verkauft',
    'transfer_complete_outgoing_message' => ':player wurde :team_a für :fee verkauft.',
    'transfer_failed_title' => 'Transfer geplatzt: :player',
    'transfer_failed_message' => 'Der vereinbarte Transfer von :player konnte nicht abgeschlossen werden und das reservierte Budget wurde freigegeben.',
    'pre_contract_failed_title' => 'Vorvertrag geplatzt: :player',
    'pre_contract_failed_message' => 'Der mit :player vereinbarte Vorvertrag konnte nicht abgeschlossen werden: Sie war am Saisonende nicht mehr bei :team. Sie stößt nicht zu deinem Kader.',
    'loan_out_complete_title' => ':player verliehen',
    'loan_out_complete_message' => ':player wurde :team_a bis Saisonende verliehen.',

    // Release clause triggered against the user (Phase 3)
    'player_left_via_release_clause_title' => 'Ausstiegsklausel: :player geht',
    'player_left_via_release_clause_message' => ':player geht :team_a, nachdem ihre Ausstiegsklausel aktiviert wurde. Dein Club erhält :fee.',

    // Expiring offers
    'offer_expiring_title' => 'Angebot für :player läuft bald ab',
    'offer_expiring_message' => '{0}Das Angebot :team_de für :player läuft heute ab.|{1}Das Angebot :team_de für :player läuft in :count Tag ab.|[2,*]Das Angebot :team_de für :player läuft in :count Tagen ab.',

    // Scout
    'scout_complete_title' => 'Scout-Bericht bereit',
    'scout_complete_message' => 'Dein Scout hat :count Spielerinnen gefunden, die zu deiner Suche passen.',

    // Contracts
    'contract_expiring_title' => 'Vertrag von :player läuft bald aus',
    'contract_expiring_message' => 'Der Vertrag von :player läuft in :months Monaten aus.',

    // Loan returns
    'loan_return_title' => ':player kehrt von der Leihe zurück',
    'loan_return_message' => ':player ist von ihrer Leihe :team_en zurückgekehrt.',

    // Low energy
    'low_fitness_title' => ':player mit geringer Energie',
    'low_fitness_message' => ':player hat nur :fitness % Energie und braucht Ruhe.',

    // Loan search
    'loan_offer_received_title' => 'Leihangebot für :player',
    'loan_offer_received_message' => ':team_el hat angeboten, die Spielerin auszuleihen.',
    'loan_search_failed_title' => 'Leihsuche gescheitert',
    'loan_search_failed_message' => 'Es wurde kein Club gefunden, der :player ausleihen möchte. Die Spielerin ist wieder verfügbar.',

    // Competition advancement
    'competition_advancement_title' => 'Weiterkommen in der :competition',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => 'Ausscheiden aus der :competition',
    'competition_elimination_message' => ':stage',
    'trophy_won_title' => 'Meisterin der :competition!',

    // Academy
    'academy_batch_title' => 'Neue Akademiespielerinnen',
    'academy_batch_message' => ':count neue Spielerinnen sind in der Akademie angekommen.',
    'academy_overage_promoted_title' => 'Akademie-Absolventinnen',
    'academy_overage_promoted_message' => ':count Akademiespielerinnen ab 21 Jahren wurden in die erste Mannschaft befördert.',
    'academy_gap_promoted_title' => 'Akademiespielerinnen befördert',
    'academy_gap_promoted_message' => ':count Akademiespielerinnen wurden befördert, um Lücken im Kader zu schließen.',
    'reserve_overage_promoted_title' => 'Absolventin der zweiten Mannschaft',
    'reserve_overage_promoted_message' => ':player ist dem Alter der zweiten Mannschaft entwachsen und stößt dauerhaft zur ersten Mannschaft.',
    'reserve_stand_in_added_title' => 'Verstärkung der C-Mannschaft',
    'reserve_stand_in_added_message' => 'Die zweite Mannschaft hat :count Spielerinnen der C-Mannschaft aufgenommen, um den Kader zu vervollständigen.',
    // Loan request results
    'loan_accepted_title' => 'Leihe von :player angenommen',
    'loan_accepted' => ':team hat deine Leihanfrage für :player angenommen.',
    'loan_rejected_title' => 'Leihe von :player abgelehnt',
    'loan_rejected' => ':team hat deine Leihanfrage für :player abgelehnt.',

    // Tournament welcome
    'tournament_welcome_title' => 'Willkommen bei der Weltmeisterschaft!',
    'tournament_welcome_message' => 'Das ganze Land schaut auf dich. Kein Druck... aber enttäusche sie nicht!',

    // Priority badges
    'priority_urgent' => 'Dringend',
    'priority_attention' => 'Achtung',

    // Ofertas de empleo del manager (modo Pro Manager)
    'job_offer_received_title' => ':count Clubs an dir interessiert',
    'job_offer_post_firing_title' => 'Wähle deinen nächsten Club (:count Angebote)',
    'job_offer_received_message' => 'Prüfe den Saisonabschluss-Bildschirm, um anzunehmen oder abzulehnen.',

    // Transfer window open
    'transfer_window_open_title' => ':window-Transferfenster geöffnet',
    'transfer_window_open_message' => 'Das Transferfenster ist geöffnet. Vereinbarte Transfers stoßen sofort zu deinem Kader.',

    // Transfer window closing
    'transfer_window_closing_title' => 'Schließung des :window-Fensters',
    'transfer_window_closing_message' => 'Das ist deine letzte Chance, zu verpflichten. Das Transferfenster schließt nach diesem Spieltag.',
    'transfer_window_closing_title_winter' => '⏰ Deadline Day im Wintertransferfenster!',
    'transfer_window_closing_message_winter' => 'Letzte Stunden des Januar-Fensters: Es schließt nach diesem Spieltag. Niemand darf einschlafen!',

    // Transfer window closed (also the AI market summary — the window-close notice
    // and the league transfer count are a single notification)
    'ai_transfer_title' => ':window-Transferfenster geschlossen',
    'ai_transfer_message' => 'Das Transferfenster ist geschlossen. :count abgeschlossene Transfers in der Liga. Vereinbarte Transfers werden beim nächsten Fenster abgeschlossen.',
    'ai_transfer_message_none' => 'Das Transferfenster ist geschlossen. Vereinbarte Transfers werden beim nächsten Fenster abgeschlossen.',
    'ai_transfer_window_summer' => 'Sommer',
    'ai_transfer_window_winter' => 'Winter',

    // Player released
    'player_released_title' => ':player freigestellt',
    'player_released_message' => ':player wurde aus deinem Kader freigestellt. Gezahlte Abfindung: :severance.',
    'player_released_message_free' => ':player wurde aus deinem Kader freigestellt.',

    // Rescisión de mutuo acuerdo
    'mutual_termination_title' => 'Einvernehmliche Vertragsauflösung: :player',
    'mutual_termination_message' => 'Du hast den Vertrag von :player einvernehmlich aufgelöst. Vereinbarte Abfindung: :amount.',

    // Plan de pago de indemnización a plazos
    'severance_plan_title' => 'Abfindung in Raten: :player',
    'severance_plan_message' => 'Du zahlst die Abfindung von :player in :months Raten à :monthly (gesamt :total mit Zinsen).',
    'severance_plan_completed_title' => 'Abfindung beglichen: :player',
    'severance_plan_completed_message' => 'Du hast die Abfindung von :player vollständig gezahlt (gesamt :total).',

    // Fichajes de emergencia
    'emergency_signing_title' => 'Notverstärkung',
    'emergency_signing_message' => 'Dein Kader war auf kritischem Niveau. Es wurden :count vereinslose Spielerinnen verpflichtet, damit du ein Team aufstellen kannst: :players.',

    // Partido perdido por incomparecencia
    'match_forfeit_title' => 'Spiel durch Nichtantritt verloren',
    'match_forfeit_message' => 'Dein Team konnte nicht die mindestens 7 Spielerinnen aufstellen. Das Spiel wurde als 0:3-Niederlage gewertet.',

    // Reputation changes
    'reputation_change_title' => 'Club-Ruf geändert',
    'reputation_improved' => 'Der Ruf deines Clubs ist auf :tier gestiegen. Sponsoren, Spielerinnen und Fans bemerken es.',
    'reputation_declined' => 'Der Ruf deines Clubs ist auf :tier gesunken. Es ist Zeit, wieder aufzubauen und an alte Glanzzeiten anzuknüpfen.',

    // Budget loan
    'budget_loan_taken_title' => 'Budgetdarlehen gewährt',
    'budget_loan_taken_message' => 'Der Club hat ein Darlehen von :amount erhalten. Die Rückzahlung von :repayment wird vom Budget der nächsten Saison abgezogen.',
    'budget_loan_repaid_title' => 'Budgetdarlehen zurückgezahlt',
    'budget_loan_repaid_message' => 'Das Budgetdarlehen wurde zurückgezahlt (:repayment mit Zinsen).',
    'budget_loan_repaid_with_debt' => 'Die Rückzahlung des Darlehens von :repayment überstieg den verfügbaren Überschuss. Das Defizit wird als Schulden übertragen.',

    // Stadium
    'stadium_supplementary_committed_title' => 'Bau der Zusatztribünen gestartet',
    'stadium_supplementary_committed_message' => 'Es wurden :capacity Zusatztribünenplätze in Auftrag gegeben. Sie sind am :completion fertig.',
    'stadium_stand_expansion_committed_title' => 'Tribünenerweiterung genehmigt',
    'stadium_stand_expansion_committed_message' => 'Eine Erweiterung um :capacity neue feste Plätze wurde genehmigt. Sie ist am :completion einsatzbereit.',
    'stadium_rebuild_committed_title' => 'Stadionumbau genehmigt',
    'stadium_rebuild_committed_message' => 'Der Umbau für eine Kapazität von :capacity Plätzen hat begonnen. Das neue Stadion eröffnet am :completion.',
    'stadium_supplementary_completed_title' => 'Zusatztribünen fertig',
    'stadium_supplementary_completed_message' => 'Die neuen Tribünen sind einsatzbereit. Gesamtkapazität: :capacity Plätze.',
    'stadium_stand_expansion_completed_title' => 'Neue Tribüne eingeweiht',
    'stadium_stand_expansion_completed_message' => 'Die erweiterte Tribüne ist einsatzbereit. Gesamtkapazität: :capacity Plätze.',
    'stadium_rebuild_completed_title' => 'Stadion eingeweiht',
    'stadium_rebuild_completed_message' => 'Das neue Stadion wurde mit einer Kapazität von :capacity Plätzen eingeweiht.',
    'stadium_uefa_upgrade_committed_title' => 'UEFA-Upgrade genehmigt',
    'stadium_uefa_upgrade_committed_message' => 'Der Umbau für die UEFA-Kategorie :capacity hat begonnen. Die neue Kategorie gilt ab :completion.',
    'stadium_uefa_upgrade_completed_title' => 'Neue UEFA-Kategorie',
    'stadium_uefa_upgrade_completed_message' => 'Dein Stadion hat die UEFA-Kategorie :capacity erreicht.',
    'stadium_loan_drawn_title' => 'Stadiondarlehen formalisiert',
    'stadium_loan_drawn_message' => 'Die Bank hat das Projekt mit :amount finanziert, zurückzuzahlen in :years Jahresraten.',
    'stadium_loan_repaid_title' => 'Stadiondarlehen zurückgezahlt',
    'stadium_loan_repaid_message' => 'Das Darlehen von :amount wurde vollständig zurückgezahlt.',
    // Solicitudes de sede (selección ↔ club, modo dual)
    'stadium_request_title' => '🏟️ :team möchte in deinem Stadion spielen',
    'stadium_request_message' => ':team bittet dich, dein Stadion für das Freundschaftsspiel gegen :opponent am :date zur Verfügung zu stellen. Du kannst die Anfrage auf der Stadionseite annehmen oder ablehnen.',
    'stadium_request_accepted_title' => 'Spielort bestätigt: :stadium',
    'stadium_request_accepted_message' => 'Der Club hat zugestimmt, :stadium zur Verfügung zu stellen. Das Freundschaftsspiel findet dort statt.',
    'stadium_request_rejected_title' => 'Spielort abgelehnt: :stadium',
    'stadium_request_rejected_message' => 'Der Club hat abgelehnt, :stadium zur Verfügung zu stellen. Grund: :excuse Das Freundschaftsspiel findet auf neutralem Boden statt.',
    'commercial_window_open_title' => 'Kommerzielles Fenster geöffnet',
    'commercial_window_open_message' => 'Bis zum ersten Ligaspiel kannst du auf der Kommerziell-Seite nach Sponsoren suchen, um deine Einnahmen und deine Gehaltsobergrenze zu erhöhen.',

    // Squad registration
    'squad_registration_required_title' => 'Kaderregistrierung erforderlich',
    'squad_registration_required_message' => 'Du hast :count nicht registrierte Spielerinnen. Registriere deinen Kader, bevor die Saison beginnt — nicht registrierte Spielerinnen können nicht nominiert werden.',
    'unenrolled_before_window_close_title' => 'Nicht registrierte Spielerinnen — :window-Fenster schließt',
    'unenrolled_before_window_close_message' => 'Du hast :count nicht registrierte Spielerinnen. Dies ist dein letzter Spieltag, um sie vor Schließung des Transferfensters zu registrieren — ohne Rückennummer können sie nicht nominiert werden.',
];
