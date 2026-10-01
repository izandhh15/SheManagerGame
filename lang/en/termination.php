<?php

return [
    // Button and modal
    'mutual_terminate' => 'Terminate by mutual agreement',
    'modal_title' => 'Mutual termination',
    'modal_intro' => ':player\'s agent is asking for compensation to terminate the contract. You can accept their figure, make a counter-offer or walk away from the negotiation.',
    'agent_demand_label' => 'The agent asks for',
    'unilateral_cost_label' => 'Unilateral release (no negotiation)',
    'your_offer_label' => 'Your offer (€)',
    'your_offer_placeholder' => 'Amount in euros',
    'btn_offer' => 'Counter-offer',
    'btn_accept_demand' => 'Accept their figure',
    'btn_walk_away' => 'Walk away',
    'btn_start' => 'Start negotiation',
    'round_label' => 'Round :current of :max',

    // Payment method step
    'payment_title' => 'How do you pay the release fee?',
    'payment_intro' => 'You agreed :amount with :player. Choose how to pay the compensation:',
    'btn_complete' => 'Confirm termination',

    // Agent messages (API)
    'chat_initial_demand' => 'My client :player would agree to terminate for :amount. That is fair for the contract time she has left.',
    'chat_resume' => 'Picking up: :player would terminate for :amount. Do you accept?',
    'chat_agent_counters' => 'No way. :player will not go below :amount. Think it over.',
    'chat_agent_accepts_offer' => 'Agreed. :player accepts terminating for :amount. Deal.',
    'chat_agent_walks_away' => ':player is not interested in terminating for that figure. She stays and will honour her contract.',
    'chat_agreed' => 'Perfect. :player terminates for :amount. Now choose how to pay the compensation.',
    'chat_you_walked_away' => 'You walked away from the negotiation with :player. She stays in your squad.',
    'chat_free' => ':player has no pending wages: she agrees to terminate with no compensation.',

    // Errors
    'no_active_negotiation' => 'There is no active negotiation with this player.',
    'no_agreement' => 'There is no termination agreement with this player.',
];
