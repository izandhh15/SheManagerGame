<?php

namespace App\Modules\Season\Services;

use App\Models\CompetitionEntry;
use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\PreseasonInvitation;
use App\Models\Team;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Support\CountryCodeMapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds the pool of candidate pre-season opponents and materialises the
 * friendlies the player chooses on the pre-season setup screen.
 *
 * Pre-season runs four fixed fixture slots (mid-July → mid-August). The player
 * picks an opponent and home/away for each slot, may leave slots empty, and may
 * pick none at all. Picking none is the deliberate replacement for the old
 * one-click "Skip Pre-Season".
 */
class PreseasonOpponentService
{
    public const PRESEASON_COMPETITION_ID = 'PRESEASON';

    public const NUM_SLOTS = 4;

    /**
     * The 5th, automatic friendly: first team vs. its own filial (the "derbi
     * de la casa"). It always goes last, after the four player-chosen slots.
     */
    public const FAMILY_DERBY_ROUND_NUMBER = 5;

    /** Fixed date (day/month) for the automatic family derby. */
    private const FAMILY_DERBY_DATE = ['day' => 17, 'month' => 8];

    /** Fixed fixture dates (day/month) for the four pre-season slots. */
    private const SCHEDULE = [
        ['day' => 12, 'month' => 7],
        ['day' => 22, 'month' => 7],
        ['day' => 2,  'month' => 8],
        ['day' => 10, 'month' => 8],
    ];

    /**
     * The four fixed fixture dates for the given game's current season.
     *
     * @return array<int, Carbon> keyed by slot index (0-3)
     */
    public function fixtureSlots(Game $game): array
    {
        $seasonYear = (int) $game->season;

        $slots = [];
        foreach (self::SCHEDULE as $i => $schedule) {
            $slots[$i] = Carbon::createFromDate($seasonYear, $schedule['month'], $schedule['day']);
        }

        return $slots;
    }

    /**
     * Candidate opponents the player can choose from: every club team in this
     * game that has a generated squad — including reserve teams (filiales),
     * so you can schedule friendlies against B teams. National teams are
     * excluded (club friendlies only); the user's own teams are excluded so
     * you can't play yourself. Sorted by name for a stable picker.
     *
     * Rivals adapt to your level: only teams from leagues within ±1 tier of
     * yours show up — no 2ª RFEF side drawing OL Lyonnes for a friendly.
     * Teams whose league tier can't be determined are kept, so the picker
     * never ends up empty for lack of data.
     *
     * Note: unlike transferMarketEligible() (which drops reserve teams because
     * they never buy/sell on the market), friendlies have no such restriction.
     *
     * @return Collection<int, Team>
     */
    public function candidatePool(Game $game): Collection
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->distinct()
            ->pluck('team_id');

        $teams = Team::where('type', '!=', 'national')
            ->whereIn('id', $teamIds)
            ->whereNotIn('id', $game->userTeamIds())
            ->whereHas('competitions', fn (Builder $q) => $q->where('role', '!=', Competition::ROLE_DOMESTIC_CUP))
            ->orderBy('name')
            ->get();

