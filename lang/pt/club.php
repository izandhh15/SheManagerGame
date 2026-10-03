<?php

return [
    'hub_title' => 'Clube',

    'nav' => [
        'finances' => 'Finanças',
        'investment' => 'Funcionários',
        'stadium' => 'Estádio',
        'commercial' => 'Comercial',
        'reputation' => 'Reputação',
        'matchday' => 'Bilheteira',
    ],

    'commercial' => [
        'title' => 'Patrocínios comerciais',
        'intro' => 'Procura patrocinadores para gerar receitas recorrentes que reforcem o orçamento do clube.',
        'naming_rights_title' => 'Naming rights do estádio',
        'seek_explainer' => 'Contrata uma agência para sondar patrocinadores. Cada procura custa :fee e tens de esperar :days dias entre procuras.',
        'seek_button' => 'Procurar patrocinadores (:fee)',
        'seek_cooldown' => '{1} Poderás voltar a procurar em :days dia.|[2,*] Poderás voltar a procurar em :days dias.',
        'seek_unaffordable' => 'Não tens orçamento para a comissão da agência (:fee).',
        'slot_shirt' => 'a camisola',
        'slot_ad_board' => 'os painéis publicitários',
        'shirt_title' => 'Patrocinador da camisola',
        'shirt_intro' => 'O logótipo no peito: o espaço mais cobiçado. Todos os meses chegam ofertas sozinhas; se vais em primeiro, ligam as grandes marcas; se vais em último, a universidade local ou a loja do bairro.',
        'ad_board_title' => 'Painéis publicitários',
        'ad_board_intro' => 'Os painéis que se veem na televisão. A mesma lógica: ofertas mensais conforme a equipa vai andando.',
        'offers_title' => 'Ofertas em cima da mesa',
        'tier_badge_local' => 'Marca local',
        'tier_badge_regional' => 'Marca regional',
        'tier_badge_nacional' => 'Marca nacional',
        'tier_badge_internacional' => 'Marca internacional',
        'annual_value' => 'Por ano',
        'contract_length' => 'Contrato',
        'seasons' => '{1} :count época|[2,*] :count épocas',
        'seasons_remaining' => '{1} Falta :count época|[2,*] Faltam :count épocas',
        'accept_button' => 'Assinar',
        'reject_button' => 'Passar',
        'accept_confirm' => 'Assinar com :sponsor por :value por ano? As outras ofertas para este espaço são descartadas.',
        'reject_confirm' => 'Descartar a oferta de :sponsor? Não volta.',
        'renewal_badge' => 'Renovação',
        'active_deal_label' => 'Patrocinador atual',
    ],

    'stadium' => [
        'home_ground' => 'Campo',
        'stadium_name' => 'Nome do estádio',
        'capacity' => 'Lotação',
        'uefa_category' => 'Categoria UEFA',
        'uefa_category_short' => 'UEFA',
        'uefa_category_tooltip' => 'A UEFA classifica os estádios em quatro categorias (1 a 4). Subir de categoria exige reformar as instalações (iluminação, balneários, sala de imprensa, camarotes) e que a lotação supere o mínimo da categoria seguinte.',

        'fan_base' => 'Adeptos',
        'fan_base_help' => 'A lealdade sobe com títulos e boas campanhas e desce após épocas fracas. Juntamente com a reputação, determina o quanto o estádio enche nos dias de jogo.',
        'fan_base_trend' => 'Tendência',
        'current_loyalty' => 'Apoio dos adeptos',

        'last_attendance' => 'Último jogo em casa',
        'fill_rate' => 'Taxa de ocupação',
        'no_home_match_yet' => 'Ainda não se jogou nenhum jogo em casa.',

        'no_finances_yet' => 'As finanças da época aparecerão quando forem geradas as projeções.',

        'stadium_revenue' => [
            'title' => 'Receitas do estádio',
            'season_tickets' => 'Passes',
            'matchday' => 'Bilheteira',
        ],

        'upgrades' => [
            'title' => 'Ampliação e reforma',
            'base_capacity' => 'Lotação base',
            'supplementary' => 'Bancadas suplementares',
            'total' => 'Lotação total',
            'seats' => 'lugares',
            'seats_total' => 'lugares totais',
            'seats_to_add' => 'Lugares a adicionar',
            'target_capacity' => 'Lotação objetivo',
            'total_cost' => 'Custo total',
            'completion_date' => 'Data de conclusão',
            'financing' => 'Financiamento',
            'financing_cash' => 'Pagamento a pronto',
            'financing_loan' => 'Empréstimo bancário',
            'financing_cash_hint' => 'É descontado do orçamento disponível ao confirmar.',
            'financing_loan_hint' => 'Teto do banco: :cap. É devolvido em 10 prestações anuais (capital constante + juros sobre o saldo).',

            'project_supplementary' => 'Bancadas suplementares',
            'project_stand_expansion' => 'Ampliação de bancada',
            'project_rebuild' => 'Reconstrução do estádio',
            'project_uefa_upgrade' => 'Melhoria UEFA',
            'ready_on' => 'Prontas a :date',
            'ready_in_season' => 'Disponível na época :season',
            'loan_remaining' => 'Em falta do empréstimo: :amount',

            'tier_label' => 'Nível :n',
            'from_total' => 'Desde :total',
            'per_seat_inline' => ':cost / lugar',
            'time_days_inline' => ':days dias',
            'time_months_inline' => ':count mês|:count meses',
            'status_available' => 'Disponível',
            'status_locked' => 'Bloqueado',
            'status_in_progress' => 'Em obra',
            'cta_planificar' => 'Planear →',
            'unlock_with_revenue' => 'Desbloqueia com :revenue de receitas anuais',
            'unlock_with_reputation' => 'Desbloqueia na categoria :tier',
            'unlock_progress_label' => 'Receitas atuais: :current',

            'cta_supplementary_full_short' => 'Lotação suplementar no limite. Reconstrói o estádio para libertar espaço.',
            'cta_locked_no_budget' => 'Desbloqueia com :cost. Orçamento disponível: :budget.',

            'budget_caps_slider' => 'O orçamento disponível (:budget) limita o lote — sem ele poderias chegar a :natural lugares.',
            'financing_cash_hint_budget' => 'É descontado do orçamento disponível (:budget) ao confirmar.',

            'cta_supplementary_label' => 'Ampliação',
            'cta_supplementary_title' => 'Adicionar bancadas suplementares',

            'cta_stand_expansion_label' => 'Ampliação',
            'cta_stand_expansion_title' => 'Ampliar uma bancada',

            'cta_rebuild_label' => 'Reconstrução',
            'cta_rebuild_title' => 'Reconstruir o estádio',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modesto',
                'established' => 'Consolidado',
                'continental' => 'Continental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Adicionar bancadas suplementares',
            'modal_supplementary_description' => 'Bancadas modulares provisórias: rápidas (30 dias) e a pronto, mas sem novo espaço comercial e são retiradas ao reconstruir o estádio.',
            'modal_stand_expansion_title' => 'Ampliar uma bancada',
            'modal_stand_expansion_description' => 'Demole uma bancada e reconstrói-a maior. Os lugares são permanentes, ao contrário das bancadas suplementares.',
            'modal_rebuild_title' => 'Reconstruir o estádio',
            'modal_rebuild_description' => 'Demole o estádio atual e constrói um novo. O preço por lugar cresce por escalões: quanto maior o estádio, mais caro cada lugar adicional. O estádio novo é entregue com a melhor categoria UEFA que a sua lotação permita, sem custo adicional.',
            'rebuild_marginal_rate_prefix' => 'Custo por lugar neste tamanho:',
            'rebuild_marginal_rate_suffix' => '',
            'rebuild_cap_explainer_reputation' => 'O máximo é fixado pelo empréstimo bancário a que o teu clube aspira (:cap). A tua reputação atual (:tier) marca esse teto: sobe de categoria para acederes a um crédito maior.',
            'rebuild_cap_explainer_affordability' => 'O máximo é fixado pelo empréstimo bancário a que o teu clube aspira (:cap), calculado sobre as tuas receitas anuais previstas. Aumenta as tuas receitas para acederes a um crédito maior.',
            'commit_project' => 'Iniciar obras',

            'cta_disabled_by_active_project' => 'Já tens um projeto em curso. Consulta o histórico abaixo.',

            'cta_uefa_label' => 'Reforma',
            'cta_uefa_title' => 'Subir a Categoria UEFA :to (desde :from)',
            'cta_uefa_title_generic' => 'Subir de categoria UEFA',
            'cta_uefa_button' => 'Melhorar instalações',
            'cta_uefa_tagline' => 'Reforma as instalações para subir a Categoria UEFA :target. Custo fixo :cost, cerca de 9 meses de obras, sem afetar a lotação.',
            'cta_uefa_capacity_floor' => 'Para aspirar a Categoria UEFA :target o estádio tem de superar os :min_cap lugares. Amplia a lotação primeiro.',
            'cta_uefa_already_max' => 'O teu estádio já está na máxima categoria UEFA. Não há mais níveis para desbloquear.',
            'cta_uefa_no_base_level' => 'O teu estádio não tem categoria UEFA atribuída. Amplia a lotação para aceder à classificação.',

            'modal_uefa_title' => 'Subir a Categoria UEFA :to',
            'modal_uefa_description' => 'Reforma das instalações para cumprir os requisitos da categoria UEFA seguinte (iluminação, balneários, zonas de imprensa, camarotes e acessibilidade). A lotação não é afetada durante as obras: a nova categoria fica registada no início da próxima época.',
            'uefa_transition_label' => 'Categoria',
        ],

        'history' => [
            'title' => 'Histórico de obras',
            'empty' => 'Ainda não há obras no estádio.',
            'empty_hint' => 'As obras passadas e em curso aparecerão aqui.',
            'col_type' => 'Projeto',
            'col_detail' => 'Detalhes',
            'col_cost' => 'Custo',
            'col_status' => 'Estado',
            'detail_seats' => ':count lugares',
            'detail_rebuild' => ':count lugares (estádio novo)',
            'detail_uefa_upgrade' => 'Categoria UEFA :from → :to',
            'status_completed' => 'Concluído',
            'status_in_progress' => 'Em curso',
            'season_label' => 'Ép. :season',
            'ready_label' => 'Pronto a :date',
        ],

        'season_tickets' => [
            'title' => 'Preços',
            'subtitle' => 'Escolhe uma política de preços para os teus passes. Preços mais baixos enchem mais o estádio; preços mais altos rendem mais por lugar. Bloqueia-se ao jogar-se o primeiro jogo de liga.',
            'deadline_notice' => 'Prazo: os preços bloqueiam-se ao jogar-se o primeiro jogo de liga da época.',
            'locked_notice' => 'Os passes estão bloqueados esta época. Poderás fixar novos preços na próxima pré-época.',
            'tickets_sold' => 'Passes vendidos',
            'projected_season_tickets' => 'Passes previstos',
            'projected_season_tickets_tooltip' => 'Passes que prevês vender (pagamento adiantado). A assistência a cada jogo é distinta: soma os bilhetes de bilheteira e desconta os sócios que não comparecem.',
            'of_capacity' => 'da lotação',
            'matchday_occupancy' => 'taxa de ocupação no jogo',
            'save_button' => 'Guardar',
            'preset' => [
                'accessible' => 'Acessível',
                'standard' => 'Padrão',
                'premium' => 'Premium',
            ],
            'preset_hint' => [
                'accessible' => 'Mais baratos, estádio mais cheio.',
                'standard' => 'Preços de referência.',
                'premium' => 'Mais caros, menos ocupação.',
            ],
        ],

        'identity' => [
            'subtitle' => 'Renomeia o teu estádio sem custo (uma vez por época, na pré-época). Vender o nome a um patrocinador é gerido na página Comercial.',
            'sponsor_owns_name' => 'Um patrocinador (:sponsor) detém o nome do estádio até expirar o acordo, por isso não o podes renomear.',
            'manage_in_commercial' => 'Gerir em Comercial',
            'sell_naming_rights' => 'Vender os naming rights',
        ],

        'naming_rights' => [
            'title' => 'Identidade do estádio e naming rights',
            'current_name' => 'Nome atual',
            'source_historic' => 'Histórico',
            'source_custom' => 'Renomeado',
            'source_sponsor' => 'Patrocinado',

            'seasons_remaining' => '{1} resta :count época|[2,*] restam :count épocas',

            'offers_title' => 'Ofertas de patrocínio',
            'becomes' => 'O estádio passa a chamar-se «:name»',
            'annual_value' => 'Valor anual',
            'contract_length' => 'Contrato',
            'seasons' => '{1} :count época|[2,*] :count épocas',
            'accept_button' => 'Aceitar acordo',
            'accept_confirm' => 'Vender os naming rights a :sponsor? Isto bloqueia o nome do estádio durante o contrato e reduz o apoio dos adeptos.',
            'renewal_badge' => 'Renovação',
            'renew_button' => 'Renovar acordo',
            'renew_confirm' => 'Renovar o acordo com :sponsor? Mantém o nome do estádio sem custo de apoio dos adeptos.',

            'rename_button' => 'Renomear estádio',
            'rename_placeholder' => 'Novo nome do estádio',
            'rename_save' => 'Guardar nome',
            'rename_locked_season' => 'O estádio já foi renomeado esta época.',

            'window_closed_notice' => 'A identidade do estádio é fixada na pré-época. Os acordos e renomeações reabrem antes do primeiro jogo de liga da próxima época.',
        ],
    ],

    'reputation' => [
        'current_tier' => 'Nível atual',

        'tiers' => 'Níveis de reputação',
        'tiers_help_toggle' => 'Como funcionam os níveis de reputação?',
        'ladder_help' => 'Os clubes sobem de nível ao terminar em cima na liga. Nos níveis mais altos, a reputação desgasta-se cada época se não for sustentada com resultados.',

        'current' => 'Atual',

        'qualitative_distance' => [
            'one_strong_season' => 'Uma boa época bastaria para chegar a :tier.',
            'two_strong_seasons' => 'Um par de boas épocas separam-te de :tier.',
            'several_seasons' => 'Várias épocas sólidas separam-te de :tier.',
            'long_road' => 'Resta um longo caminho até :tier.',
        ],

        'tier_descriptors' => [
            'local' => 'Um clube modesto com uma claque local fiel.',
            'modest' => 'Um clube pequeno que aspira a chegar ou manter-se na primeira.',
            'established' => 'Um clube histórico, com anos de experiência na primeira.',
            'continental' => 'Habitué das competições europeias.',
            'elite' => 'Referência do futebol europeu.',
        ],

        'career' => [
            'title' => 'Percurso',
            'seasons_managed' => 'Épocas orientadas',
            'starting_tier' => 'Nível inicial',
            'matches_managed' => 'Jogos orientados',
            'trophies' => 'Títulos',
        ],

        'trophy_cabinet' => [
            'title' => 'Sala de troféus',
            'empty' => 'Ainda não conquistaste nenhum título com este clube.',
        ],

        'path_title' => 'Caminho para o próximo nível',
        'path_also' => 'Os títulos de taça e as séries europeias também somam no fecho da época.',
        'maintenance_note' => 'Neste nível, a reputação desgasta-se cada época se não a sustentares com resultados.',
        'projected' => 'Projetado',

        'legend' => [
            'forward' => 'Avanço',
            'flat' => 'Sem avanço',
            'setback' => 'Recuo',
        ],

        'impact' => [
            'major_leap' => 'Grande salto em frente',
            'solid_step' => 'Passo sólido em frente',
            'small_step' => 'Pequeno avanço',
            'stalls' => 'Sem avanço',
            'setback' => 'Recuo',
        ],

        'history' => [
            'title' => 'Histórico de rendimento',
            'empty' => 'O teu histórico aparecerá no final da primeira época.',
            'current_suffix' => '(em curso)',
            'promoted' => 'Subida',
            'relegated' => 'Descida',
            'legend' => [
                'same_tier' => 'Mesma categoria',
            ],
        ],

        'impact_title' => 'O que a reputação traz ao teu clube',
        'impact_signings_title' => 'Atrair contratações',
        'impact_signings_body' => 'As jogadoras de maior nível preferem clubes com mais reputação. Jogadoras livres, alvos de transferência e clubes rivais avaliam o teu nível antes de se sentarem a negociar.',
        'impact_retain_title' => 'Reter talento',
        'impact_retain_body' => 'O teu próprio plantel também reage à reputação. Um clube em crescimento retém melhor as suas peças-chave; quando desce de nível, aparecem os predadores e as renovações complicam-se.',
        'impact_economy_title' => 'Oportunidades económicas',
        'impact_economy_body' => 'A assistência ao estádio, o preço dos bilhetes e as receitas comerciais escalam com a reputação. Subir desbloqueia maiores receitas em todas as frentes; descer aperta o orçamento.',

    ],

    // Añadidas en la revisión fase 5: claves ausentes en de/fr/pt
    'matchday' => [
        'title' => 'Bilheteira e dia de jogo',
        'intro' => 'Define preços para bilhetes avulsos, camisolas oficiais, merchandising e bares do estádio. Cada jogo em casa gera receitas conforme a assistência — mas atenção: o bilhete caro arrefece a bancada e o barato esvazia a loja.',
        'prices_title' => 'Preços',
        'ticket_label' => 'Bilhete avulso',
        'shirt_label' => 'Camisola oficial',
        'merch_label' => 'Merchandising (cachecol, etc.)',
        'bar_label' => 'Consumo no bar',
        'default_is' => 'por defeito :amount €',
        'ticket_hint' => 'Subi-lo baixa a assistência; descê-lo anima-a.',
        'shirt_hint' => 'Cerca de 2 % da bancada compra camisola; o preço mexe com a procura.',
        'merch_hint' => 'Cerca de 8 % da bancada compra merchandising; o preço mexe com a procura.',
        'bar_hint' => 'Cerca de 45 % da bancada passa pelo bar; o preço mexe com a procura.',
        'save' => 'Guardar preços',
        'projection_title' => 'Previsão do próximo jogo em casa',
        'vs' => 'contra',
        'expected_crowd' => ':attendance espetadores de :capacity de lotação.',
        'total' => 'Total estimado',
        'projection_note' => 'Estimativa com os preços atuais. As receitas reais dependem da assistência final.',
        'no_home_match' => 'Não resta nenhum jogo em casa por jogar esta época.',
        'recent_title' => 'Últimas receitas de bilheteira',
    ],
];
