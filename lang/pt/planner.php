<?php

return [
    // Cabeçalho da página
    'planner' => 'Planeador',
    'title' => 'Planeador de plantel',

    // Secções
    'section_staying' => 'Ficam',
    'section_outgoing' => 'Saídas',
    'section_incoming' => 'Entradas',
    'section_next_season' => 'Plantel da próxima época',
    'section_staying_count' => ':count jogadora|:count jogadoras',

    // Rótulos dos grupos de posição
    'goalkeepers' => 'Guarda-redes',
    'defenders' => 'Defesas',
    'midfielders' => 'Médias',
    'forwards' => 'Avançadas',

    // Linha da jogadora
    'age_next' => ':age anos na próxima época',
    'contract_until' => 'Até :year',
    'no_contract' => 'Sem contrato',

    // Motivos — FICAM
    'reason_owned' => 'No plantel',
    'reason_renewed' => 'Renovação acordada',
    'reason_returning_from_loan' => 'Regressa de empréstimo',
    'reason_still_on_loan' => 'Emprestada até :date',

    // Motivos — SAÍDAS
    'reason_retiring' => 'Retira-se',
    'reason_transfer_agreed' => 'Transferência acordada',
    'reason_pre_contract_departing' => 'Pré-contrato com outro clube',
    'reason_contract_expiring_unrenewed' => 'Termina o contrato',
    'reason_loan_ending' => 'Termina o empréstimo',

    // Motivos — ENTRADAS
    'reason_pre_contract_joining' => 'Pré-contrato assinado',
    'reason_reserve_promoted' => 'Promovida da equipa B',
    'reason_academy_promoted' => 'Promovida da formação',

    // Estados vazios
    'empty_staying' => 'Não há jogadoras previstas para ficarem.',

    // Rótulos da linha de capacidade/potencial
    'current_ability' => 'Atual',
    'projected_ability' => 'Próxima época',
    'potential' => 'Potencial',

    // Distintivos de papel no plantel
    'col_action' => 'Recomendação',
    'role_wonderkid' => 'Pérola',
    'role_key_player' => 'Jogadora-chave',
    'role_first_team' => 'Titular',
    'role_rotation' => 'Rotação',
    'role_prospect' => 'Promessa',
    'role_reserves' => 'Suplente',
    'role_departing' => 'De saída',

    // Recomendações de contratações
    'transfer_recommendations' => 'Recomendações de contratações',
    'list_conjunction' => 'e',
    'advisory_empty' => 'Sem recomendações globais. O plantel previsto parece equilibrado.',
    'advisory_depth_gap' => 'Reforça :position — faltam :count para a formação escolhida.',
    'advisory_quality_gap' => 'Reforça :position — :gap pontos abaixo do resto da equipa.',
    'advisory_no_backup' => 'Sem alternativa em :position — as titulares não têm quem as substitua em caso de lesão ou rotação.',
    'advisory_weak_backup' => 'Reforça o banco em :position — a primeira alternativa está :gap pontos abaixo da pior titular.',
    'advisory_overload' => 'Acumulação em :position — :count jogadoras de grande nível disputam :spots lugares no onze inicial (:names). Haverá descontentamento no balneário.',
    'advisory_age_gap' => 'Formação escassa em :position — sem jogadoras com :age ou menos previstas.',
    'advisory_wage_cliff' => 'Renova :name — contrato até :year e ainda sem acordo.',
    'advisory_development' => 'Dá minutos a :names para maximizar o desenvolvimento.',
    'advisory_wasted_wage' => 'Considera vender :names — salários altos e poucos minutos.',
    'advisory_key_departure' => 'Substitui :name (:position) — a sua saída deixa uma lacuna.',

    // Rótulos de grupos de posição usados nos avisos (singular e minúsculas para o fluxo da frase)
    'group_goalkeeper' => 'a baliza',
    'group_defender' => 'a defesa',
    'group_midfielder' => 'o meio-campo',
    'group_forward' => 'o ataque',

    // Botões de ação
    'action_play_often' => 'Dar minutos',
    'action_loan_out' => 'Emprestar',
    'action_keep' => 'Manter',
    'action_renew' => 'Renovar',
    'action_list' => 'Vender',
    'action_replace' => 'Substituir',

    // Ajuda
    'help_toggle' => 'Como funciona o Planeador?',
    'help_overview_intro' => 'Este ecrã projeta como será o teu plantel no início da próxima época conforme contratos, empréstimos, transferências acordadas e pré-contratos.',
    'help_overview_sections' => 'Ficam reúne as jogadoras que continuarão contigo; Entradas, as que já estão confirmadas para chegar; Saídas, as que partirão antes do próximo arranque.',
    'help_actions_title' => 'Recomendações por jogadora',
    'help_action_renew' => 'Renovar — oferece um novo contrato antes de o atual terminar.',
    'help_action_replace' => 'Substituir — a sua saída deixa uma lacuna que convém preencher no mercado.',
    'help_action_play_often' => 'Dar minutos — promessa pronta para somar jogos na equipa principal.',
    'help_action_loan_out' => 'Emprestar — sairá para outro clube para ganhar ritmo e regressará mais madura.',
    'help_action_list' => 'Vender — alternativa sem lugar na rotação que convém colocar no mercado.',

    // Textos automáticos
    'blurb_wonderkid' => 'Grande potencial — já útil e a crescer depressa.',
    'blurb_key_player' => 'Pilar da equipa. Constrói à sua volta.',
    'blurb_first_team' => 'Titular fiável na sua posição.',
    'blurb_prospect' => 'Jovem promissora, ainda em desenvolvimento.',
    'blurb_rotation' => 'Alternativa sólida, perto do onze inicial.',
    'blurb_reserves' => 'No fundo do banco na sua posição.',
    'blurb_departing' => 'Sai no final da época.',
];
