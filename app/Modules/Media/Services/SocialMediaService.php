<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\PressStatement;
use App\Models\SocialPost;

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

        PressStatement::create([
            'game_id' => $game->id,
            'match_id' => $match->id,
            'statement_key' => $statementKey,
            'target_player_id' => $playerId,
            'sentiment_impact' => $result['impact'],
        ]);

        // Generate 6-10 fan posts.
        $count = rand(6, 10);
        for ($i = 0; $i < $count; $i++) {
            $this->generatePost($game, $match, $result, $targetName, $statementKey);
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

    private function generatePost(Game $game, GameMatch $match, array $result, ?string $targetName, string $statementKey): void
    {
        $mood = $result['mood'];
        // Mixed mood: randomize each post's sentiment.
        $sentiment = $mood === 'mixed' ? (rand(0, 1) ? 1 : -1) : ($mood === 'positive' ? 1 : -1);

        $templates = $this->templatesFor($sentiment, $statementKey, $targetName);
        $text = $templates[array_rand($templates)];

        [$name, $handle] = $this->randomFan();

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
            ] : [
                "The gaffer is absolutely right 👏",
                "Finally someone telling it like it is.",
                "Back the manager. Always.",
                "Spot on from the boss.",
                "This is why he's the manager 💪",
            ];
        }

        // Negative templates, some reference the statement type.
        $specific = match ($statementKey) {
            'praise_star' => $es
                ? ["¿Elogiar a {$name}? ¿Pero qué partido ha visto este tío?", "Míster, creo que te has equivocado de jugadora..."]
                : ["Praise for {$name}? What game was he watching?", "Wrong player, gaffer..."],
            'criticize_flop' => $es
                ? ["Así no, míster. A {$name} no se la deja tirada en público.", "Señalar a {$name} delante de todos... muy feo."]
                : ["Not like this, gaffer. You don't throw {$name} under the bus.", "Calling out {$name} publicly... classless."],
            'blame_referee' => $es
                ? ["Siempre la culpa es del árbitro, nunca del planteamiento 🙄", "Excusas, excusas y más excusas."]
                : ["Always the ref's fault, never the tactics 🙄", "Excuses, excuses."],
            'defend_tactics' => $es
                ? ["Defender esto es de ser muy terco. #MisterOut", "El planteamiento fue un desastre y lo sabe todo el mundo menos él."]
                : ["Defending this is pure stubbornness. #GafferOut", "The tactics were a disaster and everyone knows it but him."],
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
        ] : [
            "This guy has no clue.",
            "Get out of our club. #GafferOut",
            "Every press conference is worse than the last.",
            "How is he still in charge?",
            "He's sinking us and still talking. Unbelievable.",
            "Resign now. This can't go on.",
        ];

        return array_merge($specific, $generic);
    }

    private function randomFan(): array
    {
        $es = app()->getLocale() === 'es';

        $names = $es
            ? ['Lucía G.', 'Marta R.', 'Carmen S.', 'Paula M.', 'Sofía L.', 'Elena V.', 'Irene T.', 'Nerea B.', 'Ainhoa Z.', 'Laia P.', 'Jordi C.', 'Marc V.', 'Pol F.', 'Oriol D.']
            : ['Lucy G.', 'Martha R.', 'Carmen S.', 'Paula M.', 'Sophie L.', 'Elena V.', 'Irene T.', 'Nerea B.', 'Ainhoa Z.', 'Laia P.'];

        $handles = ['@futbolfem_', '@woso_fan', '@grada_', '@forofa_', '@hincha_', '@ultra_'];

        $name = $names[array_rand($names)];
        $handle = $handles[array_rand($handles)] . rand(10, 99);

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

        foreach ($texts as $text) {
            [$name, $handle] = $this->randomFan();
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
    }

    private function generateSackingPosts(Game $game): void
    {
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

        foreach ($texts as $text) {
            [$name, $handle] = $this->randomFan();
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

    /** @return array<array{id: string, name: string, rating: float}> */
    private function playerRatings(Game $game, GameMatch $match): array
    {
        // TODO: pull real ratings from match data when available.
        // For now, synthesize plausible ratings from player overalls.
        $ratings = [];
        $players = \App\Models\GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->limit(14)
            ->get();

        foreach ($players as $p) {
            $base = ($p->overall ?? 70) / 10; // 70 -> 7.0
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
     * Whether the manager has been sacked (board confidence collapsed).
     */
    public function isSacked(Game $game): bool
    {
        return ($game->board_confidence ?? 70) < 15;
    }
}
