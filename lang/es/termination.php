<?php

return [
    // Botón y modal
    'mutual_terminate' => 'Rescindir de mutuo acuerdo',
    'modal_title' => 'Rescisión de mutuo acuerdo',
    'modal_intro' => 'El agente de :player pide una indemnización por rescindir el contrato. Puedes aceptar su cifra, hacer una contraoferta o romper la negociación.',
    'agent_demand_label' => 'El agente pide',
    'unilateral_cost_label' => 'Liberación unilateral (sin negociar)',
    'your_offer_label' => 'Tu oferta (€)',
    'your_offer_placeholder' => 'Cantidad en euros',
    'btn_offer' => 'Contraofertar',
    'btn_accept_demand' => 'Aceptar su cifra',
    'btn_walk_away' => 'Romper negociación',
    'btn_start' => 'Iniciar negociación',
    'round_label' => 'Ronda :current de :max',

    // Paso de forma de pago
    'payment_title' => '¿Cómo pagas la carta de libertad?',
    'payment_intro' => 'Has pactado :amount con :player. Elige cómo pagar la indemnización:',
    'btn_complete' => 'Confirmar rescisión',

    // Mensajes del agente (API)
    'chat_initial_demand' => 'Mi representada :player aceptaría rescindir por :amount. Es lo justo por el tiempo de contrato que le queda.',
    'chat_resume' => 'Retomamos: :player rescindiría por :amount. ¿Aceptas?',
    'chat_agent_counters' => 'Ni hablar. :player no baja de :amount. Piénsalo bien.',
    'chat_agent_accepts_offer' => 'De acuerdo. :player acepta rescindir por :amount. Trato hecho.',
    'chat_agent_walks_away' => ':player no está interesada en rescindir por esa cifra. Se queda y cumplirá su contrato.',
    'chat_agreed' => 'Perfecto. :player rescinde por :amount. Elige ahora cómo pagar la indemnización.',
    'chat_you_walked_away' => 'Has roto la negociación con :player. Seguirá en tu plantilla.',
    'chat_free' => ':player no tiene salario pendiente: acepta rescindir sin indemnización.',

    // Errores
    'no_active_negotiation' => 'No hay ninguna negociación activa con esta jugadora.',
    'no_agreement' => 'No hay ningún acuerdo de rescisión con esta jugadora.',
];
