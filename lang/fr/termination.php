<?php

return [
    // Bouton et modal
    'mutual_terminate' => 'Résilier d\'un commun accord',
    'modal_title' => 'Résiliation d\'un commun accord',
    'modal_intro' => 'L\'agent de :player demande une indemnité pour résilier le contrat. Vous pouvez accepter son montant, faire une contre-offre ou rompre la négociation.',
    'agent_demand_label' => 'L\'agent demande',
    'unilateral_cost_label' => 'Libération unilatérale (sans négociation)',
    'your_offer_label' => 'Votre offre (€)',
    'your_offer_placeholder' => 'Montant en euros',
    'btn_offer' => 'Faire une contre-offre',
    'btn_accept_demand' => 'Accepter son montant',
    'btn_walk_away' => 'Rompre la négociation',
    'btn_start' => 'Entamer la négociation',
    'round_label' => 'Tour :current sur :max',

    // Étape du mode de paiement
    'payment_title' => 'Comment payez-vous l\'indemnité ?',
    'payment_intro' => 'Vous avez convenu de :amount avec :player. Choisissez comment payer l\'indemnité :',
    'btn_complete' => 'Confirmer la résiliation',

    // Messages de l\'agent (API)
    'chat_initial_demand' => 'Ma joueuse :player accepterait de résilier pour :amount. C\'est juste pour le temps de contrat qu\'il lui reste.',
    'chat_resume' => 'Reprenons : :player résilierait pour :amount. Acceptez-vous ?',
    'chat_agent_counters' => 'Hors de question. :player ne descendra pas sous :amount. Réfléchissez bien.',
    'chat_agent_accepts_offer' => 'D\'accord. :player accepte de résilier pour :amount. Marché conclu.',
    'chat_agent_walks_away' => ':player n\'est pas intéressée par une résiliation à ce montant. Elle reste et honorera son contrat.',
    'chat_agreed' => 'Parfait. :player résilie pour :amount. Choisissez maintenant comment payer l\'indemnité.',
    'chat_you_walked_away' => 'Vous avez rompu la négociation avec :player. Elle restera dans votre effectif.',
    'chat_free' => ':player n\'a aucun salaire en attente : elle accepte de résilier sans indemnité.',

    // Erreurs
    'no_active_negotiation' => 'Aucune négociation en cours avec cette joueuse.',
    'no_agreement' => 'Aucun accord de résiliation avec cette joueuse.',
];
