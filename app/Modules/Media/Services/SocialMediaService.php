<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\PressStatement;
use App\Models\SocialPost;
use App\Models\TransferOffer;

/**
 * Fake in-game social network ("X" clone). Fans react to the manager's press
 * statements; sustained negativity erodes board confidence and can get the
 * manager sacked.
 */
class SocialMediaService
{
    /**
     * Statement types and their fan-reaction logic.
     *
     * Each entry: [label_es, label_en, needs_player, description]
     */
    public const STATEMENTS = [
        'praise_star' => ['Elogiar a la destacada', 'Praise the star performer', true],
        'praise_team' => ['Elogiar al equipo', 'Praise the whole team', false],
        'criticize_flop' => ['Señalar a la peor', 'Call out the worst performer', true],
        'criticize_team' => ['Criticar al equipo', 'Criticize the whole team', false],
        'blame_referee' => ['Culpar al arbitraje', 'Blame the referee', false],
        'defend_tactics' => ['Defender el planteamiento', 'Defend the tactics', false],
    ];

    /**
     * Predefined replies the manager can fire back at haters with.
     *
     * Each entry: [label_es, label_en, board_delta, morale_delta, tone]
     * tone = 'firm'|'calm'|'spicy' — drives how fans react to the comeback.
     */
    public const HATER_REPLIES = [
        'results_talk' => [
            '«Los resultados hablarán en el campo.»',
            '"Results will do the talking on the pitch."',
            2, 3, 'firm',
        ],
        'respect_opinions' => [
            '«Respeto todas las opiniones. Yo, a lo mío.»',
            '"I respect every opinion. I\'ll keep doing my thing."',
            3, 1, 'calm',
        ],
        'patience_project' => [
            '«Paciencia: estamos construyendo algo grande.»',
            '"Patience: we\'re building something big."',
            1, 2, 'calm',
        ],
        'sofa_critic' => [
            '«Es muy fácil criticar desde el sofá.»',
            '"It\'s very easy to criticise from the sofa."',
            -3, -2, 'spicy',
        ],
    ];

