<?php

return [
    // Page title
    'squad' => 'Effectif',
    'first_team' => 'Équipe Première',
    'development' => 'Développement',
    'stats' => 'Statistiques',

    // Position groups
    'goalkeepers' => 'Gardiennes',
    'defenders' => 'Défenseures',
    'midfielders' => 'Milieux',
    'forwards' => 'Attaquantes',
    'goalkeepers_short' => 'GB',
    'defenders_short' => 'DÉF',
    'midfielders_short' => 'MIL',
    'forwards_short' => 'ATT',

    // Columns
    'years_abbr' => 'ans',
    'fitness' => 'ÉN',
    'morale' => 'MOR',
    'overall' => 'Note',
    'overall_short' => 'NOT',
    'attack_short' => 'ATT',
    'defense_short' => 'DÉF',
    'attack_xg_label' => 'Attaque xG',
    'defense_xg_label' => 'Défense xG',

    // Status labels
    'on_loan' => 'Prêtée',
    'loaned_from' => 'Prêtée par',
    'loaned_to' => 'Prêtée à',
    'leaving_free' => 'Part (Libre)',
    'renewed' => 'Prolongé',
    'sale_agreed' => 'Vente Actée',
    'retiring' => 'Retraite',
    'listed' => 'En Vente',
    'list_for_sale' => 'Mettre en Vente',
    'unlist_from_sale' => 'Retirer de la Vente',
    'loan_out' => 'Prêter',
    'release_player' => 'Libérer',
    'release_confirm_title' => 'Libérer la Joueuse',
    'release_confirm_message' => 'Es-tu sûre de vouloir libérer :player ? Cette action est irréversible.',
    'release_severance_label' => 'Coût de l\'indemnité',
    'release_remaining_contract' => 'Contrat restant',
    'release_years_remaining' => ':years an(s)',
    'release_confirm_button' => 'Confirmer la Libération',
    'mutual_terminate' => 'Résilier d\'un commun accord',
    'loan_searching' => 'Recherche de destination de prêt',
    'contract_expiring' => 'Contrat bientôt expiré',

    // Summary
    'wage_bill' => 'Masse Salariale',
    'per_year' => '/an',
    'avg_fitness' => 'Énergie Moyenne',
    'avg_morale' => 'Moral Moyen',
    'low' => 'bas',

    // Contract management
    'free_transfer' => 'Libre',
    'let_go' => 'Laisser Partir',
    'pre_contract_signed' => 'Pré-contrat signé',
    'new_wage_from_next' => 'Nouveau salaire à partir de la saison prochaine',
    'has_pre_contract_offers' => 'Elle a des offres de pré-contrat !',
    'renew' => 'Prolonger',
    'expires_in_days' => '{0}Expire aujourd\'hui|{1}Expire dans :count jour|[2,*]Expire dans :count jours',

    // Lineup validation
    'formation_position_mismatch' => 'La formation :formation requiert :required :position, mais tu as sélectionné :actual.',
    'player_not_available' => 'Une ou plusieurs joueuses sélectionnées ne sont pas disponibles.',

    // Lineup
    'formation' => 'Formation',
    'mentality' => 'Mentalité',
    'auto_select' => 'Sélection Auto',
    'opponent' => 'Adversaire',
    'need' => 'il te faut',

    // Compatibility
    'natural' => 'Naturel',
    'very_good' => 'Très Bien',
    'good' => 'Bien',
    'okay' => 'Correct',
    'poor' => 'Mauvais',
    'unsuitable' => 'Inadapté',


    // Lineup editor
    'pitch' => 'Terrain',

    // Opponent scout
    'injured' => 'blessées',
    'suspended' => 'suspendues',

    // Coach assistant
    'coach_recommendations' => 'Recommandations',
    'coach_no_tips' => 'Aucune recommandation spéciale pour ce match.',
    'coach_defensive_recommended' => 'Adversaire plus fort. Une mentalité défensive réduit ses buts attendus de 30 %.',
    'coach_attacking_recommended' => 'Tu as l\'avantage. Une mentalité offensive peut maximiser tes buts.',
    'coach_risky_formation' => 'Ta formation offensive contre un adversaire supérieur leur donnera plus d\'occasions. Envisage une formation plus défensive.',
    'coach_home_advantage' => 'Vous jouez à domicile (+0,15 but attendu).',
    'coach_critical_fitness' => ':names avec une énergie critique (<50). Risque de blessure 2x plus élevé. Envisage de les faire tourner.',
    'coach_low_fitness' => ':count joueuse(s) avec une énergie faible (<70). Elles sont moins performantes et ont un plus grand risque de blessure.',
    'coach_low_morale' => ':count joueuse(s) avec un moral bas. Elles seront moins performantes pendant le match.',
    'coach_bench_frustration' => ':count joueuse(s) de qualité sans jouer et perdant le moral. Fais tourner pour les garder contentes.',
    'coach_opponent_expected_label' => 'Prévu',
    'coach_opponent_defensive_setup' => 'Adversaire prévu en :formation (:mentality). Envisage une approche offensive pour les débloquer.',
    'coach_opponent_attacking_setup' => 'Adversaire prévu en :formation (:mentality). Ils laisseront des espaces — une défense solide peut en profiter.',
    'coach_opponent_deep_block' => 'Adversaire avec 5 défenseures. Largeur et patience seront la clé.',
    'coach_out_of_position' => ':names hors de position. Elles seront moins performantes pendant le match.',
    'mentality_defensive' => 'Défensive',
    'mentality_balanced' => 'Équilibrée',
    'mentality_attacking' => 'Offensive',

    // Unavailability reasons
    'suspended_matches' => 'Suspendue (:count match)|Suspendue (:count matchs)',
    'injured_generic' => 'Blessée',
    'injury_return_date' => 'absente jusqu\'au :date',

    // Injury types
    'injury_muscle_fatigue' => 'Fatigue musculaire',
    'injury_muscle_strain' => 'Élongation musculaire',
    'injury_calf_strain' => 'Élongation du mollet',
    'injury_ankle_sprain' => 'Entorse de la cheville',
    'injury_groin_strain' => 'Élongation de l\'adducteur',
    'injury_hamstring_tear' => 'Déchirure des ischio-jambiers',
    'injury_knee_contusion' => 'Contusion du genou',
    'injury_metatarsal_fracture' => 'Fracture du métatarse',
    'injury_acl_tear' => 'Rupture du ligament croisé',
    'injury_achilles_rupture' => 'Rupture du tendon d\'Achille',

    // Development page
    'ability' => 'Niveau',
    'playing_time' => 'Minutes',
    'high_potential' => 'Fort Potentiel',
    'growing' => 'En Progression',
    'declining' => 'En Déclin',
    'peak' => 'À son Pic',
    'all' => 'Toutes',
    'no_players_match_filter' => 'Aucune joueuse ne correspond au filtre sélectionné.',
    'pot' => 'POT',
    'apps' => 'M',
    'projection' => 'Projection',
    'potential' => 'Potentiel',
    'potential_range' => 'Fourchette de Potentiel',
    'starter_bonus' => 'bonus de titulaire',
    'needs_appearances' => 'Nécessite :count+ matchs pour le bonus de titulaire',
    'qualifies_starter_bonus' => 'Qualifiée pour le bonus de titulaire (+50 % de développement)',

    // Stats page
    'goals' => 'B',
    'assists' => 'PD',
    'goal_contributions' => 'B+PD',
    'goals_per_game' => 'B/M',
    'own_goals' => 'CSC',
    'yellow_cards' => 'CJ',
    'red_cards' => 'CR',
    'clean_sheets' => 'CS',
    'appearances' => 'Matchs',
    'bookings' => 'Avertissements',
    'click_to_sort' => 'Clique sur les en-têtes de colonne pour trier',

    // Stats highlights
    'top_in_squad' => 'Meilleure de l\'effectif',

    // Legend labels
    'legend_apps' => 'Matchs',
    'legend_goals' => 'Buts',
    'legend_assists' => 'Passes décisives',
    'legend_contributions' => 'Contributions aux Buts',
    'legend_own_goals' => 'Buts Contre Son Camp',
    'legend_mvp' => 'Titres de MVP du match',
    'legend_clean_sheets' => 'Matchs Sans Encaisser (GB uniquement)',

    // Squad number
    'assign_number' => 'Attribuer un numéro',
    'number_taken' => 'Ce numéro est déjà attribué',
    'number_updated' => 'Numéro mis à jour',
    'number_invalid' => 'Le numéro doit être entre 1 et 99',

    // Player detail modal
    'abilities' => 'Capacités',
    'overall_full' => 'Note',
    'fitness_full' => 'Énergie',
    'morale_full' => 'Moral',
    'season_stats' => 'Statistiques de Saison',
    'clean_sheets_full' => 'Matchs Sans Encaisser',
    'goals_conceded_full' => 'Buts Encaissés',
    'discovered' => 'Découverte',
    'origin' => 'Provenance',
    'joined' => 'Arrivée',
    'origin_academy' => 'Équipe réserve',
    'origin_free_agent' => 'Joueuse libre',
    'precontract_banner_title' => 'Pré-contrat signé',
    'precontract_banner_body' => 'Elle n\'est pas encore dans ton effectif — elle arrivera en début de saison :year en transfert libre.',
    'career_history' => 'Carrière',
    'no_career_history' => 'Aucune saison terminée pour le moment.',

    // Academy
    'academy' => 'Centre de Formation',
    'promote_to_first_team' => 'Promouvoir en Équipe Première',
    'academy_tier' => 'Niveau du Centre de Formation',
    'academy_players' => 'Joueuses',
    'no_academy_prospects' => 'Aucune jeune disponible.',
    'academy_explanation' => 'Les nouvelles jeunes arrivent en début de chaque saison selon ton investissement dans le centre de formation.',
    'academy_dismiss' => 'Licencier',
    'academy_dismiss_confirm' => 'Es-tu sûre ? La joueuse sera licenciée de façon permanente.',
    'academy_dismiss_desc' => 'La joueuse est licenciée du club de façon permanente.',
    'academy_loan_out' => 'Prêter',
    'academy_loan_desc' => 'La joueuse part en prêt avec un développement accéléré (1,5x) et revient en fin de saison.',
    'academy_promote' => 'Promouvoir',
    'academy_promote_desc' => 'La joueuse intègre l\'équipe première avec un contrat professionnel.',
    'academy_on_loan' => 'Prêtée',
    'academy_seasons' => ':count saison|:count saisons',
    // Academy help text
    'academy_help_toggle' => 'Comment fonctionne le centre de formation ?',
    'academy_help_development' => 'Le centre de formation fonctionne comme ton équipe B, générant des joueuses calibrées au niveau de ton effectif. Les jeunes se développent au fil de la saison et peuvent être promues en équipe première quand elles sont prêtes.',
    'academy_help_actions_title' => 'Actions disponibles',
    'academy_help_promote' => 'Promouvoir — intègre définitivement l\'équipe première avec un contrat professionnel',
    'academy_help_loan' => 'Prêter — se développe plus vite en prêt et revient en fin de saison',
    'academy_help_dismiss' => 'Licencier — quitte le club de façon permanente',
    'academy_help_age_rule' => 'Les joueuses qui fêtent leurs 21 ans seront automatiquement promues en équipe première en début de saison.',

    'academy_tier_0' => 'Centre de Formation Minimal',
    'academy_tier_1' => 'Centre de Formation de Base',
    'academy_tier_2' => 'Bon Centre de Formation',
    'academy_tier_3' => 'Centre de Formation d\'Élite',
    'academy_tier_4' => 'Centre de Formation de Classe Mondiale',
    'academy_tier_unknown' => 'Inconnu',

    // Reserve team (filial)
    'reserve_team' => 'Équipe Réserve',
    'reserve_squad' => 'Effectif de l\'équipe réserve',
    'no_reserve_players' => 'Aucune joueuse dans l\'équipe réserve.',
    'call_up' => 'Promouvoir',
    'call_up_to_first_team' => 'Promouvoir en équipe première',
    'send_back' => 'Renvoyer',
    'send_back_to_reserve' => 'Renvoyer dans l\'équipe réserve',
    'send_down_to_reserve' => 'Renvoyer dans l\'équipe réserve',
    'send_down_to_reserve_confirm' => 'Renvoyer cette joueuse de moins de 23 ans dans l\'équipe réserve ?',
    'called_up_indicator' => 'En équipe première',
    'homegrown_indicator' => 'Formée au club',
    'actions' => 'Actions',
    'reserve_help_toggle' => 'Comment fonctionne l\'équipe réserve ?',
    'reserve_help_development' => 'Ton équipe réserve est le centre de formation officiel — les joueuses appartiennent à l\'équipe réserve, pas à l\'équipe première. Chaque saison, de nouvelles jeunes arrivent selon ton investissement dans le centre de formation, et se développent avec le reste de l\'équipe réserve.',
    'reserve_help_age_rule' => 'Les joueuses qui fêtent leurs 24 ans passent automatiquement en équipe première à la fin de la saison. L\'équipe première peut promouvoir des joueuses de l\'équipe réserve à tout moment.',
    'reserve_help_actions_title' => 'Actions disponibles',
    'reserve_help_call_up' => 'Promouvoir - la joueuse intègre l\'équipe première en prêt et peut disputer des matchs avec l\'équipe première',
    'reserve_help_send_back' => 'Renvoyer - renvoie la joueuse promue dans l\'équipe réserve',

    // Lineup help text
    'lineup_help_toggle' => 'Comment fonctionne la composition ?',
    'lineup_help_intro' => 'Choisis 11 joueuses pour chaque match. Ta formation, l\'énergie et la compatibilité positionnelle affectent le rendement.',
    'lineup_help_formation_title' => 'Formation et Mentalité',
    'lineup_help_formation_desc' => 'La formation détermine quelles positions sont disponibles sur le terrain. Les joueuses sont meilleures à leur position naturelle.',
    'lineup_help_compatibility_natural' => 'Naturel — la joueuse est à sa meilleure position, rendement complet.',
    'lineup_help_compatibility_good' => 'Très Bien — joue sans pénalité. Bien — pénalité de 25 % pendant le match.',
    'lineup_help_compatibility_poor' => 'Mauvais / Inadapté — pénalité de 25 %. Évite-le si possible.',
    'lineup_help_mentality_desc' => 'La mentalité affecte à quel point ton équipe joue offensif ou défensif.',
    'lineup_help_condition_title' => 'Énergie et Moral',
    'lineup_help_condition_desc' => 'Les joueuses avec une énergie ou un moral bas sont moins performantes. Fais tourner l\'effectif pour garder tout le monde fraîche.',
    'lineup_help_fitness' => 'L\'énergie diminue pendant chaque match et se récupère entre les journées. Les joueuses commencent les matchs avec leur niveau d\'énergie actuel — gère les rotations pour les garder fraîches.',
    'lineup_help_morale' => 'Le moral est affecté par les résultats, les minutes jouées et la situation contractuelle.',
    'lineup_help_auto' => 'Utilise « Sélection Auto » pour que le système choisisse le meilleur XI disponible pour ta formation.',

    // Squad selection (tournament onboarding)
    'squad_selection_title' => 'Sélectionne ta convocation',
    'squad_selection_subtitle' => 'Choisis 26 joueuses pour le tournoi',
    'confirm_squad' => 'Confirmer',
    'squad_confirmed' => 'Convocation confirmée !',
    'invalid_selection' => 'Sélection invalide. Vérifie les joueuses sélectionnées.',
    'download_squad' => 'Télécharger la convocation',
    'squad_list' => 'Liste des convoquées',
    'called_up_badge' => 'Convoquée',

    // Radar chart
    'radar_gk' => 'Gardien de but',
    'radar_def' => 'Défense',
    'radar_mid' => 'Milieu de terrain',
    'radar_att' => 'Attaque',
    'radar_fit' => 'Énergie',
    'radar_mor' => 'Moral',
    'radar_overall' => 'Note',

    // Registration
    'not_registered' => 'Non inscrite',
    'too_many_first_team' => 'Maximum 25 inscriptions en équipe première (numéros 1-25).',
    'drag_to_assign' => 'Glisse une joueuse ici pour l\'attribuer',

    // Grid positioning
    'drag_or_tap' => 'Touche une case ou glisse la joueuse',
    'select_player_for_slot' => 'Sélectionne une joueuse de la liste',

    // Squad dashboard KPIs
    'squad_size' => 'Effectif',
    'avg_age' => 'Âge Moyen',
    'condition' => 'État',
    'squad_value' => 'Valeur Effectif',

    // View modes
    'tactical' => 'Tactique',
    'planning' => 'Planification',
    'numbers' => 'Numéros',

    // Table headers
    'mvp' => 'MVP',
    'cards' => 'Cartons',
    'avg_ovr' => 'Note Moy.',

    // Filters
    'available' => 'Disponibles',
    'unavailable' => 'Indisponibles',
    'clear_filters' => 'Effacer les filtres',

    // Sidebar
    'squad_analysis' => 'Analyse Effectif',
    'alerts' => 'Alertes',
    'position_depth' => 'Profondeur Positionnelle',
    'age_profile' => 'Profil d\'Âge',
    'contract_watch' => 'Contrats',
    'expiring_this_season' => 'Expirent cette saison',
    'no_contract_issues' => 'Aucun contrat en suspens',
    'highest_earners' => 'Plus gros salaires',

    // Tooltips
    'tooltip_fitness' => 'Énergie moyenne — détermine l\'énergie initiale dans les matchs et affecte le rendement',
    'tooltip_morale' => 'Moral moyen — affecte la motivation et la régularité',
    'tooltip_avg_overall' => 'Note moyenne de l\'effectif',

    // Alerts
    'alert_many_injured' => ':count joueuses blessées — envisage de faire tourner les titulaires',
    'alert_low_morale' => ':count joueuses avec un moral bas',
    'alert_low_fitness' => ':count joueuses avec une énergie faible',
    'alert_thin_position' => 'Seulement :count joueuse(s) en :position — couverture insuffisante',
    'alert_no_cover' => 'Aucune couverture en :position',
    'alert_no_natural_cover' => 'Aucune :position naturelle — couverture partielle disponible',
    'alert_window_closing' => 'Le mercato ferme le :date',

    // Number grid
    'number_grid' => 'Numéros',
    'assigned' => 'Attribué',
    'available_number' => 'Disponible',

    // Column headers (new design)
    'player' => 'Joueuse',
    'pos' => 'Pos',
    'players_count' => 'joueuses',
    'dev_status_label' => 'Statut',

    // Morale labels
    'morale_ecstatic' => 'Euphorique',
    'morale_happy' => 'Heureuse',
    'morale_content' => 'Sereine',
    'morale_frustrated' => 'Frustrée',
    'morale_unhappy' => 'Mécontente',

    // Lineup tabs & labels
    'tactics' => 'Tactique',
    'defensive_line' => 'Ligne Défensive',
    'unsaved_changes' => 'Modifications non enregistrées',

    // Lineup redesign
    'opponent_goal' => 'But Adverse',
    'available_players' => 'Joueuses Disponibles',
    'substitutes' => 'Remplaçantes',
    'lineup_overview' => 'Aperçu de la Composition',

    // Tactical presets
    'presets' => 'Sauvegardées',
    'save_preset' => 'Sauvegarder la tactique',
    'preset_name' => 'Nom',
    'preset_name_placeholder' => 'Ex : Titulaires, Coupe, Remplaçantes...',
    'preset_apply_now' => 'Utiliser cette tactique lors du prochain match',
    'save_and_confirm' => 'Sauvegarder et confirmer',
    'preset_delete_confirm' => 'Supprimer cette tactique sauvegardée ?',
    'preset_overwrite_toggle' => 'Écraser la tactique',
    'preset_replace_required_hint' => 'Tu as déjà trois tactiques sauvegardées. Choisis laquelle remplacer par la composition actuelle.',

    // Dorsales
    'number' => 'Numéro',

    // Inscripción de plantilla
    'registration' => 'Inscription',
    'registration_title' => 'Inscription de l\'Effectif',
    'registration_subtitle' => 'Attribue les numéros pour la saison',
    'first_team_slots' => 'Équipe Première (1-25)',
    'academy_slots' => 'Centre de Formation (26-99)',
    'unregistered_players' => 'Non inscrites',
    'empty_slot' => 'Vide',
    'save_registration' => 'Enregistrer',
    'registration_saved' => 'Inscription enregistrée',
    'registered_count' => ':count inscrites',
    'academy_age_limit' => 'Seules les joueuses marquées Moins de 23 ans peuvent s\'inscrire avec un numéro du centre de formation (26-99)',
    'registration_rules_title' => 'Règles d\'Inscription',
    'registration_rule_first_team' => 'Les joueuses de l\'équipe première portent les numéros 1 à 25.',
    'registration_rule_academy' => 'Les numéros du centre de formation (26-99) sont réservés aux joueuses marquées Moins de 23 ans (moins de 24 ans au 1er janvier).',
    'registration_rule_u23_badge' => 'Les joueuses marquées Moins de 23 ans sont éligibles pour un numéro du centre de formation toute la saison, même si elles fêtent leurs 24 ans en cours de saison — l\'éligibilité est fixée selon leur âge au 1er janvier.',
    'registration_rule_unregistered' => 'Les joueuses non inscrites ne peuvent pas être convoquées pour les matchs.',
    'registration_readonly' => 'Tu peux inscrire des joueuses et modifier les numéros uniquement pendant les mercatos.',
    'u23_badge_label' => 'Moins de 23 ans',
    'u23_badge_tooltip' => 'Éligible pour un numéro du centre de formation — moins de 24 ans au 1er janvier de la saison.',

    // i18n-b review: lineup validation errors + missing injury types
    'lineup_must_select_11' => 'Tu dois sélectionner exactement 11 joueuses.',
    'lineup_duplicate_players' => 'Joueuses en double détectées.',
    'lineup_invalid_slot' => 'Attribution de poste invalide.',
    'lineup_slot_player_not_in_lineup' => 'Poste attribué à une joueuse qui n\'est pas dans la composition.',
    'injury_ligament_damage' => 'Lésion ligamentaire',
    'injury_knee_injury' => 'Blessure au genou',

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'academy_jewel' => 'Pépite',
    'academy_jewel_tooltip' => 'Pépite du centre de formation : une joueuse de 16 ans au potentiel élite.',
];
