<?php

return [
    // Caixa de entrada
    'inbox' => 'Notificações',
    'new' => 'novas',
    'all_caught_up' => 'Estás em dia',

    // Separadores da caixa de entrada por departamento
    'dept_all' => 'Tudo',
    'dept_sporting' => 'Equipa técnica',
    'dept_transfers' => 'Direção desportiva',
    'dept_scouting' => 'Observadores',
    'dept_academy' => 'Formação',
    'dept_board' => 'Direção',
    'dept_competition' => 'Competição',

    // Janela de alerta crítico (bloqueante, tem de ser dispensada)
    'alert_heading' => 'Aviso importante',
    'alerts_heading' => ':count avisos importantes',
    'celebration_heading' => 'Parabéns!',
    'alert_dismiss' => 'Descartar',
    'dismiss_all' => 'Descartar tudo',
    'alert_continue' => 'Continuar',
    'action_review_offer' => 'Rever oferta',
    'action_view_competition' => 'Ver competição',
    'action_view_details' => 'Ver detalhes',

    // Tipos de lesão
    'injury_muscle_fatigue' => 'fadiga muscular',
    'injury_muscle_strain' => 'distensão muscular',
    'injury_calf_strain' => 'distensão do gémeo',
    'injury_ankle_sprain' => 'entorse do tornozelo',
    'injury_groin_strain' => 'distensão inguinal',
    'injury_hamstring_tear' => 'rotura do isquiotibial',
    'injury_knee_contusion' => 'contusão do joelho',
    'injury_metatarsal_fracture' => 'fratura do metatarso',
    'injury_acl_tear' => 'rotura do ligamento cruzado',
    'injury_achilles_rupture' => 'rotura do tendão de Aquiles',

    // Lesões de jogadoras
    'player_injured_title' => ':player lesionada',
    'player_injured_message' => ':player sofreu :injury :location.',
    'player_injured_message_with_date' => ':player sofreu :injury :location. De baixa até :date.',
    'injury_location_match' => 'durante o jogo',
    'injury_location_training' => 'durante o treino',

    // Castigos de jogadoras
    'player_suspended_title' => ':player castigada',
    'player_suspended_message' => ':player foi castigada com :matches jogo por :reason. Vai falhar o próximo jogo da :competition.|:player foi castigada com :matches jogos por :reason. Vai falhar o próximo jogo da :competition.',
    'reason_red_card' => 'cartão vermelho',
    'reason_yellow_accumulation' => 'acumulação de amarelos',

    // Recuperação de jogadoras
    'player_recovered_title' => ':player recuperada',
    'player_recovered_message' => ':player recuperou e está disponível para jogar.',

    // Ofertas de transferência
    'transfer_offer_title' => 'Oferta de compra por :player',
    'transfer_offer_message' => ':team_el ofereceu :fee pela jogadora.',
    'free_transfer' => 'Transferência Livre',

    // Transferência concluída
    'transfer_complete_incoming_title' => ':player contratada',
    'transfer_complete_incoming_message' => ':player juntou-se ao teu plantel vinda :team_de por :fee.',
    'transfer_complete_outgoing_title' => ':player vendida',
    'transfer_complete_outgoing_message' => ':player foi transferida :team_a por :fee.',
    'transfer_failed_title' => 'Contratação falhada: :player',
    'transfer_failed_message' => 'A transferência acordada de :player não pôde ser concluída e o orçamento reservado foi libertado.',
    'pre_contract_failed_title' => 'Pré-contrato falhado: :player',
    'pre_contract_failed_message' => 'O pré-contrato que acordaste com :player não pôde ser concluído: já não estava em :team no final da época. Não se junta ao teu plantel.',
    'loan_out_complete_title' => ':player emprestada',
    'loan_out_complete_message' => ':player foi emprestada :team_a até ao final da época.',

    // Cláusula de rescisão ativada contra o utilizador (Fase 3)
    'player_left_via_release_clause_title' => 'Cláusula de rescisão: :player sai',
    'player_left_via_release_clause_message' => ':player sai :team_a após a ativação da sua cláusula de rescisão. O teu clube recebe :fee.',

    // Ofertas a expirar
    'offer_expiring_title' => 'Oferta por :player expira em breve',
    'offer_expiring_message' => '{0}A oferta :team_de por :player expira hoje.|{1}A oferta :team_de por :player expira em :count dia.|[2,*]A oferta :team_de por :player expira em :count dias.',

    // Observador
    'scout_complete_title' => 'Relatório de Observador Pronto',
    'scout_complete_message' => 'O teu observador encontrou :count jogadoras que correspondem à tua pesquisa.',

    // Contratos
    'contract_expiring_title' => 'Contrato de :player expira em breve',
    'contract_expiring_message' => 'O contrato de :player expira em :months meses.',

    // Regressos de empréstimo
    'loan_return_title' => ':player regressa de empréstimo',
    'loan_return_message' => ':player regressou do seu empréstimo :team_en.',

    // Pouca energia
    'low_fitness_title' => ':player com pouca energia',
    'low_fitness_message' => ':player tem apenas :fitness% de energia e precisa de descanso.',

    // Pesquisa de empréstimo
    'loan_offer_received_title' => 'Oferta de empréstimo por :player',
    'loan_offer_received_message' => ':team_el ofereceu levar a jogadora por empréstimo.',
    'loan_search_failed_title' => 'Pesquisa de empréstimo falhada',
    'loan_search_failed_message' => 'Não foi encontrado um clube interessado em emprestar :player. A jogadora volta a estar disponível.',

    // Apuramento em competição
    'competition_advancement_title' => 'Apuramento na :competition',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => 'Eliminação da :competition',
    'competition_elimination_message' => ':stage',
    'trophy_won_title' => 'Campeã da :competition!',

    // Formação
    'academy_batch_title' => 'Novas jogadoras da formação',
    'academy_batch_message' => ':count novas jogadoras chegaram à formação.',
    'academy_overage_promoted_title' => 'Graduadas da formação',
    'academy_overage_promoted_message' => ':count jogadoras da formação com 21+ anos foram promovidas à equipa principal.',
    'academy_gap_promoted_title' => 'Jogadoras da formação promovidas',
    'academy_gap_promoted_message' => ':count jogadoras da formação foram promovidas para preencher lacunas no plantel.',
    'reserve_overage_promoted_title' => 'Graduada da equipa B',
    'reserve_overage_promoted_message' => ':player ultrapassou a idade da equipa B e junta-se à equipa principal de forma permanente.',
    'reserve_stand_in_added_title' => 'Reforço da equipa C',
    'reserve_stand_in_added_message' => 'A equipa B incorporou :count jogadoras da equipa C para completar o plantel.',
    // Resultados de pedidos de empréstimo
    'loan_accepted_title' => 'Empréstimo de :player aceite',
    'loan_accepted' => ':team aceitou o teu pedido de empréstimo por :player.',
    'loan_rejected_title' => 'Empréstimo de :player recusado',
    'loan_rejected' => ':team recusou o teu pedido de empréstimo por :player.',

    // Boas-vindas ao torneio
    'tournament_welcome_title' => 'Bem-vindo ao Mundial!',
    'tournament_welcome_message' => 'Todo o país tem os olhos postos em ti. Sem pressão... mas não os desiludas!',

    // Distintivos de prioridade
    'priority_urgent' => 'Urgente',
    'priority_attention' => 'Atenção',

    // Ofertas de emprego do treinador (modo Pro Manager)
    'job_offer_received_title' => ':count clubes interessados em ti',
    'job_offer_post_firing_title' => 'Escolhe o teu próximo clube (:count ofertas)',
    'job_offer_received_message' => 'Revê o ecrã de fim de época para aceitar ou recusar.',

    // Janela de transferências aberta
    'transfer_window_open_title' => 'Janela de :window Aberta',
    'transfer_window_open_message' => 'A janela de transferências está aberta. As contratações acordadas juntar-se-ão ao teu plantel de imediato.',

    // Janela de transferências a fechar
    'transfer_window_closing_title' => 'Fecho da Janela de :window',
    'transfer_window_closing_message' => 'Esta é a tua última oportunidade para contratar. A janela de transferências fecha após esta jornada.',
    'transfer_window_closing_title_winter' => '⏰ Dia limite do mercado de inverno!',
    'transfer_window_closing_message_winter' => 'Últimas horas da janela de janeiro: fecha após esta jornada. Que ninguém adormeça!',

    // Janela de transferências fechada (também o resumo do mercado da IA — o aviso
    // de fecho da janela e a contagem de transferências da liga são uma só notificação)
    'ai_transfer_title' => 'Janela de :window Fechada',
    'ai_transfer_message' => 'A janela de transferências está fechada. :count transferências concluídas na liga. As contratações acordadas serão concluídas quando a próxima janela abrir.',
    'ai_transfer_message_none' => 'A janela de transferências está fechada. As contratações acordadas serão concluídas quando a próxima janela abrir.',
    'ai_transfer_window_summer' => 'Verão',
    'ai_transfer_window_winter' => 'Inverno',

    // Jogadora dispensada
    'player_released_title' => ':player dispensada',
    'player_released_message' => ':player foi dispensada do teu plantel. Indemnização paga: :severance.',
    'player_released_message_free' => ':player foi dispensada do teu plantel.',

    // Rescisão por mútuo acordo
    'mutual_termination_title' => 'Rescisão por mútuo acordo: :player',
    'mutual_termination_message' => 'Rescindiste por mútuo acordo o contrato de :player. Indemnização acordada: :amount.',

    // Plano de pagamento de indemnização em prestações
    'severance_plan_title' => 'Indemnização em prestações: :player',
    'severance_plan_message' => 'Pagarás a indemnização de :player em :months prestações de :monthly (total :total com juros).',
    'severance_plan_completed_title' => 'Indemnização saldada: :player',
    'severance_plan_completed_message' => 'Terminaste de pagar a indemnização de :player (total :total).',

    // Contratações de emergência
    'emergency_signing_title' => 'Reforço de emergência',
    'emergency_signing_message' => 'O teu plantel estava em níveis críticos. Foram contratados :count agentes livres para garantir que podes alinhar uma equipa: :players.',

    // Jogo perdido por falta de comparência
    'match_forfeit_title' => 'Jogo perdido por falta de comparência',
    'match_forfeit_message' => 'A tua equipa não conseguiu alinhar o mínimo de 7 jogadoras. O jogo foi registado como derrota por 0-3.',

    // Alterações de reputação
    'reputation_change_title' => 'Reputação do clube alterada',
    'reputation_improved' => 'A reputação do teu clube subiu para :tier. Patrocinadores, jogadoras e adeptos notam.',
    'reputation_declined' => 'A reputação do teu clube desceu para :tier. Está na hora de reconstruir e recuperar a glória passada.',

    // Empréstimo orçamental
    'budget_loan_taken_title' => 'Empréstimo orçamental concedido',
    'budget_loan_taken_message' => 'O clube obteve um empréstimo de :amount. A devolução de :repayment será descontada do orçamento da próxima época.',
    'budget_loan_repaid_title' => 'Empréstimo orçamental devolvido',
    'budget_loan_repaid_message' => 'O empréstimo orçamental foi devolvido (:repayment com juros).',
    'budget_loan_repaid_with_debt' => 'A devolução do empréstimo de :repayment excedeu o excedente disponível. O défice transita como dívida.',

    // Estádio
    'stadium_supplementary_committed_title' => 'Obras de bancadas iniciadas',
    'stadium_supplementary_committed_message' => 'Foram contratados :capacity lugares suplementares. Estarão prontos para :completion.',
    'stadium_stand_expansion_committed_title' => 'Ampliação de bancada aprovada',
    'stadium_stand_expansion_committed_message' => 'Foi aprovada uma ampliação de :capacity novos lugares permanentes. Estarão operacionais a :completion.',
    'stadium_rebuild_committed_title' => 'Reforma do estádio aprovada',
    'stadium_rebuild_committed_message' => 'Iniciou-se a reforma para uma lotação de :capacity lugares. O novo estádio abrirá a :completion.',
    'stadium_supplementary_completed_title' => 'Bancadas suplementares prontas',
    'stadium_supplementary_completed_message' => 'As novas bancadas já estão operacionais. Lotação total: :capacity lugares.',
    'stadium_stand_expansion_completed_title' => 'Nova bancada inaugurada',
    'stadium_stand_expansion_completed_message' => 'A bancada ampliada já está operacional. Lotação total: :capacity lugares.',
    'stadium_rebuild_completed_title' => 'Estádio inaugurado',
    'stadium_rebuild_completed_message' => 'O novo estádio foi inaugurado com uma lotação de :capacity lugares.',
    'stadium_uefa_upgrade_committed_title' => 'Melhoria UEFA aprovada',
    'stadium_uefa_upgrade_committed_message' => 'Iniciou-se a reforma para alcançar a Categoria UEFA :capacity. A nova categoria estará em vigor a :completion.',
    'stadium_uefa_upgrade_completed_title' => 'Nova Categoria UEFA',
    'stadium_uefa_upgrade_completed_message' => 'O teu estádio alcançou a Categoria UEFA :capacity.',
    'stadium_loan_drawn_title' => 'Empréstimo do estádio formalizado',
    'stadium_loan_drawn_message' => 'O banco financiou o projeto com :amount, a devolver em :years prestações anuais.',
    'stadium_loan_repaid_title' => 'Empréstimo do estádio devolvido',
    'stadium_loan_repaid_message' => 'O empréstimo de :amount foi devolvido na totalidade.',
    // Pedidos de sede (seleção ↔ clube, modo dual)
    'stadium_request_title' => '🏟️ :team quer jogar no teu estádio',
    'stadium_request_message' => ':team pede-te para ceder o teu estádio para o amigável contra :opponent a :date. Podes aceitar ou recusar o pedido na página do estádio.',
    'stadium_request_accepted_title' => 'Sede confirmada: :stadium',
    'stadium_request_accepted_message' => 'O clube aceitou ceder :stadium. O amigável será jogado lá.',
    'stadium_request_rejected_title' => 'Sede recusada: :stadium',
    'stadium_request_rejected_message' => 'O clube recusou ceder :stadium. Motivo: :excuse O amigável será jogado em campo neutro.',
    'commercial_window_open_title' => 'Janela comercial aberta',
    'commercial_window_open_message' => 'Até ao primeiro jogo da liga podes procurar patrocinadores na página Comercial para aumentar as tuas receitas e o teu teto salarial.',

    // Inscrição do plantel
    'squad_registration_required_title' => 'Inscrição de plantel necessária',
    'squad_registration_required_message' => 'Tens :count jogadoras por inscrever. Regista o teu plantel antes do início da época — as jogadoras não inscritas não poderão ser convocadas.',
    'unenrolled_before_window_close_title' => 'Jogadoras por inscrever — fecha a janela de :window',
    'unenrolled_before_window_close_message' => 'Tens :count jogadoras por inscrever. Esta é a tua última jornada para as registar antes do fecho da janela de transferências — sem dorsal não poderão ser convocadas.',

    // i18n-b review: missing injury types for AI-generated injuries
    'injury_ligament_damage' => 'lesão ligamentar',
    'injury_knee_injury' => 'lesão no joelho',
    'injury_unknown_injury' => 'uma lesão',
];
