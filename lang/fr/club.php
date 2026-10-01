<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finances',
        'investment' => 'Employés',
        'stadium' => 'Stade',
        'commercial' => 'Commercial',
        'reputation' => 'Réputation',
    ],

    'commercial' => [
        'title' => 'Sponsors commerciaux',
        'intro' => 'Cherchez des sponsors pour générer des revenus récurrents qui renforcent le budget du club.',
        'naming_rights_title' => 'Naming du stade',
        'seek_explainer' => 'Engagez une agence pour sonder des sponsors. Chaque recherche coûte :fee et vous devez attendre :days jours entre deux recherches.',
        'seek_button' => 'Chercher des sponsors (:fee)',
        'seek_cooldown' => '{1} Vous pourrez relancer une recherche dans :days jour.|[2,*] Vous pourrez relancer une recherche dans :days jours.',
        'seek_unaffordable' => 'Vous n\'avez pas le budget pour la commission de l\'agence (:fee).',
    ],

    'stadium' => [
        'home_ground' => 'Terrain',
        'stadium_name' => 'Stade',
        'capacity' => 'Capacité',
        'uefa_category' => 'Niveau UEFA',
        'uefa_category_short' => 'UEFA',
        'uefa_category_tooltip' => 'L\'UEFA classe les stades en quatre catégories (1 à 4). Monter de catégorie nécessite de rénover les installations (éclairage, vestiaires, salle de presse, loges) et que la capacité dépasse le minimum de la catégorie suivante.',

        'fan_base' => 'Supporters',
        'fan_base_help' => 'La fidélité augmente avec les titres et les bonnes campagnes et baisse après des saisons décevantes. Avec la réputation, elle détermine le taux de remplissage du stade les jours de match.',
        'fan_base_trend' => 'Tendance',
        'current_loyalty' => 'Soutien des supporters',

        'last_attendance' => 'Dernier match à domicile',
        'fill_rate' => 'Taux de remplissage',
        'no_home_match_yet' => 'Aucun match à domicile n\'a encore été joué.',

        'no_finances_yet' => 'Les finances de la saison apparaîtront quand les projections seront générées.',

        'stadium_revenue' => [
            'title' => 'Revenus du stade',
            'season_tickets' => 'Abonnements',
            'matchday' => 'Billetterie',
        ],

        'upgrades' => [
            'title' => 'Agrandissement et rénovation',
            'base_capacity' => 'Capacité de base',
            'supplementary' => 'Tribunes supplémentaires',
            'total' => 'Capacité totale',
            'seats' => 'places',
            'seats_total' => 'places au total',
            'seats_to_add' => 'Places à ajouter',
            'target_capacity' => 'Capacité visée',
            'total_cost' => 'Coût total',
            'completion_date' => 'Date d\'achèvement',
            'financing' => 'Financement',
            'financing_cash' => 'Paiement comptant',
            'financing_loan' => 'Prêt bancaire',
            'financing_cash_hint' => 'Sera déduit du budget disponible à la confirmation.',
            'financing_loan_hint' => 'Plafond de la banque : :cap. Remboursement en 10 échéances annuelles (capital constant + intérêts sur le solde).',

            'project_supplementary' => 'Tribunes supplémentaires',
            'project_stand_expansion' => 'Agrandissement d\'une tribune',
            'project_rebuild' => 'Rénovation du stade',
            'project_uefa_upgrade' => 'Montée UEFA',
            'ready_on' => 'Prêt pour le :date',
            'ready_in_season' => 'Disponible pour la saison :season',
            'loan_remaining' => 'Reste du prêt : :amount',

            'tier_label' => 'Niveau :n',
            'from_total' => 'Depuis :total',
            'per_seat_inline' => ':cost / place',
            'time_days_inline' => ':days jours',
            'time_months_inline' => ':count mois|:count mois',
            'status_available' => 'Disponible',
            'status_locked' => 'Verrouillé',
            'status_in_progress' => 'En travaux',
            'cta_planificar' => 'Planifier →',
            'unlock_with_revenue' => 'Débloqué avec :revenue de revenus annuels',
            'unlock_with_reputation' => 'Débloqué en catégorie :tier',
            'unlock_progress_label' => 'Revenus actuels : :current',

            'cta_supplementary_full_short' => 'Tribunes supplémentaires au maximum. Rénovez le stade pour libérer de l\'espace.',
            'cta_locked_no_budget' => 'Débloqué avec :cost. Budget disponible : :budget.',

            'budget_caps_slider' => 'Le budget disponible (:budget) limite le lot — sans lui vous pourriez atteindre :natural places.',
            'financing_cash_hint_budget' => 'Sera déduit du budget disponible (:budget) à la confirmation.',

            'cta_supplementary_label' => 'Agrandissement',
            'cta_supplementary_title' => 'Ajouter des tribunes supplémentaires',

            'cta_stand_expansion_label' => 'Agrandissement',
            'cta_stand_expansion_title' => 'Agrandir une tribune',

            'cta_rebuild_label' => 'Reconstruction',
            'cta_rebuild_title' => 'Reconstruire le stade',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modeste',
                'established' => 'Établi',
                'continental' => 'Continental',
                'elite' => 'Élite',
            ],

            'modal_supplementary_title' => 'Ajouter des tribunes supplémentaires',
            'modal_supplementary_description' => 'Tribunes modulaires provisoires : rapides (30 jours) et payées comptant, mais sans nouvel espace commercial et elles seront retirées lors de la rénovation du stade.',
            'modal_stand_expansion_title' => 'Agrandir une tribune',
            'modal_stand_expansion_description' => 'Démolit une tribune et la reconstruit plus grande. Les places sont permanentes, contrairement aux tribunes supplémentaires.',
            'modal_rebuild_title' => 'Reconstruire le stade',
            'modal_rebuild_description' => 'Rase le stade actuel et en construit un nouveau. Le prix par place augmente par paliers : plus le stade est grand, plus chaque place supplémentaire coûte cher. Le nouveau stade est livré avec la meilleure catégorie UEFA permise par sa capacité, sans coût additionnel.',
            'rebuild_marginal_rate_prefix' => 'Coût par place à cette taille :',
            'rebuild_marginal_rate_suffix' => '',
            'rebuild_cap_explainer_reputation' => 'Le maximum est fixé par le prêt bancaire auquel votre club peut prétendre (:cap). Votre réputation actuelle (:tier) fixe ce plafond : montez de catégorie pour accéder à un crédit plus important.',
            'rebuild_cap_explainer_affordability' => 'Le maximum est fixé par le prêt bancaire auquel votre club peut prétendre (:cap), calculé sur vos revenus annuels prévisionnels. Augmentez vos revenus pour accéder à un crédit plus important.',
            'commit_project' => 'Lancer les travaux',

            'cta_disabled_by_active_project' => 'Vous avez déjà un projet en cours. Consultez l\'historique ci-dessous.',

            'cta_uefa_label' => 'Rénovation',
            'cta_uefa_title' => 'Passer en Catégorie UEFA :to (depuis :from)',
            'cta_uefa_title_generic' => 'Monter de catégorie UEFA',
            'cta_uefa_button' => 'Améliorer les installations',
            'cta_uefa_tagline' => 'Rénovez les installations pour passer en Catégorie UEFA :target. Coût fixe de :cost, environ 9 mois de travaux, sans impact sur la capacité.',
            'cta_uefa_capacity_floor' => 'Pour prétendre à la Catégorie UEFA :target, le stade doit dépasser :min_cap places. Augmentez d\'abord la capacité.',
            'cta_uefa_already_max' => 'Votre stade est déjà dans la plus haute catégorie UEFA. Il n\'y a plus de niveaux à débloquer.',
            'cta_uefa_no_base_level' => 'Votre stade n\'a pas de catégorie UEFA assignée. Augmentez la capacité pour accéder au classement.',

            'modal_uefa_title' => 'Passer en Catégorie UEFA :to',
            'modal_uefa_description' => 'Rénovation des installations pour atteindre les exigences de la catégorie UEFA suivante (éclairage, vestiaires, zones de presse, loges et accessibilité). La capacité n\'est pas affectée pendant les travaux : la nouvelle catégorie est inscrite au début de la prochaine saison.',
            'uefa_transition_label' => 'Catégorie',
        ],

        'history' => [
            'title' => 'Historique des travaux',
            'empty' => 'Aucun travaux au stade pour l\'instant.',
            'empty_hint' => 'Les travaux passés et en cours apparaîtront ici.',
            'col_type' => 'Projet',
            'col_detail' => 'Détails',
            'col_cost' => 'Coût',
            'col_status' => 'Statut',
            'detail_seats' => ':count places',
            'detail_rebuild' => ':count places (nouveau stade)',
            'detail_uefa_upgrade' => 'Catégorie UEFA :from → :to',
            'status_completed' => 'Terminé',
            'status_in_progress' => 'En cours',
            'season_label' => 'Saison :season',
            'ready_label' => 'Prêt le :date',
        ],

        'season_tickets' => [
            'title' => 'Tarifs',
            'subtitle' => 'Choisissez une politique de prix pour vos abonnements. Des prix plus bas remplissent davantage le stade ; des prix plus hauts rapportent plus par place. Elle est bloquée dès le premier match de championnat joué.',
            'deadline_notice' => 'Délai : les prix sont bloqués dès le premier match de championnat de la saison joué.',
            'locked_notice' => 'Les abonnements sont bloqués cette saison. Vous pourrez fixer de nouveaux tarifs la prochaine pré-saison.',
            'tickets_sold' => 'Abonnements vendus',
            'projected_season_tickets' => 'Abonnements prévus',
            'projected_season_tickets_tooltip' => 'Abonnements que vous prévoyez de vendre (payés d\'avance). L\'affluence à chaque match est différente : additionnez les billets de billetterie et déduisez les abonnés absents.',
            'of_capacity' => 'de la capacité',
            'matchday_occupancy' => 'taux de remplissage le jour du match',
            'save_button' => 'Enregistrer',
            'preset' => [
                'accessible' => 'Accessible',
                'standard' => 'Standard',
                'premium' => 'Premium',
            ],
            'preset_hint' => [
                'accessible' => 'Moins cher, stade plus rempli.',
                'standard' => 'Tarifs de référence.',
                'premium' => 'Plus cher, moins de remplissage.',
            ],
        ],

        'identity' => [
            'subtitle' => 'Renommez votre stade sans coût (une fois par saison, en pré-saison). Vendre le nom à un sponsor se gère sur la page Commercial.',
            'sponsor_owns_name' => 'Un sponsor (:sponsor) possède le nom du stade jusqu\'à l\'expiration de l\'accord, vous ne pouvez donc pas le renommer.',
            'manage_in_commercial' => 'Gérer dans Commercial',
            'sell_naming_rights' => 'Vendre le naming du stade',
        ],

        'naming_rights' => [
            'title' => 'Identité du stade et naming',
            'current_name' => 'Nom actuel',
            'source_historic' => 'Historique',
            'source_custom' => 'Renommé',
            'source_sponsor' => 'Sponsorisé',

            'seasons_remaining' => '{1} il reste :count saison|[2,*] il reste :count saisons',

            'offers_title' => 'Offres de sponsoring',
            'becomes' => 'Le stade sera désormais nommé « :name »',
            'annual_value' => 'Valeur annuelle',
            'contract_length' => 'Contrat',
            'seasons' => '{1} :count saison|[2,*] :count saisons',
            'accept_button' => 'Accepter l\'accord',
            'accept_confirm' => 'Vendre le naming à :sponsor ? Cela bloque le nom du stade pendant le contrat et réduit le soutien des supporters.',
            'renewal_badge' => 'Renouvellement',
            'renew_button' => 'Renouveler l\'accord',
            'renew_confirm' => 'Renouveler l\'accord avec :sponsor ? Conserve le nom du stade sans coût pour le soutien des supporters.',

            'rename_button' => 'Renommer le stade',
            'rename_placeholder' => 'Nouveau nom du stade',
            'rename_save' => 'Enregistrer le nom',
            'rename_locked_season' => 'Le stade a déjà été renommé cette saison.',

            'window_closed_notice' => 'L\'identité du stade est fixée en pré-saison. Les accords et renommages rouvrent avant le premier match de championnat de la prochaine saison.',
        ],
    ],

    'reputation' => [
        'current_tier' => 'Niveau actuel',

        'tiers' => 'Niveaux de réputation',
        'tiers_help_toggle' => 'Comment fonctionnent les niveaux de réputation ?',
        'ladder_help' => 'Les clubs montent de niveau en terminant en haut du championnat. Aux niveaux les plus élevés, la réputation s\'érode chaque saison si elle n\'est pas soutenue par des résultats.',

        'current' => 'Actuel',

        'qualitative_distance' => [
            'one_strong_season' => 'Une bonne saison suffirait pour atteindre :tier.',
            'two_strong_seasons' => 'Quelques bonnes saisons vous séparent de :tier.',
            'several_seasons' => 'Plusieurs saisons solides vous séparent de :tier.',
            'long_road' => 'Il reste un long chemin jusqu\'à :tier.',
        ],

        'tier_descriptors' => [
            'local' => 'Un club modeste avec des supporters locaux fidèles.',
            'modest' => 'Un petit club qui aspire à atteindre ou à se maintenir dans l\'élite.',
            'established' => 'Un club historique, avec des années d\'expérience dans l\'élite.',
            'continental' => 'Habitué des compétitions européennes.',
            'elite' => 'Référence du football européen.',
        ],

        'career' => [
            'title' => 'Parcours',
            'seasons_managed' => 'Saisons dirigées',
            'starting_tier' => 'Niveau initial',
            'matches_managed' => 'Matchs dirigés',
            'trophies' => 'Titres',
        ],

        'trophy_cabinet' => [
            'title' => 'Salle des trophées',
            'empty' => 'Vous n\'avez encore conquis aucun titre avec ce club.',
        ],

        'path_title' => 'Le chemin vers le niveau suivant',
        'path_also' => 'Les titres de coupe et les parcours européens comptent aussi en fin de saison.',
        'maintenance_note' => 'À ce niveau, la réputation s\'érode chaque saison si elle n\'est pas soutenue par des résultats.',
        'projected' => 'Projeté',

        'legend' => [
            'forward' => 'Progrès',
            'flat' => 'Sans progrès',
            'setback' => 'Recul',
        ],

        'impact' => [
            'major_leap' => 'Grand bond en avant',
            'solid_step' => 'Pas solide en avant',
            'small_step' => 'Petit pas en avant',
            'stalls' => 'Sans progrès',
            'setback' => 'Recul',
        ],

        'history' => [
            'title' => 'Historique des performances',
            'empty' => 'Votre historique apparaîtra à la fin de la première saison.',
            'current_suffix' => '(en cours)',
            'promoted' => 'Montée',
            'relegated' => 'Descente',
            'legend' => [
                'same_tier' => 'Même catégorie',
            ],
        ],

        'impact_title' => 'Ce que la réputation apporte à votre club',
        'impact_signings_title' => 'Attirer des recrues',
        'impact_signings_body' => 'Les joueuses de plus haut niveau sont attirées par les clubs les plus réputés. Agents libres, cibles de transferts et clubs rivaux évaluent votre niveau avant de s\'asseoir pour négocier.',
        'impact_retain_title' => 'Conserver les talents',
        'impact_retain_body' => 'Votre propre effectif réagit aussi à la réputation. Un club en pleine croissance conserve mieux ses pièces maîtresses ; quand il descend de niveau, les prédateurs apparaissent et les prolongations se compliquent.',
        'impact_economy_title' => 'Opportunités économiques',
        'impact_economy_body' => 'L\'affluence au stade, le prix des billets et les revenus commerciaux évoluent avec la réputation. Monter débloque des revenus plus importants sur tous les fronts ; descendre resserre le budget.',

    ],
];
