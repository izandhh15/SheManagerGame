<?php

namespace App\Modules\Media\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\PreMatchPress;
use Illuminate\Support\Facades\DB;

/**
 * Pre-match press conferences ("Rueda de prensa").
 *
 * Before a big match — derby, final, european night, or a clash with a
 * direct rival — journalists ask the manager 2-3 questions with 3 possible
 * answers each. Answers move squad morale and board confidence through the
 * SAME channels the rest of the media system uses (game_player_match_state
 * morale + Game::board_confidence), so there is no parallel system.
 *
 * Journalists are fictional or generic — never real media brands.
 * Question selection is deterministic per match (priority-ordered reasons),
 * so the page is stable across reloads.
 */
class PressConferenceService
{
    /**
     * Reason => [fictional journalist name (or '' for generic), outlet es, outlet en].
     */
    private const QUESTIONS = [
        'importance' => [
            'by' => 'Lucía Ferrer',
            'outlet' => ['Diario deportivo', 'Sports daily'],
            'question' => [
                '¿Qué supone para el equipo este partido tan señalado?',
                'What does such a big match mean for the team?',
            ],
            'answers' => [
                ['key' => 'all_in', 'morale' => 4, 'confidence' => 2,
                    'label' => ['Es el partido del año. Vamos a por todas, sin miedo.', "It's the game of the year. We're going for it, no fear."]],
                ['key' => 'calm', 'morale' => 2, 'confidence' => 1,
                    'label' => ['Un partido más: respetamos al rival, pero confiamos en lo nuestro.', 'Just another game: we respect the opponents, but we trust ourselves.']],
                ['key' => 'fearful', 'morale' => -3, 'confidence' => -2,
                    'label' => ['Si lo perdemos se complica todo. Hay que ser realistas.', "If we lose it, everything gets complicated. Let's be realistic."]],
            ],
        ],
        'derby' => [
            'by' => 'Andrés Soler',
            'outlet' => ['Radio local', 'Local radio'],
            'question' => [
                '¿Cómo se vive el derbi en el vestuario?',
                'How is the derby being felt in the dressing room?',
            ],
            'answers' => [
                ['key' => 'heart', 'morale' => 4, 'confidence' => 1,
                    'label' => ['Los derbis se juegan con el corazón. La grada lo va a notar.', 'Derbies are played with heart. The fans will feel it.']],
                ['key' => 'cool_head', 'morale' => 2, 'confidence' => 2,
                    'label' => ['Es un derbi, sí, pero hay que mantener la cabeza fría.', "It's a derby, yes, but we must keep a cool head."]],
                ['key' => 'dismiss', 'morale' => -3, 'confidence' => -2,
                    'label' => ['El derbi les importa más a los aficionados que a nosotras.', 'The derby matters more to the fans than to us.']],
            ],
        ],
        'final' => [
            'by' => 'Marta Vidal',
            'outlet' => ['Diario deportivo', 'Sports daily'],
            'question' => [
                '¿Cómo afronta el equipo la final?',
                'How is the team approaching the final?',
            ],
            'answers' => [
                ['key' => 'winners', 'morale' => 4, 'confidence' => 2,
                    'label' => ["Las finales no se juegan: se ganan. Estamos listas.", "Finals aren't played: they're won. We're ready."]],
                ['key' => 'enjoy', 'morale' => 2, 'confidence' => 1,
                    'label' => ['Llegar hasta aquí ya es un premio, pero queremos el título.', "Getting here is already a prize, but we want the trophy."]],
                ['key' => 'pressure', 'morale' => -4, 'confidence' => -3,
                    'label' => ['La presión de una final puede con cualquiera.', 'Final pressure can get to anyone.']],
            ],
        ],
        'european' => [
            'by' => '',
            'outlet' => ['Diario deportivo', 'Sports daily'],
            'question' => [
                '¿Qué significa para el club jugar en Europa?',
                'What does playing in Europe mean for the club?',
            ],
            'answers' => [
                ['key' => 'showcase', 'morale' => 3, 'confidence' => 2,
                    'label' => ['Europa es el escaparate: vamos a demostrar quiénes somos.', "Europe is the shop window: we'll show who we are."]],
                ['key' => 'step_by_step', 'morale' => 1, 'confidence' => 1,
                    'label' => ['Partido a partido, sin mirar más allá del siguiente.', 'One game at a time, looking no further ahead.']],
                ['key' => 'suffer', 'morale' => -3, 'confidence' => -1,
                    'label' => ['Fuera de casa en Europa siempre se sufre.', 'Away in Europe is always a struggle.']],
            ],
        ],
        'rival' => [
            'by' => 'Jorge Campos',
            'outlet' => ['Periódico local', 'Local newspaper'],
            'question' => [
                '¿El rival directo parte como favorito?',
                'Are your direct rivals the favourites?',
            ],
            'answers' => [
                ['key' => 'talk_pitch', 'morale' => 3, 'confidence' => 2,
                    'label' => ['Que hablen ellos: nosotras hablamos en el campo.', 'Let them talk: we do our talking on the pitch.']],
                ['key' => 'details', 'morale' => 2, 'confidence' => 1,
                    'label' => ['Será un partido igualado, de pequeños detalles.', "It'll be a tight game, decided by small details."]],
                ['key' => 'realistic', 'morale' => -2, 'confidence' => -2,
                    'label' => ['Tienen mejor plantilla. Hay que ser realistas.', "They have the better squad. Let's be realistic."]],
            ],
        ],
        'fans' => [
            'by' => '',
            'outlet' => ['Radio local', 'Local radio'],
            'question' => [
                '¿Qué mensaje le manda a la afición?',
                'What is your message to the fans?',
            ],
            'answers' => [
                ['key' => 'fill_stadium', 'morale' => 3, 'confidence' => 2,
                    'label' => ['Que llenen el estadio: lo vamos a dar todo por ellas.', "Pack the stadium: we'll give everything for them."]],
                ['key' => 'trust', 'morale' => 2, 'confidence' => 1,
                    'label' => ['Que confíen: este equipo nunca se rinde.', 'Trust us: this team never gives up.']],
                ['key' => 'no_miracles', 'morale' => -3, 'confidence' => -2,
                    'label' => ['Que no esperen milagros este fin de semana.', "Don't expect miracles this weekend."]],
            ],
        ],
    ];

