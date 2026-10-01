<?php

return [
    // Transfer messages
    'transfer_complete' => 'Contratação concluída! :player juntou-se ao teu plantel.',
    'transfer_agreed' => ':message A contratação será concluída quando abrir a janela de :window.',
    'bid_exceeds_budget' => 'A oferta excede o teu orçamento de contratações.',
    'player_listed' => ':player colocada à venda. As ofertas podem chegar após a próxima jornada.',
    'player_unlisted' => ':player retirada da lista de contratações.',
    'cannot_sell_same_window' => 'Não podes vender :player — foi contratada recentemente e ainda não pode ser transferida.',
    'offer_rejected' => 'Oferta :team_de rejeitada.',
    'cannot_reject_release_clause_offer' => 'Não podes rejeitar esta oferta — atinge a cláusula de rescisão de :player, por isso a venda é obrigatória.',
    'offer_accepted_sale' => ':player vendida :team_a por :fee.',
    'offer_accepted_pre_contract' => 'Acordo fechado! :player será contratada por :team por :fee quando abrir a janela de :window.',
    'offer_accepted_intra_window' => 'Acordo fechado! :player sairá :team_a por :fee após o próximo jogo.',

    // Free agent signing
    'free_agent_signed' => ':player foi contratada pela tua equipa como jogadora livre!',
    'free_agent_agreed' => 'Acordo fechado! :player chegará como jogadora livre após o próximo jogo.',
    'not_free_agent' => 'Esta jogadora não está livre.',
    'free_agent_reputation_too_low' => 'Esta jogadora não tem interesse em assinar por um clube do teu nível de reputação.',
    'transfer_window_closed' => 'A janela de transferências está fechada.',
    'wage_budget_exceeded' => 'Contratar esta jogadora excederia o teu orçamento salarial.',
    'signing_exceeds_salary_cap' => 'Contratar :player por :wage/ano elevaria a tua massa salarial para :total, acima do teu teto salarial de :cap. Liberta :shortfall vendendo jogadoras primeiro.',
    'salary_cap_locked' => 'Estás acima do teu teto salarial. Vende jogadoras para voltar abaixo do limite antes de contratar ou renovar.',
    'pre_contract_exceeds_salary_cap' => 'Contratar :player por :wage/ano elevaria a tua massa salarial da próxima época para :total, acima do teu teto salarial de :cap. Faltam-te :shortfall.',

    // Bid/loan submission confirmations
    'bid_already_exists' => 'Já tens uma oferta pendente por esta jogadora.',
    'loan_request_submitted' => 'O teu pedido de empréstimo por :player foi enviado. Receberás resposta em breve.',

    // Loan messages
    'loan_agreed' => ':message O empréstimo começará quando abrir a janela de :window.',
    'loan_in_complete' => ':message O empréstimo já está ativo.',
    'already_on_loan' => ':player já está emprestada.',
    'loan_search_started' => 'Foi iniciada a procura de destino para :player. Serás notificado quando for encontrado um clube.',
    'loan_search_active' => ':player já tem uma procura de empréstimo ativa.',
    'loan_search_cancelled' => 'A procura de empréstimo de :player foi cancelada.',
    'loan_offer_accepted' => ':player emprestada :team_a.',
    'loan_offer_accepted_pre_window' => ':player será emprestada :team_a quando abrir a janela de :window.',
    'loan_offer_agreed_intra_window' => ':player será emprestada :team_a após o próximo jogo.',

    // Contract messages
    'renewal_agreed' => ':player aceitou uma extensão de :years anos a :wage/ano (efetiva a partir da próxima época).',
    'renewal_failed' => 'Não foi possível processar a renovação.',
    'renewal_declined' => 'Decidiste não renovar com :player. Sairá no final da época.',
    'renewal_reconsidered' => 'Reconsideraste a renovação de :player.',
    'cannot_renew' => 'Esta jogadora não pode receber uma oferta de renovação.',
    'renewal_invalid_offer' => 'A oferta tem de ser superior a zero.',

    // Pre-contract messages
    'pre_contract_accepted' => ':player aceitou a tua oferta de pré-contrato! Juntar-se-á à tua equipa no final da época.',
    'pre_contract_rejected' => ':player rejeitou a tua oferta de pré-contrato. Tenta melhorar as condições salariais.',
    'pre_contract_not_available' => 'As ofertas de pré-contrato só estão disponíveis entre janeiro e maio.',
    'player_not_expiring' => 'Esta jogadora não tem o contrato no último ano.',
    'pre_contract_submitted' => 'Oferta de pré-contrato enviada. A jogadora responderá nos próximos dias.',
    'pre_contract_result_accepted' => ':player aceitou a tua oferta de pré-contrato!',
    'pre_contract_result_rejected' => ':player rejeitou a tua oferta de pré-contrato.',

    // Scout messages
    'scout_search_started' => 'O olheiro iniciou a procura.',
    'scout_already_searching' => 'Já tens uma procura ativa. Cancela-a primeiro ou aguarda os resultados.',
    'scout_search_cancelled' => 'Procura do olheiro cancelada.',
    'scout_search_deleted' => 'Procura eliminada.',
    'scout_search_limit' => 'Atingiste o limite de procuras (máximo :max). Elimina uma procura antiga para iniciar uma nova.',

    // Shortlist messages
    'shortlist_added' => ':player adicionada à tua lista de observação.',
    'shortlist_removed' => ':player removida da tua lista de observação.',
    'shortlist_full' => 'A tua lista de observação está cheia (máximo :max jogadoras).',

    // Budget messages
    'budget_saved' => 'Atribuição de orçamento guardada.',
    'budget_no_projections' => 'Não foram encontradas projeções financeiras.',

    // Stadium / abonos
    'season_tickets_saved' => 'Preços dos passes guardados.',
    'season_tickets_locked' => 'Os preços dos passes já estão bloqueados para esta época.',

    // Season messages
    'budget_exceeds_surplus' => 'A atribuição total excede o superavit disponível.',
    'budget_minimum_tier' => 'Todas as áreas de infraestrutura têm de ser pelo menos Nível 1.',

    // Infrastructure upgrades
    'infrastructure_upgraded' => ':area melhorada para Nível :tier.',
    'infrastructure_upgrade_invalid_area' => 'Área de infraestrutura inválida.',
    'infrastructure_upgrade_not_higher' => 'O nível alvo tem de ser superior ao atual.',
    'infrastructure_upgrade_max_tier' => 'O nível máximo é 4.',
    'infrastructure_upgrade_insufficient_budget' => 'Orçamento de contratações insuficiente. A melhoria custa :cost.',
    'investment_downgrade_not_lower' => 'Escolhe um nível inferior ao atual.',
    'investment_saved' => 'Plano guardado.',
    'investment_locked_no_edit' => 'A época está em curso — podes melhorar a qualquer momento, mas o plano já não pode ser redistribuído livremente.',
    'investment_downgrade_staged' => 'Redução programada — produz efeitos na próxima época.',
    'investment_downgrade_cleared' => 'Redução programada cancelada.',

    // Onboarding
    'welcome_to_team' => 'Bem-vindo :team_a! A tua época espera-te.',

    // Season
    'season_not_complete' => 'Não é possível iniciar uma nova época - a época atual ainda não terminou.',

    // Academy
    'academy_player_promoted' => ':player foi subida à equipa principal.',
    'academy_player_dismissed' => ':player foi dispensada da academia.',
    'academy_player_loaned' => ':player foi emprestada.',
    'academy_must_decide_21' => 'As jogadoras com 21+ anos serão promovidas automaticamente à equipa principal.',

    // Reserve team (filial)
    'reserve_player_called_up' => ':player foi convocada para a equipa principal.',
    'reserve_player_sent_back' => ':player voltou à equipa B.',
    'reserve_player_call_up_blocked_full' => 'O plantel da equipa principal está completo. Liberta um número antes de subir mais jogadoras.',
    'reserve_player_call_up_blocked' => 'Esta jogadora não pode ser convocada.',
    'player_sent_down_to_reserve' => ':player foi enviada para a equipa B.',
    'send_down_not_allowed' => 'Esta jogadora não pode ser enviada para a equipa B.',
    'reserve_move_blocked_by_deal' => ':player tem uma transferência ou pré-contrato acordado e não pode mudar de equipa até estar concluído.',
    'send_down_squad_too_small' => 'Não é possível enviar para a equipa B — a equipa principal tem de ter pelo menos :min jogadoras.',
    'send_down_position_minimum' => 'Não é possível enviar para a equipa B — a equipa principal precisa de pelo menos :min :group.',
    'reserve_player_promoted' => ':player subiu à equipa principal.',

    // Player release messages
    'player_released' => ':player foi libertada. Indemnização paga: :severance.',
    'release_not_your_player' => 'Só podes libertar jogadoras da tua própria equipa.',
    'release_on_loan' => 'Não é possível libertar uma jogadora emprestada.',
    'release_has_agreed_transfer' => 'Não é possível libertar uma jogadora com uma transferência acordada.',
    'release_has_pre_contract' => 'Não é possível libertar uma jogadora com um pré-contrato assinado.',
    'release_squad_too_small' => 'Não é possível libertar — o teu plantel tem de ter pelo menos :min jogadoras.',
    'release_position_minimum' => 'Não é possível libertar — precisas de pelo menos :min :group.',

    // Rescisión de mutuo acuerdo
    'mutual_termination_completed' => 'Rescisão por mútuo acordo com :player concluída. Indemnização: :amount.',
    'severance_invalid_method' => 'Forma de pagamento inválida.',
    'severance_loan_active' => 'Já tens um empréstimo ativo. Não podes pedir outro.',
    'severance_loan_unavailable' => 'Não é possível solicitar o empréstimo neste momento.',

    // Squad-minimum guards on promote / demote / list / accept
    'promote_squad_too_small' => 'Não é possível subir — a equipa B tem de ter pelo menos :min jogadoras.',
    'promote_position_minimum' => 'Não é possível subir — a equipa B precisa de pelo menos :min :group.',
    'demote_squad_too_small' => 'Não é possível descer à equipa B — a equipa principal tem de ter pelo menos :min jogadoras.',
    'demote_position_minimum' => 'Não é possível descer à equipa B — a equipa principal precisa de pelo menos :min :group.',
    'list_for_sale_squad_too_small' => 'Não é possível pôr à venda — o teu plantel tem de ter pelo menos :min jogadoras.',
    'list_for_sale_position_minimum' => 'Não é possível pôr à venda — precisas de pelo menos :min :group.',
    'list_for_loan_squad_too_small' => 'Não é possível emprestar — o teu plantel tem de ter pelo menos :min jogadoras.',
    'list_for_loan_position_minimum' => 'Não é possível emprestar — precisas de pelo menos :min :group.',
    'accept_offer_squad_too_small' => 'Não é possível aceitar a oferta — o teu plantel tem de ter pelo menos :min jogadoras.',
    'accept_offer_position_minimum' => 'Não é possível aceitar a oferta — precisas de pelo menos :min :group.',
    'accept_loan_squad_too_small' => 'Não é possível aceitar o empréstimo — o teu plantel tem de ter pelo menos :min jogadoras.',
    'accept_loan_position_minimum' => 'Não é possível aceitar o empréstimo — precisas de pelo menos :min :group.',

    'cannot_loan_free_agent' => 'Não é possível emprestar uma jogadora livre. Contrata-a diretamente.',

    // Pending actions
    'action_required' => 'Há ações pendentes que tens de resolver antes de continuar.',
    'action_required_short' => 'Ação Necessária',

    // Tactical presets
    'preset_saved' => 'Tática guardada.',
    'preset_updated' => 'Tática atualizada.',
    'preset_deleted' => 'Tática eliminada.',
    'preset_limit_reached' => 'Máximo de 3 táticas guardadas atingido.',

    // Game management
    'game_deleted' => 'O jogo está a ser eliminado.',
    'game_limit_reached' => 'Atingiste o limite máximo de 3 jogos. Elimina um para criar um novo.',
    'career_mode_requires_invite' => 'Club Manager e Pro Manager requerem convite. Joga o Mundial grátis!',
    'tournament_mode_requires_access' => 'O modo torneio requer acesso. Contacta um administrador para começar.',
    'invalid_pro_manager_team' => 'Escolhe um dos clubes mostrados — o Pro Manager começa na Primeira Federação.',
    'invalid_academy_club' => 'O clube de academia selecionado não é válido.',
    'club_has_no_filial' => 'Este clube não tem equipa B disponível.',
    'cannot_apply_to_own_club' => 'Não podes candidatar-te a emprego no teu próprio clube.',

    // Pre-match confirmation
    'pre_match_title' => 'Antevisão do Jogo',
    'pre_match_no_lineup' => 'Não tens um onze inicial configurado.',
    'pre_match_incomplete' => 'O teu onze inicial tem menos de 11 jogadoras.',
    'pre_match_unavailable_injured' => 'Tens uma jogadora lesionada no teu onze inicial.',
    'pre_match_unavailable_suspended' => 'Tens uma jogadora suspensa no teu onze inicial.',
    'pre_match_unavailable_multiple' => 'Tens jogadoras indisponíveis no teu onze inicial.',
    'pre_match_auto_explanation' => 'Se não o alterares, a tua equipa técnica escolherá o melhor onze inicial entre as jogadoras disponíveis.',
    'pre_match_warning_title' => 'O teu onze inicial precisa de atenção',
    'pre_match_play' => 'Jogar Jogo',
    'pre_match_continue' => 'Continuar',
    'pre_match_edit_lineup' => 'Editar Onze Inicial',
    'pre_match_reason_injured' => 'lesionada',
    'pre_match_reason_suspended' => 'suspensa',
    'pre_match_starting_xi' => 'Onze Titular',
    'pre_match_no_lineup_set' => 'Onze inicial não configurado',
    'pre_match_auto_lineup' => 'Deixar a equipa técnica modificar o onze inicial automaticamente quando houver jogadoras indisponíveis.',
    'pre_match_auto_select_done' => 'Foi selecionado automaticamente o melhor onze inicial entre as jogadoras disponíveis.',

    // Matchday advance
    'advance_failed' => 'Erro ao avançar a jornada. Tenta de novo.',

    // Fast mode
    'fast_mode_enabled' => 'Modo rápido ativado. O teu treinador adjunto orientará a equipa.',
    'fast_mode_disabled' => 'Modo rápido desativado. Voltaste a ter o controlo.',
    'fast_mode_action_required' => 'Uma ação requer a tua atenção. Sai do modo rápido para a resolveres.',
    'fast_mode_blocked_live_match' => 'Termina o jogo atual antes de ativares o modo rápido.',
    'fast_mode_blocked_tournament' => 'O modo rápido não está disponível no modo torneio.',
    'fast_mode_advance_failed_retry' => 'Não foi possível simular a jornada. Tenta de novo.',

    // Budget loan messages
    'budget_loan_approved' => 'Empréstimo de :amount aprovado e adicionado ao teu orçamento de contratações.',
    'loan_not_available' => 'Um empréstimo orçamental não está disponível neste momento.',
    'loan_below_minimum' => 'O montante do empréstimo está abaixo do mínimo.',
    'loan_exceeds_maximum' => 'O montante do empréstimo excede o máximo permitido.',

    'stadium_supplementary_committed' => 'Obras iniciadas: :seats lugares suplementares estarão prontos em 30 dias.',
    'stadium_stand_expansion_committed' => 'Ampliação de bancada aprovada: :seats novos lugares permanentes estarão prontos na próxima época.',
    'stadium_rebuild_committed' => 'Reconstrução do estádio aprovada. Nova lotação objetivo: :capacity.',
    'stadium_active_project_exists' => 'Já tens um projeto em curso. Espera que termine antes de iniciares outro.',
    'stadium_supplementary_too_few_seats' => 'Tens de adicionar pelo menos um lugar suplementar.',
    'stadium_supplementary_exceeds_cap' => 'Excede o limite de bancadas suplementares permitidas.',
    'stadium_stand_expansion_too_few_seats' => 'A ampliação de bancada não atinge o mínimo de lugares exigido.',
    'stadium_stand_expansion_exceeds_cap' => 'A ampliação de bancada excede o tamanho máximo permitido.',
    'stadium_rebuild_reputation_too_low' => 'A tua reputação ainda não permite uma reconstrução integral do estádio.',
    'stadium_rebuild_must_be_larger' => 'A lotação objetivo tem de ser maior do que a atual.',
    'stadium_rebuild_exceeds_max_capacity' => 'A lotação objetivo excede o teto que a tua reputação e receitas permitem financiar.',
    'stadium_invalid_financing' => 'Financiamento inválido.',
    'stadium_insufficient_budget' => 'Não tens orçamento suficiente para pagar o projeto a pronto.',
    'stadium_loan_exceeds_cap' => 'O empréstimo solicitado excede o teto autorizado pelo banco.',
    'stadium_uefa_upgrade_committed' => 'Melhoria UEFA iniciada: o estádio alcançará a Categoria :level na próxima época.',
    'stadium_uefa_already_max' => 'O teu estádio já está na máxima categoria UEFA.',
    'stadium_uefa_capacity_floor' => 'A lotação atual não atinge o mínimo exigido pela próxima categoria UEFA.',
    'stadium_uefa_no_base_level' => 'O teu estádio não tem categoria UEFA atribuída. Amplia a lotação primeiro.',

    'naming_rights_accepted' => 'Acordo de naming rights assinado com :sponsor. O estádio foi renomeado.',
    'stadium_renamed' => 'Estádio renomeado para :name.',
    'naming_rights_window_closed' => 'A identidade do estádio só pode ser alterada na pré-época, até ao primeiro jogo de liga.',
    'naming_rights_deal_active' => 'Já existe um acordo de naming rights ativo: o patrocinador detém o nome do estádio até expirar.',
    'naming_rights_offer_unavailable' => 'Essa oferta de naming rights já não está disponível.',
    'stadium_already_renamed' => 'O estádio já foi renomeado esta época.',
    'naming_rights_search_complete' => '{0}A agência não encontrou novos patrocinadores.|{1}A agência trouxe :count oferta de patrocínio.|[2,*]A agência trouxe :count ofertas de patrocínio.',
    'naming_rights_search_cooldown' => 'A tua agência comercial ainda está a sondar o mercado. Espera uns dias antes de voltares a procurar.',
    'naming_rights_search_unaffordable' => 'Não tens orçamento para a comissão da agência comercial.',
    'naming_rights_board_full' => 'Já tens o máximo de ofertas sobre a mesa. Aceita uma ou rejeita-as antes de procurares mais.',
];
