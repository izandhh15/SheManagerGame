<?php

return [
    'atmosphere_shot_on_target' => [
        'Frappe de :player (:team) ! La gardienne capte sans problème',
        ':player (:team) tente de loin. Bel arrêt',
        'Frappe de :player (:team) qui oblige la gardienne à intervenir',
        ':el_team l\'a eu ! Frappe de :player que la gardienne repousse',
        ':player (:team) frappe au but, mais la gardienne était bien placée',
        'Attention à la frappe de :player (:team) ! Superbe arrêt de la gardienne',
    ],
    'atmosphere_shot_off_target' => [
        'Ouh ! Tir à côté de :player (:team)',
        ':player (:team) tente mais le ballon s\'envole à côté',
        'Frappe au-dessus de :player (:team), ça passe par-dessus la transversale',
        ':player (:team) frappe de l\'entrée de la surface... ça passe de peu',
        ':player (:team) tente de loin, mais ne trouve pas le cadre',
        'Ça passe près ! :player (:team) est tout près mais le ballon sort',
    ],
    'atmosphere_foul' => [
        'Faute de :player (:team), l\'arbitre n\'hésite pas à siffler',
        ':player (:team) stoppe par une faute une action prometteuse :del_opponent',
        'Tacle fautif de :player (:team) au milieu de terrain',
        ':player (:team) arrive en retard dans le duel et commet la faute',
        'L\'arbitre signale une faute de :player (:team) après un accrochage',
        'Faute de :player (:team) en attaque, changement de possession',
    ],
    'contextual_draw_open' => [
        'Match équilibré :venue, aucune des deux équipes ne parvient à prendre le dessus',
        'Pas de dominateur clair dans ce match entre :el_home et :el_away',
        'Jeu partagé :venue, avec des occasions dans les deux surfaces',
        'Match nul vierge :venue, les deux équipes s\'observent avec prudence',
        'Égalité parfaite entre :el_home et :el_away, qui s\'annulent mutuellement',
    ],
    'contextual_draw_with_goals' => [
        'Match ouvert et divertissant :venue, avec des buts dans les deux camps',
        'Match nul conforme à ce qu\'on a vu sur le terrain, avec des occasions pour les deux',
        'Égalité au score et dans le jeu entre :el_home et :el_away',
        'Aller-retour constant :venue, les deux défenses souffrent',
        'Spectacle pour les supporters :venue, buts partagés entre les deux équipes',
    ],
    'contextual_home_leading' => [
        ':el_home contrôle le match et le score :venue',
        'Domination claire :del_home, qui gère le match à sa guise',
        'Bon match :del_home qui a le score en sa faveur',
        ':el_home tranquille avec l\'avantage :venue',
    ],
    // Variantes connotées domicile — utilisées uniquement en cas d'avantage du terrain
    'contextual_home_leading_home_only' => [
        'Les locaux font tout bien jusqu\'ici, :el_home mérite d\'être devant',
    ],
    'contextual_away_leading' => [
        ':el_away fait un très grand match :venue',
        'Le match se complique pour :al_home, mené au score',
    ],
    // Variantes connotées domicile — utilisées uniquement en cas d'avantage du terrain
    'contextual_away_leading_home_only' => [
        ':el_away surprend :al_home dans son propre stade',
        ':el_away fait taire le public :venue avec une grande performance',
        'Exhibition à l\'extérieur, :el_away mène la rencontre loin de ses bases',
    ],
    'contextual_home_dominant' => [
        ':el_home pousse avec insistance, accumule les occasions dangereuses',
        'Pression :del_home, qui cherche le but avec beaucoup d\'intensité',
        'Beaucoup de :home en ce moment, avec des arrivées constantes dans la surface adverse',
        ':el_home étouffe :al_away dans sa propre surface, vagues d\'attaques',
    ],
    // Variantes connotées domicile — utilisées uniquement en cas d'avantage du terrain
    'contextual_home_dominant_home_only' => [
        'Les visiteuses ne peuvent pas sortir de leur camp, :el_home pousse sans relâche',
    ],
    'contextual_away_dominant' => [
        'Pressing haut :del_away, qui veut compliquer la relance :del_home',
        ':el_away est dangereux à chaque contre',
        'Beaucoup d\'intensité :del_away qui fait le jeu en ce moment',
        ':el_away donne le rythme du match, :el_home ne trouve pas son jeu',
        'Contrôle total :del_away, qui domine la possession et le territoire',
    ],
    'contextual_tight_game' => [
        'Match très fermé au milieu de terrain, avec peu d\'occasions franches',
        'Beaucoup d\'intensité et peu de clarté, le ballon n\'arrive pas dangereusement devant les buts',
        'Match fermé :venue, avec plus de combat que de football',
        'Aucune des deux ne veut faire le premier pas, rencontre très prudente :venue',
        'Bataille au milieu de terrain, les occasions brillent par leur absence',
    ],
    'contextual_end_losing' => [
        'Le temps s\'épuise et ce résultat ne suffit pas à :al_trailing qui doit réagir',
        'Les minutes s\'épuisent pour :el_trailing, qui est en grande difficulté',
        ':el_trailing se jette tout en avant mais l\'écart semble insurmontable',
        'Sans idées et sans temps, :el_trailing a une montagne à gravir',
        'Désespoir dans :el_trailing à mesure que l\'horloge tourne',
    ],
    'contextual_end_losing_by_one' => [
        'Le temps joue contre :del_trailing, qui cherche l\'égalisation avec plus de cœur que de tête',
        'L\'égalisation échappe à :el_trailing, il ne reste que quelques minutes :venue',
        ':el_trailing pourra-t-elle trouver le but qu\'il lui faut ? Le temps n\'est pas de son côté',
        ':el_trailing frappe et frappe encore mais l\'égalisation ne vient pas',
        'Tout en attaque pour :del_trailing, un but changerait tout',
    ],
    'contextual_end_winning' => [
        ':el_leading contrôle les dernières minutes du match sans souffrir',
        ':el_leading gère tranquillement les dernières minutes de la rencontre',
        'Ça sent déjà la victoire pour :el_leading :venue',
        ':el_leading refroidit le match, garde le ballon avec calme',
        'Travail presque terminé pour :el_leading, qui a été la meilleure équipe',
    ],
    'contextual_end_draw' => [
        'Dernières minutes et partage des points, sauf un dernier coup d\'éclat',
        'Le match se termine :venue sur un score de parité',
        'Match nul :venue qui ne satisfait vraiment personne',
        'Ça file vers le nul :venue, personne ne trouve le but de la victoire',
        'Un point chacun semble être le résultat final :venue',
    ],
    'contextual_end_draw_knockout' => [
        'La fin du temps réglementaire approche :venue et la qualification reste ouverte',
        'Match nul :venue, ça sent la prolongation',
        'Personne ne trouve le but décisif, le temps additionnel se rapproche',
        'Les minutes s\'épuisent :venue avec ce nul qui ne lève pas l\'incertitude',
        'Dernière poussée pour éviter la prolongation, personne ne veut prolonger l\'agonie',
    ],
    'contextual_second_half_start' => [
        'Début de la seconde période :venue avec le score :score',
        'Retour à l\'action :venue. :score à la mi-temps',
        'Les équipes reviennent sur la pelouse :venue. :score au tableau d\'affichage',
        'Coup d\'envoi de la seconde moitié :venue, :score à la pause',
        'Le jeu reprend :venue avec le :score au tableau d\'affichage',
    ],
    'contextual_away_fans' => [
        'Les supporters :del_away qui ont fait le déplacement jusqu\'à :venue encouragent leur équipe depuis la tribune',
        'Les supporters :del_away se font entendre :venue',
        'Les supporters visiteurs :del_away n\'arrêtent pas de chanter :venue',
        'Grande ambiance dans la tribune visiteuse, les supporters :del_away poussent les leurs',
        'Spectaculaire soutien des supporters voyageurs :del_away aujourd\'hui',
    ],
    'contextual_home_fans' => [
        ':venue rugit d\'émotion, les supporters :del_home poussent les leurs',
        'Le public :venue est acquis à son équipe en ce moment',
        'Ambiance de folie :venue, les supporters :del_home sont à fond',
        ':venue résonne, la tribune encourage sans relâche :al_home',
        'Les supporters :del_home sont la douzième joueuse aujourd\'hui :venue',
    ],
    // Préfixe des buts — placé devant chaque narration de but pour l'emphase
    'goal_prefix' => [
        'But :del_team !',
        'BUT :del_team !',
        'SUPERBE BUT :del_team !',
        'BUUUUT :del_team !',
        'BUUUT :del_team !',
        'But :del_team !',
        ':el_team marque !',
        ':el_team inscrit !',
        'Quel but :del_team !',
        'Superbe but :del_team !',
    ],
    'goal_assisted' => [
        'Centre dans la surface et :player apparaît libre de tout marquage pour reprendre de la tête',
        ':player reçoit au point de penalty, contrôle et conclut avec classe',
        'Passe en profondeur pour :player, seule face à la gardienne et elle ne pardonne pas',
        'Quelle action collective :del_team ! :player la conclut d\'une touche subtile',
        'Tête imparable de :player au second poteau. Impossible pour la gardienne',
        'Centre millimétré depuis l\'aile et :player reprend de la tête à bout portant',
        'Contre létal :del_team. :player conclut avec sang-froid face à la sortie de la gardienne',
        'Une-deux à l\'entrée de la surface et :player la pousse au but depuis la petite surface',
        ':player devance la défense et reprend du premier coup au fond des filets',
        'Superbe passe décisive et :player n\'a plus qu\'à pousser. Elle ne rate pas',
        'Appel intelligent de :player qui reçoit seule et pique le ballon par-dessus la gardienne',
        ':player devance la défense pour reprendre et la glisser au premier poteau',
        ':player enchaîne une volée spectaculaire qui entre comme un boulet de canon',
        'Ballon au cœur de la surface et :player reprend de la pointe pour marquer',
    ],
    'goal_solo' => [
        'Quel but de :player ! Crochet sur la gardienne et du droit au fond',
        ':player défie la défense, se met sur son pied et cloue le ballon dans la lucarne',
        'Frappasse de :player de loin ! Quel but',
        ':player récupère le ballon relâché et l\'envoie au fond des filets',
        'Action personnelle de :player qui se défait de deux adversaires et conclut croisée',
        'Quel superbe but de :player ! Frappe enroulée de l\'entrée de la surface qui se loge dans la lucarne',
        ':player profite d\'une erreur défensive et bat la gardienne d\'une frappe à ras de terre',
        'Frappe lointaine de :player déviée par une défenseuse et qui surprend la gardienne',
        'Coup franc direct de :player qui passe par-dessus le mur et se loge près du poteau',
        ':player crochète dans la surface, trouve l\'espace et frappe croisée. But !',
        ':player la claque ! Reprise du premier coup depuis l\'entrée de la surface',
        'Récupération du ballon et :player n\'hésite pas, conclut d\'une frappe croisée imparable',
        ':player s\'invente un but individuel superbe depuis l\'entrée de la surface',
    ],

    // Narrations tactiques — générées selon les configurations tactiques
    'tactical_high_press_working' => [
        ':user presse avec une intensité féroce, étouffant la relance :del_opp',
        'Le pressing haut :del_user récupère le ballon dans des zones dangereuses',
        ':user presse sans relâche — :opp peut à peine sortir de son camp',
    ],
    'tactical_high_press_fading' => [
        'L\'intensité du pressing :del_user commence à baisser. Les jambes pèsent',
        ':user ne peut pas maintenir ce pressing initial — :opp trouve plus d\'espaces',
        'La fatigue se fait sentir. Le pressing :del_user perd de son mordant',
    ],
    'tactical_high_press_exhausted' => [
        ':user semble épuisé. Le pressing haut se paie cher en fin de match',
        'Les forces manquent pour :user — ce pressing agressif leur coûte cher',
        ':opp sent la fatigue :del_user et se lance à l\'attaque en confiance',
    ],
    'tactical_opp_press_fading' => [
        'Le pressing haut :del_opp perd de sa force — :user devrait trouver plus d\'espace',
        'Le pressing :del_opp n\'est plus ce qu\'il était. Les espaces s\'ouvrent',
    ],
    'tactical_opp_exhausted' => [
        ':opp semble cuit après avoir tant pressé. :user peut profiter des jambes fatiguées',
        'Le pressing haut a épuisé :al_opp — on voit qu\'elles sont à bout de souffle',
    ],
    'tactical_low_block_wall' => [
        ':user se replie compact et bas, compliquant énormément le jeu :del_opp',
        'Un mur défensif discipliné :del_user. :opp ne trouve pas comment entrer',
        ':user défend à nombre, ne concédant aucune occasion franche à :al_opp',
    ],
    'tactical_low_block_fresh' => [
        'L\'approche prudente :del_user porte ses fruits — les joueuses semblent encore fraîches',
        'Niveaux d\'énergie élevés pour :user grâce à la discipline défensive',
    ],
    'tactical_possession_control' => [
        ':user contrôle le tempo, déplaçant le ballon avec patience pour trouver les espaces',
        'Possession dominante :del_user — :opp court après les ombres',
        ':user gère bien le ballon, dictant le rythme du match',
    ],
    'tactical_possession_frustrated' => [
        ':user domine la possession mais ne trouve pas comment passer le bloc bas :del_opp',
        'Beaucoup de possession pour :user mais le bloc bas :del_opp frustre chaque attaque',
    ],
    'tactical_counter_waiting' => [
        ':user attend tapi dans l\'ombre, prêt à partir en contre à tout moment',
        ':user cède le territoire — cherche à frapper en transition',
        'Défense patiente :del_user, prête à jaillir quand l\'opportunité se présente',
    ],
    'tactical_counter_exploiting' => [
        ':user exploite l\'espace derrière la ligne haute :del_opp avec des contres létaux',
        'L\'approche agressive :del_opp laisse des espaces — :user punit en contre',
    ],
    'tactical_direct_play' => [
        ':user saute le milieu avec des ballons longs, tenant :opp en alerte',
        'Jeu direct :del_user — sans complications, ballon long vers les attaquantes',
    ],
    'tactical_direct_bypassing_press' => [
        'Le jeu direct :del_user survole le pressing haut :del_opp — les ballons longs trouvent leur destinataire',
        'Le pressing :del_opp est annulé par les ballons longs :del_user',
    ],
    'goal_penalty' => [
        'Penalty ! :player (:team) frappe avec décision et marque. Sans option pour la gardienne',
        ':player (:team) se place devant le ballon, s\'élance et la claque dans la lucarne. But sur penalty !',
        'Penalty pour :el_team. :player prend son élan et trompe la gardienne d\'une frappe croisée',
        ':player (:team) assume la responsabilité des onze mètres et ne rate pas. Imparable',
        'But sur penalty ! :player (:team) l\'envoie au centre du but pendant que la gardienne plonge',
        'Penalty pour :el_team. :player attend la gardienne, la voit bouger et place le ballon de l\'autre côté',
    ],
    // Touche tactique dans les buts
    'goal_counter_attack' => [
        'Contre létal ! :player conclut après un contre dévastateur de :team',
        'Clinique en contre ! :player convertit après une sortie rapide de :team',
        'But en contre-attaque ! :team part à toute vitesse et :player conclut',
    ],
    'goal_possession' => [
        ':team fait circuler le ballon avec patience jusqu\'à ce que :player trouve l\'espace. Possession de manuel',
        'Construction patiente de :team et :player choisit le moment parfait pour frapper',
        'Action tricotée :del_team — :player met la touche finale',
    ],
    'goal_direct' => [
        'Ballon long et :player est là pour conclure pour :team !',
        ':team va droit au but et ça marche — :player contrôle et conclut',
        'Jeu direct pur ! Le ballon long trouve :player qui ne pardonne pas',
    ],

    // Annonce du temps additionnel. Le client choisit la variante singulier ou
    // pluriel selon les minutes (:minutes) pour éviter « 1 minutes ».
    'stoppage_announcement_singular' => [
        'L\'arbitre ajoute :minutes minute',
        'Le quatrième arbitre indique :minutes minute d\'ajout',
        ':minutes minute de temps additionnel',
        'Seulement :minutes minute de temps additionnel',
    ],
    'stoppage_announcement_plural' => [
        'L\'arbitre ajoute :minutes minutes',
        'Le quatrième arbitre indique :minutes minutes de temps additionnel',
        ':minutes minutes de temps additionnel',
        'Temps additionnel : :minutes minutes',
        ':minutes minutes ajoutées à la fin',
        ':minutes minutes de temps additionnel ! Il reste encore du temps',
    ],
];
