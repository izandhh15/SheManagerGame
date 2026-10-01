<?php

declare(strict_types=1);

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use Illuminate\Support\Facades\Cache;

/**
 * "Jugar en el estadio masculino": the user's women's club asks the men's
 * club to play a home match at the men's stadium (e.g. Valencia Femenino
 * asking to play at Mestalla).
 *
 * The men's club (AI) evaluates the match importance (0-100):
 *  - Rival ELITE: +40 / CONTINENTAL: +25
 *  - Cup/knockout or title-deciding late-season match: +30
 *  - Derby (same country): +20
 *  - Random: +0-15
 * Accepts if importance >= 50. Max 3 matches per season.
 */
class MensStadiumRequestService
{
    public const MAX_PER_SEASON = 3;

    public const ACCEPT_THRESHOLD = 50;

    /**
     * Minimum days in advance to request the men's stadium.
     * The men's club needs time to organize logistics.
     */
    public const MIN_ADVANCE_DAYS = 21;

    /** @var array<string, array{stadium: string, capacity: int}>|null */
    private ?array $stadiums = null;

    /**
     * @return array{stadium: string, capacity: int}|null
     */
    public function mensStadiumFor(string $womensTeamName): ?array
    {
        $this->loadStadiums();

        return $this->stadiums[$womensTeamName] ?? null;
    }

    public function hasMensStadium(string $womensTeamName): bool
    {
        return $this->mensStadiumFor($womensTeamName) !== null;
    }

    public function usesThisSeason(Game $game): int
    {
        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->whereNotNull('neutral_venue_name')
            ->where('neutral_venue_name', '!=', '')
            ->count();
    }

    public function canRequest(Game $game): bool
    {
        return $this->usesThisSeason($game) < self::MAX_PER_SEASON;
    }

