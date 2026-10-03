<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Media\Services\NationalSocialService;

class ShowNationalSocial
{
    public function __construct(
        private readonly NationalSocialService $nationalSocial,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        if ($game->team?->type !== 'national') {
            abort(404);
        }

        // Next home match (for the sede announcement context).
        $nextHome = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        $hasSquad = count($game->national_squad_player_ids ?? []) > 0;

        // Disable the manual "sede" button when the venue of the next home
        // match was already announced (automatic or manual path, M11).
        $sedeAnnounced = $nextHome !== null
            && ($nextHome->stadium_name || $nextHome->neutral_venue_name)
            && $this->nationalSocial->venueAnnounced($game, $nextHome);

        return view('national-social', [
            'game' => $game,
            'posts' => $this->nationalSocial->feed($game),
            'nationalHandle' => $this->nationalSocial->nationalHandle($game),
            'instagramHandle' => $this->nationalSocial->instagramHandle($game),
            'isFederationAccount' => $this->nationalSocial->isFederationAccount($game),
            'followers' => $this->nationalSocial->followers($game),
            'nextHome' => $nextHome,
            'sedeAnnounced' => $sedeAnnounced,
            'hasSquad' => $hasSquad,
            'squadCount' => count($game->national_squad_player_ids ?? []),
            'ticketTiers' => NationalSocialService::TICKET_TIERS,
        ]);
    }
}
