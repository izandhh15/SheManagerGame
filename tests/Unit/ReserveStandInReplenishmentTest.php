<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use App\Modules\Squad\Services\SquadMinimumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the BAJA review fixes in ReserveTeamService:
 *
 * 1. replenishReserveWithStandIns() must distribute the squad deficit
 *    across position groups (2 GK / 5 DEF / 5 MID / 5 FWD) instead of
 *    dumping every stand-in into 'Midfielder' (all 'Central Midfield').
 *    The old code read SquadMinimumService::POSITION_GROUP_MINIMUMS,
 *    which is empty by design, so the whole deficit fell through the
 *    rounding-drift branch into 'Midfielder'.
 *
 * 2. sendDownToReserve() must call pruneExcessStandIns() — a real player
 *    arriving on the reserve makes filler unnecessary, exactly like
 *    sendBackToReserve() already did.
 */
class ReserveStandInReplenishmentTest extends TestCase
{
    use RefreshDatabase;

    private ReserveTeamService $service;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReserveTeamService::class);

        $this->firstTeam = Team::factory()->create(['name' => 'Atlético de Madrid']);
        $this->reserveTeam = Team::factory()->create([
            'name' => 'Atlético Madrileño',
            'parent_team_id' => $this->firstTeam->id,
        ]);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);
    }

    public function test_replenish_distributes_stand_ins_across_position_groups(): void
    {
        $added = $this->service->replenishReserveWithStandIns($this->game);

        $this->assertSame(SquadMinimumService::MIN_SQUAD_SIZE, $added);

        $byPosition = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->reserveTeam->id)
            ->where('is_stand_in', true)
            ->get()
            ->groupBy('position')
            ->map->count();

        $this->assertSame(2, $byPosition['Goalkeeper'] ?? 0, 'stand-in goalkeepers');
        $this->assertSame(5, $byPosition['Centre-Back'] ?? 0, 'stand-in defenders');
        $this->assertSame(5, $byPosition['Central Midfield'] ?? 0, 'stand-in midfielders');
        $this->assertSame(5, $byPosition['Centre-Forward'] ?? 0, 'stand-in forwards');
    }

    public function test_replenish_respects_per_group_minimums_on_a_skewed_squad(): void
    {
        // 10 real midfielders: the deficit fill alone would leave the
        // reserve without a goalkeeper; the per-group top-up must kick in.
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->reserveTeam)
                ->create(['position' => 'Central Midfield', 'date_of_birth' => '2004-06-15']);
        }

        $added = $this->service->replenishReserveWithStandIns($this->game);
        $this->assertGreaterThan(0, $added, 'stand-ins must be created for the skewed squad');

        $standIns = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->reserveTeam->id)
            ->where('is_stand_in', true)
            ->get();

        $this->assertGreaterThanOrEqual(2, $standIns->where('position', 'Goalkeeper')->count(), 'at least 2 GK stand-ins');
        $this->assertGreaterThanOrEqual(5, $standIns->where('position', 'Centre-Back')->count(), 'at least 5 DEF stand-ins');
        $this->assertGreaterThanOrEqual(5, $standIns->where('position', 'Centre-Forward')->count(), 'at least 5 FWD stand-ins');

        // Real players are never touched by the replenishment.
        $this->assertSame(
            10,
            GamePlayer::where('game_id', $this->game->id)
                ->where('team_id', $this->reserveTeam->id)
                ->where('is_stand_in', false)
                ->count()
        );
    }

    public function test_stand_ins_stay_invisible_to_the_user(): void
    {
        $this->service->replenishReserveWithStandIns($this->game);

        $this->assertSame(0, $this->service->getReserveSquad($this->game)->count());
    }

    public function test_send_down_to_reserve_prunes_excess_stand_ins(): void
    {
        // Reserve: 10 real players + 10 stand-ins. Sending one more real
        // player down raises the real count to 11, so the keep-margin is
        // MIN_SQUAD_SIZE + 2 - 11 = 8 stand-ins and 2 must be pruned.
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->reserveTeam)
                ->create(['position' => 'Central Midfield', 'date_of_birth' => '2004-06-15']);
        }
        $this->service->replenishReserveWithStandIns($this->game);
        $standInsBefore = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->reserveTeam->id)
            ->where('is_stand_in', true)
            ->count();
        $this->assertGreaterThan(8, $standInsBefore, 'precondition: more stand-ins than the post-send-down keep margin');

        // First team needs a comfortable roster so the send-down guard passes.
        $this->fillFirstTeam([
            'Goalkeeper'       => 3,
            'Centre-Back'      => 8,
            'Central Midfield' => 8,
            'Centre-Forward'   => 6,
        ]);
        $u23 = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->firstTeam)
            ->create(['date_of_birth' => '2003-06-15', 'position' => 'Central Midfield']);

        $this->service->sendDownToReserve($u23, $this->game);

        $standInsAfter = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->reserveTeam->id)
            ->where('is_stand_in', true)
            ->count();

        $this->assertLessThan($standInsBefore, $standInsAfter, 'excess stand-ins must be pruned on send-down');
        $this->assertSame(
            8,
            $standInsAfter,
            'keep margin: MIN_SQUAD_SIZE(17) + 2 - 11 real players = 8 stand-ins'
        );

        $u23->refresh();
        $this->assertSame($this->reserveTeam->id, $u23->team_id);
    }

    /**
     * @param  array<string, int>  $positions  position-name → count
     */
    private function fillFirstTeam(array $positions): void
    {
        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($this->firstTeam)
                    ->create([
                        'position'      => $position,
                        'date_of_birth' => '1995-06-15',
                    ]);
            }
        }
    }
}