    /** Priority order for context questions. */
    private const REASON_PRIORITY = ['final', 'derby', 'european', 'rival'];

    /**
     * Why this match deserves a press conference, priority-ordered.
     * Reasons: final, derby, european, rival.
     *
     * @return list<string>
     */
    public function bigMatchReasons(Game $game, GameMatch $match): array
    {
        $reasons = [];
        $roundName = strtolower($match->round_name ?? '');

        if ($match->isCupMatch() && str_contains($roundName, 'final') && ! str_contains($roundName, 'semi')) {
            $reasons[] = 'final';
        }

        if (($match->competition?->role ?? '') === Competition::ROLE_EUROPEAN) {
            $reasons[] = 'european';
        }

        $directRival = $this->isDirectRival($game, $match);

        if ($this->isDerby($match) && ($match->isCupMatch() || $directRival)) {
            $reasons[] = 'derby';
        }

        if ($directRival) {
            $reasons[] = 'rival';
        }

        // Priority order (final > derby > european > rival), deduped.
        $ordered = [];
        foreach (self::REASON_PRIORITY as $reason) {
            if (in_array($reason, $reasons, true)) {
                $ordered[] = $reason;
            }
        }

        return $ordered;
    }

    public function isBigMatch(Game $game, GameMatch $match): bool
    {
        return $this->bigMatchReasons($game, $match) !== [];
    }

    /**
     * 3 questions, each with 3 answers: the importance question, up to two
     * context questions (priority-ordered), and the fans question when there
     * is only one context reason. Localized to the current app locale.
     *
     * @return list<array{key: string, by: string, outlet: string, question: string, answers: list<array{key: string, label: string, morale: int, confidence: int}>}>
     */
    public function questions(Game $game, GameMatch $match): array
    {
        $es = app()->getLocale() === 'es';
        $reasons = $this->bigMatchReasons($game, $match);

        $keys = ['importance'];
        $contextKeys = array_slice($reasons, 0, 2);
        foreach ($contextKeys as $reason) {
            $keys[] = $reason;
        }
        if (count($reasons) === 1) {
            $keys[] = 'fans';
        }
        $keys = array_values(array_unique(array_slice($keys, 0, 3)));

        $out = [];
        foreach ($keys as $key) {
            $def = self::QUESTIONS[$key];
            $out[] = [
                'key' => $key,
                'by' => $def['by'] !== ''
                    ? $def['by']
                    : ($es ? 'Un periodista' : 'A journalist'),
                'outlet' => $es ? $def['outlet'][0] : $def['outlet'][1],
                'question' => $es ? $def['question'][0] : $def['question'][1],
                'answers' => array_map(fn (array $a) => [
                    'key' => $a['key'],
                    'label' => $es ? $a['label'][0] : $a['label'][1],
                    'morale' => $a['morale'],
                    'confidence' => $a['confidence'],
                ], $def['answers']),
            ];
        }

        return $out;
    }

