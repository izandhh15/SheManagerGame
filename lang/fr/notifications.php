<?php

return [
    // Inbox
    'inbox' => 'Notifications',
    'new' => 'nouvelles',
    'all_caught_up' => 'Tu es à jour',

    // Department inbox tabs
    'dept_all' => 'Tout',
    'dept_sporting' => 'Staff technique',
    'dept_transfers' => 'Direction sportive',
    'dept_scouting' => 'Recruteurs',
    'dept_academy' => 'Centre de formation',
    'dept_board' => 'Direction',
    'dept_competition' => 'Compétition',

    // Critical-alert popup (blocking, must-dismiss)
    'alert_heading' => 'Alerte importante',
    'alerts_heading' => ':count alertes importantes',
    'celebration_heading' => 'Félicitations !',
    'alert_dismiss' => 'Ignorer',
    'dismiss_all' => 'Tout ignorer',
    'alert_continue' => 'Continuer',
    'action_review_offer' => 'Voir l\'offre',
    'action_view_competition' => 'Voir la compétition',
    'action_view_details' => 'Voir les détails',

    // Injury types
    'injury_muscle_fatigue' => 'fatigue musculaire',
    'injury_muscle_strain' => 'élongation musculaire',
    'injury_calf_strain' => 'élongation du mollet',
    'injury_ankle_sprain' => 'entorse de la cheville',
    'injury_groin_strain' => 'élongation de l\'adducteur',
    'injury_hamstring_tear' => 'déchirure de l\'ischio-jambier',
    'injury_knee_contusion' => 'contusion du genou',
    'injury_metatarsal_fracture' => 'fracture du métatarse',
    'injury_acl_tear' => 'rupture du ligament croisé',
    'injury_achilles_rupture' => 'rupture du tendon d\'Achille',

    // Player injuries
    'player_injured_title' => ':player blessée',
    'player_injured_message' => ':player a subi :injury :location.',
    'player_injured_message_with_date' => ':player a subi :injury :location. Absente jusqu\'au :date.',
    'injury_location_match' => 'pendant le match',
    'injury_location_training' => 'pendant l\'entraînement',

    // Player suspensions
    'player_suspended_title' => ':player suspendue',
    'player_suspended_message' => ':player a été suspendue pour :matches match en raison de :reason. Elle manquera le prochain match de :competition.|:player a été suspendue pour :matches matchs en raison de :reason. Elle manquera le prochain match de :competition.',
    'reason_red_card' => 'carton rouge',
    'reason_yellow_accumulation' => 'accumulation de cartons jaunes',

    // Player recovery
    'player_recovered_title' => ':player rétablie',
    'player_recovered_message' => ':player s\'est rétablie et est disponible pour jouer.',

    // Transfer offers
    'transfer_offer_title' => 'Offre d\'achat pour :player',
    'transfer_offer_message' => ':team_el a offert :fee pour la joueuse.',
    'free_transfer' => 'Transfert gratuit',

    // Transfer complete
    'transfer_complete_incoming_title' => ':player recrutée',
    'transfer_complete_incoming_message' => ':player a rejoint ton effectif en provenance :team_de pour :fee.',
    'transfer_complete_outgoing_title' => ':player vendue',
    'transfer_complete_outgoing_message' => ':player a été transférée :team_a pour :fee.',
    'transfer_failed_title' => 'Recrutement raté : :player',
    'transfer_failed_message' => 'Le transfert convenu de :player n\'a pas pu être finalisé et le budget réservé a été libéré.',
    'pre_contract_failed_title' => 'Pré-contrat raté : :player',
    'pre_contract_failed_message' => 'Le pré-contrat convenu avec :player n\'a pas pu être finalisé : elle n\'était plus à :team en fin de saison. Elle ne rejoint pas ton effectif.',
    'loan_out_complete_title' => ':player prêtée',
    'loan_out_complete_message' => ':player a été prêtée :team_a jusqu\'à la fin de la saison.',

    // Release clause triggered against the user (Phase 3)
    'player_left_via_release_clause_title' => 'Clause libératoire : :player s\'en va',
    'player_left_via_release_clause_message' => ':player s\'en va :team_a après l\'activation de sa clause libératoire. Ton club reçoit :fee.',

    // Expiring offers
    'offer_expiring_title' => 'Offre pour :player expire bientôt',
    'offer_expiring_message' => '{0}L\'offre :team_de pour :player expire aujourd\'hui.|{1}L\'offre :team_de pour :player expire dans :count jour.|[2,*]L\'offre :team_de pour :player expire dans :count jours.',

    // Scout
    'scout_complete_title' => 'Rapport du recruteur prêt',
    'scout_complete_message' => 'Ton recruteur a trouvé :count joueuses qui correspondent à ta recherche.',

    // Contracts
    'contract_expiring_title' => 'Contrat de :player expire bientôt',
    'contract_expiring_message' => 'Le contrat de :player expire dans :months mois.',

    // Loan returns
    'loan_return_title' => ':player de retour de prêt',
    'loan_return_message' => ':player est de retour de son prêt :team_en.',

    // Low energy
    'low_fitness_title' => ':player en manque d\'énergie',
    'low_fitness_message' => ':player n\'a que :fitness% d\'énergie et a besoin de repos.',

    // Loan search
    'loan_offer_received_title' => 'Offre de prêt pour :player',
    'loan_offer_received_message' => ':team_el a proposé de prendre la joueuse en prêt.',
    'loan_search_failed_title' => 'Recherche de prêt échouée',
    'loan_search_failed_message' => 'Aucun club intéressé pour prendre :player en prêt. La joueuse est de nouveau disponible.',

    // Competition advancement
    'competition_advancement_title' => 'Qualification en :competition',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => 'Élimination de :competition',
    'competition_elimination_message' => ':stage',
    'trophy_won_title' => 'Championne de la :competition !',

    // Academy
    'academy_batch_title' => 'Nouvelles jeunes du centre',
    'academy_batch_message' => ':count nouvelles joueuses ont rejoint le centre de formation.',
    'academy_overage_promoted_title' => 'Diplômées du centre de formation',
    'academy_overage_promoted_message' => ':count jeunes de 21 ans et plus ont été promues en équipe première.',
    'academy_gap_promoted_title' => 'Jeunes promues',
    'academy_gap_promoted_message' => ':count jeunes ont été promues pour combler les trous dans l\'effectif.',
    'reserve_overage_promoted_title' => 'Diplômée de l\'équipe réserve',
    'reserve_overage_promoted_message' => ':player a dépassé l\'âge de l\'équipe réserve et rejoint définitivement l\'équipe première.',
    'reserve_stand_in_added_title' => 'Renfort de l\'équipe C',
    'reserve_stand_in_added_message' => 'L\'équipe réserve a intégré :count joueuses de l\'équipe C pour compléter l\'effectif.',
    // Loan request results
    'loan_accepted_title' => 'Prêt de :player accepté',
    'loan_accepted' => ':team a accepté ta demande de prêt pour :player.',
    'loan_rejected_title' => 'Prêt de :player refusé',
    'loan_rejected' => ':team a refusé ta demande de prêt pour :player.',

    // Tournament welcome
    'tournament_welcome_title' => 'Bienvenue à la Coupe du monde !',
    'tournament_welcome_message' => 'Tout le pays a les yeux rivés sur toi. Pas de pression… mais ne les déçois pas !',

    // Priority badges
    'priority_urgent' => 'Urgent',
    'priority_attention' => 'Attention',

    // Ofertas de empleo del manager (modo Pro Manager)
    'job_offer_received_title' => ':count clubs intéressés par toi',
    'job_offer_post_firing_title' => 'Choisis ton prochain club (:count offres)',
    'job_offer_received_message' => 'Consulte l\'écran de fin de saison pour accepter ou refuser.',

    // Transfer window open
    'transfer_window_open_title' => 'Mercato de :window ouvert',
    'transfer_window_open_message' => 'La fenêtre des transferts est ouverte. Les transferts convenus rejoindront ton effectif immédiatement.',

    // Transfer window closing
    'transfer_window_closing_title' => 'Fermeture du mercato de :window',
    'transfer_window_closing_message' => 'C\'est ta dernière chance pour recruter. Le mercato ferme après cette journée.',
    'transfer_window_closing_title_winter' => '⏰ Jour J du mercato d\'hiver !',
    'transfer_window_closing_message_winter' => 'Dernières heures du mercato de janvier : il ferme après cette journée. Que personne ne s\'endorme !',

    // Transfer window closed (also the AI market summary — the window-close notice
    // and the league transfer count are a single notification)
    'ai_transfer_title' => 'Mercato de :window fermé',
    'ai_transfer_message' => 'Le mercato est fermé. :count transferts finalisés dans la ligue. Les transferts convenus seront finalisés à l\'ouverture du prochain mercato.',
    'ai_transfer_message_none' => 'Le mercato est fermé. Les transferts convenus seront finalisés à l\'ouverture du prochain mercato.',
    'ai_transfer_window_summer' => 'Été',
    'ai_transfer_window_winter' => 'Hiver',

    // Player released
    'player_released_title' => ':player libérée',
    'player_released_message' => ':player a été libérée de ton effectif. Indemnité versée : :severance.',
    'player_released_message_free' => ':player a été libérée de ton effectif.',

    // Rescisión de mutuo acuerdo
    'mutual_termination_title' => 'Rupture à l\'amiable : :player',
    'mutual_termination_message' => 'Tu as rompu à l\'amiable le contrat de :player. Indemnité convenue : :amount.',

    // Plan de pago de indemnización a plazos
    'severance_plan_title' => 'Indemnité échelonnée : :player',
    'severance_plan_message' => 'Tu paieras l\'indemnité de :player en :months mensualités de :monthly (total :total avec intérêts).',
    'severance_plan_completed_title' => 'Indemnité soldée : :player',
    'severance_plan_completed_message' => 'Tu as fini de payer l\'indemnité de :player (total :total).',

    // Fichajes de emergencia
    'emergency_signing_title' => 'Renfort d\'urgence',
    'emergency_signing_message' => 'Ton effectif était à un niveau critique. :count joueuses libres ont été recrutées pour que tu puisses aligner une équipe : :players.',

    // Partido perdido por incomparecencia
    'match_forfeit_title' => 'Match perdu par forfait',
    'match_forfeit_message' => 'Ton équipe n\'a pas pu aligner le minimum de 7 joueuses. Le match a été enregistré comme une défaite 0-3.',

    // Reputation changes
    'reputation_change_title' => 'Réputation du club modifiée',
    'reputation_improved' => 'La réputation de ton club est montée à :tier. Sponsors, joueuses et supporters le remarquent.',
    'reputation_declined' => 'La réputation de ton club est descendue à :tier. Il est temps de reconstruire et de retrouver la gloire passée.',

    // Budget loan
    'budget_loan_taken_title' => 'Prêt budgétaire accordé',
    'budget_loan_taken_message' => 'Le club a obtenu un prêt de :amount. Le remboursement de :repayment sera déduit du budget de la saison prochaine.',
    'budget_loan_repaid_title' => 'Prêt budgétaire remboursé',
    'budget_loan_repaid_message' => 'Le prêt budgétaire a été remboursé (:repayment avec intérêts).',
    'budget_loan_repaid_with_debt' => 'Le remboursement du prêt de :repayment a dépassé l\'excédent disponible. Le déficit est reporté en dette.',

    // Stadium
    'stadium_supplementary_committed_title' => 'Travaux de tribunes lancés',
    'stadium_supplementary_committed_message' => ':capacity places provisoires ont été commandées. Elles seront prêtes pour le :completion.',
    'stadium_stand_expansion_committed_title' => 'Extension de tribune approuvée',
    'stadium_stand_expansion_committed_message' => 'Une extension de :capacity nouvelles places permanentes a été approuvée. Elle sera opérationnelle le :completion.',
    'stadium_rebuild_committed_title' => 'Rénovation du stade approuvée',
    'stadium_rebuild_committed_message' => 'La rénovation pour une capacité de :capacity places a commencé. Le nouveau stade ouvrira le :completion.',
    'stadium_supplementary_completed_title' => 'Tribunes provisoires prêtes',
    'stadium_supplementary_completed_message' => 'Les nouvelles tribunes sont opérationnelles. Capacité totale : :capacity places.',
    'stadium_stand_expansion_completed_title' => 'Nouvelle tribune inaugurée',
    'stadium_stand_expansion_completed_message' => 'La tribune agrandie est opérationnelle. Capacité totale : :capacity places.',
    'stadium_rebuild_completed_title' => 'Stade inauguré',
    'stadium_rebuild_completed_message' => 'Le nouveau stade a été inauguré avec une capacité de :capacity places.',
    'stadium_uefa_upgrade_committed_title' => 'Amélioration UEFA approuvée',
    'stadium_uefa_upgrade_committed_message' => 'La rénovation pour atteindre la Catégorie UEFA :capacity a commencé. La nouvelle catégorie sera effective le :completion.',
    'stadium_uefa_upgrade_completed_title' => 'Nouvelle Catégorie UEFA',
    'stadium_uefa_upgrade_completed_message' => 'Ton stade a atteint la Catégorie UEFA :capacity.',
    'stadium_loan_drawn_title' => 'Prêt du stade formalisé',
    'stadium_loan_drawn_message' => 'La banque a financé le projet à hauteur de :amount, à rembourser en :years annuités.',
    'stadium_loan_repaid_title' => 'Prêt du stade remboursé',
    'stadium_loan_repaid_message' => 'Le prêt de :amount a été entièrement remboursé.',
    // Solicitudes de sede (selección ↔ club, modo dual)
    'stadium_request_title' => '🏟️ :team veut jouer dans ton stade',
    'stadium_request_message' => ':team te demande de céder ton stade pour l\'amical contre :opponent le :date. Tu peux accepter ou refuser la demande depuis la page du stade.',
    'stadium_request_accepted_title' => 'Stade confirmé : :stadium',
    'stadium_request_accepted_message' => 'Le club a accepté de céder :stadium. L\'amical s\'y jouera.',
    'stadium_request_rejected_title' => 'Stade refusé : :stadium',
    'stadium_request_rejected_message' => 'Le club a refusé de céder :stadium. Motif : :excuse L\'amical se jouera sur terrain neutre.',
    'commercial_window_open_title' => 'Fenêtre commerciale ouverte',
    'commercial_window_open_message' => 'Jusqu\'au premier match de championnat, tu peux chercher des sponsors sur la page Commercial pour augmenter tes revenus et ton plafond salarial.',

    // Squad registration
    'squad_registration_required_title' => 'Enregistrement de l\'effectif requis',
    'squad_registration_required_message' => 'Tu as :count joueuses non enregistrées. Enregistre ton effectif avant le début de la saison — les joueuses non enregistrées ne pourront pas être convoquées.',
    'unenrolled_before_window_close_title' => 'Joueuses non enregistrées — fermeture du mercato de :window',
    'unenrolled_before_window_close_message' => 'Tu as :count joueuses non enregistrées. C\'est ta dernière journée pour les enregistrer avant la fermeture du mercato — sans numéro, elles ne pourront pas être convoquées.',

    // i18n-b review: missing injury types for AI-generated injuries
    'injury_ligament_damage' => 'lésion ligamentaire',
    'injury_knee_injury' => 'blessure au genou',
    'injury_unknown_injury' => 'une blessure',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'academy_jewel_title' => '💎 Pépite du centre de formation !',
    'academy_jewel_message' => ':player (:position), 16 ans, se distingue au centre de formation : son potentiel est élite. Garde un œil sur elle !',
    'sponsor_offers_arrived_title' => 'Il pleut des offres de sponsoring !',
    'sponsor_offers_arrived_message' => '{1} Une marque veut sponsoriser l\'équipe : passe par la page Commercial pour voir l\'offre.|[2,*] :count marques veulent sponsoriser l\'équipe : passe par la page Commercial pour voir les offres.',
    'cwc_qualified_title' => '🌍 En route pour la Coupe du monde des clubs !',
    'cwc_qualified_message' => 'Ton équipe fait partie des meilleurs clubs de sa confédération et s\'est qualifiée pour la Coupe du monde des clubs. 32 équipes, un seul trophée… va chercher la plus grande coupe de la planète, coach !',
];
