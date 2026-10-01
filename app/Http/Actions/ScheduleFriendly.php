<?php

namespace App\Http\Actions;

use App\Http\Views\ShowScheduleFriendly;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Competition\Configs\FifaInternationalBreaks;
use App\Modules\Season\Services\GamePlayerTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleFriendly
{
    public function __construct(
        private readonly GamePlayerTemplateService $templateService,
    ) {}

    public function __invoke(Request $request, string $gameId): RedirectResponse
    {
        $game = Game::findOrFail($gameId);

        if (! $game->isTournamentMode()) {
            abort(404);
        }

        $season = $game->season ?? '2026';

        $validated = $request->validate([
            'opponent_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'stadium' => ['required', 'string'],
        ]);

        // Rival can be a national team or a club (for stage friendlies).
        // Must not be the user's own team.
        $opponent = Team::where('is_placeholder', false)
            ->where('id', '!=', $game->team_id)
            ->whereIn('type', ['national', 'club'])
            ->find($validated['opponent_id']);

        if (! $opponent) {
            return redirect()->back()->with('error', __('game.friendly_invalid_opponent'));
        }

        // Date must fall inside one of the season's FIFA windows.
        $windows = FifaInternationalBreaks::forSeason($season);
        $window = collect($windows)->first(
            fn (array $w) => $validated['date'] >= $w['start'] && $validated['date'] <= $w['end']
        );

        if (! $window) {
            return redirect()->back()->with('error', __('game.friendly_outside_window'));
        }

        // Realism cap: at most 2 friendlies per FIFA window.
        $inWindow = GameMatch::where('game_id', $gameId)
            ->where('competition_id', ShowScheduleFriendly::COMPETITION_ID)
            ->where('played', false)
            ->whereDate('scheduled_date', '>=', $window['start'])
            ->whereDate('scheduled_date', '<=', $window['end'])
            ->count();

        if ($inWindow >= ShowScheduleFriendly::MAX_PER_WINDOW) {
            return redirect()->back()->with('error', __('game.friendly_window_full'));
        }

        // Stadium must exist in the catalogue.
        $stadiums = collect(json_decode(file_get_contents(base_path('data/stadiums.json')), true) ?? []);
        $stadium = $stadiums->firstWhere('stadium', $validated['stadium']);

        if (! $stadium) {
            return redirect()->back()->with('error', __('game.friendly_invalid_stadium'));
        }

        // The user always plays at home for scheduling purposes; the chosen
        // stadium (home ground or neutral venue) is stored on the match.
        DB::transaction(function () use ($game, $season, $opponent, $validated, $stadium) {
            $this->ensureFriendlyCompetition($season);
            $this->ensureOpponentTemplates($game, $season, $opponent);
            $this->materializeRivalPlayers($game, $season, $opponent->id);

            GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'competition_id' => ShowScheduleFriendly::COMPETITION_ID,
                'round_number' => 1,
                'round_name' => __('game.friendly_round_name'),
                'home_team_id' => $game->team_id,
                'away_team_id' => $opponent->id,
                'scheduled_date' => $validated['date'],
                'home_score' => null,
                'away_score' => null,
                'played' => false,
                'neutral_venue_name' => $stadium['stadium'],
                'neutral_venue_capacity' => $stadium['capacity'],
            ]);
        });

        return redirect()
            ->route('game.schedule-friendly', $gameId)
            ->with('success', __('game.friendly_scheduled', [
                'opponent' => $opponent->name,
                'date' => $validated['date'],
                'stadium' => $stadium['stadium'],
            ]));
    }

    /**
     * Ensure the FRIENDLY pseudo-competition exists (FK on game_matches).
     */
    private function ensureFriendlyCompetition(string $season): void
    {
        DB::table('competitions')->updateOrInsert(
            ['id' => ShowScheduleFriendly::COMPETITION_ID],
            [
                'name' => 'game.friendly_competition_name',
                'country' => 'XX',
                'tier' => 0,
                'type' => 'cup',
                'handler_type' => 'friendly',
                'season' => $season,
            ]
        );
    }

    /**
     * Ensure the opponent has player templates for the season. National teams
     * always have them (from tournament setup). Clubs get them generated
     * on-demand from their country's data files.
     */
    private function ensureOpponentTemplates(Game $game, string $season, Team $opponent): void
    {
        if ($opponent->type === 'national') {
            return;
        }

        $hasTemplates = DB::table('game_player_templates')
            ->where('season', $season)
            ->where('team_id', $opponent->id)
            ->exists();

        if ($hasTemplates) {
            return;
        }

        // Generate templates for the club's country (cached; safe to re-run).
        // This populates game_player_templates for all clubs in that country,
        // from which materializeRivalPlayers() then copies this club's squad.
        try {
            $this->templateService->generateTemplates($season, $opponent->country ?? 'ES');
        } catch (\Throwable $e) {
            // If generation fails, the friendly can't be played without players.
            throw new \RuntimeException('Could not generate squad for ' . $opponent->name);
        }
    }

    /**
     * Materialise the rival's templated roster as game players (plus match
     * state), so the match engine can field them. Safe to re-run: conflicts
     * are ignored. Mirrors SetupTournamentGame::createQualifierPlayers().
     */
    private function materializeRivalPlayers(Game $game, string $season, string $rivalTeamId): void
    {
        $columns = <<<'SQL'
            INSERT INTO game_players (
                id, game_id, player_id,
                transfermarkt_id, sofascore_id, fc26_id, name, date_of_birth, nationality, height, foot,
                team_id, number, position, secondary_positions,
                market_value, market_value_cents, contract_until, annual_wage, release_clause, durability,
                overall_score,
                potential, potential_low, potential_high, tier
            )
            SELECT
                gen_random_uuid(), ?, t.player_id,
                t.transfermarkt_id, t.sofascore_id, t.fc26_id, t.name, t.date_of_birth, t.nationality, t.height, t.foot,
                t.team_id, t.number, t.position, t.secondary_positions,
                t.market_value, t.market_value_cents, t.contract_until, t.annual_wage, t.release_clause, t.durability,
                t.overall_score,
                t.potential, t.potential_low, t.potential_high, t.tier
            FROM game_player_templates t
            WHERE t.season = ? AND t.team_id = ?
            ON CONFLICT (game_id, player_id) DO NOTHING
        SQL;

        DB::insert($columns, [$game->id, $season, $rivalTeamId]);

        DB::insert(<<<'SQL'
            INSERT INTO game_player_match_state (game_player_id, game_id, fitness, morale)
            SELECT gp.id, gp.game_id, t.fitness, t.morale
            FROM game_players gp
            JOIN game_player_templates t
              ON t.player_id = gp.player_id
             AND t.team_id = gp.team_id
             AND t.season = ?
            WHERE gp.game_id = ? AND gp.team_id = ?
            ON CONFLICT (game_player_id) DO NOTHING
        SQL, [$season, $game->id, $rivalTeamId]);
    }
}
