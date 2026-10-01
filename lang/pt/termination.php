<?php

return [
    // Botão e modal
    'mutual_terminate' => 'Rescindir por mútuo acordo',
    'modal_title' => 'Rescisão por mútuo acordo',
    'modal_intro' => 'O agente de :player pede uma indemnização pela rescisão do contrato. Podes aceitar o valor dele, fazer uma contraproposta ou romper a negociação.',
    'agent_demand_label' => 'O agente pede',
    'unilateral_cost_label' => 'Rescisão unilateral (sem negociar)',
    'your_offer_label' => 'A tua proposta (€)',
    'your_offer_placeholder' => 'Quantia em euros',
    'btn_offer' => 'Contrapropor',
    'btn_accept_demand' => 'Aceitar o valor dele',
    'btn_walk_away' => 'Romper a negociação',
    'btn_start' => 'Iniciar negociação',
    'round_label' => 'Ronda :current de :max',

    // Passo da forma de pagamento
    'payment_title' => 'Como pagas a carta de alforria?',
    'payment_intro' => 'Acordaste :amount com :player. Escolhe como pagar a indemnização:',
    'btn_complete' => 'Confirmar rescisão',

    // Mensagens do agente (API)
    'chat_initial_demand' => 'A minha representada :player aceitaria rescindir por :amount. É o justo pelo tempo de contrato que lhe resta.',
    'chat_resume' => 'Retomando: :player rescindiria por :amount. Aceitas?',
    'chat_agent_counters' => 'Nem pensar. :player não desce dos :amount. Pensa bem.',
    'chat_agent_accepts_offer' => 'De acordo. :player aceita rescindir por :amount. Negócio fechado.',
    'chat_agent_walks_away' => ':player não está interessada em rescindir por esse valor. Fica e cumprirá o seu contrato.',
    'chat_agreed' => 'Perfeito. :player rescinde por :amount. Escolhe agora como pagar a indemnização.',
    'chat_you_walked_away' => 'Rompeste a negociação com :player. Continuará no teu plantel.',
    'chat_free' => ':player não tem salário em dívida: aceita rescindir sem indemnização.',

    // Erros
    'no_active_negotiation' => 'Não há nenhuma negociação ativa com esta jogadora.',
    'no_agreement' => 'Não há nenhum acordo de rescisão com esta jogadora.',
];
