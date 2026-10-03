<?php

return [

    // Page title
    'finances' => 'Finanzen',

    // Overview cards
    'squad_value' => 'Kaderwert',
    'annual_wage_bill' => 'Jährliche Gehaltssumme',
    'transfer_budget' => 'Transferbudget',
    'total_budget' => 'Gesamtbudget',

    // Projected revenue
    'projected_revenue' => 'Prognostizierte Einnahmen',
    'tv_rights' => 'TV-Rechte',
    'matchday' => 'Spieltag',
    'commercial' => 'Kommerziell',
    'naming_rights' => 'Namensrechte',
    'solidarity_funds' => 'Solidaritätszahlungen (RFEF/UEFA)',
    'public_subsidy' => 'Öffentliche Zuschüsse',
    'total_revenue' => 'Gesamteinnahmen',

    // Surplus calculation
    'projected_wages' => 'Prognostizierte Gehälter',
    'projected_surplus' => 'Prognostizierter Überschuss',
    'operating_expenses' => 'Betriebsausgaben',
    'taxes' => 'Steuern und Sozialabgaben',
    'carried_debt' => 'Übertragene Schulden',
    'carried_surplus' => 'Übertragener Überschuss',
    'available_surplus' => 'Verfügbarer Überschuss',

    // Season results
    'actual_revenue' => 'Tatsächliche Einnahmen',
    'actual_surplus' => 'Tatsächlicher Überschuss',
    'variance' => 'Abweichung',

    // No data
    'no_financial_data' => 'Keine Finanzdaten für diese Saison verfügbar.',

    // Infrastructure investment
    'infrastructure_investment' => 'Infrastruktur-Investitionen',
    'total_infrastructure' => 'Infrastruktur gesamt',
    'available_for_upgrades' => 'Verfügbar für Verbesserungen',
    'investment_state_preseason' => 'Vorbereitung — freie Anpassung',
    'investment_preseason_hint' => 'Stelle deinen Plan frei ein, bis die Saison beginnt. Auch danach kannst du jederzeit mehr investieren, aber Reduzierungen wirken erst in der nächsten Saison.',
    'investment_state_locked' => 'Saison läuft',
    'investment_locked_hint' => 'Du kannst jederzeit in jeden Bereich mehr investieren (Vorauszahlung). Eine Reduzierung wirkt zum Beginn der nächsten Saison — es gibt keine Rückerstattung mitten in der Saison.',
    'save_plan' => 'Plan speichern',
    'reduce' => 'Reduzieren',
    'reduce_hint' => 'Wirkt in der nächsten Saison — keine Rückerstattung in dieser Saison.',
    'reduce_stage' => 'Nächste Saison',
    'staged_next_season' => 'Nächste Saison: Stufe :tier',
    'staged_cancel' => 'Abbrechen',
    'adjust_allocation' => 'Zuweisung anpassen',

    // Tiers
    'youth_academy' => 'Nachwuchsakademie',

    'medical' => 'Medizinische Abteilung',

    'scouting' => 'Scouting',

    // Budget flow tooltips
    'tooltip_tv_rights' => 'TV-Verteilung basierend auf deiner endgültigen Ligaplatzierung. Je höher du landest, desto größer dein Anteil.',
    'tooltip_commercial' => 'Einnahmen aus Sponsoring und Merchandising. Hängen von der Stadiongröße und dem Ruf des Clubs ab.',
    'tooltip_naming_rights' => 'Einnahmen aus dem Verkauf des Stadionnamens an einen Sponsor. Eine feste Jahresgebühr, solange der Vertrag läuft.',
    'tooltip_matchday' => 'Einnahmen aus dem Ticketverkauf. Steigen mit Investitionen in die Anlagen und einer guten Ligaplatzierung.',
    'tooltip_solidarity_funds' => 'Solidaritätszahlungen der RFEF/UEFA an Clubs der unteren Ligen zur Förderung der Wettbewerbsfähigkeit.',
    'tooltip_public_subsidy' => 'Öffentlicher Zuschuss, der ein tragfähiges Mindestbudget für Infrastruktur und Transfers garantiert.',
    'tooltip_wages' => 'Summe der Jahresgehälter des gesamten Kaders. Transfers mitten in der Saison werden anteilig berechnet.',
    'tooltip_operating_expenses' => 'Fixkosten des Clubs: nicht-sportliches Personal, Verwaltung, Reisen, Versicherungen und Rechtskosten.',
    'tooltip_taxes' => 'Steuern und Sozialabgaben auf die Einnahmen des Clubs.',
    'tooltip_surplus' => 'Differenz zwischen Einnahmen und Ausgaben. Dieser Betrag wird auf Infrastruktur und Transfers aufgeteilt.',
    'tooltip_carried_debt' => 'Defizit der Vorsaison. Wenn die tatsächlichen Einnahmen unter der Prognose lagen, wird die Differenz übertragen.',
    'tooltip_carried_surplus' => 'Überschuss der Vorsaison. Wenn die tatsächlichen Einnahmen die Prognosen übertroffen haben, wird die Differenz übertragen.',
    'tooltip_infrastructure' => 'Investitionen in Akademie, Sportmedizin, Scouts und Anlagen. Wird abgezogen, bevor das Transferbudget berechnet wird.',
    'tooltip_transfer_budget' => 'Was vom Überschuss nach Schulden und Infrastruktur übrig bleibt. Deine Fähigkeit, Spielerinnen zu verpflichten.',

    // Budget flow
    'budget_flow' => 'Budgetfluss',
    'season_allocation' => 'Saisonzuweisung',
    'transfer_activity' => 'Transferbewegungen der Saison',
    'player_sales' => 'Spielerinnenverkäufe',
    'player_purchases' => 'Spielerinnenkäufe',
    'infrastructure_upgrades' => 'Infrastruktur-Verbesserungen',
    'current_transfer_budget' => 'Aktuelles Transferbudget',
    'budget_not_set' => 'Saisonbudget nicht eingerichtet',
    'surplus_to_allocate' => 'verfügbarer Überschuss zur Zuweisung',

    // Quick stats
    'wage_revenue_ratio' => 'Verhältnis Gehälter/Einnahmen',
    'salary_cap' => 'Gehaltsobergrenze',
    'wage_room' => 'Gehaltsspielraum',
    'over_cap' => 'Grenze überschritten',
    'over_cap_lock_notice' => 'Transfermarkt gesperrt — verkaufe Spielerinnen, um wieder unter deine Grenze zu kommen.',
    'squad_size' => ':count Spielerinnen',
    'initial_budget_caption' => 'von :amount Startbudget',
    'tooltip_salary_cap' => 'Das Maximum, das dein Club für Gehälter ausgeben darf: :percent % deiner prognostizierten wiederkehrenden Einnahmen. Einmalige Gelder (Transferüberschuss) erhöhen sie nicht, aber nachhaltig mehr zu verkaufen als zu kaufen schon (Kapitalgewinne). Steigere deine Einnahmen, um die Grenze anzuheben.',
    'salary_cap_includes_trading' => 'Inkl. +:amount Spielraum aus Kapitalgewinnen deiner jüngsten Verkäufe.',
    'income' => 'Einnahmen',
    'expenses' => 'Ausgaben',

    // Transaction filters
    'filter_all' => 'Alle',
    'filter_income' => 'Einnahmen',
    'filter_expenses' => 'Ausgaben',

    // Budget setup
    'setup_season_budget' => 'Saisonbudget einrichten',

    // Transaction history
    'transaction_history' => 'Transaktionsverlauf',
    'date' => 'Datum',
    'type' => 'Typ',
    'description' => 'Beschreibung',
    'amount' => 'Betrag',
    'no_transactions' => 'Noch keine Transaktionen erfasst.',
    'transactions_hint' => 'Transfers, Gehälter und andere Finanzaktivitäten erscheinen hier.',
    'free' => 'Kostenlos',

    // Budget allocation page
    'budget_allocation' => 'Budgetzuweisung',
    'season_budget' => 'Saisonbudget :season',
    'tier' => 'Stufe :level',
    'tier_n' => 'Stufe',
    'confirm_budget_allocation' => 'Budgetzuweisung bestätigen',
    'after_debt_deduction' => 'Nach :amount Schuldenabzug',
    'includes_carried_surplus' => 'Inkl. :amount Überschuss aus der Vorsaison',

    // Budget allocation component
    'infrastructure' => 'Infrastruktur:',
    'transfers' => 'Transfers:',
    'budget_locked' => 'Budget gesperrt',
    'budget_locked_desc' => 'Die Budgetzuweisung ist für die Saison fixiert. Änderungen sind in der nächsten Vorbereitung möglich.',
    'remainder_after_infrastructure' => 'Rest nach Infrastruktur',
    'available_remaining' => 'Verfügbar:',
    'budget_exceeds_surplus' => 'Die Infrastruktur-Investition übersteigt den verfügbaren Überschuss. Senke die Stufe eines Bereichs, um fortzufahren.',
    'tier_minimum_warning' => 'Alle Infrastruktur-Bereiche müssen die Mindeststufe deiner Division erreichen.',

    // Youth academy tier descriptions
    'youth_academy_tier_0' => 'Minimalstruktur — Talente mit geringem Potenzial',
    'youth_academy_tier_1' => 'Basis-Akademie — gelegentliche Talente',
    'youth_academy_tier_2' => 'Gute Akademie — regelmäßige Nachwuchstalente',
    'youth_academy_tier_3' => 'Elite-Akademie — Nachwuchs mit hohem Potenzial',
    'youth_academy_tier_4' => 'Weltklasse — eigene Stars',

    // Medical tier descriptions
    'medical_tier_0' => 'Minimalbesetzung — Basis-Regeneration',
    'medical_tier_1' => 'Basisversorgung — Standard-Regeneration',
    'medical_tier_2' => 'Gute Ausstattung — 15 % schneller',
    'medical_tier_3' => 'Elite-Personal — 30 % schneller, weniger Verletzungen',
    'medical_tier_4' => 'Weltklasse — 50 % schneller, Prävention',

    // Scouting tier descriptions
    'scouting_tier_0' => 'Minimale Scouts — begrenzte Reichweite',
    'scouting_tier_1' => 'Basisnetzwerk — nur nationaler Markt',
    'scouting_tier_2' => 'Erweitertes Netzwerk — national, mehr Ergebnisse und Präzision',
    'scouting_tier_3' => 'Internationale Reichweite — schnelle und präzise Suchen',
    'scouting_tier_4' => 'Globales Netzwerk — maximale Geschwindigkeit, Ergebnisse und Präzision',

    // Facilities tier descriptions
    'facilities_tier_0' => 'Mindestwartung — Basis-Spieltagseinnahmen',
    'facilities_tier_1' => 'Basisverbesserungen — 1,0-fache Einnahmen',
    'facilities_tier_2' => 'Moderne Anlagen — 1,15-fache Einnahmen',
    'facilities_tier_3' => 'Premium-Erlebnis — 1,35-fache Einnahmen',
    'facilities_tier_4' => 'Weltklasse-Stadion — 1,6-fache Einnahmen',

    // Reputation tiers
    'reputation' => [
        'elite' => 'Elite',
        'continental' => 'Kontinental',
        'established' => 'Etabliert',
        'modest' => 'Bescheiden',
        'local' => 'Lokal',
    ],

    // Categories
    'category_transfer_in' => 'Verkauf',
    'category_transfer_out' => 'Einkauf',
    'category_wage' => 'Gehälter',
    'category_tv' => 'TV-Rechte',
    'category_cup_bonus' => 'Pokalbonus',
    'category_performance_bonus' => 'Leistungsbonus',
    'category_signing_bonus' => 'Handgeld',

    'category_loan' => 'Leihe',
    'category_severance' => 'Abfindung',
    'category_infrastructure' => 'Infrastruktur',
    'category_stadium' => 'Stadion',
    'category_venue_fee' => 'Venue fee (national team)',
    'category_agent_fee' => 'Agenturprovision',
    'category_budget_loan' => 'Budgetdarlehen',
    'category_loan_repayment' => 'Darlehensrückzahlung',

    // Infrastructure upgrades
    'upgrade' => 'Verbessern',
    'upgrade_cancel' => 'Abbrechen',
    'upgrade_confirm' => 'Bestätigen',
    'upgrade_insufficient_budget' => 'Unzureichendes Transferbudget.',

    // Transaction descriptions
    'tx_free_transfer_out' => ':player ging ablösefrei :team_a',
    'tx_player_sold' => ':player verkauft :team_a',
    'tx_player_signed' => ':player verpflichtet :team_de',
    'tx_loan_in' => ':player ausgeliehen :team_de (Gehalt)',
    'tx_player_released' => ':player freigestellt (Abfindung)',
    'tx_severance_installment' => 'Abfindung :player (Rate :current/:total)',
    'tx_severance_loan_received' => 'Darlehen für die Abfindung von :player',
    'tx_cup_advancement' => ':competition - :round',
    'tx_league_phase_qualification' => ':competition - Ligaphase geschafft (:position.)',
    'tx_infrastructure_upgrade' => ':area von Stufe :from auf Stufe :to verbessert',
    'tx_budget_loan_received' => 'Budgetdarlehen erhalten: :amount',
    'tx_budget_loan_repaid' => 'Budgetdarlehen zurückgezahlt: :amount',
    'tx_stadium_supplementary_payment' => 'Zusatztribünen (:seats Plätze)',
    'tx_stadium_stand_expansion_payment' => 'Tribünenerweiterung (:seats Plätze)',
    'tx_stadium_rebuild_payment' => 'Stadionumbau (:capacity Plätze)',
    'tx_stadium_uefa_upgrade_payment' => 'Upgrade auf UEFA-Kategorie :level',
    'tx_stadium_loan_instalment' => 'Jahresrate des Stadiondarlehens: :amount',
    'tx_naming_rights_search_fee' => 'Provision der Handelsagentur (Sponsorensuche)',

    'stadium_debt_service' => 'Schuldendienst des Stadions',
    'tooltip_stadium_debt_service' => 'Jahresrate des Darlehens für den Stadionumbau (Tilgung + Zinsen auf den Saldo). Wird vom verfügbaren Überschuss abgezogen.',

    // Budget loan
    'budget_loan' => 'Budgetdarlehen',
    'loan_active' => 'Aktiv',
    'loan_principal' => 'Erhaltenes Darlehen',
    'loan_interest' => 'Zinsen (15 %)',
    'loan_repayment' => 'Rückzahlung am Saisonende',
    'loan_repayment_hint' => 'Wird automatisch am Saisonende zurückgezahlt. Die Rückzahlung reduziert den verfügbaren Überschuss der nächsten Saison.',
    'loan_description' => 'Nimm ein Darlehen gegen die prognostizierten Einnahmen auf, um dein Transferbudget zu erhöhen. Wird am Saisonende mit Zinsen zurückgezahlt.',
    'loan_max_available' => 'Maximal verfügbar',
    'tooltip_loan_max' => 'Du kannst bis zu 10 % deiner prognostizierten Gesamteinnahmen für die Saison leihen.',
    'tooltip_loan_activity' => 'Darlehen zum Transferbudget hinzugefügt. Wird am Saisonende mit 15 % Zinsen zurückgezahlt.',
    'loan_repayment_deduction' => 'Darlehensrückzahlung',
    'tooltip_loan_repayment_deduction' => 'Rückzahlung des Budgetdarlehens der Vorsaison (Tilgung + 15 % Zinsen). Wird automatisch vom verfügbaren Überschuss dieser Saison abgezogen.',
    'loan_request_button' => 'Darlehen beantragen',
    'loan_amount_label' => 'Betrag (€)',
    'loan_interest_rate' => 'Zinssatz',
    'loan_total_repayment' => 'Gesamtrückzahlung',
    'loan_warning' => 'Die vollständige Rückzahlung wird vom Überschuss der nächsten Saison abgezogen.',
    'loan_confirm' => 'Darlehen bestätigen',
    'loan_cancel' => 'Abbrechen',
    'loan_not_available_desc' => 'Darlehen können während der Transferfenster beantragt werden, wenn kein anderes Darlehen aktiv ist.',

    // Forma de pago de la indemnización (carta de libertad)
    'severance_payment_title' => 'Zahlungsweise',
    'severance_method_lump_sum' => 'Einmalzahlung',
    'severance_method_lump_sum_detail' => 'Du zahlst :amount auf einmal aus dem Budget.',
    'severance_method_installments' => 'In Raten (:months Monate)',
    'severance_method_installments_detail' => ':monthly/Monat über :months Monate (gesamt :total mit Zinsen).',
    'severance_method_bank_loan' => 'Bankdarlehen aufnehmen',
    'severance_method_bank_loan_detail' => 'Die Bank leiht dir :amount und du zahlst es am Saisonende zurück (:repayment mit Zinsen).',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'shirt_sponsor' => 'Trikotsponsor',
    'ad_board' => 'Werbebanden',
    'tooltip_shirt_sponsor' => 'Einnahmen aus dem Sponsorlogo auf dem Trikot. Feste Jahresgebühr für die Vertragslaufzeit.',
    'tooltip_ad_board' => 'Einnahmen aus den Stadionwerbebanden. Feste Jahresgebühr für die Vertragslaufzeit.',
    'category_venue_rent' => 'Stadionmiete',
    'category_tour_cost' => 'Saisonvorbereitungstour',
    'category_matchday_tickets' => 'Tageskasse: Tickets',
    'category_matchday_shirts' => 'Tageskasse: Trikots',
    'category_matchday_merch' => 'Tageskasse: Merchandising',
    'category_matchday_bars' => 'Tageskasse: Bars',

];