    /**
     * Generate the press-conference options after a match.
     *
     * @return array{key: string, label: string, player?: array{id: string, name: string, rating: float}}
     */
    public function pressOptions(Game $game, GameMatch $match): array
    {
        $ratings = $this->playerRatings($game, $match);
        $best = null;
        $worst = null;

        foreach ($ratings as $r) {
            if ($best === null || $r['rating'] > $best['rating']) {
                $best = $r;
            }
            if ($worst === null || $r['rating'] < $worst['rating']) {
                $worst = $r;
            }
        }

        $locale = app()->getLocale();
        $options = [];

        foreach (self::STATEMENTS as $key => [$labelEs, $labelEn, $needsPlayer]) {
            $option = ['key' => $key, 'label' => $locale === 'es' ? $labelEs : $labelEn];

            if ($needsPlayer) {
                $player = $key === 'praise_star' ? $best : $worst;
                if (! $player) {
                    continue;
                }
                $option['player'] = $player;
                $option['label'] .= " ({$player['name']}, {$player['rating']})";
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * Process the manager's statement: save it, generate fan reactions,
     * update board confidence. Returns the generated posts.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SocialPost>
     */
    public function makeStatement(Game $game, GameMatch $match, string $statementKey, ?string $playerId = null)
    {
        $ratings = $this->playerRatings($game, $match);
        $targetRating = null;
        $targetName = null;

        if ($playerId) {
            foreach ($ratings as $r) {
                if ($r['id'] === $playerId) {
                    $targetRating = $r['rating'];
                    $targetName = $r['name'];
                    break;
                }
            }
        }

        $result = $this->calculateReaction($game, $match, $statementKey, $targetRating);

        // M15 (QA FEED-02): exactly one statement per (game, match).
        // SubmitPressStatement guards with check-then-insert without a
        // lock, and press_statements now carries a unique constraint on
        // (game_id, match_id). firstOrCreate makes the common case atomic;
        // a lost race surfaces as a unique violation, which we swallow so a
        // double click / double submit reuses the existing statement
        // instead of emitting a second fan wave and a second confidence hit.
        try {
            $statement = PressStatement::firstOrCreate(
                ['game_id' => $game->id, 'match_id' => $match->id],
                [
                    'statement_key' => $statementKey,
                    'target_player_id' => $playerId,
                    'sentiment_impact' => $result['impact'],
                ],
            );
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $statement = null;
        }

        if ($statement === null || ! $statement->wasRecentlyCreated) {
            return SocialPost::where('game_id', $game->id)
                ->where('match_id', $match->id)
                ->orderByDesc('created_at')
                ->get();
        }

        // Generate 6-10 fan posts. $usedTexts keeps every post in the wave
        // textually unique (M14 / QA FEED-01).
        $count = rand(6, 10);
        $usedFans = [];
        $usedTexts = [];
        for ($i = 0; $i < $count; $i++) {
            $this->generatePost($game, $match, $result, $targetName, $statementKey, $usedFans, $usedTexts);
        }

        // Update board confidence.
        $game->board_confidence = max(0, min(100, ($game->board_confidence ?? 70) + $result['impact']));
        $game->save();

        // Check for sacking.
        if ($game->board_confidence < 15) {
            $this->generateSackingPosts($game);
        } elseif ($game->board_confidence < 30) {
            $this->generateWarningPosts($game);
        }

        return SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Calculate how fans react to a statement.
     *
     * @return array{impact: int, mood: string} mood = 'positive'|'negative'|'mixed'
     */
    private function calculateReaction(Game $game, GameMatch $match, string $key, ?float $targetRating): array
    {
        $won = $this->didWin($game, $match);
        $lost = $this->didLose($game, $match);

        return match ($key) {
            'praise_star' => $targetRating >= 7.0
                ? ['impact' => 8, 'mood' => 'positive']    // Fair praise
                : ['impact' => -12, 'mood' => 'negative'], // Delusional

            'praise_team' => $won
                ? ['impact' => 5, 'mood' => 'positive']
                : ($lost
                    ? ['impact' => -10, 'mood' => 'negative'] // Praising a loss?!
                    : ['impact' => 2, 'mood' => 'mixed']),

            'criticize_flop' => $targetRating <= 6.0
                ? ['impact' => 6, 'mood' => 'positive']     // Telling it like it is
                : ['impact' => -18, 'mood' => 'negative'], // Throwing a good player under the bus!

            'criticize_team' => $lost
                ? ['impact' => 3, 'mood' => 'mixed']       // Honest but harsh
                : ['impact' => -14, 'mood' => 'negative'], // Criticizing after win/draw?!

            'blame_referee' => $lost
                ? ['impact' => -4, 'mood' => 'mixed']      // Excuses...
                : ['impact' => -8, 'mood' => 'negative'], // Blaming ref when you didn't lose?!

            'defend_tactics' => $lost
                ? ['impact' => -10, 'mood' => 'negative']  // Stubborn
                : ['impact' => 4, 'mood' => 'positive'],

            default => ['impact' => 0, 'mood' => 'mixed'],
        };
    }

    /**
     * One fan post. $usedFans deduplicates "name|handle" combos; $usedTexts
     * deduplicates the literal text inside the current wave (M14 / QA
     * FEED-01): templates are picked without replacement so the same
     * sentence never appears twice in one wave. If the wave ever outgrows
     * the template pool, it falls back to the full list.
     */
    private function generatePost(Game $game, GameMatch $match, array $result, ?string $targetName, string $statementKey, array &$usedFans = [], array &$usedTexts = []): void
    {
        $mood = $result['mood'];
        // Mixed mood: randomize each post's sentiment.
        $sentiment = $mood === 'mixed' ? (rand(0, 1) ? 1 : -1) : ($mood === 'positive' ? 1 : -1);

        // Every wave has its contrarians: even after a disastrous press
        // conference a few fans defend the manager, and even a triumph
        // always has its haters. That's what makes the feed feel alive.
        if ($mood === 'negative' && rand(1, 100) <= 20) {
            $sentiment = 1;
        } elseif ($mood === 'positive' && rand(1, 100) <= 15) {
            $sentiment = -1;
        }

        $templates = $this->templatesFor($sentiment, $statementKey, $targetName);
        $fresh = array_values(array_filter($templates, fn ($t) => ! isset($usedTexts[$t])));
        $pool = $fresh !== [] ? $fresh : $templates;
        $text = $pool[array_rand($pool)];
        $usedTexts[$text] = true;

        [$name, $handle] = $this->randomFan($usedFans);

        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $name,
            'author_handle' => $handle,
            'text' => $text,
            'sentiment' => $sentiment,
            'likes' => $sentiment > 0 ? rand(5, 200) : rand(10, 500),
            'context' => 'post_match',
            'match_id' => $match->id,
        ]);
    }

    private function templatesFor(int $sentiment, string $statementKey, ?string $targetName): array
    {
        $es = app()->getLocale() === 'es';
        $name = $targetName ?? ($es ? 'la jugadora' : 'the player');

        if ($sentiment > 0) {
            return $es ? [
                "¡Grande míster! Por fin alguien que dice las cosas claras 👏",
                "Totalmente de acuerdo con el míster. Así se habla.",
                "Esto es tener personalidad. Apoyo total.",
                "El míster tiene razón, quien no lo vea es ciego.",
                "Por eso es el entrenador y tú estás en el sofá 😂",
                "Bien dicho. A seguir trabajando 💪",
                "Así se defiende a las tuyas. Chapó.",
                "Por fin un entrenador con criterio en este club.",
                "Que hablen en el campo, como dice el míster.",
                "Me gusta este discurso, sin paños calientes.",
                "El míster lo tiene clarísimo. A por el siguiente partido.",
                "Ojalá más entrenadores así de directos.",
                "Se nota que conoce al vestuario. Confianza plena.",
                "Esto es liderazgo, lo demás son tonterías.",
            ] : [
                "The gaffer is absolutely right 👏",
                "Finally someone telling it like it is.",
                "Back the manager. Always.",
                "Spot on from the boss.",
                "This is why he's the manager 💪",
                "Well said. On to the next one.",
                "That's leadership right there.",
                "Finally a manager with a clear idea.",
                "Love this honesty. More of this please.",
                "He knows his squad. Full trust.",
            ];
        }

        // Negative templates, some reference the statement type.
        $specific = match ($statementKey) {
            'praise_star' => $es
                ? [
                    "¿Elogiar a {$name}? ¿Pero qué partido ha visto este tío?",
                    "Míster, creo que te has equivocado de jugadora...",
                    "¿Pero quién le ha dicho que {$name} ha hecho un partidazo? ¿Su representante?",
                    "Elogiar a {$name} después de eso es de traca.",
                    "Si {$name} ha sido la mejor, apaga y vámonos.",
                ]
                : [
                    "Praise for {$name}? What game was he watching?",
                    "Wrong player, gaffer...",
                    "If {$name} was the best out there, we're in trouble.",
                    "Praising {$name} after that is unreal.",
                ],
            'criticize_flop' => $es
                ? [
                    "Así no, míster. A {$name} no se la deja tirada en público.",
                    "Señalar a {$name} delante de todos... muy feo.",
                    "Cargar contra {$name} en público dice mucho de él y nada bueno.",
                    "A {$name} se la defiende, no se la señala.",
                    "Qué fácil es culpar a {$name} en vez de mirar el banquillo.",
                ]
                : [
                    "Not like this, gaffer. You don't throw {$name} under the bus.",
                    "Calling out {$name} publicly... classless.",
                    "Blaming {$name} in public says a lot about him.",
                    "You protect {$name}, you don't single her out.",
                ],
            'blame_referee' => $es
                ? [
                    "Siempre la culpa es del árbitro, nunca del planteamiento 🙄",
                    "Excusas, excusas y más excusas.",
                    "El árbitro no ha fallado los pases, míster.",
                    "Siempre igual: si perdemos, la culpa es del de negro.",
                    "Que mire la repetición del partido en vez del acta.",
                ]
                : [
                    "Always the ref's fault, never the tactics 🙄",
                    "Excuses, excuses.",
                    "The ref didn't misplace those passes, gaffer.",
                    "Blame the ref again, why don't you.",
                ],
            'defend_tactics' => $es
                ? [
                    "Defender esto es de ser muy terco. #MisterOut",
                    "El planteamiento fue un desastre y lo sabe todo el mundo menos él.",
                    "Si esto era el plan, el plan era perder.",
                    "Defender lo indefendible, capítulo 47.",
                    "Con este sistema no marcamos ni en un entrenamiento.",
                ]
                : [
                    "Defending this is pure stubbornness. #GafferOut",
                    "The tactics were a disaster and everyone knows it but him.",
                    "If that was the plan, the plan was to lose.",
                    "Defending the indefensible again.",
                ],
            default => [],
        };

        $generic = $es ? [
            "Este tío no tiene ni idea de fútbol.",
            "Vete ya, por favor. #MisterOut",
            "Cada rueda de prensa es peor que la anterior.",
            "¿Cómo puede seguir en el cargo?",
            "Nos está hundiendo y encima raja. Increíble.",
            "Dimisión ya. Esto no da para más.",
            "Que alguien le quite el micrófono 🎤❌",
            "Otra rueda de prensa para enmarcar... en la pared de los horrores.",
            "Este hombre vive en una realidad paralela.",
            "Que devuelva el carnet de entrenador.",
            "Con este planteamiento no ganamos ni al filial.",
            "Rueda de prensa tras rueda de prensa y ninguna autocrítica.",
            "El vestuario tiene que estar hasta las narices.",
            "A ver si la directiva espabila de una vez.",
            "Menudo vendehumos nos han colado.",
            "Yo ya ni me enfado, me da pena.",
            "Cada partido la misma historia.",
            "Si esto es un proyecto deportivo, yo soy astronauta.",
            "Que alguien revise su contrato, tiene que haber una cláusula de escape.",
            "Habla como si fuéramos líderes. Míster, mire la clasificación.",
            "Ni en pretemporada se veía algo tan malo.",
            "Directiva, ¿a qué esperáis? #FueraYa",
            "Este tío confundiría un fuera de juego con un fuera de serie.",
            "Vergüenza de rueda de prensa.",
            "Que se dedique a otra cosa, por favor.",
            "Lo único que defiende bien son sus excusas.",
            "Da igual el rival, el discurso es el mismo de siempre.",
            "El fútbol femenino merece mejores entrenadores que este.",
        ] : [
            "This guy has no clue.",
            "Get out of our club. #GafferOut",
            "Every press conference is worse than the last.",
            "How is he still in charge?",
            "He's sinking us and still talking. Unbelievable.",
            "Resign now. This can't go on.",
            "Someone take the mic away from him 🎤❌",
            "Living in a parallel universe, this man.",
            "Hand in your coaching badge, mate.",
            "No self-criticism, ever. Just excuses.",
            "The dressing room must be sick of this.",
            "Same story every single week.",
            "If this is a project, I'm an astronaut.",
            "He talks like we're top of the league. Check the table.",
            "Shameful press conference.",
            "The only thing he defends well is his excuses.",
            "Women's football deserves better managers than this.",
            "Board, what are you waiting for? #OutNow",
            "Take up another profession, please.",
            "Every game the same tired speech.",
        ];

        return array_merge($specific, $generic);
    }

    /**
     * A random fictional fan. $used tracks "name|handle" combos already
     * handed out in the current batch so the same person doesn't post
     * twice in a row with two different handles.
     *
     * @param array<string, bool> $used
     * @return array{0:string, 1:string}
     */
    public function randomFan(array &$used = []): array
    {
        $es = app()->getLocale() === 'es';

        $names = $es
            ? [
                'Lucía G.', 'Marta R.', 'Carmen S.', 'Paula M.', 'Sofía L.', 'Elena V.', 'Irene T.', 'Nerea B.',
                'Ainhoa Z.', 'Laia P.', 'Jordi C.', 'Marc V.', 'Pol F.', 'Oriol D.',
                'Júlia F.', 'Aina V.', 'Carla R.', 'Núria S.', 'Mar B.', 'Jana L.', 'Ona T.', 'Clàudia M.',
                'Abril G.', 'Martina P.', 'Laia S.', 'Anna R.', 'Mireia C.', 'Joana V.', 'Teresa B.', 'Blanca L.',
                'Alba F.', 'Irene G.', 'Sara M.', 'Noa P.', 'Vega S.', 'Iria C.',
                'Uxue L.', 'Maialen R.', 'Naroa V.', 'June B.', 'Haizea M.', 'Ane L.', 'Izaro P.', 'Garazi S.',
                'Nil B.', 'Arnau V.', 'Quim R.', 'Roger M.', 'Biel S.', 'Jan F.', 'Èric L.', 'Martí G.',
                'Àlex P.', 'Sergi V.', 'Dani R.', 'Pau M.', 'Hugo S.', 'Leo F.', 'Teo B.', 'Gala N.',
                'Rut C.', 'Júlia B.', 'Ona R.', 'Aina S.', 'Carla V.', 'Núria F.', 'Mar R.', 'Jana P.',
            ]
            : [
                'Lucy G.', 'Martha R.', 'Carmen S.', 'Paula M.', 'Sophie L.', 'Elena V.', 'Irene T.', 'Nerea B.',
                'Ainhoa Z.', 'Laia P.',
                'Emily W.', 'Jessica H.', 'Chloe D.', 'Olivia K.', 'Amelia N.', 'Isla B.', 'Ruby T.', 'Grace M.',
                'Lily P.', 'Evie S.', 'Daisy R.', 'Freya L.', 'Hannah W.', 'Millie B.', 'Poppy D.',
                'Archie K.', 'Alfie M.', 'Freddie P.', 'George R.', 'Harry S.', 'Jack T.', 'Oliver W.',
                'Charlie B.', 'Theo D.', 'Ollie F.', 'Louie M.',
            ];

        $prefixes = [
            '@futbolfem_', '@woso_fan', '@grada_', '@forofa_', '@hincha_', '@ultra_',
            '@tribuna_', '@futfem_', '@woso_', '@gol_', '@banquillo_', '@corner_',
            '@delantera_', '@elonce_', '@minuto90_', '@fondo_',
        ];
        $words = ['gol', 'corner', 'grada', 'woso', 'futbol', 'banquillo', 'tribuna', 'once', 'fuera', 'area'];

        for ($attempt = 0; $attempt < 25; $attempt++) {
            $name = $names[array_rand($names)];

            if (rand(1, 100) <= 35) {
                // Handle built from the fan's first name: @laia_gol23.
                $first = strtolower(ClubSocialService::ascii(explode(' ', $name)[0]));
                $first = preg_replace('/[^a-z]/', '', $first);
                $handle = '@' . $first . '_' . $words[array_rand($words)] . rand(2, 99);
            } else {
                $handle = $prefixes[array_rand($prefixes)] . rand(100, 999);
            }

            $key = $name . '|' . $handle;
            if (! isset($used[$key])) {
                $used[$key] = true;
                return [$name, $handle];
            }
        }

        return [$name, $handle];
    }

    private function generateWarningPosts(Game $game): void
    {
        $es = app()->getLocale() === 'es';
        $texts = $es ? [
            "🚨 La directiva empieza a estar harta. El puesto del míster peligra.",
            "Según cuentan, la junta ya habla de un posible relevo en el banquillo.",
        ] : [
            "🚨 The board is losing patience. The manager's job is at risk.",
            "Reports say the board is already discussing a possible change in the dugout.",
        ];

        $usedFans = [];
        foreach ($texts as $text) {
            [$name, $handle] = $this->randomFan($usedFans);
            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $name,
                'author_handle' => $handle,
                'text' => $text,
                'sentiment' => -1,
                'likes' => rand(200, 1000),
                'context' => 'board_warning',
            ]);
        }

        // A fictional journalist picks up the rumour too (no-op if the
        // game's newsroom was never seeded).
        app(\App\Modules\Media\Services\JournalistService::class)
            ->postBoardRumor($game, $game->team?->name ?? ($es ? 'tu club' : 'your club'));
    }

