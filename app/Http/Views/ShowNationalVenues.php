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

        // National stadiums grouped by country for the picker.
        $stadiumList = $this->venueService->nationalStadiums();
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
