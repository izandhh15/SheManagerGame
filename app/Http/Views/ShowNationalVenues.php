<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\Team;
use App\Modules\Stadium\Services\NationalVenueOrganizationService;
use App\Modules\Stadium\Services\NationalVenueRequestService;
use Illuminate\Http\Request;

class ShowNationalVenues
{
    public function __construct(
        private readonly NationalVenueOrganizationService $orgService,
        private readonly NationalVenueRequestService $venueService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            abort(404);
        }

        $userTeam = Team::findOrFail($game->team_id);

        $pending = $this->orgService->pendingMatches($game);
        $awaitingClub = $this->orgService->pendingClubDecisions($game);

        // National (federation) stadiums: only the national team's own
        // country (Spain → Spanish grounds, default La Cartuja).
        $stadiumList = $this->venueService->federationStadiums($userTeam);
        $stadiums = (clone $stadiumList)->groupBy('country')->sortKeys();
        $defaultStadium = $this->venueService->defaultNationalStadium($stadiumList, $userTeam);

        $countryNames = $this->countryCodeToNameMap();
        $clubStadiums = $this->venueService->clubStadiums()
            ->groupBy(fn (array $s) => $countryNames[strtoupper($s['country'] ?? '')] ?? $s['country'] ?? '?')
            ->sortKeys();
        $mensStadiums = collect($this->venueService->mensStadiums());

        return view('national-venues', [
            'game' => $game,
            'userTeam' => $userTeam,
            'pending' => $pending,
            'awaitingClub' => $awaitingClub,
            'calendarMonths' => $this->buildCalendar($game, $pending),
            'stadiums' => $stadiums,
            'defaultStadium' => $defaultStadium,
            'clubStadiums' => $clubStadiums,
            'mensStadiums' => $mensStadiums,
            'federationBudget' => (int) ($game->federation_budget ?? 0),
            'neutralVenueName' => NationalVenueRequestService::NEUTRAL_VENUE_NAME,
            'neutralVenueCapacity' => NationalVenueRequestService::NEUTRAL_VENUE_CAPACITY,
            'rebateMinOffer' => NationalVenueOrganizationService::REBATE_MIN_OFFER,
            'rebateShare' => NationalVenueOrganizationService::REBATE_SHARE,
        ]);
    }

    /**
     * Two-month calendar (current + next) for picking which match to request
     * a stadium for. Each month: label + weeks of days; days carry the
     * pending matches scheduled on them.
     *
     * @return list<array{label: string, weeks: list<list<array{date: string, day: int, inMonth: bool, isToday: bool, matches: list}>}>
     */
    private function buildCalendar(Game $game, $pending): array
    {
        $byDate = [];
        foreach ($pending as $match) {
            $key = \Carbon\Carbon::parse($match->scheduled_date)->format('Y-m-d');
            $byDate[$key][] = [
                'id' => $match->id,
                'rival' => $match->awayTeam?->name ?? '',
                'round' => $match->round_name ?? '',
            ];
        }

        $today = ($game->current_date ? \Carbon\Carbon::parse($game->current_date) : \Carbon\Carbon::today())->startOfDay();
        $months = [];

        for ($m = 0; $m < 2; $m++) {
            $first = $today->copy()->addMonthsNoOverflow($m)->startOfMonth();
            // Monday-first grid.
            $start = $first->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
            $end = $first->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);

            $weeks = [];
            $cursor = $start->copy();
            while ($cursor <= $end) {
                $week = [];
                for ($d = 0; $d < 7; $d++) {
                    $key = $cursor->format('Y-m-d');
                    $week[] = [
                        'date' => $key,
                        'day' => (int) $cursor->format('j'),
                        'inMonth' => $cursor->format('Y-m') === $first->format('Y-m'),
                        'isToday' => $key === $today->format('Y-m-d'),
                        'matches' => $byDate[$key] ?? [],
                    ];
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            $months[] = [
                'label' => ucfirst($first->translatedFormat('F Y')),
                'weeks' => $weeks,
            ];
        }

        return $months;
    }

    private function countryCodeToNameMap(): array
    {
        return [
            'ES' => 'Spain',
            'GB-ENG' => 'England', 'GB-SCT' => 'Scotland', 'GB-WLS' => 'Wales', 'GB-NIR' => 'Northern Ireland',
            'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'PT' => 'Portugal',
            'NL' => 'Netherlands', 'BE' => 'Belgium', 'SE' => 'Sweden', 'NO' => 'Norway',
            'DK' => 'Denmark', 'CH' => 'Switzerland', 'AT' => 'Austria', 'US' => 'United States',
            'BR' => 'Brazil', 'JP' => 'Japan', 'AU' => 'Australia', 'CA' => 'Canada',
        ];
    }
}