    /**
     * @return array{accepted: bool, importance: int, reasons: list<string>}
     */
    public function evaluate(GameMatch $match, Game $game): array
    {
        $reasons = [];
        $importance = 0;

        $reputations = TeamReputation::resolveLevels($game->id, [$match->home_team_id, $match->away_team_id]);
        $rivalRep = $reputations->get($match->away_team_id);

        if ($rivalRep === ClubProfile::REPUTATION_ELITE) {
            $importance += 40;
            $reasons[] = 'rival_elite';
        } elseif ($rivalRep === ClubProfile::REPUTATION_CONTINENTAL) {
            $importance += 25;
            $reasons[] = 'rival_continental';
        }

        if ($this->isCupOrKnockout($match) || $this->isTitleDecider($match, $game)) {
            $importance += 30;
            $reasons[] = $this->isCupOrKnockout($match) ? 'cup_match' : 'title_decider';
        }

        if ($this->isDerby($match)) {
            $importance += 20;
            $reasons[] = 'derby';
        }

        $luck = mt_rand(0, 15);
        $importance += $luck;

        return [
            'accepted' => $importance >= self::ACCEPT_THRESHOLD,
            'importance' => min(100, $importance),
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array{accepted: bool, importance: int, reasons: list<string>}
     */
    public function requestForMatch(GameMatch $match, Game $game): array
    {
        $teamName = $game->team?->name ?? '';
        $mens = $this->mensStadiumFor($teamName);

        if ($mens === null) {
            return ['accepted' => false, 'importance' => 0, 'reasons' => ['no_mens_stadium']];
        }

        if (! $this->canRequest($game)) {
            return ['accepted' => false, 'importance' => 0, 'reasons' => ['limit_reached']];
        }

        // Must request at least 3 weeks in advance
        if (! $this->hasEnoughAdvance($match, $game)) {
            return ['accepted' => false, 'importance' => 0, 'reasons' => ['too_late']];
        }

        $result = $this->evaluate($match, $game);

        if ($result['accepted']) {
            $match->neutral_venue_name = $mens['stadium'];
            $match->neutral_venue_capacity = $mens['capacity'];
            $match->save();
        } else {
            // Add a random excuse from the men's club
            $result['reasons'][] = $this->randomExcuse();
        }

        return $result;
    }

    /**
     * Check if the request is made at least MIN_ADVANCE_DAYS before the match.
     */
    public function hasEnoughAdvance(GameMatch $match, Game $game): bool
    {
        if (! $match->scheduled_date || ! $game->current_date) {
            return true; // Can't verify, allow it
        }

        $matchDate = \Carbon\Carbon::parse($match->scheduled_date);
        $now = \Carbon\Carbon::parse($game->current_date);

        return $now->diffInDays($matchDate, false) >= self::MIN_ADVANCE_DAYS;
    }

    /**
     * Days remaining to request for a match (for UI display).
     */
    public function daysUntilDeadline(GameMatch $match, Game $game): int
    {
        if (! $match->scheduled_date || ! $game->current_date) {
            return 0;
        }

        $matchDate = \Carbon\Carbon::parse($match->scheduled_date);
        $now = \Carbon\Carbon::parse($game->current_date);
        $deadline = $matchDate->copy()->subDays(self::MIN_ADVANCE_DAYS);

        return (int) $now->diffInDays($deadline, false);
    }

    /**
     * Random excuse from the men's club when they reject the request.
     */
    private function randomExcuse(): string
    {
        $excuses = [
            'excuse_laliga',      // Men's team has a LALIGA EA Sports match that weekend
            'excuse_grass',       // Changing the pitch grass
            'excuse_concert',     // Stadium booked for a concert/event
            'excuse_maintenance', // Scheduled maintenance works
            'excuse_reserve',     // Reserve team playing there
        ];

        return $excuses[array_rand($excuses)];
    }

    private function isCupOrKnockout(GameMatch $match): bool
    {
        $compId = strtoupper($match->competition_id ?? '');

        return str_contains($compId, 'CUP')
            || str_contains($compId, 'UCL')
            || str_contains($compId, 'UWCL')
            || $match->cup_tie_id !== null;
    }

    private function isTitleDecider(GameMatch $match, Game $game): bool
    {
        // Last 5 league matchdays with the title race alive.
        if ($this->isCupOrKnockout($match)) {
            return false;
        }

        $totalMatchdays = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $match->competition_id)
            ->distinct()
            ->count('round_number');

        if ($totalMatchdays < 10 || ($match->round_number ?? 0) < $totalMatchdays - 5) {
            return false;
        }

        // Title race alive: home team within 9 points of the leader.
        // Standings are computed elsewhere; use a lightweight points check.
        $standings = $this->pointsTable($game, $match->competition_id);
        if (empty($standings)) {
            return false;
        }

        $leader = max($standings);
        $home = $standings[$match->home_team_id] ?? 0;

        return ($leader - $home) <= 9;
    }

    /**
     * @return array<string, int> team_id => points
     */
    private function pointsTable(Game $game, string $competitionId): array
    {
        $table = [];
        $matches = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        foreach ($matches as $m) {
            $table[$m->home_team_id] ??= 0;
            $table[$m->away_team_id] ??= 0;
            if ($m->home_score > $m->away_score) {
                $table[$m->home_team_id] += 3;
            } elseif ($m->home_score < $m->away_score) {
                $table[$m->away_team_id] += 3;
            } else {
                $table[$m->home_team_id] += 1;
                $table[$m->away_team_id] += 1;
            }
        }

        return $table;
    }

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

    private function loadStadiums(): void
    {
        if ($this->stadiums !== null) {
            return;
        }

        $this->stadiums = Cache::remember('mens_stadiums_map', 86400, function () {
            $path = base_path('data/mens_stadiums.json');
            if (! is_file($path)) {
                return [];
            }

            $data = json_decode(file_get_contents($path), true);
            $map = [];
            foreach ($data['stadiums'] ?? [] as $row) {
                $map[$row['womens_team']] = [
                    'stadium' => $row['stadium'],
                    'capacity' => (int) $row['capacity'],
                ];
            }

            return $map;
        });
    }
}
