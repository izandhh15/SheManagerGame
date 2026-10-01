<?php

return [
    // Seiten-Chrome
    'planner' => 'Planer',
    'title' => 'Kaderplaner',

    // Abschnitte
    'section_staying' => 'Bleiben',
    'section_outgoing' => 'Abgänge',
    'section_incoming' => 'Zugänge',
    'section_next_season' => 'Kader nächste Saison',
    'section_staying_count' => ':count Spielerin|:count Spielerinnen',

    // Positionsgruppen
    'goalkeepers' => 'Torhüterinnen',
    'defenders' => 'Verteidigerinnen',
    'midfielders' => 'Mittelfeldspielerinnen',
    'forwards' => 'Stürmerinnen',

    // Spielerzeilen
    'age_next' => ':age Jahre nächste Saison',
    'contract_until' => 'Bis :year',
    'no_contract' => 'Kein Vertrag',

    // Gründe — BLEIBEN
    'reason_owned' => 'Im Kader',
    'reason_renewed' => 'Verlängerung vereinbart',
    'reason_returning_from_loan' => 'Kehrt von Leihe zurück',
    'reason_still_on_loan' => 'Ausgeliehen bis :date',

    // Gründe — ABGÄNGE
    'reason_retiring' => 'Beendet die Karriere',
    'reason_transfer_agreed' => 'Transfer vereinbart',
    'reason_pre_contract_departing' => 'Vorvertrag bei anderem Klub',
    'reason_contract_expiring_unrenewed' => 'Vertrag läuft aus',
    'reason_loan_ending' => 'Leihe endet',

    // Gründe — ZUGÄNGE
    'reason_pre_contract_joining' => 'Vorvertrag unterschrieben',
    'reason_reserve_promoted' => 'Rückt aus der zweiten Mannschaft auf',
    'reason_academy_promoted' => 'Rückt aus der Akademie auf',

    // Leere Zustände
    'empty_staying' => 'Keine Spielerinnen, die voraussichtlich bleiben.',

    // Labels für aktuelle Stärke / Potenzial
    'current_ability' => 'Aktuell',
    'projected_ability' => 'Nächste Saison',
    'potential' => 'Potenzial',

    // Kaderrollen-Badges
    'col_action' => 'Empfehlung',
    'role_wonderkid' => 'Juwel',
    'role_key_player' => 'Schlüsselspielerin',
    'role_first_team' => 'Stammspielerin',
    'role_rotation' => 'Rotation',
    'role_prospect' => 'Talent',
    'role_reserves' => 'Ersatz',
    'role_departing' => 'Abgängerin',

    // Transferempfehlungen
    'transfer_recommendations' => 'Transferempfehlungen',
    'list_conjunction' => 'und',
    'advisory_empty' => 'Keine allgemeinen Empfehlungen. Der geplante Kader wirkt ausgewogen.',
    'advisory_depth_gap' => 'Verstärke :position — :count fehlen für die gewählte Formation.',
    'advisory_quality_gap' => 'Verstärke :position — :gap Punkte unter dem Rest des Teams.',
    'advisory_no_backup' => 'Kein Ersatz auf :position — die Stammspielerinnen haben bei Verletzung oder Rotation keine Vertretung.',
    'advisory_weak_backup' => 'Verstärke die Bank auf :position — die erste Ersatzspielerin liegt :gap Punkte unter der schwächsten Stammspielerin.',
    'advisory_overload' => 'Überbesetzung auf :position — :count Spielerinnen auf Topniveau kämpfen um :spots Stammplätze (:names). Das wird Unruhe in der Kabine geben.',
    'advisory_age_gap' => 'Dünner Nachwuchs auf :position — keine geplanten Spielerinnen von :age oder jünger.',
    'advisory_wage_cliff' => 'Verlängere mit :name — Vertrag bis :year und noch keine Einigung.',
    'advisory_development' => 'Gib :names Spielzeit, um ihre Entwicklung zu maximieren.',
    'advisory_wasted_wage' => 'Erwäge, :names zu verkaufen — hohe Gehälter und wenig Spielzeit.',
    'advisory_key_departure' => 'Ersetze :name (:position) — ihr Abgang reißt eine Lücke.',

    // Positionsgruppen in den Empfehlungen (Singular, kleingeschrieben)
    'group_goalkeeper' => 'das Tor',
    'group_defender' => 'die Abwehr',
    'group_midfielder' => 'das Mittelfeld',
    'group_forward' => 'der Angriff',

    // Aktions-Chips
    'action_play_often' => 'Spielzeit geben',
    'action_loan_out' => 'Verleihen',
    'action_keep' => 'Behalten',
    'action_renew' => 'Verlängern',
    'action_list' => 'Verkaufen',
    'action_replace' => 'Ersetzen',

    // Hilfe-Bereich
    'help_toggle' => 'Wie funktioniert der Kaderplaner?',
    'help_overview_intro' => 'Diese Ansicht zeigt, wie dein Kader zu Beginn der nächsten Saison aussehen wird — basierend auf Verträgen, Leihen, vereinbarten Transfers und Vorverträgen.',
    'help_overview_sections' => '„Bleiben“ versammelt die Spielerinnen, die bei dir bleiben; „Zugänge“ die bereits bestätigten Neuzugänge; „Abgänge“ die, die vor dem nächsten Saisonstart gehen.',
    'help_actions_title' => 'Empfehlungen je Spielerin',
    'help_action_renew' => 'Verlängern — biete einen neuen Vertrag an, bevor der aktuelle ausläuft.',
    'help_action_replace' => 'Ersetzen — ihr Abgang reißt eine Lücke, die auf dem Markt geschlossen werden sollte.',
    'help_action_play_often' => 'Spielzeit geben — Talent, bereit für Einsätze in der ersten Mannschaft.',
    'help_action_loan_out' => 'Verleihen — geht zu einem anderen Klub, um Spielpraxis zu sammeln, und kehrt gereifter zurück.',
    'help_action_list' => 'Verkaufen — Ersatz ohne Platz in der Rotation, die auf den Markt sollte.',

    // Automatisch erzeugte Texte
    'blurb_wonderkid' => 'Großes Potenzial — schon jetzt nützlich und entwickelt sich rasant.',
    'blurb_key_player' => 'Stütze des Teams. Baue um sie herum auf.',
    'blurb_first_team' => 'Verlässliche Stammspielerin auf ihrer Position.',
    'blurb_prospect' => 'Junges Talent, noch in der Entwicklung.',
    'blurb_rotation' => 'Solide Ergänzung, nah an der Startelf.',
    'blurb_reserves' => 'Weit hinten auf der Bank auf ihrer Position.',
    'blurb_departing' => 'Geht am Saisonende.',
];
