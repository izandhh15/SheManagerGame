<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class Dashboard
{
    public function __construct()
    {
    }

    public function __invoke(Request $request)
    {
        $games = Game::with(['team', 'competition'])->where('user_id', $request->user()->id)->whereNull('deleting_at')->get();

        if (! $games->count()) {
            return redirect()->route('select-team');
        }

        // Resilience: a corrupt save (orphaned team_id, unreadable dates or
        // JSON…) must never 500 the whole dashboard. Each save is probed the
        // same way the view consumes it; broken ones are skipped from the
        // grid and listed separately with a recovery option.
        $healthyGames = collect();
        $brokenGames = collect();
        foreach ($games as $game) {
            try {
                self::assertGameRenders($game);
                $healthyGames->push($game);
            } catch (\Throwable $e) {
                $brokenGames->push([
                    'id' => $game->getKey(),
                    'label' => self::brokenGameLabel($game),
                ]);
                report($e);
            }
        }

        $maxGames = 5;

        // Same limit semantics as InitGame/InitDualGame/SelectTeam: only
        // primary saves count against the 5-game limit. Dual-mode
        // secondaries (linked_game_id not null) ride along with their club
        // half and don't consume a slot — otherwise the dashboard would
        // show "6 de 5" after creating a dual career.
        $primaryCount = $games->count();
        if (Schema::hasColumn('games', 'linked_game_id')) {
            $primaryCount = $games->whereNull('linked_game_id')->count();
        }

        // Affiliate-mode CTA: surfaced on the dashboard so the mode isn't
        // buried in the new-game screen. Only when the user has a free
        // slot and at least one club has a playable filial.
        $showAffiliateCta = $primaryCount < $maxGames && Team::where('type', '!=', 'national')
            ->where('is_placeholder', false)
            ->whereNull('parent_team_id')
            ->whereHas('reserveTeam', fn ($q) => $q->where('is_placeholder', false))
            ->exists();

        return view('dashboard', [
            'user' => $request->user(),
            'games' => $healthyGames,
            'brokenGames' => $brokenGames,
            'canCreateGame' => $primaryCount < $maxGames,
            // Only users who already have a career from an older data season
            // need telling that saves keep the squads they started with.
            'hasLegacySaves' => $healthyGames->contains(
                fn (Game $game) => ! $game->isTournamentMode() && $game->isFromPastBaseSeason()
            ),
            'gameCount' => $primaryCount,
            'maxGames' => $maxGames,
            'showAffiliateCta' => $showAffiliateCta,
        ]);
    }

    /**
     * Touch everything the dashboard card for one save relies on. Anything
     * that throws here would 500 the whole page, so the save is quarantined
     * instead.
     *
     * @throws \Throwable when the save cannot be rendered.
     */
    private static function assertGameRenders(Game $game): void
    {
        if (! $game->team?->name) {
            throw new \RuntimeException('Game ' . $game->getKey() . ' has no team.');
        }

        $game->isTournamentMode();
        $game->isFromPastBaseSeason();

        // Accessors / casts the view consumes: a corrupt date or a broken
        // JSON column throws on read, which is exactly what we want to catch.
        $game->nextLeagueMatchday;
        $game->pending_actions;
        $game->updated_at->diffForHumans();

        if ($game->current_date) {
            $game->current_date->format('d/m/Y');
        }
    }

    /**
     * A human-readable label for a quarantined save, built from raw
     * attributes only (casts may be the very thing that's broken).
     */
    private static function brokenGameLabel(Game $game): string
    {
        $raw = $game->getAttributes();
        $shortId = substr((string) $game->getKey(), 0, 8);

        return ! empty($raw['player_name'])
            ? $raw['player_name'] . ' (' . $shortId . ')'
            : $shortId;
    }
}
