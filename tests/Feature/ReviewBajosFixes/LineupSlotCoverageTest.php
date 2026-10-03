<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\LineupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BAJA validación: LineupService::validateLineup() con slotAssignments
 * solo validaba las entradas dadas — un mapa parcial se persistía.
 * Ahora exige cobertura 1:1 (11 slots asignados a 11 jugadoras).
 */
class LineupSlotCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;
    /** @var list<string> */
    private array $playerIds;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);

        $this->playerIds = GamePlayer::factory()->count(11)
            ->forGame($this->game)->forTeam($this->team)
            ->create(['is_squad_member' => true, 'injury_until' => null])
            ->pluck('id')->all();
    }

    private function validate(?array $slots): array
    {
        return app(LineupService::class)->validateLineup(
            $this->playerIds,
            $this->game->id,
            $this->team->id,
            Carbon::parse('2026-08-20'),
            'ESP1',
            Formation::F_4_4_2,
            $slots,
        );
    }

    public function test_partial_slot_map_is_rejected(): void
    {
        // Solo 5 de los 11 slots asignados.
        $slots = [];
        foreach (range(0, 4) as $i) {
            $slots[$i] = $this->playerIds[$i];
        }

        $this->assertNotEmpty($this->validate($slots));
    }

    public function test_slot_map_with_unassigned_player_is_rejected(): void
    {
        // 11 slots pero una jugadora ocupa dos y otra queda fuera.
        $slots = [];
        foreach (range(0, 10) as $i) {
            $slots[$i] = $this->playerIds[$i];
        }
        $slots[10] = $this->playerIds[0];

        $this->assertNotEmpty($this->validate($slots));
    }

    public function test_full_slot_map_is_accepted(): void
    {
        $slots = [];
        foreach (range(0, 10) as $i) {
            $slots[$i] = $this->playerIds[$i];
        }

        $this->assertSame([], $this->validate($slots));
    }
}