        return $this->filterByCategory($game, $teams);
    }

    /**
     * Keep only teams whose league tier is within ±1 of the user's league
     * tier. League tiers come from this game's competition entries, so they
     * reflect promotions and relegations, not stale reference data.
     *
     * @param  Collection<int, Team>  $teams
     * @return Collection<int, Team>
     */
    private function filterByCategory(Game $game, Collection $teams): Collection
    {
        if ($teams->isEmpty()) {
            return $teams;
        }

        $userTier = (int) ($game->competition?->tier ?? 1);
        $tiers = $this->leagueTiersFor($game, $teams->pluck('id'));

        return $teams->filter(
            fn (Team $team) => ! isset($tiers[$team->id])
                || abs($tiers[$team->id] - $userTier) <= 1
        )->values();
    }

    /**
     * League tier per team for this game (lowest league tier wins when a team
     * holds several entries). Teams with no league entry are absent from the
     * map.
     *
     * @param  Collection<int, string>  $teamIds
     * @return array<string, int>
     */
    private function leagueTiersFor(Game $game, Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return CompetitionEntry::query()
            ->join('competitions as c', 'c.id', '=', 'competition_entries.competition_id')
            ->where('competition_entries.game_id', $game->id)
            ->where('c.role', Competition::ROLE_LEAGUE)
            ->whereIn('competition_entries.team_id', $teamIds->all())
            ->groupBy('competition_entries.team_id')
            ->selectRaw('competition_entries.team_id as team_id, MIN(c.tier) as tier')
            ->pluck('tier', 'team_id')
            ->map(fn ($tier) => (int) $tier)
            ->all();
    }

    /**
     * The linked side for the automatic family derby: if the user manages a
     * filial (academy career), its first team; if the user manages a first
     * team, its own filial. Null when the club has no linked side.
     */
    public function familyDerbyOpponent(Game $game): ?Team
    {
        $userTeam = Team::find($game->team_id);

        if (! $userTeam) {
            return null;
        }

        // Filial career: the derby is against your own first team.
        if ($userTeam->isReserveTeam()) {
            return $userTeam->parentTeam;
        }

        // First-team career: the derby is against your own filial.
        return $userTeam->reserveTeam;
    }

    /**
     * The candidate pool grouped by country for the picker modal. Mirrors the
     * shape Explore's pool mode consumes:
     * {code, name, flag, teams: [{id, name, image}]}.
     *
     * @return Collection<int, array{code: string, name: string, flag: string, teams: array}>
     */
    public function candidateTeamsGroupedByCountry(Game $game): Collection
    {
        return $this->candidatePool($game)
            ->groupBy('country')
            ->map(function (Collection $teams, ?string $countryCode) {
                $code = strtolower((string) $countryCode);
                $englishName = CountryCodeMapper::toName((string) $countryCode) ?? $countryCode;

                return [
                    'code' => $code,
                    'name' => __("countries.{$englishName}"),
                    // The Team flag accessor handles the EN -> gb-eng case.
                    'flag' => $teams->first()->flag,
                    'teams' => $teams->map(fn (Team $team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'image' => $team->image,
                    ])->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * Materialise the chosen friendlies and end pre-season setup.
     *
     * @param  array<int, array{slot:int, team_id:string, is_home:bool, trophy_name?:string|null, stadium_name?:string|null}>  $selections
     */
    public function confirmSelections(Game $game, array $selections): void
    {
        // The whole confirmation runs in one transaction with the game row
        // locked: the 'pending' guard is re-checked under the lock, so a
        // double POST can't create the friendlies twice.
        DB::transaction(function () use ($game, $selections) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->first();

            if (! $lockedGame || ! $lockedGame->needsPreseasonOpponentSelection()) {
                return;
            }

            $selections = $this->sanitizeSelections($lockedGame, $selections);
            $slots = $this->fixtureSlots($lockedGame);

            $createdDates = [];
            foreach ($selections as $selection) {
                $slot = $selection['slot'];
                $date = $slots[$slot];

                GameMatch::create([
                    'id' => Str::uuid()->toString(),
                    'game_id' => $lockedGame->id,
                    'competition_id' => self::PRESEASON_COMPETITION_ID,
                    'home_team_id' => $selection['is_home'] ? $lockedGame->team_id : $selection['team_id'],
                    'away_team_id' => $selection['is_home'] ? $selection['team_id'] : $lockedGame->team_id,
                    'scheduled_date' => $date->toDateString(),
                    'round_number' => $slot + 1,
                    'trophy_name' => $selection['trophy_name'] ?? null,
                    'stadium_name' => $selection['stadium_name'] ?? null,
                    'played' => false,
                ]);

                $createdDates[] = $date;
            }

            if ($createdDates !== []) {
                // At least one friendly: park the game on the earliest one. Playing
                // the friendlies advances dates (and closes the transfer window)
                // naturally, exactly as before this feature existed.
                $earliest = collect($createdDates)->min();
                $lockedGame->update([
                    'current_date' => $earliest->toDateString(),
                    'preseason_opponents_pending' => false,
                ]);

                // The club always organises its own extra derby on top.
                $this->scheduleFamilyDerby($lockedGame);

                return;
            }

            // No friendlies chosen — the deliberate replacement for the old skip.
            $this->skipPreSeason($lockedGame);
        });
    }

    /**
     * Schedule the automatic 5th friendly: the "derbi de la casa", first team
     * vs. its own filial — the lab match for trying out academy players and
     * wild tactics. Idempotent per season: never schedules it twice.
     *
     * @return GameMatch|null the created derby, or null when the club has no
     *                       linked side (or the derby is already scheduled)
     */
    public function scheduleFamilyDerby(Game $game): ?GameMatch
    {
        $opponent = $this->familyDerbyOpponent($game);

        if (! $opponent || $opponent->id === $game->team_id) {
            return null;
        }

        $seasonYear = (int) $game->season;

        $alreadyScheduled = GameMatch::where('game_id', $game->id)
            ->where('competition_id', self::PRESEASON_COMPETITION_ID)
            ->where('round_number', self::FAMILY_DERBY_ROUND_NUMBER)
            ->whereYear('scheduled_date', $seasonYear)
            ->exists();

        if ($alreadyScheduled) {
            return null;
        }

        $userTeam = Team::findOrFail($game->team_id);
        $date = Carbon::createFromDate(
            $seasonYear,
            self::FAMILY_DERBY_DATE['month'],
            self::FAMILY_DERBY_DATE['day']
        );

        return GameMatch::create([
            'id' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'competition_id' => self::PRESEASON_COMPETITION_ID,
            'round_number' => self::FAMILY_DERBY_ROUND_NUMBER,
            'round_name' => 'game.preseason_family_derby_round',
            'trophy_name' => __('game.preseason_family_derby_trophy'),
            'stadium_name' => $userTeam->stadium_name,
            'home_team_id' => $game->team_id,
            'away_team_id' => $opponent->id,
            'scheduled_date' => $date->toDateString(),
            'played' => false,
        ]);
    }

    /**
     * Validate and normalise raw selections: drop blanks, clamp to valid slots
     * and pool members, and enforce one fixture per slot (two friendlies can't
     * share a date). The same opponent may be picked in multiple slots.
     *
     * @param  array<int, array{slot?:mixed, team_id?:mixed, is_home?:mixed, trophy_name?:mixed, stadium_name?:mixed}>  $selections
     * @return array<int, array{slot:int, team_id:string, is_home:bool, trophy_name:string|null, stadium_name:string|null}>
     */
    private function sanitizeSelections(Game $game, array $selections): array
    {
        $validTeamIds = $this->candidatePool($game)->pluck('id')->flip();

        // Accepted invitations bypass the candidate pool (the inviter is valid by definition).
        $invitedTeamIds = PreseasonInvitation::where('game_id', $game->id)
            ->where('status', PreseasonInvitation::STATUS_ACCEPTED)
            ->pluck('inviting_team_id')
            ->flip();

        $clean = [];
        $usedSlots = [];

        foreach ($selections as $selection) {
            $slot = (int) ($selection['slot'] ?? -1);
            $teamId = $selection['team_id'] ?? null;

            if ($slot < 0 || $slot >= self::NUM_SLOTS) {
                continue;
            }
            if (! is_string($teamId) || (! $validTeamIds->has($teamId) && ! $invitedTeamIds->has($teamId))) {
                continue;
            }
            if (isset($usedSlots[$slot])) {
                continue;
            }

            $usedSlots[$slot] = true;

            // Optional custom trophy name (e.g. "Trofeo Joan Gamper"). Trimmed,
            // max 100 chars, null when empty.
            $trophyName = isset($selection['trophy_name']) && is_string($selection['trophy_name'])
                ? trim(mb_substr($selection['trophy_name'], 0, 100))
                : null;
            if ($trophyName === '') {
                $trophyName = null;
            }

            // Optional custom stadium (e.g. play at Camp Nou).
            $stadiumName = isset($selection['stadium_name']) && is_string($selection['stadium_name'])
                ? trim(mb_substr($selection['stadium_name'], 0, 100))
                : null;
            if ($stadiumName === '') {
                $stadiumName = null;
            }

            $clean[] = [
                'slot' => $slot,
                'team_id' => $teamId,
                'is_home' => (bool) ($selection['is_home'] ?? true),
                'trophy_name' => $trophyName,
                'stadium_name' => $stadiumName,
            ];
        }

        return $clean;
    }

    /**
     * End pre-season with no friendlies: jump to the first competitive match,
     * flush summer signings / close the window, and simulate AI window activity.
     * This is the deliberate replacement for the old one-click "Skip Pre-Season".
     */
    private function skipPreSeason(Game $game): void
    {
        // The club's automatic derby happens even when the player skips their
        // own picks: it's the club's match, not the player's.
        $this->scheduleFamilyDerby($game);

        $earliestMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        $previousDate = $game->current_date;
        $updates = [
            'pre_season' => false,
            'preseason_opponents_pending' => false,
        ];
        if ($earliestMatch) {
            $updates['current_date'] = $earliestMatch->scheduled_date->toDateString();
        }

        $game->update($updates);

        // Notify listeners that the date jumped from pre-season to matchday 1.
        // This lets the transfer subsystem flush summer signings (parked as
        // STATUS_AGREED) and lets the AI window-close handler run before the
        // first competitive match.
        if ($earliestMatch && $earliestMatch->scheduled_date->gt($previousDate)) {
            GameDateAdvanced::dispatch($game->refresh(), $previousDate, $earliestMatch->scheduled_date);
        }

        // Run career action ticks in the background to simulate pre-season transfer activity
        $updated = Game::where('id', $game->id)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessCareerActions::dispatch($game->id, 4);
            } catch (\Throwable $e) {
                Game::where('id', $game->id)->update(['career_actions_processing_at' => null]);
                Log::error('Failed to dispatch pre-season career actions', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
