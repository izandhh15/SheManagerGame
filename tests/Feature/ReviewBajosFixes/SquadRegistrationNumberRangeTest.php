<?php

namespace Tests\Feature\ReviewBajosFixes;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Squad\Services\RegistrationException;
use App\Modules\Squad\Services\SquadRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BAJA validación: SquadRegistrationService::save() no validaba el rango
 * del dorsal — un POST directo podía persistir 0, 999 o valores no
 * numéricos. Ahora exige 1–99.
 */
class SquadRegistrationNumberRangeTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private SquadRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $team = Team::factory()->create(['type' => 'club', 'is_placeholder' => false]);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
        $this->service = app(SquadRegistrationService::class);
    }

    private function assignments(array $numbers): \Illuminate\Support\Collection
    {
        return collect($numbers)->map(fn ($number, $i) => [
            'player_id' => (string) \Illuminate\Support\Str::uuid(),
            'number' => $number,
        ])->values();
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_out_of_range_numbers(mixed $number): void
    {
        $this->expectException(RegistrationException::class);

        $this->service->save($this->game, $this->assignments([$number]));
    }

    public static function invalidNumbers(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'above 99' => [100],
            'huge' => [999],
            'non-numeric string' => ['abc'],
            'null' => [null],
        ];
    }

    public function test_accepts_boundary_numbers_1_and_99(): void
    {
        $team = Team::find($this->game->team_id);
        // Young enough to satisfy the academy age limit for numbers > 25.
        $p1 = GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['date_of_birth' => '2008-01-01']);
        $p2 = GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['date_of_birth' => '2008-06-15']);

        $this->service->save($this->game, collect([
            ['player_id' => $p1->id, 'number' => 1],
            ['player_id' => $p2->id, 'number' => 99],
        ]));

        $this->assertSame(1, $p1->fresh()->number);
        $this->assertSame(99, $p2->fresh()->number);
    }

    public function test_nothing_persisted_when_number_invalid(): void
    {
        $team = Team::find($this->game->team_id);
        $player = GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['number' => 7]);

        try {
            $this->service->save($this->game, collect([
                ['player_id' => $player->id, 'number' => 0],
            ]));
            $this->fail('Expected RegistrationException');
        } catch (RegistrationException $e) {
            $this->assertSame(7, $player->fresh()->number);
        }
    }
}
