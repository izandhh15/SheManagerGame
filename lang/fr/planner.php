<?php

return [
    // Page chrome
    'planner' => 'Planificateur',
    'title' => 'Planificateur d\'effectif',

    // Sections
    'section_staying' => 'Elles restent',
    'section_outgoing' => 'Départs',
    'section_incoming' => 'Arrivées',
    'section_next_season' => 'Effectif de la saison prochaine',
    'section_staying_count' => ':count joueuse|:count joueuses',

    // Position group labels
    'goalkeepers' => 'Gardiennes',
    'defenders' => 'Défenseures',
    'midfielders' => 'Milieux',
    'forwards' => 'Attaquantes',

    // Player row chrome
    'age_next' => ':age ans la saison prochaine',
    'contract_until' => 'Jusqu\'en :year',
    'no_contract' => 'Sans contrat',

    // Reasons — STAYING
    'reason_owned' => 'Dans l\'effectif',
    'reason_renewed' => 'Prolongation actée',
    'reason_returning_from_loan' => 'Retour de prêt',
    'reason_still_on_loan' => 'Prêtée jusqu\'au :date',

    // Reasons — OUTGOING
    'reason_retiring' => 'Prend sa retraite',
    'reason_transfer_agreed' => 'Transfert acté',
    'reason_pre_contract_departing' => 'Pré-contrat avec un autre club',
    'reason_contract_expiring_unrenewed' => 'Fin de contrat',
    'reason_loan_ending' => 'Fin du prêt',

    // Reasons — INCOMING
    'reason_pre_contract_joining' => 'Pré-contrat signé',
    'reason_reserve_promoted' => 'Promue de l\'équipe réserve',
    'reason_academy_promoted' => 'Promue du centre de formation',

    // Empty states
    'empty_staying' => 'Aucune joueuse prévue pour rester.',

    // Ability/potential row labels
    'current_ability' => 'Actuel',
    'projected_ability' => 'Saison prochaine',
    'potential' => 'Potentiel',

    // Squad role badges
    'col_action' => 'Recommandation',
    'role_wonderkid' => 'Pépite',
    'role_key_player' => 'Joueuse clé',
    'role_first_team' => 'Titulaire',
    'role_rotation' => 'Rotation',
    'role_prospect' => 'Espoir',
    'role_reserves' => 'Remplaçante',
    'role_departing' => 'Sur le départ',

    // Transfer Recommendations
    'transfer_recommendations' => 'Recommandations de transferts',
    'list_conjunction' => 'et',
    'advisory_empty' => 'Aucune recommandation globale. L\'effectif prévu semble équilibré.',
    'advisory_depth_gap' => 'Renforcez :position — il en manque :count pour la formation choisie.',
    'advisory_quality_gap' => 'Renforcez :position — :gap points sous le niveau du reste de l\'équipe.',
    'advisory_no_backup' => 'Pas de doublure en :position — les titulaires n\'ont personne pour les remplacer en cas de blessure ou de rotation.',
    'advisory_weak_backup' => 'Renforcez le banc en :position — la première remplaçante est à :gap points de la moins bonne titulaire.',
    'advisory_overload' => 'Surnombre en :position — :count joueuses de grand niveau se disputent :spots places de titulaires (:names). Il y aura du mécontentement dans le vestiaire.',
    'advisory_age_gap' => 'Peu de jeunes en :position — aucune joueuse de :age ou moins prévue.',
    'advisory_wage_cliff' => 'Prolongez :name — contrat jusqu\'en :year et toujours sans accord.',
    'advisory_development' => 'Donnez du temps de jeu à :names pour maximiser leur développement.',
    'advisory_wasted_wage' => 'Envisagez de vendre :names — salaires élevés et peu de temps de jeu.',
    'advisory_key_departure' => 'Remplacez :name (:position) — son départ laisse un vide.',

    // Position group labels used inside advisories (singular & lowercase for sentence flow)
    'group_goalkeeper' => 'les buts',
    'group_defender' => 'la défense',
    'group_midfielder' => 'le milieu de terrain',
    'group_forward' => 'l\'attaque',

    // Action chips
    'action_play_often' => 'Donner du temps de jeu',
    'action_loan_out' => 'Prêter',
    'action_keep' => 'Garder',
    'action_renew' => 'Prolonger',
    'action_list' => 'Vendre',
    'action_replace' => 'Remplacer',

    // Help disclosure
    'help_toggle' => 'Comment fonctionne le Planificateur ?',
    'help_overview_intro' => 'Cet écran projette ce que sera votre effectif au début de la saison prochaine en fonction des contrats, prêts, transferts actés et pré-contrats.',
    'help_overview_sections' => 'Elles restent réunit les joueuses qui seront toujours avec vous ; Arrivées, celles déjà confirmées ; Départs, celles qui partiront avant la reprise.',
    'help_actions_title' => 'Recommandations par joueuse',
    'help_action_renew' => 'Prolonger — proposez un nouveau contrat avant que l\'actuel n\'expire.',
    'help_action_replace' => 'Remplacer — son départ laisse un vide qu\'il faudra combler sur le marché.',
    'help_action_play_often' => 'Donner du temps de jeu — espoir prête à accumuler des matchs en équipe première.',
    'help_action_loan_out' => 'Prêter — elle ira prendre du rythme dans un autre club et reviendra plus mature.',
    'help_action_list' => 'Vendre — remplaçante sans place dans la rotation à mettre sur le marché.',

    // Auto-generated blurbs
    'blurb_wonderkid' => 'Grand potentiel — déjà utile et en pleine progression.',
    'blurb_key_player' => 'Pilier de l\'équipe. Construisez autour d\'elle.',
    'blurb_first_team' => 'Titulaire fiable à son poste.',
    'blurb_prospect' => 'Jeune prometteuse, encore en développement.',
    'blurb_rotation' => 'Remplaçante solide, proche du onze.',
    'blurb_reserves' => 'Au fond du banc à son poste.',
    'blurb_departing' => 'Part en fin de saison.',
];
