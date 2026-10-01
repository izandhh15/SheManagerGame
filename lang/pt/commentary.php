<?php

return [
    'atmosphere_shot_on_target' => [
        'Remate de :player (:team)! A guarda-redes agarra sem problemas',
        ':player (:team) tenta de fora da área. Boa defesa',
        'Remate de :player (:team) que obriga a guarda-redes a intervir',
        'Teve-a :el_team! Remate de :player que a guarda-redes afasta',
        ':player (:team) remata à baliza, mas a guarda-redes estava bem posicionada',
        'Atenção ao remate de :player (:team)! Tremenda defesa da guarda-redes',
    ],
    'atmosphere_shot_off_target' => [
        'Uiii! Remate para fora de :player (:team)',
        ':player (:team) tenta mas a bola sai desviada',
        'Remate por cima de :player (:team), foi por cima da trave',
        ':player (:team) remata da entrada da área... sai por pouco',
        'Tenta :player (:team) de longe, mas não encontra a baliza',
        'Quase! :player (:team) fica perto mas a bola vai para fora',
    ],
    'atmosphere_foul' => [
        'Falta de :player (:team), o árbitro não hesita em assinalar',
        ':player (:team) corta em falta uma jogada prometedora :del_opponent',
        'Derrube de :player (:team) no meio-campo',
        ':player (:team) chega tarde à disputa e comete falta',
        'O árbitro assinala falta de :player (:team) após uma disputa',
        'Falta de :player (:team) no ataque, mudança de posse',
    ],
    'contextual_draw_open' => [
        'Jogo equilibrado no :venue, nenhuma das duas equipas consegue impor-se',
        'Não tem um dominador claro o jogo entre :el_home e :el_away',
        'Repartição de jogo no :venue, com ocasiões em ambas as balizas',
        'Empate a zero no :venue, ambas as equipas estudam-se com cautela',
        'Igualdade máxima entre :el_home e :el_away, que se anulam mutuamente',
    ],
    'contextual_draw_with_goals' => [
        'Jogo aberto e entretido no :venue, com golos em ambas as balizas',
        'Empate que se ajusta ao visto em campo, com ocasiões para ambos',
        'Igualdade no marcador e no jogo entre :el_home e :el_away',
        'Ida e volta constante no :venue, as duas defesas sofrem',
        'Espetáculo para os adeptos no :venue, golos repartidos entre ambas as equipas',
    ],
    'contextual_home_leading' => [
        ':el_home controla o jogo e o marcador no :venue',
        'Domínio claro :del_home, que gere o jogo a seu bel-prazer',
        'Bom jogo :del_home que tem o marcador a favor',
        ':el_home confortável com a vantagem no :venue',
    ],
    // Variantes com conotação de casa — só usadas quando há vantagem do terreno
    'contextual_home_leading_home_only' => [
        'Os da casa fazem tudo bem até agora, :el_home merece ir na frente',
    ],
    'contextual_away_leading' => [
        ':el_away está a fazer um grande jogo no :venue',
        'Difícil se põe o jogo :al_home, que vai atrás no marcador',
    ],
    // Variantes com conotação de casa — só usadas quando há vantagem do terreno
    'contextual_away_leading_home_only' => [
        ':el_away surpreende :al_home no seu próprio estádio',
        ':el_away a calar o público do :venue com uma grande exibição',
        'Exibição visitante, :el_away lidera o encontro fora de casa',
    ],
    'contextual_home_dominant' => [
        ':el_home aperta com insistência, acumula chegadas com perigo',
        'Pressão :del_home, que procura o golo com muita intensidade',
        'Muito :home nestes minutos, com chegadas constantes à área rival',
        ':el_home asfixia :al_away na sua própria área, vagas de ataque',
    ],
    // Variantes com conotação de casa — só usadas quando há vantagem do terreno
    'contextual_home_dominant_home_only' => [
        'Os visitantes não conseguem sair do seu campo, :el_home aperta sem descanso',
    ],
    'contextual_away_dominant' => [
        'Pressão adiantada :del_away, que quer complicar a saída de bola :del_home',
        ':el_away ameaça perigo em cada contra',
        'Muita intensidade :del_away que leva o peso do jogo nestes minutos',
        ':el_away marca o ritmo do jogo, :el_home não encontra o seu jogo',
        'Controlo absoluto :del_away, que domina a posse e o território',
    ],
    'contextual_tight_game' => [
        'Jogo muito disputado no meio-campo, com poucas ocasiões claras',
        'Muita intensidade e pouca clarividência, a bola não chega com perigo a nenhuma baliza',
        'Jogo fechado no :venue, com mais luta do que futebol',
        'Nenhuma das duas se atreve a dar um passo em frente, encontro muito cauteloso no :venue',
        'Batalha no meio-campo, as ocasiões brilham pela ausência',
    ],
    'contextual_end_losing' => [
        'Esgota-se o tempo e este resultado não serve :al_trailing que precisa de reagir',
        'Acabam-se os minutos para :el_trailing, que o tem muito complicado',
        ':el_trailing lança tudo para a frente mas a diferença parece insuperável',
        'Sem ideias e sem tempo, :el_trailing tem uma montanha para escalar',
        'Desespero em :el_trailing à medida que o relógio se consome',
    ],
    'contextual_end_losing_by_one' => [
        'O tempo corre contra :del_trailing, que procura o empate com mais coração do que cabeça',
        'A :el_trailing escapa-se o empate, restam poucos minutos no :venue',
        'Será que :el_trailing encontra o golo de que precisa? O tempo não está do seu lado',
        ':el_trailing bate e bate mas o empate não chega',
        'Tudo ao ataque :del_trailing, um golo mudaria tudo',
    ],
    'contextual_end_winning' => [
        ':el_leading controla os últimos minutos do jogo sem sofrer',
        ':el_leading gere com tranquilidade os últimos minutos do encontro',
        'Já cheira a vitória para :el_leading no :venue',
        ':el_leading arrefece o jogo, mantém a bola com calma',
        'Trabalho quase feito para :el_leading, que foi a melhor equipa',
    ],
    'contextual_end_draw' => [
        'Últimos minutos e repartição de pontos, na falta de um arranco final',
        'Acaba o jogo no :venue com igualdade no marcador',
        'Empate no :venue que a ninguém parece satisfazer por completo',
        'Caminha para a igualdade no :venue, ninguém encontra o golo da vitória',
        'Um ponto para cada uma parece ser o resultado final no :venue',
    ],
    'contextual_end_draw_knockout' => [
        'Aproxima-se o fim do tempo regulamentar no :venue e a eliminatória continua aberta',
        'Igualdade no :venue, isto cheira a prolongamento',
        'Ninguém encontra o golo decisivo, o tempo extra está cada vez mais perto',
        'Esgotam-se os minutos no :venue com o empate que não desfaz a incógnita',
        'Último empurrão para evitar o prolongamento, ninguém quer prolongar a agonia',
    ],
    'contextual_second_half_start' => [
        'Começa a segunda parte no :venue com o marcador :score',
        'De volta à ação no :venue. :score ao intervalo',
        'As equipas saltam de novo ao relvado no :venue. :score o marcador',
        'Arranca a segunda metade no :venue, :score ao intervalo',
        'Reata-se o jogo no :venue com o :score no eletrónico',
    ],
    'contextual_away_fans' => [
        'Os adeptos :del_away que se deslocaram até ao :venue animam a sua equipa desde a bancada',
        'Fazem-se ouvir os adeptos :del_away no :venue',
        'A claque visitante :del_away não para de cantar no :venue',
        'Grande ambiente na bancada visitante, os adeptos :del_away empurram os seus',
        'Espetacular o apoio da claque viajante :del_away hoje',
    ],
    'contextual_home_fans' => [
        'O :venue ruge de emoção, a claque :del_home empurra os seus',
        'O público do :venue entregue com a sua equipa nestes minutos',
        'Ambientão no :venue, a claque :del_home está entregue',
        'Retumba o :venue, a bancada anima sem parar :al_home',
        'A claque :del_home é a jogadora número doze hoje no :venue',
    ],
    // Prefixo de golos — anteposto a cada narração de golo para dar ênfase
    'goal_prefix' => [
        'Golo :del_team!',
        'GOLO :del_team!',
        'GOLAÇO :del_team!',
        'GOOOOLO :del_team!',
        'GOOOL :del_team!',
        'Golo :del_team!',
        'Marca :el_team!',
        'Aponta :el_team!',
        'Que golo :del_team!',
        'Golaço :del_team!',
    ],
    'goal_assisted' => [
        'Cruzamento para a área e :player aparece livre de marcação para cabecear para o golo',
        ':player recebe na marca de penálti, controla e finaliza com classe',
        'Passe filtrado para :player, fica isolada diante da guarda-redes e não perdoa',
        'Que jogada coletiva :del_team! Finaliza :player com um toque subtil',
        'Cabeceamento indefensável de :player ao segundo poste. Impossível para a guarda-redes',
        'Cruzamento medido do flanco e :player remata de cabeça à vontade',
        'Contra-ataque letal :del_team. :player finaliza com frieza diante da saída da guarda-redes',
        'Tabela à entrada da área e :player empurra para o golo dentro da pequena área',
        ':player antecipa-se à defesa e remata de primeira para o fundo das redes',
        'Grande assistência e :player só tem de a empurrar. Não falha',
        'Desmarcação inteligente de :player que recebe isolada e pica a bola por cima da guarda-redes',
        ':player antecipa-se à defesa para rematar e colocá-la no primeiro poste',
        ':player liga um vólei espetacular que entra como uma bala',
        'Bola no coração da área e :player remata de biqueira para marcar',
    ],
    'goal_solo' => [
        'Golaço de :player! Finta à guarda-redes e de direita para dentro',
        ':player encara a defesa, enquadra-se e prega a bola no ângulo',
        'Bomba de :player de fora da área! Golaço',
        ':player recolhe a recarga e manda-a para o fundo das redes',
        'Jogada individual de :player que se livra de duas rivais e finaliza cruzado',
        'Que golaço de :player! Remate com efeito da entrada da área que se encaixa no ângulo',
        ':player aproveita um erro defensivo e bate a guarda-redes com um remate rasteiro',
        'Remate de longe de :player que desvia numa defesa e surpreende a guarda-redes',
        'Livre direto de :player que supera a barreira e se encaixa junto ao poste',
        ':player finta dentro da área, encontra o espaço e remata cruzado. Golo!',
        'Prega-a :player! Remate de primeira da entrada da área',
        'Roubo de bola e :player não hesita, finaliza com um remate cruzado indefensável',
        ':player inventa um golaço individual da entrada da área',
    ],

    // Narrativas táticas — geradas segundo as configurações táticas
    'tactical_high_press_working' => [
        ':user pressiona com intensidade feroz, asfixiando a saída de bola :del_opp',
        'A pressão alta :del_user está a recuperar a bola em zonas perigosas',
        ':user pressiona sem descanso — :opp mal consegue sair do seu campo',
    ],
    'tactical_high_press_fading' => [
        'A intensidade da pressão :del_user começa a cair. As pernas pesam',
        ':user não consegue manter essa pressão inicial — :opp encontra mais espaços',
        'Nota-se o cansaço. A pressão :del_user perde fulgor',
    ],
    'tactical_high_press_exhausted' => [
        ':user parece esgotado. A pressão alta cobra-se no tramo final',
        'Acabam-se as forças para :user — essa pressão agressiva está a cobrar-lhes',
        ':opp percebe o cansaço :del_user e lança-se ao ataque com confiança',
    ],
    'tactical_opp_press_fading' => [
        'A pressão alta :del_opp perde força — :user deveria encontrar mais espaço',
        'A pressão :del_opp já não é a de antes. Abrem-se os espaços',
    ],
    'tactical_opp_exhausted' => [
        ':opp parece fundido após pressionar tanto. :user pode aproveitar as pernas cansadas',
        'A pressão alta drenou :al_opp — nota-se que vão de língua de fora',
    ],
    'tactical_low_block_wall' => [
        ':user planta-se compacto e recuado, a complicar muito o jogo :del_opp',
        'Um muro defensivo disciplinado :del_user. :opp não encontra a forma de entrar',
        ':user defende com muitos elementos, negando qualquer ocasião clara :al_opp',
    ],
    'tactical_low_block_fresh' => [
        'A abordagem conservadora :del_user dá frutos — as jogadoras ainda parecem frescas',
        'Níveis de energia altos para :user graças à disciplina defensiva',
    ],
    'tactical_possession_control' => [
        ':user controla o ritmo, a mover a bola com paciência à procura de espaços',
        'Posse dominante :del_user — :opp a perseguir sombras',
        ':user gere bem a bola, a ditar o ritmo do jogo',
    ],
    'tactical_possession_frustrated' => [
        ':user domina a posse mas não encontra a forma de superar o bloco baixo :del_opp',
        'Muita posse para :user mas o bloco baixo :del_opp frustra cada ataque',
    ],
    'tactical_counter_waiting' => [
        ':user espera agachado, pronto para sair em contra-ataque a qualquer momento',
        ':user cede o território — procura bater na transição',
        'Defesa paciente :del_user, pronta para saltar quando surgir a oportunidade',
    ],
    'tactical_counter_exploiting' => [
        ':user explora o espaço atrás da linha alta :del_opp com contra-ataques letais',
        'A abordagem agressiva :del_opp deixa espaços — :user castiga em contra-ataque',
    ],
    'tactical_direct_play' => [
        ':user salta o meio-campo com bolas longas, a manter :opp em alerta',
        'Jogo direto :del_user — sem complicações, bola longa para as avançadas',
    ],
    'tactical_direct_bypassing_press' => [
        'O jogo direto :del_user sobrevoa a pressão alta :del_opp — as bolas longas encontram o seu destino',
        'A pressão :del_opp fica anulada pelas bolas longas :del_user',
    ],
    'goal_penalty' => [
        'Penálti! :player (:team) remata com decisão e marca. Sem hipótese para a guarda-redes',
        ':player (:team) coloca-se diante da bola, arranca e prega-a no ângulo. Golo de penálti!',
        'Penálti para :el_team. :player toma balanço e engana a guarda-redes com um remate cruzado',
        ':player (:team) assume a responsabilidade dos onze metros e não falha. Inapelável',
        'Golo de penálti! :player (:team) envia-a ao centro da baliza enquanto a guarda-redes se atira',
        'Pena máxima para :el_team. :player espera pela guarda-redes, vê-a mexer-se e coloca a bola do outro lado',
    ],
    // Sabor tático nos golos
    'goal_counter_attack' => [
        'Contra-ataque letal! :player finaliza após um contra devastador de :team',
        'Clínico em contra-ataque! :player converte após uma saída veloz de :team',
        'Golo de contra-ataque! :team sai a toda a velocidade e :player finaliza',
    ],
    'goal_possession' => [
        ':team move a bola com paciência até :player encontrar o espaço. Posse de manual',
        'Elaboração paciente de :team e :player escolhe o momento perfeito para bater',
        'Jogada rendilhada :del_team — :player põe o ponto final',
    ],
    'goal_direct' => [
        'Bola longa e :player está lá para finalizar por :team!',
        ':team vai direto e funciona — :player controla e finaliza',
        'Jogo direto puro! A bola longa encontra :player que não perdoa',
    ],

    // Anúncio do tempo de compensação. O cliente escolhe a variante singular ou
    // plural segundo os minutos (:minutes) para evitar "1 minutos".
    'stoppage_announcement_singular' => [
        'O árbitro acrescenta :minutes minuto',
        'O quarto árbitro indica :minutes minuto de compensação',
        ':minutes minuto de compensação',
        'Só :minutes minuto de tempo extra',
    ],
    'stoppage_announcement_plural' => [
        'O árbitro acrescenta :minutes minutos',
        'O quarto árbitro indica :minutes minutos de compensação',
        ':minutes minutos de compensação',
        'Tempo extra: :minutes minutos',
        'Acrescentam-se :minutes minutos no final',
        ':minutes minutos de compensação! Ainda há tempo',
    ],
];
