<?php

return [
    // Button und Modal
    'mutual_terminate' => 'Einvernehmliche Vertragsauflösung',
    'modal_title' => 'Einvernehmliche Vertragsauflösung',
    'modal_intro' => 'Der Berater von :player verlangt eine Abfindung für die Vertragsauflösung. Du kannst seine Summe akzeptieren, ein Gegenangebot machen oder die Verhandlung abbrechen.',
    'agent_demand_label' => 'Der Berater verlangt',
    'unilateral_cost_label' => 'Einseitige Freigabe (ohne Verhandlung)',
    'your_offer_label' => 'Dein Angebot (€)',
    'your_offer_placeholder' => 'Betrag in Euro',
    'btn_offer' => 'Gegenangebot',
    'btn_accept_demand' => 'Seine Summe akzeptieren',
    'btn_walk_away' => 'Verhandlung abbrechen',
    'btn_start' => 'Verhandlung starten',
    'round_label' => 'Runde :current von :max',

    // Zahlungsart
    'payment_title' => 'Wie zahlst du die Abfindung?',
    'payment_intro' => 'Du hast dich mit :player auf :amount geeinigt. Wähle, wie du die Abfindung zahlst:',
    'btn_complete' => 'Auflösung bestätigen',

    // Berater-Nachrichten (API)
    'chat_initial_demand' => 'Meine Klientin :player wäre für :amount zu einer Auflösung bereit. Das ist fair für die restliche Vertragslaufzeit.',
    'chat_resume' => 'Zurück zum Thema: :player würde für :amount auflösen. Akzeptierst du?',
    'chat_agent_counters' => 'Kommt nicht infrage. :player geht nicht unter :amount. Überleg es dir gut.',
    'chat_agent_accepts_offer' => 'Einverstanden. :player akzeptiert die Auflösung für :amount. Abgemacht.',
    'chat_agent_walks_away' => ':player ist an einer Auflösung zu dieser Summe nicht interessiert. Sie bleibt und wird ihren Vertrag erfüllen.',
    'chat_agreed' => 'Perfekt. :player löst für :amount auf. Wähle nun, wie du die Abfindung zahlst.',
    'chat_you_walked_away' => 'Du hast die Verhandlung mit :player abgebrochen. Sie bleibt in deinem Kader.',
    'chat_free' => ':player hat kein ausstehendes Gehalt: Sie akzeptiert die Auflösung ohne Abfindung.',

    // Fehler
    'no_active_negotiation' => 'Es gibt keine aktive Verhandlung mit dieser Spielerin.',
    'no_agreement' => 'Es gibt keine Auflösungsvereinbarung mit dieser Spielerin.',
];
