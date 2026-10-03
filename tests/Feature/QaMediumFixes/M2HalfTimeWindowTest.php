<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Services\SubstitutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del bug medio M2 (QA agent-7):
 * un cambio al descanso (minuto 45) se rechazaba tras usar las 3 ventanas,
 * aunque el descanso es ventana gratis por diseño (el propio comentario del
 * código y AISubstitutionService lo tratan así).
 *
 * Causa raíz: SubstitutionService::validateBatchSubstitution solo descontaba
 * los minutos gratis de los cambios *previos*, pero lanzaba
 * game.sub_error_windows_reached antes de comprobar si el *nuevo* cambio cae
 * en un minuto gratis. El fix solo bloquea el batch cuando este abriría una
 * NUEVA ventana táctica (ni minuto gratis ni ventana ya usada).
 */
class M2HalfTimeWindowTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $team;
    private GameMatch $match;
    private SubstitutionService $service;
    private array $lineupIds = [];
    private array $benchIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->team = Team::factory()->create(['name' => 'QAM2 Sub WFC', 'country' => 'ES']);
        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'country' => 'ES',
            'team_id' => $this->team->id,
        ]);

        $positions = ['Goalkeeper', 'Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back',
            'Central Midfield', 'Central Midfield', 'Attacking Midfield',
            'Centre-Forward', 'Right Winger', 'Left Winger'];
        $n = 1;
        foreach ($positions as $pos) {
            $this->lineupIds[] = GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'position' => $pos,
                'number' => $n++,
            ])->id;
        }
        foreach (['Central Midfield', 'Centre-Forward', 'Left-Back', 'Right Winger', 'Goalkeeper'] as $pos) {
            $this->benchIds[] = GamePlayer::factory()->create([
                'game_id' => $this->game->id,
                'team_id' => $this->team->id,
                'position' => $pos,
                'number' => $n++,
            ])->id;
        }

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->game->competition_id,
            'home_team_id' => $this->team->id,
            'away_team_id' => Team::factory()->create()->id,
            'home_lineup' => $this->lineupIds,
            'scheduled_date' => Carbon::parse('2024-08-20'),
        ]);

        $this->service = app(SubstitutionService::class);
    }

    /** Tres ventanas tácticas previas (min 55, 65, 75). */
    private function threeWindowsUsed(): array
    {
        return [
            ['playerOutId' => $this->lineupIds[1], 'playerInId' => $this->benchIds[0], 'minute' => 55],
            ['playerOutId' => $this->lineupIds[2], 'playerInId' => $this->benchIds[1], 'minute' => 65],
            ['playerOutId' => $this->lineupIds[3], 'playerInId' => $this->benchIds[2], 'minute' => 75],
        ];
    }

    private function validate(array $newSubs, array $previous = [], int $minute = 60): ?string
    {
        try {
            $this->service->validateBatchSubstitution(
                $this->match->fresh(),
                $this->game,
                $newSubs,
                $minute,
                $previous,
            );

            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    public function test_half_time_window_is_free_after_three_windows_used(): void
    {
        $error = $this->validate(
            [['playerOutId' => $this->lineupIds[7], 'playerInId' => $this->benchIds[3]]],
            $this->threeWindowsUsed(),
            45,
        );

        $this->assertNull($error, 'BUG M2: el descanso (min 45) es ventana gratis y debería pasar con las 3 ventanas usadas');
    }

    public function test_pre_extra_time_window_is_free_after_three_windows_used(): void
    {
        $error = $this->validate(
            [['playerOutId' => $this->lineupIds[7], 'playerInId' => $this->benchIds[3]]],
            $this->threeWindowsUsed(),
            90,
        );

        $this->assertNull($error, 'BUG M2: el min 90 (pre-prórroga) es ventana gratis y debería pasar con las 3 ventanas usadas');
    }

    public function test_fourth_tactical_window_still_rejected(): void
    {
        $error = $this->validate(
            [['playerOutId' => $this->lineupIds[7], 'playerInId' => $this->benchIds[3]]],
            $this->threeWindowsUsed(),
            80,
        );

        $this->assertSame('game.sub_error_windows_reached', $error, 'Una 4.ª ventana táctica distinta debe seguir rechazándose');
    }

    public function test_batch_in_already_used_window_does_not_open_a_new_one(): void
    {
        $error = $this->validate(
            [['playerOutId' => $this->lineupIds[7], 'playerInId' => $this->benchIds[3]]],
            $this->threeWindowsUsed(),
            65, // misma ventana que un cambio previo
        );

        $this->assertNull($error, 'Un cambio en una ventana ya usada no abre una ventana nueva y no debería rechazarse');
    }

    public function test_three_distinct_windows_still_allowed(): void
    {
        $previous = [
            ['playerOutId' => $this->lineupIds[1], 'playerInId' => $this->benchIds[0], 'minute' => 55],
            ['playerOutId' => $this->lineupIds[2], 'playerInId' => $this->benchIds[1], 'minute' => 65],
        ];
        $error = $this->validate(
            [['playerOutId' => $this->lineupIds[7], 'playerInId' => $this->benchIds[3]]],
            $previous,
            75,
        );

        $this->assertNull($error, 'La 3.ª ventana táctica debe seguir permitiéndose');
    }
}
