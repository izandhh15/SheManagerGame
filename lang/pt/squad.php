<?php

return [
    // Título da página
    'squad' => 'Plantel',
    'first_team' => 'Primeira Equipa',
    'development' => 'Desenvolvimento',
    'stats' => 'Estatísticas',

    // Grupos de posições
    'goalkeepers' => 'Guarda-redes',
    'defenders' => 'Defesas',
    'midfielders' => 'Médias',
    'forwards' => 'Avançadas',
    'goalkeepers_short' => 'GR',
    'defenders_short' => 'DEF',
    'midfielders_short' => 'MÉD',
    'forwards_short' => 'AVA',

    // Colunas
    'years_abbr' => 'anos',
    'fitness' => 'ENE',
    'morale' => 'MOR',
    'overall' => 'Média',
    'overall_short' => 'MÉD',
    'attack_short' => 'ATQ',
    'defense_short' => 'DEF',
    'attack_xg_label' => 'Ataque xG',
    'defense_xg_label' => 'Defesa xG',

    // Rótulos de estado
    'on_loan' => 'Emprestada',
    'loaned_from' => 'Emprestada por',
    'loaned_to' => 'Emprestada para',
    'leaving_free' => 'Vai embora (Livre)',
    'renewed' => 'Renovado',
    'sale_agreed' => 'Venda Acordada',
    'retiring' => 'Vai reformar-se',
    'listed' => 'À Venda',
    'list_for_sale' => 'Colocar à Venda',
    'unlist_from_sale' => 'Retirar da Venda',
    'loan_out' => 'Emprestar',
    'release_player' => 'Dispensar',
    'release_confirm_title' => 'Dispensar Jogadora',
    'release_confirm_message' => 'Tens a certeza de que queres dispensar a :player? Esta ação não pode ser anulada.',
    'release_severance_label' => 'Custo de indemnização',
    'release_remaining_contract' => 'Contrato restante',
    'release_years_remaining' => ':years ano(s)',
    'release_confirm_button' => 'Confirmar Dispensa',
    'mutual_terminate' => 'Rescindir por mútuo acordo',
    'loan_searching' => 'À procura de destino de empréstimo',
    'contract_expiring' => 'Contrato a expirar',

    // Resumo
    'wage_bill' => 'Massa Salarial',
    'per_year' => '/ano',
    'avg_fitness' => 'Média de Energia',
    'avg_morale' => 'Média de Moral',
    'low' => 'baixo',

    // Gestão de contratos
    'free_transfer' => 'Livre',
    'let_go' => 'Deixar Sair',
    'pre_contract_signed' => 'Pré-contrato assinado',
    'new_wage_from_next' => 'Novo salário desde a próxima época',
    'has_pre_contract_offers' => 'Tem ofertas de pré-contrato!',
    'renew' => 'Renovar',
    'expires_in_days' => '{0}Expira hoje|{1}Expira em :count dia|[2,*]Expira em :count dias',

    // Validação do onze inicial
    'formation_position_mismatch' => 'A formação :formation exige :required :position, mas selecionaste :actual.',
    'player_not_available' => 'Uma ou mais jogadoras selecionadas não estão disponíveis.',

    // Onze inicial
    'formation' => 'Formação',
    'mentality' => 'Mentalidade',
    'auto_select' => 'Seleção Automática',
    'opponent' => 'Adversário',
    'need' => 'precisas de',

    // Compatibilidade
    'natural' => 'Natural',
    'very_good' => 'Muito Bom',
    'good' => 'Bom',
    'okay' => 'Aceitável',
    'poor' => 'Mau',
    'unsuitable' => 'Inadequado',

    // Editor do onze inicial
    'pitch' => 'Campo',

    // Observação do adversário
    'injured' => 'lesionadas',
    'suspended' => 'suspensas',

    // Treinador adjunto
    'coach_recommendations' => 'Recomendações',
    'coach_no_tips' => 'Sem recomendações especiais para este jogo.',
    'coach_defensive_recommended' => 'Adversário mais forte. A mentalidade defensiva reduz os seus golos esperados em 30%.',
    'coach_attacking_recommended' => 'Tens vantagem. Uma mentalidade ofensiva pode maximizar os teus golos.',
    'coach_risky_formation' => 'A tua formação ofensiva contra um adversário superior vai dar-lhes mais oportunidades. Considera uma mais defensiva.',
    'coach_home_advantage' => 'Jogam em casa (+0,15 golos esperados).',
    'coach_critical_fitness' => ':names com energia crítica (<50). Risco de lesão 2x maior. Considera rodá-las.',
    'coach_low_fitness' => ':count jogadora(s) com energia baixa (<70). Rendem pior e têm maior risco de lesão.',
    'coach_low_morale' => ':count jogadora(s) com moral baixa. Terão pior rendimento no jogo.',
    'coach_bench_frustration' => ':count jogadora(s) de qualidade sem jogar e a perder moral. Roda para as manteres contentes.',
    'coach_opponent_expected_label' => 'Previsto',
    'coach_opponent_defensive_setup' => 'Adversário previsto com :formation (:mentality). Considera uma abordagem ofensiva para os desbloquear.',
    'coach_opponent_attacking_setup' => 'Adversário previsto com :formation (:mentality). Vão deixar espaços — uma defesa sólida pode aproveitá-los.',
    'coach_opponent_deep_block' => 'Adversário com 5 defesas. Amplitude e paciência serão chave.',
    'coach_out_of_position' => ':names fora de posição. Renderão pior no jogo.',
    'mentality_defensive' => 'Defensiva',
    'mentality_balanced' => 'Equilibrada',
    'mentality_attacking' => 'Ofensiva',

    // Motivos de indisponibilidade
    'suspended_matches' => 'Suspensa (:count jogo)|Suspensa (:count jogos)',
    'injured_generic' => 'Lesionada',
    'injury_return_date' => 'baixa até :date',

    // Tipos de lesão
    'injury_muscle_fatigue' => 'Fadiga muscular',
    'injury_muscle_strain' => 'Distensão muscular',
    'injury_calf_strain' => 'Distensão do gémeo',
    'injury_ankle_sprain' => 'Entorse do tornozelo',
    'injury_groin_strain' => 'Distensão do adutor',
    'injury_hamstring_tear' => 'Rutura dos isquiotibiais',
    'injury_knee_contusion' => 'Contusão do joelho',
    'injury_metatarsal_fracture' => 'Fratura do metatarso',
    'injury_acl_tear' => 'Rutura do ligamento cruzado',
    'injury_achilles_rupture' => 'Rutura do tendão de Aquiles',

    // Página de desenvolvimento
    'ability' => 'Habilidade',
    'playing_time' => 'Minutos',
    'high_potential' => 'Alto Potencial',
    'growing' => 'Em crescimento',
    'declining' => 'Em declínio',
    'peak' => 'No auge',
    'all' => 'Todas',
    'no_players_match_filter' => 'Nenhuma jogadora corresponde ao filtro selecionado.',
    'pot' => 'POT',
    'apps' => 'J',
    'projection' => 'Projeção',
    'potential' => 'Potencial',
    'potential_range' => 'Intervalo de Potencial',
    'starter_bonus' => 'bónus de titular',
    'needs_appearances' => 'Precisa de :count+ jogos para o bónus de titular',
    'qualifies_starter_bonus' => 'Qualifica-se para o bónus de titular (+50% de desenvolvimento)',

    // Página de estatísticas
    'goals' => 'G',
    'assists' => 'A',
    'goal_contributions' => 'G+A',
    'goals_per_game' => 'G/P',
    'own_goals' => 'PP',
    'yellow_cards' => 'TA',
    'red_cards' => 'TR',
    'clean_sheets' => 'PC',
    'appearances' => 'Jogos',
    'bookings' => 'Advertências',
    'click_to_sort' => 'Clica nos cabeçalhos das colunas para ordenar',

    // Destaques de estatísticas
    'top_in_squad' => 'Máximo no plantel',

    // Rótulos da legenda
    'legend_apps' => 'Jogos',
    'legend_goals' => 'Golos',
    'legend_assists' => 'Assistências',
    'legend_contributions' => 'Contribuições de Golo',
    'legend_own_goals' => 'Golos na Própria',
    'legend_mvp' => 'Prémios MVP do jogo',
    'legend_clean_sheets' => 'Jogos sem sofrer golos (só GR)',

    // Dorsal
    'assign_number' => 'Atribuir dorsal',
    'number_taken' => 'Este dorsal já está atribuído',
    'number_updated' => 'Dorsal atualizado',
    'number_invalid' => 'O dorsal deve estar entre 1 e 99',

    // Modal de detalhe da jogadora
    'abilities' => 'Habilidades',
    'overall_full' => 'Média',
    'fitness_full' => 'Energia',
    'morale_full' => 'Moral',
    'season_stats' => 'Estatísticas da Época',
    'clean_sheets_full' => 'Jogos sem sofrer golos',
    'goals_conceded_full' => 'Golos Sofridos',
    'discovered' => 'Descoberto',
    'origin' => 'Origem',
    'joined' => 'Chegada',
    'origin_academy' => 'Filial',
    'origin_free_agent' => 'Agente livre',
    'precontract_banner_title' => 'Pré-contrato assinado',
    'precontract_banner_body' => 'Ainda não está no teu plantel — junta-se no início da época :year a custo zero.',
    'career_history' => 'Percurso',
    'no_career_history' => 'Ainda não há épocas concluídas.',

    // Formação
    'academy' => 'Formação',
    'promote_to_first_team' => 'Subir à Primeira Equipa',
    'academy_tier' => 'Nível da Formação',
    'academy_players' => 'Jogadoras',
    'no_academy_prospects' => 'Não há formandos disponíveis.',
    'academy_explanation' => 'Os novos formandos chegam no início de cada época conforme o teu investimento na formação.',
    'academy_dismiss' => 'Dispensar',
    'academy_dismiss_confirm' => 'Tens a certeza? A jogadora será dispensada de forma permanente.',
    'academy_dismiss_desc' => 'A jogadora é dispensada do clube de forma permanente.',
    'academy_loan_out' => 'Emprestar',
    'academy_loan_desc' => 'A jogadora sai emprestada com desenvolvimento acelerado (1.5x) e regressa no final da época.',
    'academy_promote' => 'Subir',
    'academy_promote_desc' => 'A jogadora integra a primeira equipa com contrato profissional.',
    'academy_on_loan' => 'Emprestada',
    'academy_seasons' => ':count época|:count épocas',
    // Texto de ajuda da formação
    'academy_help_toggle' => 'Como funciona a formação?',
    'academy_help_development' => 'A formação funciona como a tua equipa B, gerando jogadoras calibradas ao nível do teu plantel. As formandas desenvolvem-se ao longo da época e podem subir à primeira equipa quando estiverem prontas.',
    'academy_help_actions_title' => 'Ações disponíveis',
    'academy_help_promote' => 'Subir — integra permanentemente a primeira equipa com contrato profissional',
    'academy_help_loan' => 'Emprestar — desenvolve-se mais rápido emprestada e regressa no final da época',
    'academy_help_dismiss' => 'Dispensar — abandona o clube de forma permanente',
    'academy_help_age_rule' => 'As jogadoras que completem 21 anos serão promovidas automaticamente à primeira equipa no início da época.',

    'academy_tier_0' => 'Formação Mínima',
    'academy_tier_1' => 'Formação Básica',
    'academy_tier_2' => 'Boa Formação',
    'academy_tier_3' => 'Formação de Elite',
    'academy_tier_4' => 'Formação de Classe Mundial',
    'academy_tier_unknown' => 'Desconhecido',

    // Equipa filial
    'reserve_team' => 'Filial',
    'reserve_squad' => 'Plantel da filial',
    'no_reserve_players' => 'Não há jogadoras na filial.',
    'call_up' => 'Subir',
    'call_up_to_first_team' => 'Subir à primeira equipa',
    'send_back' => 'Descer',
    'send_back_to_reserve' => 'Descer à filial',
    'send_down_to_reserve' => 'Descer à filial',
    'send_down_to_reserve_confirm' => 'Descer esta jogadora sub-23 à filial?',
    'called_up_indicator' => 'Na primeira equipa',
    'homegrown_indicator' => 'Formada em casa',
    'actions' => 'Ações',
    'reserve_help_toggle' => 'Como funciona a filial?',
    'reserve_help_development' => 'A tua filial é a formação oficial — as jogadoras pertencem à filial, não à primeira equipa. Cada época chegam novos formandos conforme o teu investimento na formação, que se desenvolvem junto com o resto da filial.',
    'reserve_help_age_rule' => 'As jogadoras que completam 24 anos passam automaticamente à primeira equipa no final da época. A primeira equipa pode subir jogadoras da filial a qualquer momento.',
    'reserve_help_actions_title' => 'Ações disponíveis',
    'reserve_help_call_up' => 'Subir - a jogadora integra a primeira equipa por empréstimo e pode disputar jogos com a primeira equipa',
    'reserve_help_send_back' => 'Descer - devolve à filial a jogadora subida',

    // Texto de ajuda do onze inicial
    'lineup_help_toggle' => 'Como funciona o onze inicial?',
    'lineup_help_intro' => 'Escolhe 11 jogadoras para cada jogo. A tua formação, a energia e a compatibilidade posicional afetam o rendimento.',
    'lineup_help_formation_title' => 'Formação e Mentalidade',
    'lineup_help_formation_desc' => 'A formação determina que posições há disponíveis em campo. As jogadoras rendem melhor na sua posição natural.',
    'lineup_help_compatibility_natural' => 'Natural — a jogadora está na sua melhor posição, rendimento completo.',
    'lineup_help_compatibility_good' => 'Muito Bom — joga sem penalização. Bom — penalização de 25% no jogo.',
    'lineup_help_compatibility_poor' => 'Mau / Inadequado — penalização de 25%. Evita-o se possível.',
    'lineup_help_mentality_desc' => 'A mentalidade afeta o quão ofensiva ou defensiva a tua equipa joga.',
    'lineup_help_condition_title' => 'Energia e Moral',
    'lineup_help_condition_desc' => 'As jogadoras com baixa energia ou moral rendem pior. Roda o plantel para as manteres frescas.',
    'lineup_help_fitness' => 'A energia desce durante cada jogo e recupera entre jornadas. As jogadoras começam os jogos com o seu nível de energia atual — gere as rotações para as manteres frescas.',
    'lineup_help_morale' => 'A moral é afetada pelos resultados, pelos minutos jogados e pela situação contratual.',
    'lineup_help_auto' => 'Usa "Seleção Automática" para o sistema escolher o melhor XI disponível para a tua formação.',

    // Seleção do plantel (onboarding do torneio)
    'squad_selection_title' => 'Seleciona a tua convocatória',
    'squad_selection_subtitle' => 'Escolhe 26 jogadoras para o torneio',
    'confirm_squad' => 'Confirmar',
    'squad_confirmed' => 'Convocatória confirmada!',
    'invalid_selection' => 'Seleção inválida. Verifica as jogadoras selecionadas.',
    'download_squad' => 'Descarregar convocatória',
    'squad_list' => 'Lista de convocadas',
    'called_up_badge' => 'Convocada',

    // Gráfico radar
    'radar_gk' => 'Baliza',
    'radar_def' => 'Defesa',
    'radar_mid' => 'Meio-campo',
    'radar_att' => 'Ataque',
    'radar_fit' => 'Energia',
    'radar_mor' => 'Moral',
    'radar_overall' => 'Média',

    // Inscrição
    'not_registered' => 'Não inscrita',
    'too_many_first_team' => 'Máximo de 25 inscrições da primeira equipa (dorsais 1-25).',
    'drag_to_assign' => 'Arrasta uma jogadora para aqui para a atribuir',

    // Posicionamento na grelha
    'drag_or_tap' => 'Toca numa célula ou arrasta a jogadora',
    'select_player_for_slot' => 'Seleciona uma jogadora da lista',

    // KPIs do painel do plantel
    'squad_size' => 'Plantel',
    'avg_age' => 'Idade Média',
    'condition' => 'Estado',
    'squad_value' => 'Valor do Plantel',

    // Modos de vista
    'tactical' => 'Tático',
    'planning' => 'Planeamento',
    'numbers' => 'Dorsais',

    // Cabeçalhos da tabela
    'mvp' => 'MVP',
    'cards' => 'Cartões',
    'avg_ovr' => 'Média',

    // Filtros
    'available' => 'Disponíveis',
    'unavailable' => 'Indisponíveis',
    'clear_filters' => 'Limpar filtros',

    // Barra lateral
    'squad_analysis' => 'Análise do Plantel',
    'alerts' => 'Alertas',
    'position_depth' => 'Profundidade Posicional',
    'age_profile' => 'Perfil de Idade',
    'contract_watch' => 'Contratos',
    'expiring_this_season' => 'Expiram esta época',
    'no_contract_issues' => 'Sem contratos pendentes',
    'highest_earners' => 'Salários mais altos',

    // Dicas
    'tooltip_fitness' => 'Energia média — determina a energia inicial nos jogos e afeta o rendimento',
    'tooltip_morale' => 'Moral média — afeta motivação e consistência',
    'tooltip_avg_overall' => 'Pontuação média do plantel',

    // Alertas
    'alert_many_injured' => ':count jogadoras lesionadas — considera rodar as titulares',
    'alert_low_morale' => ':count jogadoras com moral baixa',
    'alert_low_fitness' => ':count jogadoras com baixa energia',
    'alert_thin_position' => 'Apenas :count jogadora(s) em :position — pouca cobertura',
    'alert_no_cover' => 'Sem cobertura em :position',
    'alert_no_natural_cover' => 'Sem :position natural — cobertura parcial disponível',
    'alert_window_closing' => 'A janela de transferências fecha a :date',

    // Grelha de dorsais
    'number_grid' => 'Dorsais',
    'assigned' => 'Atribuído',
    'available_number' => 'Disponível',

    // Cabeçalhos de coluna (novo design)
    'player' => 'Jogadora',
    'pos' => 'Pos',
    'players_count' => 'jogadoras',
    'dev_status_label' => 'Estado',

    // Rótulos de moral
    'morale_ecstatic' => 'Eufórica',
    'morale_happy' => 'Contente',
    'morale_content' => 'Normal',
    'morale_frustrated' => 'Frustrada',
    'morale_unhappy' => 'Descontente',

    // Separadores e rótulos do onze inicial
    'tactics' => 'Tática',
    'defensive_line' => 'Linha Defensiva',
    'unsaved_changes' => 'Alterações não guardadas',

    // Redesign do onze inicial
    'opponent_goal' => 'Baliza Adversária',
    'available_players' => 'Jogadoras Disponíveis',
    'substitutes' => 'Suplentes',
    'lineup_overview' => 'Resumo do Onze Inicial',

    // Presets táticos
    'presets' => 'Guardadas',
    'save_preset' => 'Guardar tática',
    'preset_name' => 'Nome',
    'preset_name_placeholder' => 'Ex: Titulares, Taça, Suplentes...',
    'preset_apply_now' => 'Usar esta tática no próximo jogo',
    'save_and_confirm' => 'Guardar e confirmar',
    'preset_delete_confirm' => 'Eliminar esta tática guardada?',
    'preset_overwrite_toggle' => 'Sobrescrever tática',
    'preset_replace_required_hint' => 'Já tens três táticas guardadas. Escolhe qual substituir pelo onze inicial atual.',

    // Dorsais
    'number' => 'Dorsal',

    // Inscrição do plantel
    'registration' => 'Inscrição',
    'registration_title' => 'Inscrição do Plantel',
    'registration_subtitle' => 'Atribui dorsais para a época',
    'first_team_slots' => 'Primeira Equipa (1-25)',
    'academy_slots' => 'Formação (26-99)',
    'unregistered_players' => 'Não inscritas',
    'empty_slot' => 'Vazio',
    'save_registration' => 'Guardar',
    'registration_saved' => 'Inscrição guardada',
    'registered_count' => ':count inscritas',
    'academy_age_limit' => 'Só as jogadoras marcadas Sub-23 podem inscrever-se com dorsal da formação (26-99)',
    'registration_rules_title' => 'Regras de Inscrição',
    'registration_rule_first_team' => 'As jogadoras da primeira equipa usam dorsais de 1 a 25.',
    'registration_rule_academy' => 'Os dorsais da formação (26-99) estão reservados a jogadoras marcadas Sub-23 (menores de 24 a 1 de janeiro).',
    'registration_rule_u23_badge' => 'As jogadoras marcadas Sub-23 são elegíveis para dorsal da formação toda a época, mesmo que completem 24 a meio da época — a elegibilidade é fixada segundo a sua idade a 1 de janeiro.',
    'registration_rule_unregistered' => 'As jogadoras não inscritas não podem ser convocadas para os jogos.',
    'registration_readonly' => 'Podes inscrever jogadoras e modificar dorsais apenas durante as janelas de transferências.',
    'u23_badge_label' => 'Sub-23',
    'u23_badge_tooltip' => 'Apta para dorsal da formação — menor de 24 a 1 de janeiro da época.',
];
