<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\SquadReplenishmentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA review: with generation disabled (MIN_SQUAD_SIZE=0,
 * YOUTH_INTAKE_MIN/MAX=0, GROUP_MINIMUMS all 0) the trim to
 * YOUTH_INTAKE_SQUAD_CAP=28 was still live and silently released REAL,
 * contracted AI-team players to free agency — contradicting the
 * "squads use only real players" design. The trim must never cut a
 * player under contract; only expired-contract players are eligible.
 */
class SquadReplenishmentTrimTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $aiTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $userTeam = Team::factory()->create(['name' => 'User FC']);
        $this->aiTeam = Team::factory()->create(['name' => 'AI FC']);
        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_trim_never_releases_contracted_players(): void
    {
        // 30 players, all under contract past the current date.
        $ids = $this->seedAiSquad(30, '2027-06-30');

        $this->runProcessor();

        $remaining = GamePlayer::whereIn('id', $ids)->where('team_id', $this->aiTeam->id)->count();
        $this->assertSame(30, $remaining, 'no contracted player may be trimmed to hit the cap');
    }

    public function test_trim_only_releases_expired_contract_players(): void
    {
        // 25 under contract + 5 expired: the trim needs 2 releases to hit
        // 28, and only the expired ones are eligible.
        $contractedIds = $this->seedAiSquad(25, '2027-06-30');
        $expiredIds = $this->seedAiSquad(5, '2025-06-30');

        $this->runProcessor();

        $contractedRemaining = GamePlayer::whereIn('id', $contractedIds)->where('team_id', $this->aiTeam->id)->count();
        $this->assertSame(25, $contractedRemaining, 'contracted players must stay');

        $expiredReleased = GamePlayer::whereIn('id', $expiredIds)->whereNull('team_id')->count();
        $this->assertSame(2, $expiredReleased, 'only expired-contract players may be trimmed (30 -> 28 cap)');
    }

    public function test_squad_at_or_below_cap_is_untouched(): void
    {
        $ids = $this->seedAiSquad(20, '2027-06-30');

        $this->runProcessor();

        $this->assertSame(20, GamePlayer::whereIn('id', $ids)->where('team_id', $this->aiTeam->id)->count());
    }

    /**
     * @return string[]
     */
    private function seedAiSquad(int $count, string $contractUntil): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->aiTeam)
                ->create([
                    'date_of_birth' => '1998-06-15',
                    'position' => 'Central Midfield',
                    'overall_score' => 65,
                    'contract_until' => $contractUntil,
                ])->id;
        }

        return $ids;
    }

    private function runProcessor(): void
    {
        $data = new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: 'ESP1',
        );

        app(SquadReplenishmentProcessor::class)->process($this->game, $data);
    }
}
