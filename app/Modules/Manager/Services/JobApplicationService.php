<?php

namespace App\Modules\Manager\Services;

use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Job applications: the manager can apply for jobs at other clubs
 * mid-season. There's a risk — if the current club finds out and they're
 * happy with you, they might fire you on the spot.
 *
 * Acceptance chance is based on manager reputation vs club reputation.
 */
class JobApplicationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ManagerReputationService $managerReputationService,
    ) {}

    /**
     * Get clubs the manager can apply to. Excludes the current club and
     * clubs that are too far above the manager's reputation.
     *
     * @return Collection<int, Team>
     */
    public function getAvailableJobs(Game $game, int $limit = 12): Collection
    {
        $managerLevel = $this->managerReputationService->getReputationLevel($game);

        // Clubs with a reputation not too far above the manager's.
        // We show a mix: some at the same level, some slightly above (ambitious),
        // some below (safe options if you're about to be fired).
        return Team::whereNull('parent_team_id')
            ->where('type', 'club')
            ->where('is_placeholder', false)
            ->where('id', '!=', $game->team_id)
            ->whereNotIn('id', function ($query) use ($game) {
                // Exclude clubs the manager already applied to this season
                $query->select('team_id')
                    ->from('manager_job_offers')
                    ->where('game_id', $game->id)
                    ->where('season', $game->season)
                    ->where('offer_type', ManagerJobOffer::TYPE_JOB_APPLICATION);
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Apply for a job at another club.
     *
     * Returns the created application. The application is resolved
     * immediately (accepted/rejected) for simplicity — the drama is in
     * whether your current club finds out.
     *
     * R-review-medios: the old version accepted ANY team_id (national
     * sides, reserve teams, placeholders). An accepted application stages
     * pending_team_switch, and pointing it at a selection/filial breaks the
     * pro-manager career. The target is now validated with exactly the
     * same filters as getAvailableJobs(), plus a one-application-per-season
     * deduplication.
     *
     * @return array{offer: ManagerJobOffer, accepted: bool, discovered: bool, fired: bool}
     *
     * @throws \InvalidArgumentException when the target team is not eligible
     */
    public function apply(Game $game, Team $targetTeam): array
    {
        // Only real clubs: no national sides, no reserve teams, no
        // placeholders, and never the manager's own club.
        if (
            $targetTeam->parent_team_id !== null
            || $targetTeam->type !== 'club'
            || (bool) $targetTeam->is_placeholder
            || $targetTeam->id === $game->team_id
        ) {
            throw new \InvalidArgumentException('Team is not eligible for a job application.');
        }

        // Deduplication: one application per club per season, mirroring
        // the exclusion in getAvailableJobs().
        $alreadyApplied = ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('team_id', $targetTeam->id)
            ->where('offer_type', ManagerJobOffer::TYPE_JOB_APPLICATION)
            ->exists();

        if ($alreadyApplied) {
            throw new \InvalidArgumentException('Already applied to this club this season.');
        }

        $managerLevel = $this->managerReputationService->getReputationLevel($game);
        $targetReputation = $this->getTeamReputationLevel($targetTeam);

        // Acceptance chance: base 50%, +/- based on reputation difference
        $reputationDiff = $this->reputationToInt($managerLevel) - $this->reputationToInt($targetReputation);
        $acceptChance = 0.50 + ($reputationDiff * 0.15);
        $acceptChance = max(0.10, min(0.90, $acceptChance));

        $accepted = (mt_rand(1, 100) / 100) <= $acceptChance;

        // The offer row and the staged team switch must land together: if
        // the game update fails after the offer is created, the offer would
        // be orphaned (pending_team_switch never points at it).
        [$offer, $discovered, $fired] = DB::transaction(function () use ($game, $targetTeam, $targetReputation, $accepted) {
            $offer = ManagerJobOffer::create([
                'user_id' => $game->user_id,
                'game_id' => $game->id,
                'team_id' => $targetTeam->id,
                'season' => $game->season,
                'offer_type' => ManagerJobOffer::TYPE_JOB_APPLICATION,
                'status' => $accepted ? ManagerJobOffer::STATUS_ACCEPTED : ManagerJobOffer::STATUS_REJECTED,
                'target_reputation_level' => $targetReputation,
                'created_on_game_date' => $game->current_date,
            ]);

            $discovered = false;
            $fired = false;

            if ($accepted) {
                // 25% chance the current club finds out about the application
                $discovered = (mt_rand(1, 100) <= 25);

                if ($discovered) {
                    // If the club is happy with you (good season so far), they
                    // feel betrayed and might fire you on the spot (40% chance).
                    // If they're unhappy, they don't care — you're probably
                    // getting fired anyway.
                    $clubHappy = $this->isClubHappyWithManager($game);
                    if ($clubHappy && mt_rand(1, 100) <= 40) {
                        $fired = true;
                        // The firing is handled by the caller via the game state.
                        // We just report it; the UI shows the drama.
                    }
                }

                // If accepted and not fired for betrayal, stage the team switch.
                // The manager moves at the next season transition, OR immediately
                // if the current club fires them for the betrayal.
                if (!$fired) {
                    $game->update(['pending_team_switch' => $offer->id]);
                }
            }

            return [$offer, $discovered, $fired];
        });

        return [
            'offer' => $offer,
            'accepted' => $accepted,
            'discovered' => $discovered,
            'fired' => $fired,
        ];
    }

    /**
     * Is the current club happy with the manager? Based on league position
     * vs expectations (simplified: top half = happy).
     *
     * R-review-medios (hallazgo adyacente al fix 7): los where('season') de
     * este metodo referenciaban una columna que no existe en game_standings
     * (la tabla va por game_id, sin season) -> SQLSTATE 42703 (500) cada vez
     * que el club descubria una solicitud aceptada. Se eliminan: el game_id
     * ya acota la busqueda a la partida actual.
     */
    private function isClubHappyWithManager(Game $game): bool
    {
        $standing = \App\Models\GameStanding::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if (!$standing || !$standing->position) {
            return true; // No data — assume they're happy (early season)
        }

        // Top half = happy
        $totalTeams = \App\Models\GameStanding::where('game_id', $game->id)
            ->where('competition_id', $standing->competition_id)
            ->count();

        return $standing->position <= ceil($totalTeams / 2);
    }

    private function getTeamReputationLevel(Team $team): string
    {
        $reputation = \App\Models\TeamReputation::where('team_id', $team->id)->first();

        return $reputation?->level ?? 'local';
    }

    /**
     * R-review-medios: the old map used tiers that don't exist
     * ('world_class'/'national'/'regional'), so the real tiers
     * 'modest'/'established'/'elite' all fell into `default => 1` and the
     * acceptance chance barely told a local manager apart from an elite
     * one. Now it mirrors ClubProfile::REPUTATION_TIERS.
     */
    private function reputationToInt(string $level): int
    {
        return match (strtolower($level)) {
            'elite' => 5,
            'continental' => 4,
            'established' => 3,
            'modest' => 2,
            'local' => 1,
            default => 1,
        };
    }
}
