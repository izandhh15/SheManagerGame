<?php

namespace App\Modules\Season\Processors;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\Log;

/**
 * Qualifies clubs for the women's Club World Cup (CWC) at season close.
 *
 * The entrants come from the continental ranking: the best clubs of each
 * confederation by squad market value (sum of game_players.market_value_cents
 * — the same ranking the game already uses to seed Swiss pots every season
 * after the first). Slots: 16 UEFA, 8 CONMEBOL, 8 CONCACAF — the three
 * confederations with real club data in the game. A confederation that
 * can't fill its slots yields the remainder to the global ranking so the
 * tournament always fields 32 teams.
 *
 * Reserve sides (parent_team_id) and national teams never qualify.
 * Runs after UefaQualificationProcessor (priority 100) so the European
 * places are settled first; the CWC entries are drawn into groups at
 * season setup by ClubWorldCupInitProcessor.
 */
class ClubWorldCupQualificationProcessor implements SeasonProcessor
{
    public const COMPETITION_ID = 'CWC';

    public const TEAM_COUNT = 32;

    /** Slots per confederation. */
    public const CONFEDERATION_SLOTS = [
        'UEFA' => 16,
        'CONMEBOL' => 8,
        'CONCACAF' => 8,
    ];

    private const CONMEBOL_COUNTRIES = ['AR', 'BR'];

    private const CONCACAF_COUNTRIES = ['MX', 'US'];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 101;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!$game->isCareerMode()) {
            return $data;
        }

        $userTeam = Team::find($game->team_id);
        if ($userTeam === null || $userTeam->type === 'national') {
            return $data;
        }

        // Rebuild from scratch: last season's CWC entries are stale.
        CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        $qualified = $this->qualifyClubs($game);

        $rows = array_map(fn (string $teamId) => [
            'game_id' => $game->id,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], $qualified);

        foreach (array_chunk($rows, 500) as $chunk) {
            CompetitionEntry::insert($chunk);
        }

        Log::info('[ClubWorldCup] qualified ' . count($qualified) . " clubs for game {$game->id}");

        $data->setMetadata('clubWorldCupQualifiers', $qualified);

        if (in_array((string) $game->team_id, $qualified, true)) {
            $this->notifyUserQualified($game);
        }

        return $data;
    }

    /**
     * @return string[] qualified team ids, strongest first
     */
    private function qualifyClubs(Game $game): array
    {
        // Continental ranking: squad market value per team.
        $squadValues = GamePlayer::where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->selectRaw('team_id, SUM(market_value_cents) AS squad_value')
            ->groupBy('team_id')
            ->pluck('squad_value', 'team_id');

        $candidates = Team::where('type', '!=', 'national')
            ->whereNull('parent_team_id')
            ->whereNotNull('country')
            ->where('country', '!=', 'XX')
            ->where('is_placeholder', false)
            ->get(['id', 'country'])
            ->filter(fn (Team $team) => ($squadValues[$team->id] ?? 0) > 0)
            ->map(fn (Team $team) => [
                'id' => (string) $team->id,
                'confederation' => $this->confederationFor($team->country),
                'value' => (int) $squadValues[$team->id],
            ])
            ->filter(fn (array $row) => $row['confederation'] !== null)
            ->sortByDesc('value')
            ->values();

        $qualified = [];
        foreach (self::CONFEDERATION_SLOTS as $confederation => $slots) {
            $picked = $candidates
                ->where('confederation', $confederation)
                ->reject(fn (array $row) => in_array($row['id'], $qualified, true))
                ->take($slots)
                ->pluck('id')
                ->all();
            $qualified = array_merge($qualified, $picked);
        }

        // Top up to 32 from the global ranking when a confederation
        // couldn't fill its slots (thin data in tests, new countries…).
        if (count($qualified) < self::TEAM_COUNT) {
            $extra = $candidates
                ->reject(fn (array $row) => in_array($row['id'], $qualified, true))
                ->take(self::TEAM_COUNT - count($qualified))
                ->pluck('id')
                ->all();
            $qualified = array_merge($qualified, $extra);
        }

        return array_values(array_slice($qualified, 0, self::TEAM_COUNT));
    }

    private function confederationFor(?string $country): ?string
    {
        if ($country === null || $country === '' || $country === 'XX') {
            return null;
        }
        if (in_array($country, self::CONMEBOL_COUNTRIES, true)) {
            return 'CONMEBOL';
        }
        if (in_array($country, self::CONCACAF_COUNTRIES, true)) {
            return 'CONCACAF';
        }

        return 'UEFA';
    }

    private function notifyUserQualified(Game $game): void
    {
        $this->notificationService->create(
            game: $game,
            type: 'cwc_qualified',
            title: __('notifications.cwc_qualified_title'),
            message: __('notifications.cwc_qualified_message'),
            priority: \App\Models\GameNotification::PRIORITY_MILESTONE,
        );
    }
}