    private function generateSackingPosts(Game $game): void
    {
        // M17 (QA FEED-04): the sacking is announced ONCE per game. The
        // three texts are fixed strings, so re-announcing after every
        // subsequent press conference fills the feed with identical posts.
        $alreadyAnnounced = SocialPost::where('game_id', $game->id)
            ->where('context', 'sacked')
            ->exists();

        if ($alreadyAnnounced) {
            return;
        }

        $es = app()->getLocale() === 'es';
        $texts = $es ? [
            "🚨🚨 OFICIAL: El club destituye al entrenador con efecto inmediato.",
            "Se acabó. La directiva no aguanta más y echa al míster.",
            "Era cuestión de tiempo. Gracias por nada.",
        ] : [
            "🚨🚨 OFFICIAL: The club sacks the manager with immediate effect.",
            "It's over. The board has had enough.",
            "It was only a matter of time.",
        ];

        $usedFans = [];
        foreach ($texts as $text) {
            [$name, $handle] = $this->randomFan($usedFans);
            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $name,
                'author_handle' => $handle,
                'text' => $text,
                'sentiment' => -1,
                'likes' => rand(500, 2000),
                'context' => 'sacked',
            ]);
        }
    }

    /**
     * Deadline-day drama for the winter window: during the final week of
     * January, fans react to pending bids on the user's players with rumor
     * posts ("👀 RUMOR: ..."). Deduplicated — at most one batch per game
     * date (M18 / QA FEED-05: the old dedup used the REAL clock, so playing
     * the deadline week across 3+ real days re-generated identical batches
     * for the same still-active offers).
     */
    public function generateDeadlineDayRumors(Game $game): void
    {
        if (! $game->isWinterWindowOpen()) {
            return;
        }

        // Deadline week: last 7 days of January.
        $date = $game->current_date;
        if ($date->month !== 1 || $date->day < 25) {
            return;
        }

        $gameDate = $date->toDateString();

        $recent = SocialPost::where('game_id', $game->id)
            ->where('context', 'deadline_rumor')
            ->where('game_date', $gameDate)
            ->exists();

        if ($recent) {
            return;
        }

        $offers = TransferOffer::with(['gamePlayer', 'offeringTeam'])
            ->where('game_id', $game->id)
            ->ofType(TransferOffer::TYPE_UNSOLICITED)
            ->active()
            ->departingFrom($game->userTeamIds())
            ->orderByDesc('transfer_fee')
            ->take(3)
            ->get();

        if ($offers->isEmpty()) {
            return;
        }

        $es = app()->getLocale() === 'es';

        foreach ($offers as $offer) {
            $playerName = $offer->gamePlayer?->name;
            $clubName = $offer->offeringTeam?->name;

            if (! $playerName || ! $clubName) {
                continue;
            }

            $templates = $es ? [
                "👀 RUMOR: {$clubName} aprieta por {$playerName} en el día límite. ¡No la vendáis!",
                "Dicen que {$playerName} tiene una oferta del {$clubName} sobre la mesa... Últimas horas del mercado 😰",
                "⏰ {$clubName} quiere a {$playerName} SÍ o SÍ antes de que cierre enero. La afición cruza los dedos.",
            ] : [
                "👀 RUMOUR: {$clubName} are pushing for {$playerName} on deadline day. Don't sell her!",
                "Word is {$playerName} has an offer from {$clubName} on the table... Final hours of the window 😰",
                "⏰ {$clubName} want {$playerName} no matter what before January closes. Fans have their fingers crossed.",
            ];

            [$name, $handle] = $this->randomFan();

            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $name,
                'author_handle' => $handle,
                'text' => $templates[array_rand($templates)],
                'sentiment' => 0,
                'likes' => rand(300, 1500),
                'context' => 'deadline_rumor',
                'game_date' => $gameDate,
            ]);
        }
    }

    /** @return array<array{id: string, name: string, rating: float}> */
    private function playerRatings(Game $game, GameMatch $match): array
    {
        // TODO: pull real ratings from match data when available.
        // For now, synthesize plausible ratings from player overalls.
        // M16 (QA FEED-03): GamePlayer has no `overall` attribute — the
        // column is `overall_score`. Using it makes star/flop picks derive
        // from the actual squad quality instead of noise around 7.0.
        $ratings = [];
        $players = \App\Models\GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->limit(14)
            ->get();

        foreach ($players as $p) {
            $base = (($p->overall_score ?? 70)) / 10; // 70 -> 7.0
            $ratings[] = [
                'id' => $p->id,
                'name' => $p->name ?? 'Jugadora',
                'rating' => round(max(4.0, min(10.0, $base + (rand(-15, 15) / 10))), 1),
            ];
        }

        return $ratings;
    }

    private function didWin(Game $game, GameMatch $match): bool
    {
        if ($match->home_team_id === $game->team_id) {
            return ($match->home_score ?? 0) > ($match->away_score ?? 0);
        }
        return ($match->away_score ?? 0) > ($match->home_score ?? 0);
    }

    private function didLose(Game $game, GameMatch $match): bool
    {
        if ($match->home_team_id === $game->team_id) {
            return ($match->home_score ?? 0) < ($match->away_score ?? 0);
        }
        return ($match->away_score ?? 0) < ($match->home_score ?? 0);
    }

    /**
     * The manager fires back at a hater post with one of the predefined
     * replies. Applies small effects (board confidence, squad morale),
     * stores the reply on the post and generates fan reactions to it.
     *
     * @throws \InvalidArgumentException on unknown reply key
     */
    public function replyToHater(Game $game, SocialPost $post, string $replyKey): SocialPost
    {
        if (! isset(self::HATER_REPLIES[$replyKey])) {
            throw new \InvalidArgumentException("Unknown hater reply: {$replyKey}");
        }

        [$labelEs, $labelEn, $boardDelta, $moraleDelta, $tone] = self::HATER_REPLIES[$replyKey];
        $es = app()->getLocale() === 'es';
        $replyText = $es ? $labelEs : $labelEn;

        $post->manager_reply_key = $replyKey;
        $post->manager_reply_text = $replyText;
        $post->save();

        // Board confidence effect.
        $game->board_confidence = max(0, min(100, ($game->board_confidence ?? 70) + $boardDelta));
        $game->save();

        // Squad morale effect (clamped 0-100, single query).
        if ($moraleDelta !== 0) {
            $delta = (int) $moraleDelta;
            \DB::table('game_player_match_state')
                ->where('game_id', $game->id)
                ->whereIn('game_player_id', fn ($q) => $q->select('id')->from('game_players')
                    ->where('game_id', $game->id)
                    ->where('team_id', $game->team_id))
                ->update(['morale' => \DB::raw("LEAST(100, GREATEST(0, morale + {$delta}))")]);
        }

        // Fans react to the comeback.
        $this->generateReplyReactions($game, $post, $tone, $es);

        return $post->refresh();
    }

    /**
     * Localized reply options for the reply picker UI.
     *
     * @return list<array{key: string, label: string}>
     */
    public function haterReplyOptions(): array
    {
        $es = app()->getLocale() === 'es';
        $options = [];

        foreach (self::HATER_REPLIES as $key => [$labelEs, $labelEn]) {
            $options[] = ['key' => $key, 'label' => $es ? $labelEs : $labelEn];
        }

        return $options;
    }

    private function generateReplyReactions(Game $game, SocialPost $post, string $tone, bool $es): void
    {
        // Spicy comebacks split the fanbase; firm/calm ones mostly land well.
        $positiveChance = $tone === 'spicy' ? 35 : ($tone === 'firm' ? 75 : 60);

        $positive = $es ? [
            'Jajaja, bien dicho míster 🔥',
            'Así se responde. A por ellos.',
            'El míster tiene carácter, me gusta.',
            'Por fin alguien que no se esconde.',
        ] : [
            'Haha, well said gaffer 🔥',
            'That\'s how you answer. Let\'s go.',
            'The gaffer has character, I like it.',
            'Finally someone who doesn\'t hide.',
        ];

        $negative = $es ? [
            'Chulería en vez de resultados... mal vamos.',
            'Menos tuits y más entrenar.',
            'Esto no lo arregla con frases.',
            'Que se centre en el campo y calle.',
        ] : [
            'Sass instead of results... not good.',
            'Less tweeting, more training.',
            'He won\'t fix this with quotes.',
            'Focus on the pitch and stay quiet.',
        ];

        $count = rand(2, 4);
        $usedFans = [];
        for ($i = 0; $i < $count; $i++) {
            $isPositive = rand(1, 100) <= $positiveChance;
            $pool = $isPositive ? $positive : $negative;

            [$name, $handle] = $this->randomFan($usedFans);

            SocialPost::create([
                'game_id' => $game->id,
                'author_name' => $name,
                'author_handle' => $handle,
                'text' => $pool[array_rand($pool)],
                'sentiment' => $isPositive ? 1 : -1,
                'likes' => rand(5, 300),
                'context' => 'manager_reply',
                'match_id' => $post->match_id,
            ]);
        }
    }

    /**
     * Whether the manager has been sacked (board confidence collapsed).
     */
    public function isSacked(Game $game): bool
    {
        return ($game->board_confidence ?? 70) < 15;
    }
}
