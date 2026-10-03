<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA validación: capacity/seats tenían min:1 sin max en los tres
 * commits de estadio. Ahora tope 150000.
 */
class StadiumCapacityMaxTest extends TestCase
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

    #[DataProvider('stadiumEndpoints')]
    public function test_rejects_absurd_capacity(string $route, string $field): void
    {
        $payload = [$field => 999_999_999];
        if ($route === 'game.club.stadium.rebuild' || $route === 'game.club.stadium.expansion') {
            $payload['financing'] = 'cash';
        }

        $response = $this->actingAs($this->user)->post(route($route, $this->game->id), $payload);

        $response->assertSessionHasErrors($field);
    }

    public static function stadiumEndpoints(): array
    {
        return [
            'rebuild capacity' => ['game.club.stadium.rebuild', 'capacity'],
            'expansion seats' => ['game.club.stadium.expansion', 'seats'],
            'supplementary seats' => ['game.club.stadium.supplementary', 'seats'],
        ];
    }
}