    public function alreadyAnswered(Game $game, GameMatch $match): bool
    {
        return PreMatchPress::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->exists();
    }

    public function findRecord(Game $game, GameMatch $match): ?PreMatchPress
    {
        return PreMatchPress::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->first();
    }

    /**
     * Store the manager's answers and apply their effects. Idempotent: if
     * the press conference was already held for this match, the existing
     * record is returned and nothing is applied twice.
     *
     * @param array<string, string> $answers question key => answer key
     *
     * @throws \InvalidArgumentException on unknown question or answer keys
     */
    public function answer(Game $game, GameMatch $match, array $answers): PreMatchPress
    {
        $existing = $this->findRecord($game, $match);
        if ($existing) {
            return $existing;
        }

        $questions = $this->questions($game, $match);
        $byKey = [];
        foreach ($questions as $q) {
            $byKey[$q['key']] = $q;
        }

        $moraleDelta = 0;
        $confidenceDelta = 0;
        $stored = [];

        foreach ($answers as $questionKey => $answerKey) {
            if (! isset($byKey[$questionKey])) {
                throw new \InvalidArgumentException("Unknown press question: {$questionKey}");
            }

            $answer = null;
            foreach ($byKey[$questionKey]['answers'] as $a) {
                if ($a['key'] === $answerKey) {
                    $answer = $a;
                    break;
                }
            }

            if (! $answer) {
                throw new \InvalidArgumentException("Unknown answer '{$answerKey}' for question '{$questionKey}'");
            }

            $stored[$questionKey] = $answerKey;
            $moraleDelta += $answer['morale'];
            $confidenceDelta += $answer['confidence'];
        }

        $record = PreMatchPress::create([
            'game_id' => $game->id,
            'match_id' => $match->id,
            'answers' => $stored,
            'morale_delta' => $moraleDelta,
            'confidence_delta' => $confidenceDelta,
        ]);

        $this->applyEffects($game, $moraleDelta, $confidenceDelta);

        return $record;
    }

    /**
     * A derby is two clubs from the same country meeting in a cup tie or as
     * direct league rivals — the matches that genuinely feel like derbies.
     */
    private function isDerby(GameMatch $match): bool
    {
        $home = $match->homeTeam;
        $away = $match->awayTeam;

        if (! $home || ! $away) {
            return false;
        }

        return ($home->country ?? null) !== null
            && $home->country === ($away->country ?? null);
    }

    /**
     * Direct rival: a league match (matchday 4+) against a team within 3
     * positions and 4 points in the standings.
     */
    private function isDirectRival(Game $game, GameMatch $match): bool
    {
        if ($game->pre_season || ! $game->competition_id || $match->competition_id !== $game->competition_id) {
            return false;
        }

        if (($match->round_number ?? 0) < 4) {
            return false;
        }

        $userIsHome = $match->home_team_id === $game->team_id;
        $opponentId = $userIsHome ? $match->away_team_id : $match->home_team_id;

        $player = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();
        $opponent = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $opponentId)
            ->first();

        if (! $player || ! $opponent) {
            return false;
        }

        return abs($player->position - $opponent->position) <= 3
            && abs($player->points - $opponent->points) <= 4;
    }

    /**
     * Same effect channels as the rest of the media system: board confidence
     * on the game, squad morale on every player's match state (clamped).
     */
    private function applyEffects(Game $game, int $moraleDelta, int $confidenceDelta): void
    {
        if ($confidenceDelta !== 0) {
            $game->board_confidence = max(0, min(100, ($game->board_confidence ?? 70) + $confidenceDelta));
            $game->save();
        }

        if ($moraleDelta !== 0) {
            $delta = (int) $moraleDelta;
            DB::table('game_player_match_state')
                ->where('game_id', $game->id)
                ->whereIn('game_player_id', fn ($q) => $q->select('id')->from('game_players')
                    ->where('game_id', $game->id)
                    ->where('team_id', $game->team_id))
                ->update(['morale' => DB::raw("LEAST(100, GREATEST(0, morale + {$delta}))")]);
        }
    }
}
