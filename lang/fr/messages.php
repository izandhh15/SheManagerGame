<?php

return [
    // Transfer messages
    'transfer_complete' => 'Transfert bouclé ! :player a rejoint ton effectif.',
    'transfer_agreed' => ':message Le transfert sera finalisé à l\'ouverture du mercato de :window.',
    'bid_exceeds_budget' => 'L\'offre dépasse ton budget transferts.',
    'player_listed' => ':player mise en vente. Les offres peuvent arriver après la prochaine journée.',
    'player_unlisted' => ':player retirée de la liste des transferts.',
    'cannot_sell_same_window' => 'Impossible de vendre :player — elle vient d\'être recrutée et ne peut pas encore être transférée.',
    'offer_rejected' => 'Offre :team_de refusée.',
    'cannot_reject_release_clause_offer' => 'Tu ne peux pas refuser cette offre — elle atteint la clause libératoire de :player, la vente est donc obligatoire.',
    'offer_accepted_sale' => ':player vendue :team_a pour :fee.',
    'offer_accepted_pre_contract' => 'Accord conclu ! :player signera à :team pour :fee à l\'ouverture du mercato de :window.',
    'offer_accepted_intra_window' => 'Accord conclu ! :player partira :team_a pour :fee après le prochain match.',

    // Free agent signing
    'free_agent_signed' => ':player a signé dans ton équipe comme joueuse libre !',
    'free_agent_agreed' => 'Accord conclu ! :player rejoindra le club comme joueuse libre après le prochain match.',
    'not_free_agent' => 'Cette joueuse n\'est pas libre.',
    'free_agent_reputation_too_low' => 'Cette joueuse n\'est pas intéressée pour signer dans un club de ton niveau de réputation.',
    'transfer_window_closed' => 'Le mercato est fermé.',
    'wage_budget_exceeded' => 'Recruter cette joueuse dépasserait ton budget salarial.',
    'signing_exceeds_salary_cap' => 'Recruter :player pour :wage/an porterait ta masse salariale à :total, au-dessus de ton plafond salarial de :cap. Libère :shortfall en vendant des joueuses d\'abord.',
    'salary_cap_locked' => 'Tu es au-dessus de ton plafond salarial. Vends des joueuses pour repasser sous le plafond avant de recruter ou de prolonger.',
    'pre_contract_exceeds_salary_cap' => 'Recruter :player pour :wage/an porterait ta masse salariale de la saison prochaine à :total, au-dessus de ton plafond salarial de :cap. Il te manque :shortfall.',

    // Bid/loan submission confirmations
    'bid_already_exists' => 'Tu as déjà une offre en cours pour cette joueuse.',
    'loan_request_submitted' => 'Ta demande de prêt pour :player a été envoyée. Tu recevras une réponse prochainement.',

    // Loan messages
    'loan_agreed' => ':message Le prêt débutera à l\'ouverture du mercato de :window.',
    'loan_in_complete' => ':message Le prêt est déjà actif.',
    'already_on_loan' => ':player est déjà en prêt.',
    'loan_search_started' => 'La recherche d\'un club pour :player a commencé. Tu seras notifiée quand un club sera trouvé.',
    'loan_search_active' => ':player a déjà une recherche de prêt active.',
    'loan_search_cancelled' => 'La recherche de prêt de :player a été annulée.',
    'loan_offer_accepted' => ':player prêtée :team_a.',
    'loan_offer_accepted_pre_window' => ':player sera prêtée :team_a à l\'ouverture du mercato de :window.',
    'loan_offer_agreed_intra_window' => ':player sera prêtée :team_a après le prochain match.',

    // Contract messages
    'renewal_agreed' => ':player a accepté une prolongation de :years ans à :wage/an (effective dès la saison prochaine).',
    'renewal_failed' => 'La prolongation n\'a pas pu être traitée.',
    'renewal_declined' => 'Tu as décidé de ne pas prolonger :player. Elle partira en fin de saison.',
    'renewal_reconsidered' => 'Tu as reconsidéré la prolongation de :player.',
    'cannot_renew' => 'Cette joueuse ne peut pas recevoir d\'offre de prolongation.',
    'renewal_invalid_offer' => 'L\'offre doit être supérieure à zéro.',

    // Pre-contract messages
    'pre_contract_accepted' => ':player a accepté ton offre de pré-contrat ! Elle rejoindra ton équipe en fin de saison.',
    'pre_contract_rejected' => ':player a refusé ton offre de pré-contrat. Essaie d\'améliorer les conditions salariales.',
    'pre_contract_not_available' => 'Les offres de pré-contrat sont disponibles uniquement entre janvier et mai.',
    'player_not_expiring' => 'Cette joueuse n\'est pas dans sa dernière année de contrat.',
    'pre_contract_submitted' => 'Offre de pré-contrat envoyée. La joueuse répondra dans les prochains jours.',
    'pre_contract_result_accepted' => ':player a accepté ton offre de pré-contrat !',
    'pre_contract_result_rejected' => ':player a refusé ton offre de pré-contrat.',

    // Scout messages
    'scout_search_started' => 'Le recruteur a lancé la recherche.',
    'scout_already_searching' => 'Tu as déjà une recherche active. Annule-la d\'abord ou attends les résultats.',
    'scout_search_cancelled' => 'Recherche du recruteur annulée.',
    'scout_search_deleted' => 'Recherche supprimée.',
    'scout_search_limit' => 'Tu as atteint la limite de recherches (maximum :max). Supprime une ancienne recherche pour en lancer une nouvelle.',

    // Shortlist messages
    'shortlist_added' => ':player ajoutée à ta liste de suivi.',
    'shortlist_removed' => ':player retirée de ta liste de suivi.',
    'shortlist_full' => 'Ta liste de suivi est pleine (maximum :max joueuses).',

    // Budget messages
    'budget_saved' => 'Répartition du budget enregistrée.',
    'budget_no_projections' => 'Aucune projection financière trouvée.',

    // Stadium / abonos
    'season_tickets_saved' => 'Prix des abonnements enregistrés.',
    'season_tickets_locked' => 'Les prix des abonnements sont déjà verrouillés pour cette saison.',

    // Season messages
    'budget_exceeds_surplus' => 'La répartition totale dépasse l\'excédent disponible.',
    'budget_minimum_tier' => 'Toutes les zones d\'infrastructure doivent être au moins au Niveau 1.',

    // Infrastructure upgrades
    'infrastructure_upgraded' => ':area améliorée au Niveau :tier.',
    'infrastructure_upgrade_invalid_area' => 'Zone d\'infrastructure non valide.',
    'infrastructure_upgrade_not_higher' => 'Le niveau cible doit être supérieur au niveau actuel.',
    'infrastructure_upgrade_max_tier' => 'Le niveau maximum est 4.',
    'infrastructure_upgrade_insufficient_budget' => 'Budget transferts insuffisant. L\'amélioration coûte :cost.',
    'investment_downgrade_not_lower' => 'Choisis un niveau inférieur au niveau actuel.',
    'investment_saved' => 'Plan enregistré.',
    'investment_locked_no_edit' => 'La saison est en cours — tu peux améliorer à tout moment, mais le plan ne peut plus être réaffecté librement.',
    'investment_downgrade_staged' => 'Réduction programmée — effective la saison prochaine.',
    'investment_downgrade_cleared' => 'Réduction programmée annulée.',

    // Onboarding
    'welcome_to_team' => 'Bienvenue :team_a ! Ta saison t\'attend.',

    // Season
    'season_not_complete' => 'Impossible de lancer une nouvelle saison — la saison en cours n\'est pas terminée.',

    // Academy
    'academy_player_promoted' => ':player a été promue en équipe première.',
    'academy_player_dismissed' => ':player a été renvoyée du centre de formation.',
    'academy_player_loaned' => ':player a été prêtée.',
    'academy_must_decide_21' => 'Les joueuses de 21 ans et plus seront promues automatiquement en équipe première.',

    // Reserve team (filial)
    'reserve_player_called_up' => ':player a été convoquée en équipe première.',
    'reserve_player_sent_back' => ':player est retournée en équipe réserve.',
    'reserve_player_call_up_blocked_full' => 'L\'effectif de l\'équipe première est complet. Libère un numéro avant de faire monter plus de joueuses.',
    'reserve_player_call_up_blocked' => 'Cette joueuse ne peut pas être convoquée.',
    'player_sent_down_to_reserve' => ':player a été envoyée en équipe réserve.',
    'send_down_not_allowed' => 'Cette joueuse ne peut pas être envoyée en équipe réserve.',
    'reserve_move_blocked_by_deal' => ':player a un transfert ou un pré-contrat convenu et ne peut pas changer d\'équipe avant sa finalisation.',
    'send_down_squad_too_small' => 'Impossible de l\'envoyer en réserve — l\'équipe première doit avoir au moins :min joueuses.',
    'send_down_position_minimum' => 'Impossible de l\'envoyer en réserve — l\'équipe première a besoin d\'au moins :min :group.',
    'reserve_player_promoted' => ':player est montée en équipe première.',

    // Player release messages
    'player_released' => ':player a été libérée. Indemnité versée : :severance.',
    'release_not_your_player' => 'Tu ne peux libérer que les joueuses de ta propre équipe.',
    'release_on_loan' => 'Impossible de libérer une joueuse en prêt.',
    'release_has_agreed_transfer' => 'Impossible de libérer une joueuse avec un transfert convenu.',
    'release_has_pre_contract' => 'Impossible de libérer une joueuse avec un pré-contrat signé.',
    'release_squad_too_small' => 'Impossible de libérer — ton effectif doit avoir au moins :min joueuses.',
    'release_position_minimum' => 'Impossible de libérer — tu as besoin d\'au moins :min :group.',

    // Rescisión de mutuo acuerdo
    'mutual_termination_completed' => 'Rupture à l\'amiable avec :player finalisée. Indemnité : :amount.',
    'severance_invalid_method' => 'Mode de paiement non valide.',
    'severance_loan_active' => 'Tu as déjà un prêt actif. Tu ne peux pas en demander un autre.',
    'severance_loan_unavailable' => 'Le prêt ne peut pas être demandé pour le moment.',

    // Squad-minimum guards on promote / demote / list / accept
    'promote_squad_too_small' => 'Impossible de faire monter — l\'équipe réserve doit avoir au moins :min joueuses.',
    'promote_position_minimum' => 'Impossible de faire monter — l\'équipe réserve a besoin d\'au moins :min :group.',
    'demote_squad_too_small' => 'Impossible de descendre en réserve — l\'équipe première doit avoir au moins :min joueuses.',
    'demote_position_minimum' => 'Impossible de descendre en réserve — l\'équipe première a besoin d\'au moins :min :group.',
    'list_for_sale_squad_too_small' => 'Impossible de mettre en vente — ton effectif doit avoir au moins :min joueuses.',
    'list_for_sale_position_minimum' => 'Impossible de mettre en vente — tu as besoin d\'au moins :min :group.',
    'list_for_loan_squad_too_small' => 'Impossible de prêter — ton effectif doit avoir au moins :min joueuses.',
    'list_for_loan_position_minimum' => 'Impossible de prêter — tu as besoin d\'au moins :min :group.',
    'accept_offer_squad_too_small' => 'Impossible d\'accepter l\'offre — ton effectif doit avoir au moins :min joueuses.',
    'accept_offer_position_minimum' => 'Impossible d\'accepter l\'offre — tu as besoin d\'au moins :min :group.',
    'accept_loan_squad_too_small' => 'Impossible d\'accepter le prêt — ton effectif doit avoir au moins :min joueuses.',
    'accept_loan_position_minimum' => 'Impossible d\'accepter le prêt — tu as besoin d\'au moins :min :group.',

    'cannot_loan_free_agent' => 'Impossible de prêter une joueuse libre. Recrute-la directement.',

    // Pending actions
    'action_required' => 'Il y a des actions en attente que tu dois résoudre avant de continuer.',
    'action_required_short' => 'Action requise',

    // Tactical presets
    'preset_saved' => 'Tactique enregistrée.',
    'preset_updated' => 'Tactique mise à jour.',
    'preset_deleted' => 'Tactique supprimée.',
    'preset_limit_reached' => 'Maximum de 3 tactiques enregistrées atteint.',

    // Game management
    'game_deleted' => 'La partie est en cours de suppression.',
    'game_limit_reached' => 'Tu as atteint le maximum de 5 parties. Supprimes-en une pour en créer une nouvelle.',
    'career_mode_requires_invite' => 'Club Manager et Pro Manager nécessitent une invitation. Joue la Coupe du monde gratuitement !',
    'tournament_mode_requires_access' => 'Le mode tournoi nécessite un accès. Contacte un administrateur pour commencer.',
    'invalid_pro_manager_team' => 'Choisis l\'un des clubs affichés — Pro Manager commence en Primera Federación.',
    'invalid_academy_club' => 'Le club du centre de formation sélectionné n\'est pas valide.',
    'club_has_no_filial' => 'Ce club n\'a pas d\'équipe réserve disponible.',
    'team_has_no_competition_link' => 'Cette équipe n’est liée à aucune compétition : la partie ne peut pas être créée.',
    'team_squad_too_small' => 'Cette équipe ne compte que :count joueuses dans son effectif (minimum :minimum) : la partie ne peut pas être créée.',
    'cannot_apply_to_own_club' => 'Tu ne peux pas postuler dans ton propre club.',

    // Pre-match confirmation
    'pre_match_title' => 'Avant-match',
    'pre_match_no_lineup' => 'Tu n\'as pas de composition configurée.',
    'pre_match_incomplete' => 'Ta composition compte moins de 11 joueuses.',
    'pre_match_unavailable_injured' => 'Tu as une joueuse blessée dans ta composition.',
    'pre_match_unavailable_suspended' => 'Tu as une joueuse suspendue dans ta composition.',
    'pre_match_unavailable_multiple' => 'Tu as des joueuses indisponibles dans ta composition.',
    'pre_match_auto_explanation' => 'Si tu ne changes rien, ton staff technique choisira la meilleure composition parmi les joueuses disponibles.',
    'pre_match_warning_title' => 'Ta composition a besoin d\'attention',
    'pre_match_play' => 'Jouer le match',
    'pre_match_continue' => 'Continuer',
    'pre_match_edit_lineup' => 'Modifier la composition',
    'pre_match_reason_injured' => 'blessée',
    'pre_match_reason_suspended' => 'suspendue',
    'pre_match_starting_xi' => 'Onze de départ',
    'pre_match_no_lineup_set' => 'Composition non configurée',
    'pre_match_auto_lineup' => 'Laisser le staff technique modifier la composition automatiquement quand des joueuses sont indisponibles.',
    'pre_match_auto_select_done' => 'La meilleure composition parmi les joueuses disponibles a été sélectionnée automatiquement.',

    // Matchday advance
    'advance_failed' => 'Erreur lors de l\'avancement de la journée. Réessaie.',

    // Fast mode
    'fast_mode_enabled' => 'Mode rapide activé. Ton entraîneur adjoint dirigera l\'équipe.',
    'fast_mode_disabled' => 'Mode rapide désactivé. Tu reprends le contrôle.',
    'fast_mode_action_required' => 'Une action nécessite ton attention. Quitte le mode rapide pour la résoudre.',
    'fast_mode_blocked_live_match' => 'Termine le match en cours avant d\'activer le mode rapide.',
    'fast_mode_blocked_tournament' => 'Le mode rapide n\'est pas disponible en mode tournoi.',
    'fast_mode_advance_failed_retry' => 'Impossible de simuler la journée. Réessaie.',

    // Budget loan messages
    'budget_loan_approved' => 'Prêt de :amount approuvé et ajouté à ton budget transferts.',
    'loan_not_available' => 'Un prêt budgétaire n\'est pas disponible pour le moment.',
    'loan_below_minimum' => 'Le montant du prêt est inférieur au minimum.',
    'loan_exceeds_maximum' => 'Le montant du prêt dépasse le maximum autorisé.',

    'stadium_supplementary_committed' => 'Travaux lancés : :seats places provisoires seront prêtes dans 30 jours.',
    'stadium_stand_expansion_committed' => 'Extension de tribune approuvée : :seats nouvelles places permanentes seront prêtes la saison prochaine.',
    'stadium_rebuild_committed' => 'Rénovation du stade approuvée. Nouvelle capacité cible : :capacity.',
    'stadium_active_project_exists' => 'Tu as déjà un projet en cours. Attends sa fin avant d\'en lancer un autre.',
    'stadium_supplementary_too_few_seats' => 'Tu dois ajouter au moins une place provisoire.',
    'stadium_supplementary_exceeds_cap' => 'Dépasse la limite de tribunes provisoires autorisée.',
    'stadium_stand_expansion_too_few_seats' => 'L\'extension de tribune n\'atteint pas le minimum de places exigé.',
    'stadium_stand_expansion_exceeds_cap' => 'L\'extension de tribune dépasse la taille maximale autorisée.',
    'stadium_rebuild_reputation_too_low' => 'Ta réputation ne permet pas encore une rénovation complète du stade.',
    'stadium_rebuild_must_be_larger' => 'La capacité cible doit être supérieure à l\'actuelle.',
    'stadium_rebuild_exceeds_max_capacity' => 'La capacité cible dépasse le maximum que ta réputation et tes revenus permettent de financer.',
    'stadium_invalid_financing' => 'Financement non valide.',
    'stadium_insufficient_budget' => 'Tu n\'as pas assez de budget pour payer le projet comptant.',
    'stadium_loan_exceeds_cap' => 'Le prêt demandé dépasse le plafond autorisé par la banque.',
    'stadium_uefa_upgrade_committed' => 'Amélioration UEFA lancée : le stade atteindra la Catégorie :level la saison prochaine.',
    'stadium_uefa_already_max' => 'Ton stade est déjà à la catégorie UEFA maximale.',
    'stadium_uefa_capacity_floor' => 'La capacité actuelle n\'atteint pas le minimum exigé par la prochaine catégorie UEFA.',
    'stadium_uefa_no_base_level' => 'Ton stade n\'a pas de catégorie UEFA attribuée. Augmente d\'abord la capacité.',

    'naming_rights_accepted' => 'Accord de naming signé avec :sponsor. Le stade a été renommé.',
    'stadium_renamed' => 'Stade renommé en :name.',
    'naming_rights_window_closed' => 'L\'identité du stade ne peut changer qu\'en pré-saison, jusqu\'au premier match de championnat.',
    'naming_rights_deal_active' => 'Un accord de naming est déjà actif : le sponsor possède le nom du stade jusqu\'à son expiration.',
    'naming_rights_offer_unavailable' => 'Cette offre de naming n\'est plus disponible.',
    'stadium_already_renamed' => 'Le stade a déjà été renommé cette saison.',
    'naming_rights_search_complete' => '{0}L\'agence n\'a trouvé aucun nouveau sponsor.|{1}L\'agence a apporté :count offre de sponsoring.|[2,*]L\'agence a apporté :count offres de sponsoring.',
    'naming_rights_search_cooldown' => 'Ton agence commerciale est encore en train de sonder le marché. Attends quelques jours avant de chercher à nouveau.',
    'naming_rights_search_unaffordable' => 'Tu n\'as pas le budget pour la commission de l\'agence commerciale.',
    'naming_rights_board_full' => 'Tu as déjà le maximum d\'offres sur la table. Acceptes-en une ou refuse-les avant d\'en chercher d\'autres.',

    // i18n-b review: poach youth player + generic error flashes
    'poach_not_enough_budget' => 'Budget insuffisant (:fee€ nécessaires).',
    'poach_player_gone' => ':name n\'est plus disponible.',
    'poach_refused' => ':team refuse de négocier pour :name. L\'approche a coûté :cost€ en recrutement.',
    'poach_success' => ':name rejoint ton centre de formation !',
    'season_summary_load_error' => 'Impossible de charger le résumé de la saison. Réessaie.',
    'new_season_start_error' => 'Impossible de lancer la nouvelle saison. Réessaie.',
    'lineup_confirmed' => 'Composition confirmée ! Clique sur Continuer pour jouer le match.',

    // i18n-b review: poach youth player social buzz
    'poach_buzz' => '🚨 :team \'vole\' la pépite :player (:potential de potentiel) à un centre rival.',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'naming_rights_offer_rejected' => 'Offre de :sponsor écartée. Ils ne le sauront jamais.',
    'sponsor_deal_accepted' => 'Marché conclu ! :sponsor sponsorisera :slot. À la caisse.',
    'sponsor_deal_rejected' => 'Offre de :sponsor écartée. Passons à autre chose.',
    'sponsor_offer_unavailable' => 'Cette offre de sponsoring n\'est plus disponible.',
    'sponsor_deal_active' => 'Tu as déjà un sponsor actif sur cet emplacement. Attends l\'expiration du contrat.',

