<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\PreseasonInvitation;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Services\PreseasonInvitationService;
use App\Modules\Season\Services\PreseasonOpponentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-season friendlies, Izan's three rules:
 *
 * (a) Reserve teams (filiales) never send friendly invitations — the first
 *     team does the inviting.
 * (b) Friendly rivals adapt to the user's category: same tier ±1 at most,
 *     never a 2ª RFEF side against OL Lyonnes.
 * (c) A 5th automatic friendly: first team vs. its own filial (or, in the
 *     affiliate career, filial vs. its first team) — the "derbi de la casa"
 *     for trying things out.
 */
class PreseasonFamilyDerbyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Competition $league;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player First Team', 'country' => 'ES']);

        $this->league = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'Liga F',
            'country' => 'ES',
            'tier' => 1,
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->league->id,
            'country' => 'ES',
            'season' => '2025',
            'current_date' => '2025-07-01',
            'pre_season' => true,
            'preseason_opponents_pending' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
        ]);

        // The player's own team also plays in the game (but can never be its own rival).
        $this->registerInLeague($this->playerTeam, 'ESP1', 1);
    }

    /**
     * Register a team in a league for this game, both as reference data
     * (competition_teams) and as a game entry (competition_entries).
     */
    private function registerInLeague(Team $team, string $competitionId, int $tier, string $country = 'ES'): Competition
    {
        $competition = Competition::firstOrCreate(
            ['id' => $competitionId],
            Competition::factory()->league()->raw([
                'id' => $competitionId,
                'name' => $competitionId,
                'country' => $country,
                'tier' => $tier,
            ])
        );

        CompetitionTeam::create([
            'competition_id' => $competition->id,
            'team_id' => $team->id,
            'season' => '2025',
            'entry_round' => 1,
        ]);

        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $competition->id,
            'team_id' => $team->id,
            'entry_round' => 1,
        ]);

        return $competition;
    }

    private function makeClub(string $name, string $competitionId, int $tier, string $country = 'ES'): Team
    {
        $team = Team::factory()->create(['name' => $name, 'country' => $country]);
        $this->registerInLeague($team, $competitionId, $tier, $country);

        return $team;
    }

    // ------------------------------------------------------------------
    // (a) Filiales never invite
    // ------------------------------------------------------------------

    public function test_reserve_teams_never_send_friendly_invitations(): void
    {
        // Seven first-team clubs so invitations can be generated…
        for ($i = 1; $i <= 7; $i++) {
            $this->makeClub("Rival Club {$i}", 'ESP1', 1);
        }

        // …plus a filial (in the pool by design, but it must never invite).
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $this->assertTrue($reserve->isReserveTeam());

        app(PreseasonInvitationService::class)->generateFor($this->game);

        $invitations = PreseasonInvitation::where('game_id', $this->game->id)->get();
        $this->assertNotEmpty($invitations, 'Invitations should have been generated.');

        $this->assertFalse(
            $invitations->contains(fn (PreseasonInvitation $inv) => $inv->inviting_team_id === $reserve->id),
            'A filial must never invite the user to a friendly.'
        );

        foreach ($invitations as $invitation) {
            $inviter = Team::find($invitation->inviting_team_id);
            $this->assertFalse($inviter->isReserveTeam(), "{$inviter->name} is a filial and must not invite.");
        }
    }

    public function test_reserve_teams_stay_available_as_manual_rivals(): void
    {
        // Filiales are only banned from *inviting*; the player may still pick
        // one manually for a slot.
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $pool = app(PreseasonOpponentService::class)->candidatePool($this->game);

        $this->assertTrue(
            $pool->contains(fn (Team $team) => $team->id === $reserve->id),
            'Filiales remain valid manual picks for friendly slots.'
        );
    }

    // ------------------------------------------------------------------
    // (b) Rivals within ±1 tier
    // ------------------------------------------------------------------

    public function test_friendly_rivals_stay_within_one_tier_of_user(): void
    {
        $sameTier = $this->makeClub('Same Tier FC', 'ESP1', 1);
        $oneBelow = $this->makeClub('One Below FC', 'ESP2', 2);
        $oneAbove = $this->makeClub('One Above FC', 'ENG1', 1, 'GB');
        $twoBelow = $this->makeClub('Two Below FC', 'ESP3', 3);
        $twoAbove = $this->makeClub('Two Above FC', 'FRA0', 3, 'FR');

        $pool = app(PreseasonOpponentService::class)->candidatePool($this->game)->pluck('id');

        $this->assertContains($sameTier->id, $pool, 'Same-tier rival must be offered.');
        $this->assertContains($oneBelow->id, $pool, 'One tier below must be offered.');
        $this->assertContains($oneAbove->id, $pool, 'Foreign same-tier rival must be offered.');
        $this->assertNotContains($twoBelow->id, $pool, 'Two tiers below must NOT be offered.');
        $this->assertNotContains($twoAbove->id, $pool, 'Two tiers above must NOT be offered.');
        $this->assertNotContains($this->playerTeam->id, $pool, 'You cannot play yourself.');
    }

    // ------------------------------------------------------------------
    // (c) The 5th friendly: derbi de la casa
    // ------------------------------------------------------------------

    public function test_family_derby_first_team_vs_own_reserve(): void
    {
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
            'stadium_name' => 'Ciudad Deportiva',
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $match = app(PreseasonOpponentService::class)->scheduleFamilyDerby($this->game->refresh());

        $this->assertNotNull($match, 'The family derby must be scheduled.');
        $this->assertSame('PRESEASON', $match->competition_id);
        $this->assertSame(PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER, $match->round_number);
        $this->assertSame($this->playerTeam->id, $match->home_team_id);
        $this->assertSame($reserve->id, $match->away_team_id);
        $this->assertSame('2025-08-17', $match->scheduled_date->toDateString());
        $this->assertFalse($match->played);
        $this->assertSame(__('game.preseason_family_derby_trophy'), $match->trophy_name);
        $this->assertSame('game.preseason_family_derby_round', $match->round_name);
    }

    public function test_family_derby_affiliate_career_reserve_vs_first_team(): void
    {
        // Affiliate ("Carrera con Filiales") career: the user manages the
        // filial, so the derby is against its first team.
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $affiliateGame = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $reserve->id,
            'competition_id' => 'ESP2',
            'country' => 'ES',
            'season' => '2025',
            'current_date' => '2025-07-01',
            'pre_season' => true,
            'preseason_opponents_pending' => true,
            'setup_completed_at' => now(),
            'needs_new_season_setup' => false,
            'needs_welcome' => false,
            'pair_mode' => 'affiliate',
        ]);

        $match = app(PreseasonOpponentService::class)->scheduleFamilyDerby($affiliateGame);

        $this->assertNotNull($match, 'The family derby must be scheduled in affiliate careers too.');
        $this->assertSame($reserve->id, $match->home_team_id);
        $this->assertSame($this->playerTeam->id, $match->away_team_id);
        $this->assertSame(PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER, $match->round_number);
    }

    public function test_confirm_selections_schedules_family_derby_on_top(): void
    {
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $rival = $this->makeClub('Rival Club', 'ESP1', 1);

        app(PreseasonOpponentService::class)->confirmSelections($this->game, [
            ['slot' => 0, 'team_id' => $rival->id, 'is_home' => true],
        ]);

        // The chosen friendly…
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'round_number' => 1,
            'away_team_id' => $rival->id,
        ]);

        // …plus the automatic 5th, the family derby.
        $this->assertDatabaseHas('game_matches', [
            'game_id' => $this->game->id,
            'competition_id' => 'PRESEASON',
            'round_number' => PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $reserve->id,
            'played' => false,
        ]);

        $this->assertFalse($this->game->fresh()->preseason_opponents_pending);
    }

    public function test_family_derby_is_idempotent(): void
    {
        $reserve = Team::factory()->create([
            'name' => 'Player Team B',
            'country' => 'ES',
            'parent_team_id' => $this->playerTeam->id,
        ]);
        $this->registerInLeague($reserve, 'ESP2', 2);

        $service = app(PreseasonOpponentService::class);

        $first = $service->scheduleFamilyDerby($this->game->refresh());
        $second = $service->scheduleFamilyDerby($this->game->refresh());

        $this->assertNotNull($first);
        $this->assertNull($second, 'Scheduling twice must not create a second derby.');

        $this->assertSame(1, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'PRESEASON')
            ->where('round_number', PreseasonOpponentService::FAMILY_DERBY_ROUND_NUMBER)
            ->count());
    }

    public function test_no_family_derby_without_linked_side(): void
    {
        // A club with no filial at all: nothing to schedule, nothing breaks.
        $match = app(PreseasonOpponentService::class)->scheduleFamilyDerby($this->game->refresh());

        $this->assertNull($match);
        $this->assertSame(0, GameMatch::where('game_id', $this->game->id)
            ->where('competition_id', 'PRESEASON')
            ->count());
    }
}
