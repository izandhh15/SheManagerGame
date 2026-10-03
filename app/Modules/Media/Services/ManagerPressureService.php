<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;
use App\Models\Team;
use App\Modules\Competition\Services\CalendarService;
use App\Modules\Match\DTOs\MatchNarrative;

/**
 * Detects managers "on the ropes" and turns the press rumour mill into full
 * media articles ("El entrenador del Barça Femenino podría ser cesado").
 *
 * A team is under pressure when its last 5 matches yield 4 points or fewer,
 * or it has lost the last 3 in a row (minimum 3 played, to avoid
 * opening-weekend noise). Checked teams: the player's own team, the
 * AI-managed first team in affiliate careers (so the rumours build up before
 * the board actually pulls the trigger), and a few league rivals.
 *
 * Coach names come from Team::manager_name (real names only — never
 * invented). When the name is unknown the article talks about "el
 * entrenador del X" without naming anyone.
 *
 * Picks and template variants are deterministic per round (crc32 seeds), so
 * the news feed is stable across page loads.
 */
class ManagerPressureService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly MediaOutletService $mediaOutlets,
    ) {}

    /**
     * @return array<MatchNarrative>
     */
    public function pressureArticles(Game $game, ?GameMatch $nextMatch): array
    {
        $round = $nextMatch?->round_number ?? 0;
        $articles = [];

        foreach ($this->candidateTeamIds($game, $nextMatch, $round) as $teamId) {
            $team = Team::find($teamId);
            if (! $team) {
                continue;
            }

            $form = $this->calendar->getTeamForm($game->id, $teamId, 5);
            if (! $this->isUnderPressure($form)) {
                continue;
            }

            $articles[] = $this->buildArticle($game, $team, $form, $round);
        }

        return $articles;
    }

    /**
     * @return list<string>
     */
    private function candidateTeamIds(Game $game, ?GameMatch $nextMatch, int $round): array
    {
        $ids = [$game->team_id];

        // Affiliate career: the AI-managed first team. Rumours here foreshadow
        // the board sacking its coach and handing the job to the user.
        $managed = $game->team;
        if ($managed?->parent_team_id) {
            $ids[] = $managed->parent_team_id;
        }

        // A few league rivals, picked deterministically per round.
        if ($game->competition_id) {
            $rivals = GameStanding::where('game_id', $game->id)
                ->where('competition_id', $game->competition_id)
                ->whereNotIn('team_id', $ids)
                ->orderBy('team_id')
                ->pluck('team_id')
                ->all();

            if (! empty($rivals)) {
                $start = (crc32($game->id.'rivals'.$round) & 0x7FFFFFFF) % count($rivals);
                for ($i = 0; $i < 3; $i++) {
                    $ids[] = $rivals[($start + $i) % count($rivals)];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param list<string> $form e.g. ['W','D','L','L','L'] oldest-first
     */
    private function isUnderPressure(array $form): bool
    {
        if (count($form) < 3) {
            return false;
        }

        $points = 0;
        foreach ($form as $r) {
            $points += $r === 'W' ? 3 : ($r === 'D' ? 1 : 0);
        }

        if ($points <= 4) {
            return true;
        }

        $last3 = array_slice($form, -3);

        return $last3 === ['L', 'L', 'L'];
    }

    /**
     * @param list<string> $form
     */
    private function buildArticle(Game $game, Team $team, array $form, int $round): MatchNarrative
    {
        $es = app()->getLocale() === 'es';
        // Deterministic outlet per round (stable feed across page loads).
        $outlets = $this->mediaOutlets->outletsFor($game);
        $outlet = $outlets[(crc32($game->id.'outlet'.$round.$team->id) & 0x7FFFFFFF) % max(1, count($outlets))] ?? 'EFE Deportes';
        $coach = $team->manager_name ?: null; // null = unknown: never invent a name
        $teamName = $team->name;

        $points = 0;
        foreach ($form as $r) {
            $points += $r === 'W' ? 3 : ($r === 'D' ? 1 : 0);
        }
        $played = count($form);

        $last = $this->lastPlayedMatch($game->id, $team->id);
        $lastLine = $this->lastMatchLine($es, $team, $last);

        if ($es) {
            $headline = $coach
                ? "«{$coach}» podría ser cesado como entrenador del {$teamName}"
                : "El entrenador del {$teamName} podría ser cesado";
            $text = "Racha de {$played} partidos con solo {$points} puntos: el banquillo del {$teamName} tiembla.";
            $coachRef = $coach ?? "el entrenador del {$teamName}";
            $body = [
                "{$lastLine} La posición de {$coachRef} ha quedado muy tocada.",
                "El {$teamName} solo ha sumado {$points} puntos de los últimos ".($played * 3).' posibles y la grada ya murmura.',
                "Según ha podido saber {$outlet}, la directiva se reunirá esta semana para evaluar la situación.",
                '«Hay que revertir esto cuanto antes», admiten desde el vestuario.',
            ];
        } else {
            $headline = $coach
                ? "{$coach} on the brink as {$teamName} coach"
                : "{$teamName} coach on the brink of the sack";
            $text = "{$points} points from {$played} games: the {$teamName} dugout is shaking.";
            $coachRef = $coach ?? "the {$teamName} coach";
            $body = [
                "{$lastLine} {$coachRef}'s position looks very shaky.",
                "{$teamName} have taken just {$points} points from the last ".($played * 3).' on offer and the fans are grumbling.',
                "According to {$outlet}, the board will meet this week to assess the situation.",
                '"We have to turn this around as soon as possible," admit dressing-room sources.',
            ];
        }

        // Deterministic template variant per round so the feed doesn't flicker.
        // R20: mask crc32 to 32-bit-safe non-negative before the modulo
        // (on 32-bit PHP crc32() can return a negative int and % 2 then
        // yields -1, hiding variant 2 in production).
        $variant = (crc32($game->id.'pv'.$round.$team->id) & 0x7FFFFFFF) % 2;
        if ($variant === 1) {
            // Swap the closing quote for a second variant.
            $body[3] = $es
                ? 'El próximo partido se presenta como una final para el banquillo.'
                : 'The next match is shaping up as a final for the dugout.';
        }

        return new MatchNarrative(
            text: $text,
            category: 'pressure',
            source: $outlet,
            headline: $headline,
            body: $body,
        );
    }

    private function lastPlayedMatch(string $gameId, string $teamId): ?GameMatch
    {
        return GameMatch::where('game_id', $gameId)
            ->where('played', true)
            ->where(fn ($q) => $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId))
            ->orderByDesc('scheduled_date')
            ->first();
    }

    /**
     * R-review-medios: the old version always wrote "derrota" even when the
     * last match was won or drawn (the points<=4 trigger fires on form like
     * L,L,L,D,W too). The line now reflects the actual last result.
     */
    private function lastMatchLine(bool $es, Team $team, ?GameMatch $last): string
    {
        if (! $last) {
            return $es ? 'Los malos resultados han dejado el banquillo en la cuerda floja.' : 'The poor run has left the dugout on the ropes.';
        }

        $isHome = $last->home_team_id === $team->id;
        $opponent = $isHome ? $last->awayTeam?->name : $last->homeTeam?->name;
        $score = ($last->home_score ?? 0).'-'.($last->away_score ?? 0);

        if (! $opponent) {
            return $es ? 'Los malos resultados han dejado el banquillo en la cuerda floja.' : 'The poor run has left the dugout on the ropes.';
        }

        $teamGoals = $isHome ? ($last->home_score ?? 0) : ($last->away_score ?? 0);
        $oppGoals = $isHome ? ($last->away_score ?? 0) : ($last->home_score ?? 0);

        if ($teamGoals > $oppGoals) {
            return $es
                ? "Ni siquiera la victoria por {$score} ante el {$opponent} ha calmado los ánimos."
                : "Not even the {$score} victory over {$opponent} has calmed the nerves.";
        }

        if ($teamGoals === $oppGoals) {
            return $es
                ? "El empate a {$score} ante el {$opponent} no ha servido para calmar los ánimos."
                : "The {$score} draw with {$opponent} did nothing to calm the nerves.";
        }

        return $es
            ? "La derrota por {$score} ante el {$opponent} ha hecho mucho daño."
            : "The {$score} defeat to {$opponent} did a lot of damage.";
    }
}
