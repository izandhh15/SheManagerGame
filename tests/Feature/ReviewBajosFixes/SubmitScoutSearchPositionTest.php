<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\ScoutReport;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA validación: SubmitScoutSearch aceptaba `position` como string
 * libre. Ahora exige uno de los filtros reales del juego.
 */
class SubmitScoutSearchPositionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    private function postSearch(string $position)
    {
        return $this->actingAs($this->user)->post(
            route('game.scouting.search', $this->game->id),
            ['position' => $position]
        );
    }

    public function test_rejects_unknown_position(): void
    {
        $response = $this->postSearch('nonsense"; DROP TABLE users; --');

        $response->assertSessionHasErrors('position');
        $this->assertSame(0, ScoutReport::where('game_id', $this->game->id)->count());
    }

    #[DataProvider('validPositions')]
    public function test_accepts_real_scout_filters(string $position): void
    {
        $response = $this->postSearch($position);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $report = ScoutReport::where('game_id', $this->game->id)->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertSame($position, $report->filters['position']);
    }

    public static function validPositions(): array
    {
        return [
            'group gk' => ['gk'],
            'group def' => ['def'],
            'group mid' => ['mid'],
            'group fwd' => ['fwd'],
            'any defender' => ['any_defender'],
            'slot code' => ['CM'],
            'slot code GK' => ['GK'],
        ];
    }
}
