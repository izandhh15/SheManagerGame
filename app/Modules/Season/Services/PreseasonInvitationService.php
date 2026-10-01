<?php

namespace App\Modules\Season\Services;

use App\Models\Game;
use App\Models\PreseasonInvitation;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Generates pre-season invitations from AI clubs to the user's team.
 *
 * The machine invites you: AI clubs offer friendlies (some as trophy matches
 * like the Joan Gamper), and you choose which to accept. Invitations fill the
 * same 4 pre-season slots as manual picks — accepting one occupies its slot.
 */
class PreseasonInvitationService
{
    /**
     * Famous pre-season trophies by club name (partial match). Clubs not listed
     * get a generic "Trofeo <Club>" invitation instead.
     */
    private const FAMOUS_TROPHIES = [
        'Barcelona' => 'Trofeo Joan Gamper',
        'Real Madrid' => 'Trofeo Santiago Bernabéu',
        'Valencia' => 'Trofeu Taronja',
        'Atlético de Madrid' => 'Trofeo Teresa Herrera',
        'Athletic Club' => 'Trofeo Aitzina',
        'Sevilla' => 'Trofeo Antonio Puerta',
        'Real Sociedad' => 'Trofeo Euskal Herria',
        'Arsenal' => 'Emirates Cup',
        'Bayern' => 'Audi Cup',
        'Juventus' => 'Trofeo Luigi Berlusconi',
        'Milan' => 'Trofeo Luigi Berlusconi',
        'Inter' => 'Trofeo Moretti',
        'PSG' => 'Trophée des Champions',
        'Lyon' => 'Trophée Veolia',
    ];

    /**
     * Generate invitations for a game that needs pre-season setup.
     * Idempotent: skips if invitations already exist for the game.
     */
    public function generateFor(Game $game): void
    {
        if (! $game->needsPreseasonOpponentSelection()) {
            return;
        }

        if (PreseasonInvitation::where('game_id', $game->id)->exists()) {
            return;
        }

        $candidates = $this->invitingClubs($game);
        if ($candidates->isEmpty()) {
            return;
        }

        // 6-8 invitations across the 4 slots (multiple per slot, user picks one).
        $count = min(8, max(6, $candidates->count()));
        $picked = $candidates->random($count);

        $slot = 0;
        foreach ($picked as $team) {
            PreseasonInvitation::create([
                'game_id' => $game->id,
                'inviting_team_id' => $team->id,
                'slot' => $slot % PreseasonOpponentService::NUM_SLOTS,
                'trophy_name' => $this->trophyNameFor($team),
                'stadium_name' => $team->stadium_name,
            ]);
            $slot++;
        }
    }

    /**
     * Pending invitations for the pre-season setup screen, with team data.
     *
     * @return Collection<int, PreseasonInvitation>
     */
    public function pendingFor(Game $game): Collection
    {
        return PreseasonInvitation::where('game_id', $game->id)
            ->where('status', PreseasonInvitation::STATUS_PENDING)
            ->with('invitingTeam')
            ->orderBy('slot')
            ->get();
    }

    /**
     * Accept an invitation: occupies its slot with a friendly against the
     * inviting team. Returns the slot index, or null if the slot is taken.
     */
    public function accept(Game $game, string $invitationId): ?int
    {
        $invitation = PreseasonInvitation::where('game_id', $game->id)
            ->where('id', $invitationId)
            ->where('status', PreseasonInvitation::STATUS_PENDING)
            ->first();

        if (! $invitation) {
            return null;
        }

        $invitation->update(['status' => PreseasonInvitation::STATUS_ACCEPTED]);

        return $invitation->slot;
    }

    public function decline(Game $game, string $invitationId): void
    {
        PreseasonInvitation::where('game_id', $game->id)
            ->where('id', $invitationId)
            ->where('status', PreseasonInvitation::STATUS_PENDING)
            ->update(['status' => PreseasonInvitation::STATUS_DECLINED]);
    }

    /**
     * Clubs that can invite: from the friendly candidate pool, preferring
     * bigger clubs (higher reputation) for trophy invitations.
     *
     * @return Collection<int, Team>
     */
    private function invitingClubs(Game $game): Collection
    {
        $opponentService = app(PreseasonOpponentService::class);
        $pool = $opponentService->candidatePool($game);

        // Prefer clubs (not reserve teams) as inviters; shuffle for variety.
        return $pool->shuffle();
    }

    /**
     * Trophy name for an inviting club, or null for a plain friendly.
     * ~40% of invitations are trophy matches.
     */
    private function trophyNameFor(Team $team): ?string
    {
        if (random_int(1, 100) > 40) {
            return null;
        }

        foreach (self::FAMOUS_TROPHIES as $needle => $trophy) {
            if (stripos($team->name, $needle) !== false) {
                return $trophy;
            }
        }

        // Generic trophy named after the inviting club.
        $shortName = explode(' ', $team->name)[0];
        return "Trofeo {$shortName}";
    }
}
