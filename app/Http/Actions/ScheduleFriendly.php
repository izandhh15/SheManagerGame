<?php

namespace App\Http\Actions;

use App\Http\Views\ShowScheduleFriendly;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Competition\Configs\FifaInternationalBreaks;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\GamePlayerTemplateService;
use App\Modules\Stadium\Services\NationalVenueRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScheduleFriendly
{
    public function __construct(
        private readonly GamePlayerTemplateService $templateService,
        private readonly NationalVenueRequestService $venueService,
        private readonly NotificationService $notifications,
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
            'venue_type' => ['required', 'in:national,club,mens,neutral'],
            'stadium' => ['nullable', 'string'],
            'club_team_id' => ['nullable', 'string'],
            'mens_stadium' => ['nullable', 'string'],
        ]);

        $venueType = $validated['venue_type'];

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

        // ---- Venue organization ----
        // national: own national stadium from the catalogue (always available).
        // neutral: generic neutral ground (always available, smaller gate).
        // club: request the women's club home ground (club must accept).
        // mens: request the men's big stadium (men's club, always AI, decides).
        $venue = $this->resolveVenue($game, $opponent, $venueType, $validated);

        if ($venue === null) {
            return redirect()->back()->with('error', __('game.friendly_invalid_stadium'));
        }

        $userTeam = Team::findOrFail($game->team_id);

        // The user always plays at home for scheduling purposes. The match is
        // created with a playable venue in all cases: the requested stadium
        // when confirmed, otherwise the neutral fallback (fewer earnings).
        // A pending club request keeps the neutral venue until answered.
        $match = null;
        DB::transaction(function () use ($game, $season, $opponent, $validated, $venue, &$match) {
            $this->ensureFriendlyCompetition($season);
            $this->ensureOpponentTemplates($game, $season, $opponent);
            $this->materializeRivalPlayers($game, $season, $opponent->id);

            $match = GameMatch::create([
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
                'neutral_venue_name' => $venue['stadium'],
                'neutral_venue_capacity' => $venue['capacity'],
                'venue_status' => $venue['status'],
                'venue_request_team_id' => $venue['request_team_id'],
                'venue_request_type' => $venue['request_type'],
                'venue_request_excuse' => $venue['excuse_key'],
            ]);
        });

        // Side effects outside the transaction: notifications.
        $this->dispatchVenueNotifications($game, $userTeam, $opponent, $match, $venue, $validated);

        return redirect()
            ->route('game.schedule-friendly', $gameId)
            ->with('success', $venue['success_message']);
    }

    /**
     * Resolve the requested venue.
     *
     * @return array{stadium: string, capacity: int, status: string, request_team_id: string|null, request_type: string|null, excuse_key: string|null, success_message: string, pending_club: bool}|null
     */
    private function resolveVenue(Game $game, Team $opponent, string $venueType, array $validated): ?array
    {
        $neutral = [
            'stadium' => NationalVenueRequestService::NEUTRAL_VENUE_NAME,
            'capacity' => NationalVenueRequestService::NEUTRAL_VENUE_CAPACITY,
        ];

        // 1. National stadium: catalogue lookup, always confirmed.
        if ($venueType === 'national') {
            $stadiums = $this->venueService->nationalStadiums();
            $stadium = $stadiums->firstWhere('stadium', $validated['stadium'] ?? null);
            if (! $stadium) {
                return null;
            }

            return [
                'stadium' => $stadium['stadium'],
                'capacity' => $stadium['capacity'],
                'status' => 'confirmed',
                'request_team_id' => null,
                'request_type' => null,
                'excuse_key' => null,
                'pending_club' => false,
                'success_message' => __('game.friendly_scheduled', [
                    'opponent' => $opponent->name,
                    'date' => $validated['date'],
                    'stadium' => $stadium['stadium'],
                ]),
            ];
        }

        // 2. Neutral ground: always available, smaller gate revenue.
        if ($venueType === 'neutral') {
            return [
                'stadium' => $neutral['stadium'],
                'capacity' => $neutral['capacity'],
                'status' => 'confirmed',
                'request_team_id' => null,
                'request_type' => null,
                'excuse_key' => null,
                'pending_club' => false,
                'success_message' => __('game.friendly_scheduled_neutral', [
                    'date' => $validated['date'],
                ]),
            ];
        }

        // 3. Women's club home ground: the club must accept.
        if ($venueType === 'club') {
            $clubTeam = Team::where('type', 'club')
                ->where('is_placeholder', false)
                ->find($validated['club_team_id'] ?? null);

            if (! $clubTeam || ! $clubTeam->stadium_name) {
                return null;
            }

            $userTeam = Team::find($game->team_id);

            // The user manages this club (dual mode): they decide.
            if ($this->venueService->userManagesClub($game, $clubTeam->id)) {
                return [
                    'stadium' => $neutral['stadium'],
                    'capacity' => $neutral['capacity'],
                    'status' => 'pending_club',
                    'request_team_id' => $clubTeam->id,
                    'request_type' => 'club',
                    'excuse_key' => null,
                    'pending_club' => true,
                    'pending_club_name' => $clubTeam->name,
                    'pending_stadium' => $clubTeam->stadium_name,
                    'success_message' => __('game.friendly_venue_requested', [
                        'stadium' => $clubTeam->stadium_name,
                        'club' => $clubTeam->name,
                    ]),
                ];
            }

            // AI club decides now.
            $decision = $this->venueService->evaluateClubRequest($clubTeam, $userTeam, $opponent);

            if ($decision['accepted']) {
                return [
                    'stadium' => $clubTeam->stadium_name,
                    'capacity' => (int) $clubTeam->stadium_seats,
                    'status' => 'confirmed',
                    'request_team_id' => $clubTeam->id,
                    'request_type' => 'club',
                    'excuse_key' => null,
                    'pending_club' => false,
                    'success_message' => __('game.friendly_venue_accepted', [
                        'stadium' => $clubTeam->stadium_name,
                        'club' => $clubTeam->name,
                    ]),
                ];
            }

            return [
                'stadium' => $neutral['stadium'],
                'capacity' => $neutral['capacity'],
                'status' => 'rejected',
                'request_team_id' => $clubTeam->id,
                'request_type' => 'club',
                'excuse_key' => $decision['excuse'],
                'pending_club' => false,
                'requested_stadium' => $clubTeam->stadium_name,
                'success_message' => __('game.friendly_venue_rejected', [
                    'stadium' => $clubTeam->stadium_name,
                    'excuse' => __($decision['excuse']),
                ]),
            ];
        }

        // 4. Men's big stadium: the men's club (always AI) decides.
        if ($venueType === 'mens') {
            $mens = collect($this->venueService->mensStadiums())
                ->firstWhere('stadium', $validated['mens_stadium'] ?? null);

            if (! $mens) {
                return null;
            }

            $userTeam = Team::find($game->team_id);
            $decision = $this->venueService->evaluateMensRequest($mens['club'], $userTeam, $opponent);

            if ($decision['accepted']) {
                return [
                    'stadium' => $mens['stadium'],
                    'capacity' => $mens['capacity'],
                    'status' => 'confirmed',
                    'request_team_id' => null,
                    'request_type' => 'mens',
                    'excuse_key' => null,
                    'pending_club' => false,
                    'success_message' => __('game.friendly_venue_mens_accepted', [
                        'stadium' => $mens['stadium'],
                    ]),
                ];
            }

            return [
                'stadium' => $neutral['stadium'],
                'capacity' => $neutral['capacity'],
                'status' => 'rejected',
                'request_team_id' => null,
                'request_type' => 'mens',
                'excuse_key' => $decision['excuse'],
                'pending_club' => false,
                'requested_stadium' => $mens['stadium'],
                'success_message' => __('game.friendly_venue_mens_rejected', [
                    'stadium' => $mens['stadium'],
                    'excuse' => __($decision['excuse']),
                ]),
            ];
        }

        return null;
    }

    /**
     * Notifications for venue requests: to the club game when the user
     * must decide, or the result back in the national-team game.
     */
    private function dispatchVenueNotifications(Game $game, Team $userTeam, Team $opponent, GameMatch $match, array $venue, array $validated): void
    {
        $date = \Carbon\Carbon::parse($validated['date'])->format('d/m/Y');

        // Pending: the user manages the club (dual mode) -> ask in club game.
        if ($venue['pending_club']) {
            $clubGame = $game->dualPartner();
            if ($clubGame) {
                $this->notifications->notifyStadiumRequest(
                    $clubGame,
                    $userTeam->name,
                    $opponent->name,
                    $date,
                    $venue['pending_stadium'],
                    $match->id,
                    $game->id,
                );
            }
            return;
        }

        // AI rejections already surface via the flash message; also leave a
        // notification so the excuse isn't lost.
        if ($venue['status'] === 'rejected') {
            $this->notifications->notifyStadiumRequestResult(
                $game,
                false,
                $venue['requested_stadium'] ?? '',
                $venue['excuse_key'],
            );
        }
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
