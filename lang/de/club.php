<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finanzen',
        'investment' => 'Mitarbeiter',
        'stadium' => 'Stadion',
        'commercial' => 'Kommerziell',
        'reputation' => 'Ruf',
    ],

    'commercial' => [
        'title' => 'Kommerzielle Sponsorings',
        'intro' => 'Suche Sponsoren, um wiederkehrende Einnahmen zu generieren, die das Budget des Clubs stärken.',
        'naming_rights_title' => 'Stadion-Namensrechte',
        'seek_explainer' => 'Beauftrage eine Agentur, Sponsoren zu sondieren. Jede Suche kostet :fee und du musst :days Tage zwischen den Suchen warten.',
        'seek_button' => 'Sponsoren suchen (:fee)',
        'seek_cooldown' => '{1} Du kannst in :days Tag erneut suchen.|[2,*] Du kannst in :days Tagen erneut suchen.',
        'seek_unaffordable' => 'Du hast kein Budget für die Agenturprovision (:fee).',
    ],

    'stadium' => [
        'home_ground' => 'Heimstätte',
        'stadium_name' => 'Stadion',
        'capacity' => 'Kapazität',
        'uefa_category' => 'UEFA-Stufe',
        'uefa_category_short' => 'UEFA',
        'uefa_category_tooltip' => 'Die UEFA klassifiziert Stadien in vier Kategorien (1 bis 4). Ein Aufstieg erfordert den Umbau der Anlagen (Flutlicht, Kabinen, Presseraum, Logen) und dass die Kapazität das Mindestmaß der nächsten Kategorie übersteigt.',

        'fan_base' => 'Fans',
        'fan_base_help' => 'Die Loyalität steigt mit Titeln und guten Kampagnen und sinkt nach schwachen Saisons. Zusammen mit dem Ruf bestimmt sie, wie voll das Stadion an Spieltagen wird.',
        'fan_base_trend' => 'Trend',
        'current_loyalty' => 'Unterstützung der Fans',

        'last_attendance' => 'Letztes Heimspiel',
        'fill_rate' => 'Auslastung',
        'no_home_match_yet' => 'Es wurde noch kein Heimspiel ausgetragen.',

        'no_finances_yet' => 'Die Finanzen der Saison erscheinen, sobald die Prognosen erstellt werden.',

        'stadium_revenue' => [
            'title' => 'Stadioneinnahmen',
            'season_tickets' => 'Dauerkarten',
            'matchday' => 'Tageskasse',
        ],

        'upgrades' => [
            'title' => 'Erweiterung und Umbau',
            'base_capacity' => 'Basiskapazität',
            'supplementary' => 'Zusatztribünen',
            'total' => 'Gesamtkapazität',
            'seats' => 'Plätze',
            'seats_total' => 'Plätze gesamt',
            'seats_to_add' => 'Hinzugefügte Plätze',
            'target_capacity' => 'Zielkapazität',
            'total_cost' => 'Gesamtkosten',
            'completion_date' => 'Fertigstellungsdatum',
            'financing' => 'Finanzierung',
            'financing_cash' => 'Barzahlung',
            'financing_loan' => 'Bankdarlehen',
            'financing_cash_hint' => 'Wird bei Bestätigung vom verfügbaren Budget abgezogen.',
            'financing_loan_hint' => 'Banklimit: :cap. Rückzahlung in 10 Jahresraten (konstante Tilgung + Zinsen auf den Saldo).',

            'project_supplementary' => 'Zusatztribünen',
            'project_stand_expansion' => 'Tribünenerweiterung',
            'project_rebuild' => 'Stadionumbau',
            'project_uefa_upgrade' => 'UEFA-Upgrade',
            'ready_on' => 'Fertig am :date',
            'ready_in_season' => 'Verfügbar in der Saison :season',
            'loan_remaining' => 'Darlehen ausstehend: :amount',

            'tier_label' => 'Stufe :n',
            'from_total' => 'Ab :total',
            'per_seat_inline' => ':cost / Platz',
            'time_days_inline' => ':days Tage',
            'time_months_inline' => ':count Monat|:count Monate',
            'status_available' => 'Verfügbar',
            'status_locked' => 'Gesperrt',
            'status_in_progress' => 'Im Bau',
            'cta_planificar' => 'Planen →',
            'unlock_with_revenue' => 'Freischalten mit :revenue Jahreseinnahmen',
            'unlock_with_reputation' => 'Freischalten in Kategorie :tier',
            'unlock_progress_label' => 'Aktuelle Einnahmen: :current',

            'cta_supplementary_full_short' => 'Zusatztribünen am Limit. Baue das Stadion um, um Platz zu schaffen.',
            'cta_locked_no_budget' => 'Freischalten mit :cost. Verfügbares Budget: :budget.',

            'budget_caps_slider' => 'Das verfügbare Budget (:budget) begrenzt das Kontingent — ohne es könntest du :natural Plätze erreichen.',
            'financing_cash_hint_budget' => 'Wird bei Bestätigung vom verfügbaren Budget (:budget) abgezogen.',

            'cta_supplementary_label' => 'Erweiterung',
            'cta_supplementary_title' => 'Zusatztribünen hinzufügen',

            'cta_stand_expansion_label' => 'Erweiterung',
            'cta_stand_expansion_title' => 'Eine Tribüne erweitern',

            'cta_rebuild_label' => 'Neubau',
            'cta_rebuild_title' => 'Das Stadion neu bauen',

            'reputation_tiers' => [
                'local' => 'Lokal',
                'modest' => 'Bescheiden',
                'established' => 'Etabliert',
                'continental' => 'Kontinental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Zusatztribünen hinzufügen',
            'modal_supplementary_description' => 'Modulare Provisorium-Tribünen: schnell (30 Tage) und bar bezahlt, aber ohne neue kommerzielle Flächen und beim Stadionumbau wieder entfernt.',
            'modal_stand_expansion_title' => 'Eine Tribüne erweitern',
            'modal_stand_expansion_description' => 'Reißt eine Tribüne ab und baut sie größer wieder auf. Die Plätze sind dauerhaft, anders als bei den Zusatztribünen.',
            'modal_rebuild_title' => 'Das Stadion neu bauen',
            'modal_rebuild_description' => 'Reißt das aktuelle Stadion ab und baut ein neues. Der Preis pro Platz steigt staffelweise: Je größer das Stadion, desto teurer jeder zusätzliche Platz. Das neue Stadion wird mit der besten UEFA-Kategorie übergeben, die seine Kapazität erlaubt, ohne Zusatzkosten.',
            'rebuild_marginal_rate_prefix' => 'Preis pro Platz bei dieser Größe:',
            'rebuild_marginal_rate_suffix' => '',
            'rebuild_cap_explainer_reputation' => 'Das Maximum setzt das Bankdarlehen, für das sich dein Club qualifiziert (:cap). Dein aktueller Ruf (:tier) setzt diese Obergrenze: Steige in die nächste Kategorie auf, um einen größeren Kredit zu erhalten.',
            'rebuild_cap_explainer_affordability' => 'Das Maximum setzt das Bankdarlehen, für das sich dein Club qualifiziert (:cap), berechnet auf Basis deiner prognostizierten Jahreseinnahmen. Steigere deine Einnahmen, um einen größeren Kredit zu erhalten.',
            'commit_project' => 'Bau starten',

            'cta_disabled_by_active_project' => 'Du hast bereits ein laufendes Projekt. Siehe Verlauf unten.',

            'cta_uefa_label' => 'Umbau',
            'cta_uefa_title' => 'Auf UEFA-Kategorie :to aufsteigen (von :from)',
            'cta_uefa_title_generic' => 'UEFA-Kategorie aufsteigen',
            'cta_uefa_button' => 'Anlagen verbessern',
            'cta_uefa_tagline' => 'Baue die Anlagen um, um auf UEFA-Kategorie :target aufzusteigen. Fixkosten :cost, etwa 9 Monate Bauzeit, ohne Auswirkung auf die Kapazität.',
            'cta_uefa_capacity_floor' => 'Für die UEFA-Kategorie :target muss das Stadion mehr als :min_cap Plätze haben. Erweitere zuerst die Kapazität.',
            'cta_uefa_already_max' => 'Dein Stadion ist bereits in der höchsten UEFA-Kategorie. Es gibt keine weiteren Stufen freizuschalten.',
            'cta_uefa_no_base_level' => 'Deinem Stadion ist keine UEFA-Kategorie zugewiesen. Erweitere die Kapazität, um in die Klassifizierung zu kommen.',

            'modal_uefa_title' => 'Auf UEFA-Kategorie :to aufsteigen',
            'modal_uefa_description' => 'Umbau der Anlagen, um die Anforderungen der nächsten UEFA-Kategorie zu erfüllen (Flutlicht, Kabinen, Pressebereiche, Logen und Barrierefreiheit). Die Kapazität bleibt während der Bauarbeiten unberührt: Die neue Kategorie wird zu Beginn der nächsten Saison eingetragen.',
            'uefa_transition_label' => 'Kategorie',
        ],

        'history' => [
            'title' => 'Bauverlauf',
            'empty' => 'Noch keine Bauarbeiten im Stadion.',
            'empty_hint' => 'Vergangene und laufende Bauarbeiten erscheinen hier.',
            'col_type' => 'Projekt',
            'col_detail' => 'Details',
            'col_cost' => 'Kosten',
            'col_status' => 'Status',
            'detail_seats' => ':count Plätze',
            'detail_rebuild' => ':count Plätze (neues Stadion)',
            'detail_uefa_upgrade' => 'UEFA-Kategorie :from → :to',
            'status_completed' => 'Abgeschlossen',
            'status_in_progress' => 'Laufend',
            'season_label' => 'Sai. :season',
            'ready_label' => 'Fertig am :date',
        ],

        'season_tickets' => [
            'title' => 'Preise',
            'subtitle' => 'Wähle eine Preisstrategie für deine Dauerkarten. Niedrigere Preise füllen das Stadion mehr; höhere Preise bringen mehr pro Platz. Wird mit dem ersten Ligaspiel gesperrt.',
            'deadline_notice' => 'Frist: Die Preise werden mit dem ersten Ligaspiel der Saison gesperrt.',
            'locked_notice' => 'Die Dauerkarten sind in dieser Saison gesperrt. In der nächsten Vorbereitung kannst du neue Preise festlegen.',
            'tickets_sold' => 'Verkaufte Dauerkarten',
            'projected_season_tickets' => 'Prognostizierte Dauerkarten',
            'projected_season_tickets_tooltip' => 'Dauerkarten, die du voraussichtlich verkaufst (im Voraus bezahlt). Die Zuschauerzahl pro Spiel ist unterschiedlich: Addiere die Tageskassen-Tickets und ziehe die Dauerkarteninhaber ab, die nicht kommen.',
            'of_capacity' => 'der Kapazität',
            'matchday_occupancy' => 'Auslastung am Spieltag',
            'save_button' => 'Speichern',
            'preset' => [
                'accessible' => 'Günstig',
                'standard' => 'Standard',
                'premium' => 'Premium',
            ],
            'preset_hint' => [
                'accessible' => 'Billiger, volleres Stadion.',
                'standard' => 'Referenzpreise.',
                'premium' => 'Teurer, geringere Auslastung.',
            ],
        ],

        'identity' => [
            'subtitle' => 'Benenne dein Stadion kostenlos um (einmal pro Saison, in der Vorbereitung). Der Verkauf des Namens an einen Sponsor wird auf der Kommerziell-Seite verwaltet.',
            'sponsor_owns_name' => 'Ein Sponsor (:sponsor) besitzt den Stadionnamen bis zum Vertragsablauf, daher kannst du ihn nicht umbenennen.',
            'manage_in_commercial' => 'In Kommerziell verwalten',
            'sell_naming_rights' => 'Namensrechte verkaufen',
        ],

        'naming_rights' => [
            'title' => 'Stadionidentität und Namensrechte',
            'current_name' => 'Aktueller Name',
            'source_historic' => 'Historisch',
            'source_custom' => 'Umbenannt',
            'source_sponsor' => 'Gesponsert',

            'seasons_remaining' => '{1} :count Saison übrig|[2,*] :count Saisons übrig',

            'offers_title' => 'Sponsoringangebote',
            'becomes' => 'Das Stadion heißt künftig «:name»',
            'annual_value' => 'Jahreswert',
            'contract_length' => 'Vertrag',
            'seasons' => '{1} :count Saison|[2,*] :count Saisons',
            'accept_button' => 'Vertrag annehmen',
            'accept_confirm' => 'Namensrechte an :sponsor verkaufen? Das sperrt den Stadionnamen für die Vertragsdauer und kostet Fan-Unterstützung.',
            'renewal_badge' => 'Verlängerung',
            'renew_button' => 'Vertrag verlängern',
            'renew_confirm' => 'Vertrag mit :sponsor verlängern? Behält den Stadionnamen ohne Verlust an Fan-Unterstützung.',

            'rename_button' => 'Stadion umbenennen',
            'rename_placeholder' => 'Neuer Stadionname',
            'rename_save' => 'Name speichern',
            'rename_locked_season' => 'Das Stadion wurde in dieser Saison bereits umbenannt.',

            'window_closed_notice' => 'Die Stadionidentität wird in der Vorbereitung festgelegt. Verträge und Umbenennungen öffnen sich wieder vor dem ersten Ligaspiel der nächsten Saison.',
        ],
    ],

    'reputation' => [
        'current_tier' => 'Aktuelle Stufe',

        'tiers' => 'Rufstufen',
        'tiers_help_toggle' => 'Wie funktionieren die Rufstufen?',
        'ladder_help' => 'Clubs steigen auf, indem sie oben in der Liga landen. In den höheren Stufen nutzt sich der Ruf jede Saison ab, wenn er nicht mit Ergebnissen untermauert wird.',

        'current' => 'Aktuell',

        'qualitative_distance' => [
            'one_strong_season' => 'Eine gute Saison würde reichen, um :tier zu erreichen.',
            'two_strong_seasons' => 'Ein paar gute Saisons trennen dich von :tier.',
            'several_seasons' => 'Mehrere solide Saisons trennen dich von :tier.',
            'long_road' => 'Es ist ein langer Weg bis :tier.',
        ],

        'tier_descriptors' => [
            'local' => 'Ein bescheidener Club mit treuer lokaler Fangemeinde.',
            'modest' => 'Ein kleiner Club, der in die erste Liga will oder sich dort halten will.',
            'established' => 'Ein historischer Club mit Jahren der Erfahrung in der ersten Liga.',
            'continental' => 'Stammgast in europäischen Wettbewerben.',
            'elite' => 'Referenz des europäischen Fußballs.',
        ],

        'career' => [
            'title' => 'Karriereverlauf',
            'seasons_managed' => 'Geführte Saisons',
            'starting_tier' => 'Startstufe',
            'matches_managed' => 'Geleitete Spiele',
            'trophies' => 'Titel',
        ],

        'trophy_cabinet' => [
            'title' => 'Trophäenschrank',
            'empty' => 'Du hast mit diesem Club noch keinen Titel erobert.',
        ],

        'path_title' => 'Weg zur nächsten Stufe',
        'path_also' => 'Pokaltitel und Europapokal-Läufe zählen ebenfalls am Saisonende.',
        'maintenance_note' => 'In dieser Stufe nutzt sich der Ruf jede Saison ab, wenn du ihn nicht mit Ergebnissen untermauerst.',
        'projected' => 'Prognostiziert',

        'legend' => [
            'forward' => 'Fortschritt',
            'flat' => 'Kein Fortschritt',
            'setback' => 'Rückschritt',
        ],

        'impact' => [
            'major_leap' => 'Großer Sprung nach vorne',
            'solid_step' => 'Solider Schritt nach vorne',
            'small_step' => 'Kleiner Fortschritt',
            'stalls' => 'Kein Fortschritt',
            'setback' => 'Rückschritt',
        ],

        'history' => [
            'title' => 'Leistungsverlauf',
            'empty' => 'Dein Verlauf erscheint am Ende der ersten Saison.',
            'current_suffix' => '(laufend)',
            'promoted' => 'Aufstieg',
            'relegated' => 'Abstieg',
            'legend' => [
                'same_tier' => 'Gleiche Kategorie',
            ],
        ],

        'impact_title' => 'Was der Ruf für deinen Club bringt',
        'impact_signings_title' => 'Verpflichtungen anziehen',
        'impact_signings_body' => 'Spielerinnen auf höherem Niveau tendieren zu Clubs mit mehr Ruf. Vereinslose Spielerinnen, Transferziele und rivalisierende Clubs prüfen deine Stufe, bevor sie sich an den Verhandlungstisch setzen.',
        'impact_retain_title' => 'Talente halten',
        'impact_retain_body' => 'Auch dein eigener Kader reagiert auf den Ruf. Ein wachsender Club hält seine Schlüsselspielerinnen besser; wenn er in der Stufe fällt, tauchen die Räuber auf und Verlängerungen werden kompliziert.',
        'impact_economy_title' => 'Wirtschaftliche Chancen',
        'impact_economy_body' => 'Stadionbesuch, Ticketpreise und kommerzielle Einnahmen skalieren mit dem Ruf. Aufsteigen schaltet höhere Einnahmen an allen Fronten frei; Absteigen schnürt das Budget ein.',

    ],
];
